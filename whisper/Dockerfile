FROM php:8.3-apache

WORKDIR /var/www/html

# システムの依存関係をインストール
RUN apt-get update && apt-get install -y \
    ffmpeg \
    build-essential \
    python3 \
    python3-pip \
    python3-dev \
    python3-venv \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHPの拡張機能をインストール
RUN docker-php-ext-install \
    mysqli \
    zip \
    gd \
    mbstring \
    exif \
    pcntl \
    bcmath

# Python仮想環境を作成（外部管理環境の問題を回避）
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# 依存関係のバージョンを固定してインストール
RUN pip3 install --upgrade pip && \
    pip3 install --no-cache-dir \
    numpy==1.24.3 \
    torch==2.0.1 --extra-index-url https://download.pytorch.org/whl/cpu \
    transformers==4.31.0 \
    sentencepiece \
    protobuf==3.20.3 \
    sacremoses \
    requests==2.31.0

# OpenAI Whisperをインストール
RUN pip3 install --no-cache-dir openai-whisper

# Apacheの設定を更新
RUN a2enmod rewrite

# PHP設定をカスタマイズ
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHPカスタム設定ファイルをコピー
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# アップロードサイズの上限を設定
RUN echo "upload_max_filesize = 500M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 900" >> /usr/local/etc/php/conf.d/uploads.ini

# タイムゾーンの設定
RUN echo "date.timezone = Asia/Tokyo" >> /usr/local/etc/php/conf.d/timezone.ini

# ディレクトリの作成
RUN mkdir -p /data /data/uploads /data/processed /data/exports /data/logs /data/config /data/cache /data/archives

# scriptsディレクトリの作成
RUN mkdir -p /var/www/html/scripts

# キャッシュディレクトリの作成と権限設定（Permission denied問題を解決）
ENV XDG_CACHE_HOME=/data/cache

# ディレクトリの権限を設定
RUN chown -R www-data:www-data /var/www/html /data && \
    chmod -R 775 /data

# 環境変数の設定
ENV MODEL_SIZE=base
ENV LANGUAGE=ja
ENV PYTHONPATH=/opt/venv/lib/python3.*/site-packages
ENV TRANSFORMERS_CACHE=/data/cache/transformers
ENV GEMINI_API_KEY=""
ENV GEMINI_MODEL="gemini-1.5-pro"

# Dockerホスト名解決のためのhostsファイル追記スクリプト（Mac/Windows対応）
RUN echo '#!/bin/bash\n\
# Dockerホストの解決を追加\n\
HOST_DOMAIN="host.docker.internal"\n\
if ! grep -q $HOST_DOMAIN /etc/hosts; then\n\
  echo -e "$(ip route | awk '"'"'/default/ { print $3 }'"'"')\t$HOST_DOMAIN" >> /etc/hosts\n\
fi\n\
\n\
# Apacheを起動\n\
apache2-foreground\n' > /usr/local/bin/docker-entrypoint.sh && \
chmod +x /usr/local/bin/docker-entrypoint.sh

# ポートの公開
EXPOSE 80

# エントリーポイントの設定
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]