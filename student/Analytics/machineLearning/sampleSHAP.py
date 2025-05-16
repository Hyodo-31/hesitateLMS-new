import sys
#sys.path.append('c:\\users\\tomoya\\anaconda3\\lib\\site-packages')
import io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
#import matplotlib.pyplot as plt
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
from sklearn.model_selection import cross_val_score,KFold,cross_validate
from sklearn.metrics import precision_score, recall_score, f1_score, classification_report
import numpy as np
import json
import mysql.connector
#import shap
#import matplotlib.font_manager as fm

# フォントの設定
#plt.rcParams['font.family'] = 'IPAexGothic'  # フォント名を指定
# 利用可能な日本語フォントを指定
#plt.rcParams['font.family'] = 'Meiryo'  # 'Meiryo', 'Yu Gothic', 'MS Gothic' などから選択


# 利用可能なフォントを確認
#available_fonts = [f.name for f in fm.fontManager.ttflist if 'Gothic' in f.name or 'Mincho' in f.name or 'Meiryo' in f.name]
#print(available_fonts)

# 必要なパートを含む上記のコードをインポートまたは実装

"""
def plot_feature_importance_comparison(featuresdict, shap_values, features):
    # ジニ係数の特徴量の重要度
    gini_importances = pd.DataFrame(list(featuresdict), columns=['Feature', 'Importance'])

    # SHAPの特徴量の重要度
    shap_importances = np.abs(shap_values).mean(axis=0)
    shap_importances_df = pd.DataFrame({'Feature': features, 'Importance': shap_importances})

    # プロット用にデータフレームをソート
    gini_importances = gini_importances.sort_values(by='Importance', ascending=True)
    shap_importances_df = shap_importances_df.sort_values(by='Importance', ascending=True)

    # プロットの作成
    fig, ax = plt.subplots(1, 2, figsize=(14, 8))

    # ジニ係数による特徴量の重要度
    ax[0].barh(gini_importances['Feature'], gini_importances['Importance'], color='skyblue')
    ax[0].set_title('Feature Importance (Gini Coefficient)')
    ax[0].set_xlabel('Importance')

    # SHAP値による特徴量の重要度
    ax[1].barh(shap_importances_df['Feature'], shap_importances_df['Importance'], color='salmon')
    ax[1].set_title('Feature Importance (SHAP Values)')
    ax[1].set_xlabel('Importance')

    plt.tight_layout()
    plt.show()
"""
def generate_hesitation_feedback(shap_values, feature_names):
    # SHAP値が重要な順に特徴量をソート
    #絶対値をとる
    #feature_importances = np.abs(shap_values).mean(axis=0)
    """
    feature_importances = shap_values.mean(axis=0)
    important_features = sorted(zip(feature_names, feature_importances), key=lambda x: x[1], reverse=True)
    # SHAP値の絶対値で特徴量を並べ替え
    """
    absolute_importances = np.abs(shap_values).mean(axis=0)
    important_features_indices = np.argsort(absolute_importances)[::-1]
    # SHAP値の絶対値で特徴量を並べ替え
    absolute_importances = np.abs(shap_values).mean(axis=0)
    important_features_indices = np.argsort(absolute_importances)[::-1]


    # インデックスを元に元のSHAP値から全ての特徴量を取得
    important_features = [(feature_names[i], shap_values.mean(axis=0)[i]) for i in important_features_indices]

    # 各特徴量のSHAP値を表示
    #print("Feature Importances based on SHAP values:")
    #for feature, importance in important_features:
    #    print(f"{feature}: {importance}")

    
    
    # 上位三件の特徴量を特定
    top_three_features = important_features[:3]
    
    feedback = []
    grouped_features = {
        "解答時間が長くなり、迷いが生じた可能性を示しています。": ["answeringTime", "time"],
        "迷いが生じた際に，マウスを動作する癖を持っている可能性があります": ["distance"],
        "低いことで，迷いが生じている可能性があります。": ["averageSpeed", "maxSpeed"],
        "迷いに起因している可能性があります。これは迷いの際にレジスタを使用し，単語をチャンク単位で分割して考えている可能性があります。": [
            "register_move_count1", "register_move_count2", "register_move_count3", 
            "register_move_count4", "register01count1", "register01count2", 
            "register01count3", "register01count4", "registerDDCount"],
        "迷いの際にグループ化機能を使用した回数が多いことを示しています。グループ化された単語のチャンクを見ることで学習者の理解している単語群を知ることが出来る可能性があります。": ["groupingDDCount"],
        "迷いが発生した際はグループ化機能を使用していることが考えられます。": ["groupingCountbool"],
        "迷いが生じた際に，単語と単語の選択の間が長いことを示しています。": ["maxDDIntervalTime", "minDDIntervalTime", "totalDDIntervalTime"],
        "迷いが生じた際にドラッグしながら迷っている可能性があります。": ["totalDDTime", "maxDDTime", "minDDTime"],
        "迷いが生じた際に単語のドラッグアンドドロップ操作が多くなっている可能性があります。": ["DDCount"],
        "迷いに起因している可能性があります。これは，学習者が解答を始めるまでに頭の中で思考している可能性があります。": ["thinkingTime"],
        "迷いに起因している可能性があります。これは，迷いが生じた際にマウスの上下左右の移動を行い，問題文や日本語文，解答文を何度も読み直している可能性があります。": ["xUTurnCount", "yUTurnCount", "xUTurnCountDD", "yUTurnCountDD"],
        "迷いに起因している可能性があります。これは解答文を並べ替えた後によく注意した後に決定ボタンを押している可能性があります": ["FromlastdropToanswerTime"],
        "迷いに起因している可能性があります。これは迷いが生じた際はマウスから手を放して考慮している癖があると考えられます。": ["totalStopTime", "maxStopTime", "stopcount"],
    }

    for message, features in grouped_features.items():
        # 該当する特徴量を抽出
        matched_features = [feature for feature, _ in top_three_features if feature in features]
        if matched_features:
            # 該当する特徴量名を結合してフィードバックメッセージを作成
            feature_names_str = "、".join(matched_features)
            feedback.append(f"{feature_names_str}が高いことは、{message}")

    # 他の特徴量に対するフィードバックメッセージも追加可能
    other_features = [feature for feature, _ in top_three_features if all(feature not in group for group in grouped_features.values())]
    if other_features:
        feedback.append(f"{', '.join(other_features)}の値が高いことは、問題を解く際に迷いが生じた可能性を示しています。")

    # フィードバックを1つの文章にまとめる
    return " ".join(feedback)
