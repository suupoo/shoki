FROM pytorch/pytorch:2.0.1-cuda11.7-cudnn8-runtime

WORKDIR /app

# 非インタラクティブモードを設定
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Tokyo

# 必要なパッケージのインストール
RUN apt-get update && apt-get install -y \
    ffmpeg \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Pythonパッケージのインストール
RUN pip install --no-cache-dir \
    openai-whisper==20230314 \
    tqdm \
    more-itertools \
    watchdog

# ディレクトリを作成
RUN mkdir -p /app/audio_files
RUN mkdir -p /app/output_files

# 自動文字起こしのスクリプトをコピー
COPY auto_transcribe.py /app/

# 実行権限を付与
RUN chmod +x /app/auto_transcribe.py

# コンテナ起動時にスクリプトを実行
CMD ["python", "auto_transcribe.py"]