FROM python:3.9-slim

WORKDIR /app

# 非インタラクティブモードを設定
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Tokyo

# 必要最小限のパッケージのインストール
RUN pip install --no-cache-dir \
    watchdog \
    nltk

# NLTKのデータをダウンロード
RUN python -c "import nltk; nltk.download('punkt')"

# ディレクトリを作成
RUN mkdir -p /app/output_files
RUN mkdir -p /app/summary_files

# 要約スクリプトをコピー
COPY summarize_simple.py /app/summarize.py

# 実行権限を付与
RUN chmod +x /app/summarize.py

# コンテナ起動時にスクリプトを実行
CMD ["python", "summarize.py"]