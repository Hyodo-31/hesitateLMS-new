#!/usr/bin/env python3
"""Generate Japanese feedback text from feature attributions for word-level hesitation."""
import numpy as np


def _as_feature_importances(shap_values):
    values = np.asarray(shap_values)
    if values.ndim == 1:
        return np.abs(values)
    return np.abs(values).mean(axis=0)


def generate_hesitation_feedback(shap_values, feature_names, top_k=3):
    """Create explanation text based on top-k important features.

    `shap_values` can be either:
      - 1D (single sample contribution vector), or
      - 2D (n_samples x n_features) matrix.
    """
    if not feature_names:
        return ""

    importances = _as_feature_importances(shap_values)
    feature_names = list(feature_names)
    top_k = max(1, min(int(top_k), len(feature_names)))

    important_idx = np.argsort(importances)[::-1]
    important_features = [(feature_names[i], float(importances[i])) for i in important_idx]
    top_features = important_features[:top_k]

    grouped_features = {
        "単語の長さに起因して操作中に迷いが生じた可能性があります。": ["word_length"],
        "文中の位置の影響で、後半ほど判断に時間がかかった可能性があります。": ["position_ratio"],
        "選択中に同じ単語へ繰り返し注目しており、迷いが発生した可能性があります。": ["label_hit_count", "hlabel_hit_count"],
        "ドラッグ&ドロップ操作が増え、試行錯誤しながら解いた可能性があります。": ["drag_count", "drop_count"],
        "単語上での滞在時間が長く、慎重に検討していた可能性があります。": ["dwell_time"],
        "問題全体の解答時間が長く、判断に迷いがあった可能性があります。": ["total_time"],
    }

    feedback = []
    for message, features in grouped_features.items():
        matched = [name for name, _ in top_features if name in features]
        if matched:
            feature_names_str = "、".join(matched)
            feedback.append(f"{feature_names_str}が高いことは、{message}")

    other_features = [
        name for name, _ in top_features
        if all(name not in group for group in grouped_features.values())
    ]
    if other_features:
        feedback.append(
            f"{', '.join(other_features)}の影響が大きく、単語選択時に迷いが生じた可能性があります。"
        )

    if not feedback:
        return "主要特徴量からは明確な迷い要因を特定できませんでした。"
    return " ".join(feedback)
