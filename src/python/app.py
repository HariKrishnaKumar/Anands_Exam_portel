"""
Fluent 2 Test Platform — PCI Analysis API
===========================================
Flask microservice that calculates PCI scores and generates chart data.

Endpoints:
    GET /health                  — Health check
    GET /api/pci/calculate       — Calculate PCI for a submission
    GET /api/pci/batch/<test_id> — Get all PCI scores for a test
    GET /api/pci/student/<student_id> — Get PCI history for a student
    GET /api/charts/test/<test_id>    — Get chart data for a test
"""

import os
import json
from datetime import datetime

from flask import Flask, jsonify, request

from analysis.pci import calculate_pci, get_performance_band, get_stats, get_distribution
from analysis.charts import (
    build_bar_chart_data,
    build_stacked_bar_data,
    build_doughnut_data,
    build_radar_data,
    build_histogram_data,
)

app = Flask(__name__)

# ─── Database Connection ─────────────────────────────────────
DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "127.0.0.1"),
    "port": int(os.environ.get("DB_PORT", "3306")),
    "database": os.environ.get("DB_NAME", "test_platform"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASS", ""),
}


def get_db():
    """Get a MySQL database connection."""
    import mysql.connector

    conn = mysql.connector.connect(**DB_CONFIG)
    conn.autocommit = True
    return conn


# ─── Helper Functions ────────────────────────────────────────


def fetch_submission_scores(submission_id: int):
    """Fetch per-category scores for a submission."""
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute(
            """
            SELECT q.type,
                   SUM(sa.marks_obtained) AS obtained,
                   SUM(q.marks) AS total
            FROM student_answers sa
            JOIN questions q ON q.id = sa.question_id
            WHERE sa.submission_id = %s
            GROUP BY q.type
            """,
            (submission_id,),
        )
        rows = cursor.fetchall()
        scores = {"mcq": [0, 0], "coding": [0, 0], "explanation": [0, 0]}
        for row in rows:
            scores[row["type"]] = [
                float(row["obtained"] or 0),
                float(row["total"] or 0),
            ]
        return scores
    finally:
        cursor.close()
        conn.close()


# ─── Routes ──────────────────────────────────────────────────


@app.route("/health")
def health():
    """Health check endpoint."""
    return jsonify({"status": "ok", "service": "pci-analysis", "timestamp": datetime.utcnow().isoformat()})


@app.route("/api/pci/calculate", methods=["POST"])
def api_calculate_pci():
    """
    Calculate PCI for a specific submission.
    Body: { "submission_id": int }
    """
    data = request.get_json(silent=True) or {}
    submission_id = data.get("submission_id")

    if not submission_id:
        return jsonify({"error": "submission_id is required"}), 400

    scores = fetch_submission_scores(submission_id)
    result = calculate_pci(
        mcq_score=scores["mcq"][0],
        mcq_total=scores["mcq"][1],
        coding_score=scores["coding"][0],
        coding_total=scores["coding"][1],
        explanation_score=scores["explanation"][0],
        explanation_total=scores["explanation"][1],
    )
    band = get_performance_band(result["pci_score"])
    result["band"] = band

    # Save to pci_records table
    conn = get_db()
    cursor = conn.cursor()
    try:
        # Get student_id and test_id from submission
        cursor.execute("SELECT student_id, test_id FROM submissions WHERE id = %s", (submission_id,))
        sub = cursor.fetchone()
        if sub:
            student_id, test_id = sub
            cursor.execute(
                """
                INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score,
                                         mcq_weight, coding_weight, explanation_weight)
                VALUES (%s, %s, %s, %s, %s, %s, 40.00, 30.00, 30.00)
                ON DUPLICATE KEY UPDATE
                    pci_score = VALUES(pci_score),
                    mcq_score = VALUES(mcq_score),
                    coding_score = VALUES(coding_score),
                    explanation_score = VALUES(explanation_score),
                    generated_at = NOW()
                """,
                (
                    student_id,
                    test_id,
                    result["pci_score"],
                    result["mcq_percent"],
                    result["coding_percent"],
                    result["explanation_percent"],
                ),
            )
            result["student_id"] = student_id
            result["test_id"] = test_id
    finally:
        cursor.close()
        conn.close()

    return jsonify(result)


@app.route("/api/pci/batch/<int:test_id>")
def api_batch_pci(test_id: int):
    """Get all PCI scores for a test."""
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute(
            """
            SELECT pr.*, st.name AS student_name, st.email, st.roll_number
            FROM pci_records pr
            JOIN students st ON st.id = pr.student_id
            WHERE pr.test_id = %s
            ORDER BY pr.pci_score DESC
            """,
            (test_id,),
        )
        records = cursor.fetchall()

        # Add performance band to each
        for rec in records:
            rec["band"] = get_performance_band(float(rec["pci_score"]))

        # Stats
        scores = [float(r["pci_score"]) for r in records]
        stats = get_stats(scores) if scores else {}
        distribution = get_distribution(scores) if scores else {}

        return jsonify({
            "records": records,
            "stats": stats,
            "distribution": distribution,
        })
    finally:
        cursor.close()
        conn.close()


@app.route("/api/pci/student/<int:student_id>")
def api_student_pci(student_id: int):
    """Get PCI history for a student."""
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute(
            """
            SELECT pr.*, t.title AS test_title, t.created_at
            FROM pci_records pr
            JOIN tests t ON t.id = pr.test_id
            WHERE pr.student_id = %s
            ORDER BY pr.generated_at DESC
            """,
            (student_id,),
        )
        records = cursor.fetchall()

        for rec in records:
            rec["band"] = get_performance_band(float(rec["pci_score"]))

        return jsonify({"records": records})
    finally:
        cursor.close()
        conn.close()


@app.route("/api/charts/test/<int:test_id>")
def api_charts_test(test_id: int):
    """Get chart data for a test."""
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute(
            """
            SELECT pr.*, st.name AS student_name
            FROM pci_records pr
            JOIN students st ON st.id = pr.student_id
            WHERE pr.test_id = %s
            ORDER BY pr.pci_score DESC
            """,
            (test_id,),
        )
        records = cursor.fetchall()

        if not records:
            return jsonify({"error": "No PCI records found for this test"}), 404

        labels = [r["student_name"] for r in records]
        pci_scores = [float(r["pci_score"]) for r in records]
        mcq_scores = [float(r["mcq_score"]) for r in records]
        coding_scores = [float(r["coding_score"]) for r in records]
        expl_scores = [float(r["explanation_score"]) for r in records]

        charts = {
            "bar_chart": build_bar_chart_data(labels, pci_scores, "PCI Score (%)"),
            "stacked_bar": build_stacked_bar_data(labels, mcq_scores, coding_scores, expl_scores),
            "doughnut": build_doughnut_data(
                ["MCQ (40%)", "Coding (30%)", "Explanation (30%)"],
                [
                    sum(mcq_scores) / len(mcq_scores) if mcq_scores else 0,
                    sum(coding_scores) / len(coding_scores) if coding_scores else 0,
                    sum(expl_scores) / len(expl_scores) if expl_scores else 0,
                ],
            ),
            "histogram": build_histogram_data(pci_scores),
        }

        stats = get_stats(pci_scores)

        return jsonify({
            "charts": charts,
            "stats": stats,
            "count": len(records),
        })
    finally:
        cursor.close()
        conn.close()


# ─── Main ─────────────────────────────────────────────────────

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    debug = os.environ.get("FLASK_DEBUG", "1") == "1"
    print(f"🔬 PCI Analysis API running on http://127.0.0.1:{port}")
    app.run(host="0.0.0.0", port=port, debug=debug)
