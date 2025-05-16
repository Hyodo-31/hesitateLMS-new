import sys
import pandas as pd
from sklearn.cluster import KMeans
import json

def main():
    # コマンドライン引数からCSVファイルとクラスタ数を取得
    csv_file = sys.argv[1]
    cluster_count = int(sys.argv[2])

    # CSVファイルを読み込む
    df = pd.read_csv(csv_file)

    # クラスタリングの実行
    kmeans = KMeans(n_clusters=cluster_count, random_state=42)
    df['cluster'] = kmeans.fit_predict(df.iloc[:, 1:])

    # 結果をJSON形式に変換
    result = df.to_dict(orient='records')
    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
