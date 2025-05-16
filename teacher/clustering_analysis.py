import sys
import pandas as pd
import numpy as np
from sklearn.preprocessing import MinMaxScaler
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
import matplotlib.pyplot as plt

# コマンドライン引数
input_file = sys.argv[1]
output_file = sys.argv[2]

# データ読み込み
data = pd.read_csv(input_file)

# 欠損値を補完
data.fillna(data.mean(), inplace=True)

# uid, name, wid を除く特徴量のみ取得
feature_columns = data.columns.difference(['uid', 'name', 'wid'])
scaled_features = MinMaxScaler().fit_transform(data[feature_columns])

# クラスタリング評価用のパラメータ
k_range = range(2, 11)  # クラスタ数を2から10まで試す
inertia = []  # エルボー法用のリスト
silhouette_scores = []  # シルエット法用のリスト

# クラスタリングと評価
for k in k_range:
    kmeans = KMeans(n_clusters=k, random_state=42)
    cluster_labels = kmeans.fit_predict(scaled_features)
    inertia.append(kmeans.inertia_)  # エルボー法の評価値
    silhouette_scores.append(silhouette_score(scaled_features, cluster_labels))  # シルエット法の評価値

# エルボー法のグラフ
plt.figure()
plt.plot(k_range, inertia, marker='o')
plt.title('Elbow Method')
plt.xlabel('Number of Clusters')
plt.ylabel('Inertia')
plt.savefig(output_file.replace('.json', '_elbow.png'))
plt.close()

# シルエット法のグラフ
plt.figure()
plt.plot(k_range, silhouette_scores, marker='o')
plt.title('Silhouette Method')
plt.xlabel('Number of Clusters')
plt.ylabel('Silhouette Score')
plt.savefig(output_file.replace('.json', '_silhouette.png'))
plt.close()

print("エルボー法およびシルエット法のグラフが出力されました。")
