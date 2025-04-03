FROM python:3.9-slim

WORKDIR /app

# 必要なパッケージのインストール
RUN apt-get update && apt-get install -y \
    ffmpeg \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Pythonパッケージのインストール
RUN pip install --no-cache-dir \
    streamlit \
    pandas \
    watchdog

# ディレクトリを作成
RUN mkdir -p /app/audio_files
RUN mkdir -p /app/output_files

# WebUIのスクリプトをコピー
COPY app.py /app/

# Streamlit設定ファイル
RUN mkdir -p /root/.streamlit
RUN echo "\
[server]\n\
enableCORS = false\n\
enableXsrfProtection = false\n\
" > /root/.streamlit/config.toml

# ポートを公開
EXPOSE 8501

# コンテナ起動時にStreamlitを実行
CMD ["streamlit", "run", "app.py", "--server.address=0.0.0.0"]