"""
def generate_hesitation_feedback(shap_values, feature_names):
    feedback = []
    
    # SHAP値が重要な順に特徴量をソート
    feature_importances = np.abs(shap_values).mean(axis=0)
    important_features = sorted(zip(feature_names, feature_importances), key=lambda x: x[1], reverse=True)
    
    # 上位三件の特徴量を特定
    top_three_features = important_features[:3]
    
    # 迷いに関連する特徴量のリストを生成
    feature_names_str = ', '.join([feature for feature, _ in top_three_features])
    # 特徴量に基づくメッセージの生成
    for feature, _ in top_three_features:
        if feature in ["answeringTime", "time"]:
            feedback.append(f"{feature}が高いことは、解答時間が長くなり、迷いが生じた可能性を示しています．")
        elif feature == "totalDDIntervalTime":
            feedback.append(f"{feature}が高いことは、解答中に迷いを生じながら解答している可能性があります．")
        elif feature == "distance":
            feedback.append(f"{feature}が高いことは、迷いが生じた際に，マウスを動作する癖を持っている可能性があります")
        elif feature in ["averageSpeed","maxSpeed"]:
            feedback.append(f"{feature}が低いことで，迷いが生じている可能性があります．")
        elif feature in ["register_move_count1","register_move_count2","register_move_count3","register_move_count4","register01count1","register01count2","register01count3","register01count4","registerDDCount"]:
            feedback.append(f"{feature}が迷いに起因している可能性があります．これは迷いの際にレジスタを使用し，単語をチャンク単位で分割して考えている可能性があります．")
        elif feature in ["groupingDDCount"]:
            feedback.append(f"{feature}が高いことは，迷いの際にグループ化機能を使用した回数が多いことを示しています．グループ化された単語のチャンクを見ることで学習者の理解している単語群を知ることが出来る可能性があります．")
        elif feature in ["groupingCountbool"]:
            feedback.append(f"迷いが発生した際はグループ化機能を使用していることが考えられます．")
        elif feature in ["maxDDIntervalTime","minDDIntervalTime","totalDDIntervalTime"]:
            feedback.append(f"{feature}が高いことは迷いが生じた際に，単語と単語の選択の間が長いことを示しています．")
        elif feature in ["totalDDTime","maxDDTime","minDDTime"]:
            feedback.append(f"{feature}が高いことは迷いが生じた際にドラッグしながら迷っている可能性があります．")
        elif feature in ["DDCount"]:
            feedback.append(f"{feature}が高いことは迷いが生じた際に単語のドラッグアンドドロップ操作が多くなっている可能性があります．")
        elif feature in ["thinkingTime"]:
            feedback.append(f"{feature}が迷いに起因している可能性があります．これは，学習者が解答を始めるまでに頭の中で思考している可能性があります．")
        elif feature in ["xUTurnCount","yUTurnCount","xUTurnCountDD","yUTurnCountDD"]:
            feedback.append(f"{feature}が迷いに起因している可能性があります．これは，迷いが生じた際にマウスの上下左右の移動を行い，問題文や日本語文，解答文を何度も読み直している可能性があります．")
        elif feature in ["FromlastdropToanswerTime"]:
            feedback.append(f"{feature}が迷いに起因している可能性があります．これは解答文を並べ替えた後によく注意した後に決定ボタンを押している可能性があります")
        elif feature in ["totalStopTime","maxStopTime","stopcount"]:
            feedback.append(f"{feature}が間宵に起因している可能性があります．これは迷いが生じた際はマウスから手を放して考慮している癖があると考えられます．")
        # 他の特徴量に対するフィードバックメッセージも追加可能
        else:
            feedback.append(f"{feature}の値が高いことは、問題を解く際に迷いが生じた可能性を示しています。")

    # フィードバックを1つの文章にまとめる
    return " ".join(feedback)
"""
class Classify:
    def __init__(self,df,testdf,results_csv,metrics_json):
        self.df = df                #教師データ
        self.testdf = testdf        #実際の未知のデータ
        self.results_csv = results_csv
        self.metrics_json = metrics_json
        self.Understand1_count = 0
        self.Understand2_count = 0
        self.Understand3_count = 0
        self.Understand4_count = 0
        self.mean_accuracy = 0
        self.precision_y = 0
        self.recall_y = 0
        self.f1score_y = 0
        self.precision_n = 0
        self.recall_n = 0
        self.f1score_n = 0
        self.classifydf = pd.DataFrame()
        self.Featureimportances = []
        self.result = {}

    def binary(self):
        self.classify_df=self.df[self.df['Understand'].isin([2,4])]    #2がかなり迷った4がほとんど迷わなかった
        return self.classify_df

    def countUnderstand(self,df):
        self.Understand1_count = df[df['Understand'] == 1].shape[0]
        self.Understand2_count = df[df['Understand'] == 2].shape[0]
        self.Understand3_count = df[df['Understand'] == 3].shape[0]
        self.Understand4_count = df[df['Understand'] == 4].shape[0]

    def makingclassifydf(self):
        if self.Understand2_count >= self.Understand4_count:
            samplerow = self.Understand4_count
        else:
            samplerow = self.Understand2_count

        sampledf_2 =self.classify_df[self.classify_df['Understand'] == 2].sample(n=samplerow,random_state = 0)
        sampledf_4 =self.classify_df[self.classify_df['Understand'] == 4].sample(n=samplerow,random_state = 0)

        self.classifydf = pd.concat([sampledf_2,sampledf_4])
        #print(self.classifydf)
    def train_model(self):
        #データの準備
        tmpdf = self.classifydf     #これは教師データ（モデル学習用）
        tmpdf = tmpdf.drop(["UID","WID","Understand","attempt"], axis=1)
        self.features = tmpdf.columns.tolist()
        self.featuresdict = {}
        objective = ['Understand']

        for i in self.features:
            self.featuresdict[i] = 0

        X_data = self.classifydf[self.features]             #モデル作成用の教師データ（特徴量）
        Y_data = self.classifydf[objective]                 #モデル作成用の教師データ（目的変数）
        self.model = RandomForestClassifier(n_estimators=100, random_state=0)
        self.model.fit(X_data, Y_data)
        print("モデル学習完了")
    def predict_new_data(self):
        """
        新しいデータを予測する関数
        :param new_data:新規データ(dataframe)
        :return :　予測結果
        """
        print("教師データの個数" + str(self.classifydf.shape[0]))
        print("予測データの個数" + str(self.testdf.shape[0]))
        if self.model is None:
            print("モデルが学習されていません。")
        
        new_data_preprocessed = self.testdf[self.features]
        #予測の実行
        predictions = self.model.predict(new_data_preprocessed)

        # UID, WID, 予測結果を格納
        results = pd.DataFrame({
            "UID": self.testdf["UID"],
            "WID": self.testdf["WID"],
            "Understand": predictions,
            "attempt": self.testdf["attempt"]
        })
        #結果をcsvファイルに保存
        results.to_csv(self.results_csv, index=False)


        # 結果の出力完了メッセージ
        print("予測結果が './machineLearning/results_actual.csv' に保存されました。")
        

