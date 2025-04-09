# SHOKI音声文字起こしシステムへのGemini API機能統合仕様

## システム概要

SHOKIは、OpenAI Whisperを使用した音声文字起こしシステムで、Gemini APIを使用して文字起こし結果の補正と要約を実行します。この文書は、Claude向けにシステム仕様を解説するためのプロンプトファイルです。

## 主要機能

1. **Gemini APIによる文章補正**：音声認識結果を自然な文章に修正
2. **AI自動要約**：文字起こし内容から重要ポイントを抽出
3. **複数フォーマット対応**：標準、箇条書き、見出し形式、Q&A形式など
4. **会議議事録形式**：開催日、参加者、内容、タスクを自動抽出
5. **独立要約ツール**：文字起こしとは別にテキスト要約機能を提供
6. **履歴管理機能**：過去の文字起こし結果を表示・再利用

## システムアーキテクチャ

### ディレクトリ構造
```
SHOKI/
├── compose.yaml            # Docker Compose設定
├── .env                    # 環境変数設定
├── src/                    # PHPソースコード (コンテナ内の/var/www/html)
│   ├── index.php           # Webインターフェース
│   ├── api.php             # APIエンドポイント
│   ├── gemini_helper.php   # Gemini API連携
│   ├── summarize.php       # 独立要約ツール
│   └── .htaccess           # Apache設定
├── whisper/                # ビルド関連ファイル
│   ├── Dockerfile          # Dockerイメージ定義
│   └── php.ini             # PHP設定
└── data/                   # データディレクトリ
    ├── uploads/            # アップロードファイル
    ├── processed/          # 処理済みファイル
    ├── exports/            # エクスポートファイル
    ├── logs/               # ログファイル
    ├── config/             # 設定ファイル
    ├── cache/              # キャッシュ
    └── archives/           # アーカイブ
```

### コンポーネント

1. **Dockerコンテナ**
   - PHP + Apache：Webサーバーとバックエンド処理
   - OpenAI Whisper：音声文字起こしエンジン
   - 仮想環境：Python依存関係を分離

2. **バックエンドAPI**
   - `api.php`：文字起こしと要約の主要エンドポイント
   - `summarize.php`：独立した要約機能
   - `gemini_helper.php`：Gemini API連携ヘルパー関数

3. **フロントエンド**
   - HTML/CSS/JavaScript：ユーザーインターフェース
   - Tailwind CSS：スタイリング

## 技術的ポイント

### 1. 環境変数設定
```
PORT=9999
MODEL_SIZE=small
LANGUAGE=ja
GEMINI_API_KEY="your_gemini_api_key_here"
GEMINI_MODEL="gemini-1.5-flash"
```

### 2. Docker設定
```yaml
# compose.yaml (重要部分)
services:
  whisper:
    volumes:
      - ./data:/data
      - ./src:/var/www/html  # ソースコードを直接マウント
    environment:
      - GEMINI_API_KEY=${GEMINI_API_KEY}
      - GEMINI_MODEL=${GEMINI_MODEL:-gemini-1.5-flash}
```

### 3. テキスト分割処理
長いテキストを処理する際の500エラーを回避するため、テキストを適切なサイズに分割して処理します：

```php
function processLongText($text, $maxChunkSize = 4000, $processFunction, $processArgs = []) {
    // テキストが短い場合はそのまま処理
    if (mb_strlen($text) <= $maxChunkSize) {
        return call_user_func_array($processFunction, array_merge([$text], $processArgs));
    }

    // テキストを段落で分割
    $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

    // チャンク処理ロジック...

    // 各チャンクを処理して結果を取得
    $results = [];
    foreach ($chunks as $index => $chunk) {
        // APIレート制限を考慮して少し待機
        if ($index > 0) {
            usleep(500000); // 0.5秒待機
        }

        // 処理と結果の格納...
    }

    // 結果のマージ
    return mergeProcessedResults($results);
}
```

