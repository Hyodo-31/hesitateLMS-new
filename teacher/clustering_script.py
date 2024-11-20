import sys
import pandas as pd
from sklearn.preprocessing import MinMaxScaler
from sklearn.cluster import KMeans
import json

# 入力ファイルと出力ファイルのパス
input_file = sys.argv[1]
output_file = sys.argv[2]

# データの読み込み
data = pd.read_csv(input_file)

# 特徴量部分の列名を抽出（uid と name を除く）
feature_columns = data.columns.difference(['uid', 'name'])

# 学生単位で特徴量を集約 (平均値を算出)
aggregated_data = data.groupby('uid')[feature_columns].mean().reset_index()

# 名前の情報をマージ (uid と name を対応付け)
aggregated_data = pd.merge(
    aggregated_data,
    data[['uid', 'name']].drop_duplicates(),
    on='uid'
)

# 特徴量部分をスケーリング (最小-最大正規化を適用)
scaler = MinMaxScaler()
scaled_features = scaler.fit_transform(aggregated_data[feature_columns])

# クラスタリング実行
kmeans = KMeans(n_clusters=3, random_state=42)
aggregated_data['cluster'] = kmeans.fit_predict(scaled_features)

# クラスタごとに結果をまとめる
clusters = {}
for cluster_id in range(kmeans.n_clusters):
    cluster_data = aggregated_data[aggregated_data['cluster'] == cluster_id]
    clusters[cluster_id] = [{'id': row['uid'], 'name': row['name']} for _, row in cluster_data.iterrows()]

# JSONで結果を保存
with open(output_file, 'w') as f:
    json.dump(clusters, f, ensure_ascii=False)

print(f"クラスタリング結果が {output_file} に保存されました。")
