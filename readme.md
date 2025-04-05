# 議事録自動生成システム - セットアップ手順（最終版）

以下の簡単な手順で議事録自動生成システムをセットアップできます。test.phpの内容をindex.phpに統合し、より簡潔なURLでアクセスできるようになりました。

## 1. ファイル構造の作成

以下のファイル構造を作成します：

```
議事録自動生成システム/
├── docker-compose.yml
├── .env
└── whisper/
    ├── Dockerfile
    ├── php.ini
    ├── index.php
    └── api.php
```

## 2. 各ファイルを以下の内容で作成します：

### docker-compose.yml

```yaml
version: '3'

services:
  whisper:
    build:
      context: ./whisper
      dockerfile: Dockerfile
    volumes:
      - ./data:/data
      - ./models:/models
    ports:
      - "${PORT:-9999}:80"
    environment:
      - MODEL_SIZE=${MODEL_SIZE:-medium}
      - LANGUAGE=${LANGUAGE:-ja}
    restart: unless-stopped
    # 初期化スクリプトを実行
    entrypoint: >
      bash -c "
        mkdir -p /data/uploads /data/processed /data/exports /data/logs /data/config /data/cache &&
        chown -R www-data:www-data /data /models &&
        chmod -R 775 /data /models &&
        apache2-foreground
      "

volumes:
  models:
```

### .env

```
# ポート設定
PORT=9999

# 音声認識設定
MODEL_SIZE=medium
LANGUAGE=ja
```

### whisper/Dockerfile

```dockerfile
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
```

### whisper/php.ini

```ini
[PHP]
; 最大実行時間を拡大（音声処理のため）
max_execution_time = 300
max_input_time = 300

; エラー表示設定
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /data/logs/php_errors.log

; 日本語関連設定
default_charset = "UTF-8"
mbstring.language = Japanese
mbstring.internal_encoding = UTF-8

; セキュリティ設定
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

; アップロード設定
file_uploads = On
upload_max_filesize = 500M
post_max_size = 500M
max_file_uploads = 20

; メモリ設定
memory_limit = 512M
```

### whisper/index.php および whisper/api.php

前述のコードを使用してファイルを作成してください。
- index.php: メインのフォーム画面を含むHTMLコード
- api.php: 音声認識処理を行うPHPコード

## 3. セットアップの実行

セットアップはとても簡単です：

```bash
docker-compose up -d
```

これだけ！必要なディレクトリはすべて自動的に作成され、サービスが起動します。

## 4. 使用方法

ブラウザで以下のURLにアクセスします：

```
http://localhost:9999
```

フォームから音声ファイルをアップロードし、「文字起こしを実行」ボタンをクリックすれば処理が始まります。

## 5. 注意点

- 初回起動時には、Whisperモデルのダウンロードが行われるため、数分かかることがあります。
- PCのスペックに応じて、モデルサイズを調整することをお勧めします：
    - 低スペックマシン：tiny または base
    - 標準的なマシン：medium（デフォルト）
    - 高スペックマシン：large