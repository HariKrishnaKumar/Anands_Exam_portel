"""
PCI Calculation Logic.

PCI (Performance Competency Index) is a weighted composite score:
    PCI = (MCQ% × 0.40) + (Coding% × 0.30) + (Explanation% × 0.30)
"""

from typing import Dict, Optional


def calculate_pci(
    mcq_score: float,
    mcq_total: float,
    coding_score: float,
    coding_total: float,
    explanation_score: float,
    explanation_total: float,
    mcq_weight: float = 40.0,
    coding_weight: float = 30.0,
    explanation_weight: float = 30.0,
) -> Dict[str, float]:
    """
    Calculate PCI and category percentages.

    Args:
        mcq_score: Marks obtained in MCQ questions
        mcq_total: Total marks for MCQ questions
        coding_score: Marks obtained in Coding questions
        coding_total: Total marks for Coding questions
        explanation_score: Marks obtained in Explanation questions
        explanation_total: Total marks for Explanation questions
        mcq_weight: Weight percentage for MCQ (default 40%)
        coding_weight: Weight percentage for Coding (default 30%)
        explanation_weight: Weight percentage for Explanation (default 30%)

    Returns:
        Dict with keys: pci_score, mcq_percent, coding_percent, explanation_percent
    """

    mcq_percent = (mcq_score / mcq_total * 100) if mcq_total > 0 else 0.0
    coding_percent = (coding_score / coding_total * 100) if coding_total > 0 else 0.0
    explanation_percent = (explanation_score / explanation_total * 100) if explanation_total > 0 else 0.0

    total_weight = mcq_weight + coding_weight + explanation_weight

    if total_weight > 0:
        pci_score = (
            (mcq_percent * mcq_weight)
            + (coding_percent * coding_weight)
            + (explanation_percent * explanation_weight)
        ) / total_weight
    else:
        pci_score = 0.0

    return {
        "pci_score": round(pci_score, 2),
        "mcq_percent": round(mcq_percent, 2),
        "coding_percent": round(coding_percent, 2),
        "explanation_percent": round(explanation_percent, 2),
    }


def get_performance_band(pci_score: float) -> Dict[str, str]:
    """
    Categorize a PCI score into a performance band.

    Bands:
        - Excellent:  >= 85
        - Good:       >= 70
        - Average:    >= 50
        - Below Avg:  >= 30
        - Poor:       < 30
    """
    if pci_score >= 85:
        return {"band": "Excellent", "color": "#0B6A0B", "icon": "★"}
    elif pci_score >= 70:
        return {"band": "Good", "color": "#0078D4", "icon": "●"}
    elif pci_score >= 50:
        return {"band": "Average", "color": "#8A6D00", "icon": "◆"}
    elif pci_score >= 30:
        return {"band": "Below Average", "color": "#C85A00", "icon": "▲"}
    else:
        return {"band": "Poor", "color": "#BC2F32", "icon": "▼"}


def get_distribution(scores: list) -> Dict:
    """
    Build a histogram distribution from a list of PCI scores.

    Returns a dict with bin labels and counts.
    """
    bins = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
    counts = [0] * (len(bins) - 1)

    for score in scores:
        for i in range(len(bins) - 1):
            if bins[i] <= score < bins[i + 1]:
                counts[i] += 1
                break
        if score == 100.0:
            counts[-1] += 1

    return {
        "labels": [f"{bins[i]}-{bins[i+1]}" for i in range(len(bins) - 1)],
        "counts": counts,
    }


def get_stats(scores) -> Dict:
    """Calculate basic statistics for a list of scores."""
    if not scores:
        return {"mean": 0, "median": 0, "min": 0, "max": 0, "count": 0, "std": 0}

    sorted_scores = sorted(scores)
    n = len(sorted_scores)
    mean = sum(sorted_scores) / n

    # Median
    if n % 2 == 1:
        median = sorted_scores[n // 2]
    else:
        median = (sorted_scores[n // 2 - 1] + sorted_scores[n // 2]) / 2

    # Std deviation
    variance = sum((x - mean) ** 2 for x in sorted_scores) / n

    return {
        "mean": round(mean, 2),
        "median": round(median, 2),
        "min": round(sorted_scores[0], 2),
        "max": round(sorted_scores[-1], 2),
        "count": n,
        "std": round(variance ** 0.5, 2),
    }