class DBaction:
    def __init__(self,host,user,password,dbname):
        self.host = host
        self.user = user
        self.password = password
        self.dbname = dbname
        self.conn = None
        self.cursor = None

    def connectDB(self):
        try:
            self.conn = mysql.connector.connect(
                host = self.host,
                user = self.user,
                password = self.password,
                database = self.dbname
            )
            self.cursor = self.conn.cursor()
            print("MySQL connection established")
        except mysql.connector.Error as e:
            print(f"Error connecting to MySQL database: {e}")

    def execute_query(self, query, params=None):
        try:
            self.cursor.execute(query, params)
            self.conn.commit()
            print("Query executed successfully")
        except mysql.connector.Error as e:
            print(f"Error executing query: {e}")

    def insertDB(self,model_name,featurename,gini_results,accuracy,precision_y,precision_n,recall_y,recall_n,f1score_y,f1score_n):
        query = """
        INSERT INTO ml_results (model_name,featurename,gini_results,acc_result,pre_result_y,pre_result_n,rec_result_y,rec_result_n,f1_score_y,f1_score_n)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        gini_results_json = json.dumps(gini_results)
        params = (model_name,featurename,gini_results_json,accuracy,precision_y,precision_n,recall_y,recall_n,f1score_y,f1score_n)
        self.execute_query(query, params)


        

def main():
    #データセット作成
    # コマンドライン引数からファイルパスを取得
    #input_csv:学習データ
    #input_csv_test:実際の未知のデータ
    #results_csv:学習データの予測結果
    #matrics_json:評価指標
    if len(sys.argv) != 5:
        print("引数の個数は" + str(len(sys.argv)) + "個")
        print("Usage: python script.py <input_csv> <input_csv_test> <results_csv> <metrics_json>")
        sys.exit(1)

    inputfilename = sys.argv[1]
    testfilename = sys.argv[2]
    results_csv = sys.argv[3]
    metrics_json = sys.argv[4]

    df = pd.read_csv(inputfilename)
    testdf = pd.read_csv(testfilename)
    datamarge=Classify(df,testdf,results_csv,metrics_json)

    #データを分割
    return_df = datamarge.binary()      #全データの中から迷い有りと無しのみ抽出
    datamarge.countUnderstand(return_df)    #迷い有りと無しの数を数える

    datamarge.makingclassifydf()        #ここで迷い無しとありが1:1のデータセットができている．
    datamarge.train_model()             #モデル学習
    datamarge.predict_new_data()

    


    """
    #データベース接続
    db = DBaction(host="127.0.0.1",user="root",password="8181saisaI",dbname="2019su1")
    db.connectDB()
    #データベースinsertするデータ
    model_name = "RandomForest" #今後別のアルゴリズムを使用するなら可変にする
    featurename = json.dumps(datamarge.features)  # リストをJSON文字列に変換    #特徴量の任意設定可能にする
    gini_results = datamarge.featuresdict  #ジニ係数をこの形式で記述する
    accuracy = datamarge.mean_accuracy
    precision_y = datamarge.precision_y
    recall_y = datamarge.recall_y
    f1_score_y = datamarge.f1score_y
    precision_n = datamarge.precision_n
    recall_n = datamarge.recall_n
    f1_score_n = datamarge.f1score_n

    #db.insertDB(model_name,featurename,gini_results,accuracy,precision_y,precision_n,recall_y,recall_n,f1_score_y,f1_score_n)
    """
    


    


if __name__ == '__main__':
    main()