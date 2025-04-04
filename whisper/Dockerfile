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

# OpenAI Whisperのインストール（公式パッケージ）
RUN pip3 install --upgrade pip && \
    pip3 install --no-cache-dir openai-whisper

# Apacheの設定を更新
RUN a2enmod rewrite

# PHP設定をカスタマイズ
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHPカスタム設定ファイルをコピー
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# アップロードサイズの上限を設定
RUN echo "upload_max_filesize = 500M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 900" >> /usr/local/etc/php/conf.d/uploads.ini

# タイムゾーンの設定
RUN echo "date.timezone = Asia/Tokyo" >> /usr/local/etc/php/conf.d/timezone.ini

# ディレクトリの作成
RUN mkdir -p /models /data /data/uploads /data/processed /data/exports /data/logs /data/config /data/cache

# キャッシュディレクトリの作成と権限設定（Permission denied問題を解決）
ENV XDG_CACHE_HOME=/data/cache

# PHPファイルをコピー
COPY index.php api.php /var/www/html/

# ディレクトリの権限を設定（修正: www-dataユーザーに十分な権限を付与）
RUN chown -R www-data:www-data /var/www/html /data /models && \
    chmod -R 775 /data /models

# 環境変数の設定
ENV MODEL_SIZE=medium
ENV LANGUAGE=ja
ENV PYTHONPATH=/opt/venv/lib/python3.*/site-packages

# ポートの公開
EXPOSE 80

# Apacheの起動
CMD ["apache2-foreground"]