### 4. 要約フォーマット
システムは以下の要約フォーマットをサポートしています：

```php
$formats = [
    'standard' => '文章を要約して段落形式で出力してください。',
    'bullet' => '文章を要約して、重要なポイントを箇条書き（- で始まる行）形式で出力してください。',
    'headline' => '文章を要約して、主要な話題を「## 見出し」形式で示し、各見出しの下に簡潔な説明を追加してください。',
    'qa' => '文章の内容に基づいて、重要なポイントを質問と回答の形式でまとめてください。各質問は「Q:」で始め、回答は「A:」で始めてください。',
    'executive' => 'ビジネス文書のエグゼクティブサマリーとして、目的、結論、推奨事項を含む簡潔な要約を作成してください。',
    'meeting' => '会議議事録形式の詳細なプロンプト...'
];
```

### 5. Geminiモデル選択
環境変数またはUIから選択可能な以下のモデルをサポートしています：

- `gemini-1.5-flash`：高速処理向け（デフォルト推奨）
- `gemini-1.5-pro`：高精度処理向け
- `gemini-pro`：旧モデル（安定性重視）

## APIエンドポイント

### 1. 文字起こしと要約
```
POST /api.php
Content-Type: multipart/form-data

[音声ファイル]
[オプション]
[要約オプション]
```

### 2. 独立要約ツール
```
POST /summarize.php
Content-Type: application/json

{
  "text": "要約するテキスト",
  "format": "standard",
  "model": "gemini-1.5-flash",
  "language": "ja"
}
```

### 3. 履歴一覧取得
```
GET /api.php?list_transcriptions=1
```

### 4. 特定の文字起こし結果取得
```
GET /api.php?load_transcription=SESSION_ID
```

## 主要なプロンプト設計

### 1. 文章補正と要約
```
以下は音声認識によって生成された文字起こしテキストです。このテキストには認識エラーや不自然な表現、句読点の問題などがある可能性があります。

まず、このテキストを自然な日本語に補正してください。補正の際は、明らかな認識ミスを修正し、文法的に正しく、読みやすい文章にしてください。
次に、補正したテキストの要約を作成してください。{フォーマット固有の指示}

補正と要約の両方をJSON形式ではなく、以下のフォーマットで出力してください：

==== 補正テキスト ====
（補正された文章をここに記載）

==== 要約 ====
（要約をここに記載）

元のテキスト:
{テキスト}
```

### 2. 要約のみ
```
以下のテキストを要約してください。{フォーマット固有の指示}

JSONではなく、通常のテキスト形式で出力してください。

対象テキスト:
{テキスト}
```

## 履歴機能の実装

履歴機能は以下のデータ構造で管理されています：

```javascript
// 履歴項目の例
{
  'id': 'session_123abc',
  'file_name': 'audio_session_123abc.mp3',
  'date': '2025-04-09 15:30:45',
  'duration': 350, // 秒数
  'language': 'ja'
}
```

履歴データは`/data/processed/`ディレクトリ内のJSONファイルから読み取られます。

## エラーハンドリング戦略

1. **API制限エラー対策**
   - テキスト分割で長いコンテンツを分割
   - 失敗時のリトライ機構
   - エラーログ記録

2. **ユーザーフィードバック**
   - APIエラーの適切な表示
   - 処理中インジケーター
   - エラー後の回復オプション

## 今後の課題と展望

1. **パフォーマンス最適化**
   - キャッシュシステムの強化
   - 並列処理の検討

2. **UI/UX改善**
   - モバイル対応の強化
   - ダークモード対応

3. **機能拡張**
   - 多言語要約の強化
   - カスタム要約テンプレートの追加

## 付録：既知の問題

1. 会議議事録フォーマットでJSON形式の出力となる場合がある問題
2. 超長文の処理は複数のチャンクで分割されるため、全体的な一貫性が低下する可能性がある