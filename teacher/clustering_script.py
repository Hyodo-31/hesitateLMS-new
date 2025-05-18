
import sys
import pandas as pd
from sklearn.preprocessing import MinMaxScaler
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans

# pyclustering 用
from pyclustering.cluster.xmeans import xmeans, splitting_type
from pyclustering.cluster.gmeans import gmeans
from pyclustering.cluster.center_initializer import kmeans_plusplus_initializer

import json

# コマンドライン引数
input_file = sys.argv[1]
output_file = sys.argv[2]
cluster_count = int(sys.argv[3])
#method = "kmeans"  # デフォルトは k-means
method = sys.argv[4]  # 'kmeans' or 'xmeans' or 'gmeans'

# データ読み込み
data = pd.read_csv(input_file)

# uid, name, wid を除く特徴量のみ取得
feature_columns = data.columns.difference(['uid', 'name', 'wid'])

# 学生単位で平均をとる
aggregated_data = data.groupby('uid')[feature_columns].mean().reset_index()

# 名前情報のマージ
aggregated_data = pd.merge(
    aggregated_data,
    data[['uid', 'name']].drop_duplicates(),
    on='uid'
)

# スケーリング
scaler = MinMaxScaler()
scaled_features = scaler.fit_transform(aggregated_data[feature_columns])

print(scaled_features)

# PCA (2次元) 
if scaled_features.shape[1] > 2:
    pca = PCA(n_components=2)
    reduced_features = pca.fit_transform(scaled_features)
    aggregated_data['pca1'] = reduced_features[:, 0]
    aggregated_data['pca2'] = reduced_features[:, 1]
elif scaled_features.shape[1] == 2:
    aggregated_data['pca1'] = scaled_features[:, 0]
    aggregated_data['pca2'] = scaled_features[:, 1]
else:
    aggregated_data['pca1'] = scaled_features[:, 0]
    aggregated_data['pca2'] = 0

# クラスタリングの分岐
if method == 'kmeans':
    # --- K-Means ---
    model = KMeans(n_clusters=cluster_count, random_state=42)
    aggregated_data['cluster'] = model.fit_predict(scaled_features)

elif method == 'xmeans':
    # --- X-Means ---
    # 初期クラスタ中心を kmeans++ で抽出
    initial_centers = kmeans_plusplus_initializer(scaled_features, cluster_count,random_state=42).initialize()
    # X-means インスタンスを作成 (BIC を利用)
    xmeans_instance = xmeans(scaled_features, initial_centers, 
                             tolerance=0.0001, criterion=splitting_type.BAYESIAN_INFORMATION_CRITERION, ccore=True)
    xmeans_instance.process()
    # クラスタ結果 (index のリスト)
    x_clusters = xmeans_instance.get_clusters()
    
    # 各データポイントがどのクラスタに属するかを aggregated_data['cluster'] に記録する
    # x_clusters は例えば [[0, 5, 6], [1,2,4], [3,7,8], ...] のような構造
    labels = [-1] * len(scaled_features)
    for cluster_id, points_idx_list in enumerate(x_clusters):
        for idx in points_idx_list:
            labels[idx] = cluster_id
    aggregated_data['cluster'] = labels

elif method == 'gmeans':
    # --- G-Means ---
    # 初期クラスタ中心を kmeans++ で抽出
    initial_centers = kmeans_plusplus_initializer(scaled_features, 1).initialize()
    # G-Means インスタンス (初期1クラスタから開始)
    gmeans_instance = gmeans(scaled_features, 2, ccore=True)
    gmeans_instance.process()
    g_clusters = gmeans_instance.get_clusters()
    
    labels = [-1] * len(scaled_features)
    for cluster_id, points_idx_list in enumerate(g_clusters):
        for idx in points_idx_list:
            labels[idx] = cluster_id
    aggregated_data['cluster'] = labels

else:
    print("Unsupported method:", method)
    sys.exit(1)

# 結果を clusters 辞書にまとめる (クラスタIDごとに配列)
clusters = {}
for cluster_id in sorted(aggregated_data['cluster'].unique()):
    cluster_id = int(cluster_id)  # numpy.int64 を Python の int に変換
    cluster_points = aggregated_data[aggregated_data['cluster'] == cluster_id]
    clusters[cluster_id] = [
        {
            'id': row['uid'],
            'name': row['name'],
            'pca1': row['pca1'],
            'pca2': row['pca2']
        }
        for _, row in cluster_points.iterrows()
    ]


# JSON出力
with open(output_file, 'w') as f:
    json.dump({'clusters': clusters}, f, ensure_ascii=False)

print(f"クラスタリング結果が {output_file} に保存されました (method={method}).")
"""
#クラスタリングプログラムk-means法を用いてクラスタリングを行うプログラム

import sys
import pandas as pd
from sklearn.preprocessing import MinMaxScaler
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
import json

# 入力ファイルと出力ファイルのパス
input_file = sys.argv[1]
output_file = sys.argv[2]
cluster_count = int(sys.argv[3])

# データの読み込み
data = pd.read_csv(input_file)

# 特徴量部分の列名を抽出（uid、name、widを除く）
feature_columns = data.columns.difference(['uid', 'name', 'wid'])

# 学生単位で特徴量を集約 (平均値を算出)
aggregated_data = data.groupby('uid')[feature_columns].mean().reset_index()

#aggregated_dataの表示
print(aggregated_data)


# 名前の情報をマージ (uid と name を対応付け)
aggregated_data = pd.merge(
    aggregated_data,
    data[['uid', 'name']].drop_duplicates(),
    on='uid'
)

# 特徴量部分をスケーリング (最小-最大正規化を適用)
scaler = MinMaxScaler()
scaled_features = scaler.fit_transform(aggregated_data[feature_columns])

# PCAによる次元削減（2次元）
if scaled_features.shape[1] > 2:  # 特徴量が2次元以上の場合のみPCAを適用
    pca = PCA(n_components=2)
    reduced_features = pca.fit_transform(scaled_features)
    aggregated_data['pca1'] = reduced_features[:, 0]
    aggregated_data['pca2'] = reduced_features[:, 1]
elif scaled_features.shape[1] == 2:
    aggregated_data['pca1'] = scaled_features[:, 0]
    aggregated_data['pca2'] = scaled_features[:, 1]
else:
    aggregated_data['pca1'] = scaled_features[:, 0]
    aggregated_data['pca2'] = 0

# クラスタリング実行
kmeans = KMeans(n_clusters=cluster_count, random_state=42)
aggregated_data['cluster'] = kmeans.fit_predict(scaled_features)

# クラスタごとに結果をまとめる（可視化用のPCA値を含む）
clusters = {}
for cluster_id in range(kmeans.n_clusters):
    cluster_data = aggregated_data[aggregated_data['cluster'] == cluster_id]
    clusters[cluster_id] = [
        {
            'id': row['uid'],
            'name': row['name'],
            'pca1': row['pca1'],
            'pca2': row['pca2']
        } for _, row in cluster_data.iterrows()
    ]

# JSONで結果を保存
with open(output_file, 'w') as f:
    json.dump({'clusters': clusters}, f, ensure_ascii=False)

print(f"クラスタリング結果が {output_file} に保存されました。")
"""

