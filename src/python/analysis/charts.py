"""
Chart data generation utilities for PCI reports.
Returns data structures suitable for Chart.js consumption.
"""

from typing import Dict, List, Optional


def build_bar_chart_data(
    labels: List[str],
    values: List[float],
    label: str = "Score",
    color: str = "#0078D4",
) -> Dict:
    """Build a Chart.js bar chart dataset."""
    return {
        "type": "bar",
        "data": {
            "labels": labels,
            "datasets": [
                {
                    "label": label,
                    "data": values,
                    "backgroundColor": color + "88",
                    "borderColor": color,
                    "borderWidth": 1,
                }
            ],
        },
        "options": {
            "responsive": True,
            "maintainAspectRatio": False,
            "scales": {
                "y": {"beginAtZero": True, "max": 100},
            },
        },
    }


def build_stacked_bar_data(
    labels: List[str],
    mcq_scores: List[float],
    coding_scores: List[float],
    explanation_scores: List[float],
) -> Dict:
    """Build a Chart.js stacked bar chart dataset."""
    return {
        "type": "bar",
        "data": {
            "labels": labels,
            "datasets": [
                {
                    "label": "MCQ",
                    "data": mcq_scores,
                    "backgroundColor": "#0B6A0BCC",
                },
                {
                    "label": "Coding",
                    "data": coding_scores,
                    "backgroundColor": "#C85A00CC",
                },
                {
                    "label": "Explanation",
                    "data": explanation_scores,
                    "backgroundColor": "#8A6D00CC",
                },
            ],
        },
        "options": {
            "responsive": True,
            "maintainAspectRatio": False,
            "scales": {
                "x": {"stacked": True},
                "y": {"stacked": True, "max": 100},
            },
        },
    }


def build_doughnut_data(
    labels: List[str],
    values: List[float],
    colors: Optional[List[str]] = None,
) -> Dict:
    """Build a Chart.js doughnut chart dataset."""
    if colors is None:
        colors = ["#0078D4", "#0B6A0B", "#C85A00", "#8A6D00", "#BC2F32"]

    return {
        "type": "doughnut",
        "data": {
            "labels": labels,
            "datasets": [
                {
                    "data": values,
                    "backgroundColor": colors[: len(values)],
                    "borderWidth": 2,
                }
            ],
        },
        "options": {
            "responsive": True,
            "maintainAspectRatio": False,
        },
    }


def build_radar_data(
    label: str,
    mcq_score: float,
    coding_score: float,
    explanation_score: float,
) -> Dict:
    """Build a Chart.js radar chart for a single student."""
    return {
        "type": "radar",
        "data": {
            "labels": ["MCQ", "Coding", "Explanation"],
            "datasets": [
                {
                    "label": label,
                    "data": [mcq_score, coding_score, explanation_score],
                    "backgroundColor": "rgba(0,120,212,0.2)",
                    "borderColor": "#0078D4",
                    "borderWidth": 2,
                    "pointBackgroundColor": "#0078D4",
                }
            ],
        },
        "options": {
            "responsive": True,
            "maintainAspectRatio": False,
            "scales": {
                "r": {
                    "beginAtZero": True,
                    "max": 100,
                    "ticks": {"stepSize": 20},
                }
            },
        },
    }


def build_histogram_data(scores: List[float]) -> Dict:
    """Build histogram data for PCI score distribution."""
    from .pci import get_distribution

    dist = get_distribution(scores)

    return {
        "type": "bar",
        "data": {
            "labels": dist["labels"],
            "datasets": [
                {
                    "label": "Students",
                    "data": dist["counts"],
                    "backgroundColor": "#0078D488",
                    "borderColor": "#0078D4",
                    "borderWidth": 1,
                }
            ],
        },
        "options": {
            "responsive": True,
            "maintainAspectRatio": False,
            "scales": {
                "y": {"beginAtZero": True, "title": {"display": True, "text": "Number of Students"}},
                "x": {"title": {"display": True, "text": "PCI Score Range (%)"}},
            },
        },
    }
