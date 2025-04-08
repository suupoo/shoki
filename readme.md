# SHOKI：音声文字起こしシステム

AI技術を活用して音声を文字起こしする「SHOKI」システムです。会議や講義の録音から簡単に文字起こしを生成できます。

## 主な機能

- **音声文字起こし**: OpenAI Whisperを使用した高精度な音声認識
- **話者ラベル**: 会議の参加者ごとにセリフを分類
- **AI文章補正**: 文字起こし結果を自然な文章に補正（Gemini AI）
- **AI要約生成**: 文字起こし内容の要点をまとめた要約を生成（Gemini AI）
- **結果エクスポート**: テキスト、Markdown、CSVなど様々な形式での保存

## 1. 必要な環境

- Docker および Docker Compose
- 4GB以上のRAM
- インターネット接続（初回のモデルダウンロード用）
- Gemini APIキー（要約機能を使用する場合）

## 2. ファイル構造

以下のファイル構造を作成します：

```
SHOKI/
├── compose.yaml       # Docker Compose設定
├── .env               # 環境変数設定
└── whisper/
    ├── Dockerfile     # Dockerイメージ定義
    ├── .htaccess      # Apache設定
    ├── php.ini        # PHP設定
    ├── index.php      # Webインターフェース
    ├── api.php        # APIエンドポイント
    └── gemini_helper.php # Gemini API連携
```

## 3. セットアップ手順

1. リポジトリをクローンまたはファイルをダウンロード

```bash
git clone https://github.com/yourusername/shoki.git
cd shoki
```

2. 環境設定（必要に応じて）

`.env`ファイルを作成して設定をカスタマイズできます：

```
# ポート設定
PORT=9999

# 音声認識設定
MODEL_SIZE=base  # tiny, base, small, medium, large から選択
LANGUAGE=ja      # 主に使用する言語コード

# Gemini API設定
GEMINI_API_KEY=your_gemini_api_key_here  # 要約機能用APIキー
GEMINI_MODEL=gemini-pro  # gemini-pro, gemini-1.5-flash, gemini-1.5-pro から選択
```

3. Dockerコンテナのビルドと起動

```bash
docker-compose up -d
```

4. 初回起動時の注意点

- 初回起動時には、Whisperモデルがダウンロードされるため、数分〜数十分かかることがあります
- ダウンロードの進行状況はログで確認できます：`docker-compose logs -f`

## 4. 使用方法

1. ブラウザでアクセス：`http://localhost:9999`
2. 音声ファイルをアップロードし、必要に応じてオプションを設定
3. 「処理を開始」ボタンをクリックして文字起こしを実行
4. 処理完了後、結果を確認・ダウンロード
5. 「要約」タブで「生成」ボタンをクリックすると、AIによる文章補正と要約が行われます

## 5. システム仕様

### 対応音声フォーマット
- MP3, WAV, M4A, OGG
- 最大ファイルサイズ: 500MB

### モデルサイズと性能
- tiny: 最も軽量、低精度（低スペックPCに最適）
- base: 軽量、やや低精度
- small: 中程度のサイズと精度
- medium: バランスの取れた精度と処理速度（推奨）
- large: 最高精度、高負荷

### Gemini AIモデル
- gemini-pro: Gemini 1.0 Pro（安定性重視）
- gemini-1.5-flash: Gemini 1.5 Flash（速度重視）
- gemini-1.5-pro: Gemini 1.5 Pro（精度重視）

### メモリ要件
- 文字起こし: 2GB以上のRAM

## 6. トラブルシューティング

### 一般的な問題

- **エラー「メモリ不足」**: モデルサイズを小さくする
- **処理が遅い**: より小さいモデルサイズを選択
- **特定の言語での精度が低い**: 適切な言語コードを指定
- **NumPyエラー**: Dockerfileの依存関係バージョンを確認・修正
- **「APIキーが設定されていません」**: `.env`ファイルにGemini APIキーを設定
- **要約機能の404エラー**: Geminiモデル名やAPIエンドポイントが最新か確認

### ログの確認

```bash
docker-compose logs -f
```

### キャッシュのクリア

```bash
docker-compose down
rm -rf ./data/cache/*
docker-compose up -d
```

## ライセンスとメンテナンス状況

### ライセンス情報

このプロジェクト「SHOKI：音声文字起こしシステム」は**MITライセンス**の下で公開されています。

本プロジェクトは以下の外部ライブラリおよびモデルに依存しており、それぞれ独自のライセンスが適用されます：

- **OpenAI Whisper**: MITライセンス
- **Google Gemini AI**: [Google APIサービス利用規約](https://developers.google.com/terms)

### メンテナンス状況

**重要**: 本プロジェクトは現在、アクティブにメンテナンスされていません。

- バグ修正や機能追加の予定はありません
- セキュリティアップデートは提供されない可能性があります
- 依存ライブラリの非互換性によって将来的に動作しなくなる可能性があります

このプロジェクトは「現状のまま」提供され、いかなる保証もありません。本番環境での使用は自己責任で行ってください。

### コントリビューション

このプロジェクトはアクティブにメンテナンスされていませんが、フォークして独自のバージョンを開発することを歓迎します。

## 謝辞

このプロジェクトは以下のオープンソースプロジェクトとAPIに支えられています：

- [OpenAI Whisper](https://github.com/openai/whisper)
- [Tailwind CSS](https://tailwindcss.com/)
- [Google Gemini AI](https://ai.google.dev/)

各プロジェクトの開発者の皆様に感謝いたします。