# メモ助：議事録自動生成システム

AI技術を活用して音声を文字起こし・要約する「メモ助」システムです。会議や講義の録音から簡単に議事録を生成できます。

## 主な機能

- **音声文字起こし**: OpenAI Whisperを使用した高精度な音声認識
- **要約機能**: rinna/japanese-gpt-neox-3.6b-instruction-ppoモデルによる文字起こしテキストの自動要約
- **話者ラベル**: 会議の参加者ごとにセリフを分類
- **結果エクスポート**: テキスト、Markdown、CSVなど様々な形式での保存

## 1. 必要な環境

- Docker および Docker Compose
- 8GB以上のRAM（要約機能使用時）
- インターネット接続（初回のモデルダウンロード用）

## 2. ファイル構造

以下のファイル構造を作成します：

```
メモ助/
├── compose.yaml       # Docker Compose設定
├── .env               # 環境変数設定
└── whisper/
    ├── Dockerfile     # Dockerイメージ定義
    ├── .htaccess      # Apache設定
    ├── php.ini        # PHP設定
    ├── index.php      # Webインターフェース
    ├── api.php        # APIエンドポイント
    └── scripts/       # Pythonスクリプト
        └── summarize.py # 要約処理スクリプト
```

## 3. セットアップ手順

1. リポジトリをクローンまたはファイルをダウンロード

```bash
git clone https://github.com/yourusername/memo-assistant.git
cd memo-assistant
```

2. 環境設定（必要に応じて）

`.env`ファイルを編集して設定をカスタマイズできます：

```
# ポート設定
PORT=9999

# 音声認識設定
MODEL_SIZE=medium  # tiny, base, small, medium, large から選択
LANGUAGE=ja        # 主に使用する言語コード

# 要約機能設定
SUMMARIZE_ENABLED=true
```

3. Dockerコンテナのビルドと起動

```bash
docker-compose up -d
```

4. 初回起動時の注意点

- 初回起動時には、Whisperモデルと要約モデルがダウンロードされるため、数分〜数十分かかることがあります
- ダウンロードの進行状況はログで確認できます：`docker-compose logs -f`

## 4. 使用方法

1. ブラウザでアクセス：`http://localhost:9999`
2. 音声ファイルをアップロードし、必要に応じてオプションを設定
3. 「処理を開始」ボタンをクリックして文字起こし／要約を実行
4. 処理完了後、結果を確認・ダウンロード

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

### メモリ要件
- 文字起こしのみ: 2GB以上のRAM
- 要約機能使用時: 8GB以上のRAM推奨

## 6. トラブルシューティング

### 一般的な問題

- **エラー「メモリ不足」**: モデルサイズを小さくするか、要約機能を無効化
- **処理が遅い**: より小さいモデルサイズを選択
- **特定の言語での精度が低い**: 適切な言語コードを指定
- **NumPyエラー**: Dockerfileの依存関係バージョンを確認・修正

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

このプロジェクト「メモ助：議事録自動生成システム」は**MITライセンス**の下で公開されています。ただし、以下の重要な例外があります：

本プロジェクトは以下の外部ライブラリおよびモデルに依存しており、それぞれ独自のライセンスが適用されます：

- **OpenAI Whisper**: MITライセンス
- **rinna/japanese-gpt-neox-3.6b-instruction-ppo**: [CC BY-SA 4.0ライセンス](https://creativecommons.org/licenses/by-sa/4.0/)

特に注意すべき点として、**rinna/japanese-gpt-neox-3.6b-instruction-ppoモデル**はCC BY-SA 4.0ライセンスの条件に従います：
1. **表示 (Attribution)**: rinnaのモデルを使用していることを明記する必要があります
2. **継承 (ShareAlike)**: このモデルを含むソフトウェアを改変して配布する場合、同じCC BY-SA 4.0ライセンスで公開する必要があります

これは、このプロジェクトの要約機能を利用・改変する場合、その部分についてはCC BY-SA 4.0ライセンスに従う必要があることを意味します。

### メンテナンス状況

**重要**: 本プロジェクトは現在、アクティブにメンテナンスされていません。

- バグ修正や機能追加の予定はありません
- セキュリティアップデートは提供されない可能性があります
- 依存ライブラリの非互換性によって将来的に動作しなくなる可能性があります

このプロジェクトは「現状のまま」提供され、いかなる保証もありません。本番環境での使用は自己責任で行ってください。

### コントリビューション

このプロジェクトはアクティブにメンテナンスされていませんが、フォークして独自のバージョンを開発することを歓迎します。ただし、上記のライセンス条件に注意してください：

- プロジェクト全体はMITライセンスですが、要約機能（rinnaモデル使用部分）についてはCC BY-SA 4.0の条件が適用されます
- 要約機能を含めてフォークする場合、その部分についてはCC BY-SA 4.0ライセンスで公開する必要があります

コントリビューションを検討される場合は、本リポジトリへのプルリクエストではなく、プロジェクトをフォークして独自に開発することをお勧めします。

## 謝辞

このプロジェクトは以下のオープンソースプロジェクトに支えられています：

- [OpenAI Whisper](https://github.com/openai/whisper)
- [rinna/japanese-gpt-neox-3.6b-instruction-ppo](https://huggingface.co/rinna/japanese-gpt-neox-3.6b-instruction-ppo)
- [Tailwind CSS](https://tailwindcss.com/)

各プロジェクトの開発者の皆様に感謝いたします。