<?php
/**
 * 要約専用エンドポイント
 *
 * 文字起こしAPIとは独立して要約のみを実行する
 */

// エラーレポート設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ディレクトリ定義
$dataDir = '/data';
$logsDir = $dataDir . '/logs';

// ログファイルの設定
$logFile = $logsDir . '/summarize.log';

/**
 * ログメッセージを記録
 */
function logMessage($message) {
  global $logFile;
  $timestamp = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
}

/**
 * 長いテキストを分割して処理する関数
 *
 * @param string $text 処理する長いテキスト
 * @param int $maxChunkSize 各チャンクの最大文字数
 * @return array チャンクの配列
 */
function splitTextIntoChunks($text, $maxChunkSize = 4000) {
  // テキストが短い場合はそのまま返す
  if (mb_strlen($text) <= $maxChunkSize) {
    return [$text];
  }

  // テキストを段落で分割
  $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

  $chunks = [];
  $currentChunk = '';

  foreach ($paragraphs as $paragraph) {
    // 現在のチャンクに段落を追加してもサイズ制限を超えない場合
    if (mb_strlen($currentChunk . "\n\n" . $paragraph) <= $maxChunkSize) {
      $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
    } else {
      // 現在のチャンクが空でなければ保存
      if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
        $currentChunk = '';
      }

      // 段落自体がサイズ制限を超える場合は分割
      if (mb_strlen($paragraph) > $maxChunkSize) {
        $sentences = preg_split('/(。|！|\!|？|\?|\.|\n)/', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
        $subChunk = '';

        for ($i = 0; $i < count($sentences); $i += 2) {
          $sentence = isset($sentences[$i]) ? $sentences[$i] : '';
          $delimiter = isset($sentences[$i+1]) ? $sentences[$i+1] : '';
          $sentenceWithDelimiter = $sentence . $delimiter;

          if (mb_strlen($subChunk . $sentenceWithDelimiter) <= $maxChunkSize) {
            $subChunk .= $sentenceWithDelimiter;
          } else {
            if (!empty($subChunk)) {
              $chunks[] = $subChunk;
            }

            // 1つの文が最大サイズを超える場合はさらに分割
            if (mb_strlen($sentenceWithDelimiter) > $maxChunkSize) {
              $words = preg_split('/\s+/', $sentenceWithDelimiter);
              $wordChunk = '';

              foreach ($words as $word) {
                if (mb_strlen($wordChunk . ' ' . $word) <= $maxChunkSize) {
                  $wordChunk .= ($wordChunk ? ' ' : '') . $word;
                } else {
                  if (!empty($wordChunk)) {
                    $chunks[] = $wordChunk;
                  }
                  $wordChunk = $word;
                }
              }

              if (!empty($wordChunk)) {
                $subChunk = $wordChunk;
              } else {
                $subChunk = '';
              }
            } else {
              $subChunk = $sentenceWithDelimiter;
            }
          }
        }

        if (!empty($subChunk)) {
          $currentChunk = $subChunk;
        }
      } else {
        $currentChunk = $paragraph;
      }
    }
  }

  // 最後のチャンクがあれば追加
  if (!empty($currentChunk)) {
    $chunks[] = $currentChunk;
  }

  return $chunks;
}

/**
 * テキストを要約する
 *
 * @param string $text 要約するテキスト
 * @param string $apiKey Gemini API キー
 * @param string $model Gemini モデル名
 * @param string $format 要約フォーマット
 * @param string $language 言語コード
 * @return array 結果配列
 */
function summarizeText($text, $apiKey, $model = 'gemini-1.5-flash', $format = 'standard', $language = 'ja') {
  // テキストが短すぎる場合はエラー
  if (mb_strlen($text) < 50) {
    return [
        'success' => false,
        'error' => 'テキストが短すぎます（50文字以上必要です）'
    ];
  }

  // フォーマットに応じたプロンプト接尾辞を取得
  $formatSuffix = getFormatSuffix($format);

  // テキストが長すぎる場合は分割処理
  $maxChunkSize = 4000;
  $chunks = splitTextIntoChunks($text, $maxChunkSize);

  if (count($chunks) > 1) {
    logMessage("テキストを " . count($chunks) . " チャンクに分割しました");
  }

  // 各チャンクを要約
  $summaries = [];
  foreach ($chunks as $index => $chunk) {
    try {
      // APIリクエストの準備
      $prompt = "以下のテキストを要約してください。{$formatSuffix}\n\nJSONではなく、通常のテキスト形式で出力してください。\n\n対象テキスト:\n{$chunk}";

      $data = [
          'contents' => [
              [
                  'parts' => [
                      [
                          'text' => $prompt
                      ]
                  ]
              ]
          ],
          'generationConfig' => [
              'temperature' => 0.2,
              'topK' => 40,
              'topP' => 0.95,
              'maxOutputTokens' => 1024,
          ]
      ];

      // APIリクエストを実行
      $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json'
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      // エラーチェック
      if (!empty($error)) {
        throw new Exception("API通信エラー: " . $error);
      }

      if ($httpCode != 200) {
        throw new Exception("APIレスポンスエラー: HTTPコード " . $httpCode);
      }

      // レスポンスの解析
      $result = json_decode($response, true);

      if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("APIレスポンス形式エラー");
      }

      $summaryText = $result['candidates'][0]['content']['parts'][0]['text'];
      $summaries[] = $summaryText;

      // APIレート制限を避けるため少し待機
      if ($index < count($chunks) - 1) {
        usleep(500000); // 0.5秒待機
      }

    } catch (Exception $e) {
      logMessage("チャンク {$index} の要約に失敗: " . $e->getMessage());
      return [
          'success' => false,
          'error' => $e->getMessage()
      ];
    }
  }

  // 複数のチャンクがある場合は全体をまとめる
  if (count($summaries) > 1) {
    $combinedSummary = implode("\n\n", $summaries);

    // 結合した要約が長すぎる場合は再度要約
    if (mb_strlen($combinedSummary) > $maxChunkSize) {
      logMessage("結合された要約が長すぎるため、再要約を実行します");
      try {
        // 再要約用のプロンプト
        $prompt = "以下は複数の要約をまとめたものです。全体をまとめて簡潔な要約を作成してください。{$formatSuffix}\n\n{$combinedSummary}";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];

        // APIリクエストを実行
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 200) {
          $result = json_decode($response, true);
          if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $finalSummary = $result['candidates'][0]['content']['parts'][0]['text'];
            return [
                'success' => true,
                'summary' => $finalSummary,
                'chunks_count' => count($chunks)
            ];
          }
        }

        // 再要約に失敗した場合は元の結合された要約を返す
        logMessage("再要約に失敗しました。元の結合された要約を使用します");
        return [
            'success' => true,
            'summary' => $combinedSummary,
            'chunks_count' => count($chunks)
        ];

      } catch (Exception $e) {
        logMessage("再要約に失敗: " . $e->getMessage());
        return [
            'success' => true,
            'summary' => $combinedSummary,
            'chunks_count' => count($chunks),
            'warning' => '要約が長いため、読みやすさが低下している可能性があります'
        ];
      }
    } else {
      // 結合された要約を返す
      return [
          'success' => true,
          'summary' => $combinedSummary,
          'chunks_count' => count($chunks)
      ];
    }
  } else {
    // 単一チャンクの要約を返す
    return [
        'success' => true,
        'summary' => $summaries[0]
    ];
  }
}

/**
 * フォーマットに応じたプロンプト接尾辞を取得
 */
function getFormatSuffix($format) {
  $formats = [
      'standard' => '文章を要約して段落形式で出力してください。',
      'bullet' => '文章を要約して、重要なポイントを箇条書き（- で始まる行）形式で出力してください。',
      'headline' => '文章を要約して、主要な話題を「## 見出し」形式で示し、各見出しの下に簡潔な説明を追加してください。',
      'qa' => '文章の内容に基づいて、重要なポイントを質問と回答の形式でまとめてください。各質問は「Q:」で始め、回答は「A:」で始めてください。',
      'executive' => 'ビジネス文書のエグゼクティブサマリーとして、目的、結論、推奨事項を含む簡潔な要約を作成してください。',
      'meeting' => '以下の会議の文字起こしから、適切な会議議事録をMarkdown形式で作成してください。JSONではなく、以下のようなMarkdown形式で出力してください：
## 開催日
文中から特定できる会議日時を記載してください。特定できない場合は「記載なし」としてください。

## 参加メンバー
- メンバー1
- メンバー2（役職）

## 内容まとめ
会議で議論された主な内容の要約を記載してください。

### 主な議題と結論
議題と結論の説明

### 重要な決定事項
決定事項の説明

### 議論されたオプションや選択肢
オプションや選択肢の説明

## タスク
- [ ] タスク1 @担当者（期限）
- [ ] タスク2 @担当者（期限）

以上を、整理された議事録形式で出力してください。JSONのような構造化データではなく、人間が読みやすいMarkdown形式にしてください。'
  ];

  return $formats[$format] ?? $formats['standard'];
}

// POSTリクエストを処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');

  try {
    // リクエストデータの取得
    $requestData = json_decode(file_get_contents('php://input'), true);

    if (!isset($requestData['text']) || empty($requestData['text'])) {
      throw new Exception('テキストが指定されていません');
    }

    $text = $requestData['text'];
    $language = $requestData['language'] ?? 'ja';
    $format = $requestData['format'] ?? 'standard';
    $model = $requestData['model'] ?? 'gemini-1.5-flash';

    // APIキーの取得
    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey)) {
      throw new Exception('APIキーが設定されていません。環境変数 GEMINI_API_KEY を設定してください。');
    }

    // 処理開始時間の記録
    $startTime = microtime(true);

    // ログに記録
    logMessage("要約リクエスト: format={$format}, model={$model}, text_length=" . mb_strlen($text));

    // 要約を実行
    $result = summarizeText($text, $apiKey, $model, $format, $language);

    // 処理時間の計算
    $processingTime = round(microtime(true) - $startTime, 2);
    $result['processing_time'] = $processingTime;
    $result['model'] = $model;

    // ログに記録
    logMessage("要約完了: 処理時間={$processingTime}秒, 成功=" . ($result['success'] ? 'true' : 'false'));

    // 結果を返却
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    // エラー処理
    logMessage("エラー: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
  }

  exit;
}

// GETリクエストの場合は簡易的なフォームを表示
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>テキスト要約ツール</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-4">
<div class="max-w-3xl mx-auto bg-white rounded-lg shadow p-6">
  <h1 class="text-2xl font-bold text-gray-800 mb-4">テキスト要約ツール</h1>

  <div class="mb-4">
    <label for="text" class="block text-sm font-medium text-gray-700 mb-1">要約するテキスト</label>
    <textarea id="text" class="w-full h-64 p-3 border border-gray-300 rounded font-mono text-sm" placeholder="要約したいテキストを入力してください..."></textarea>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
      <label for="format" class="block text-sm font-medium text-gray-700 mb-1">要約フォーマット</label>
      <select id="format" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
        <option value="standard">標準 (段落形式)</option>
        <option value="bullet">箇条書き</option>
        <option value="headline">見出し形式</option>
        <option value="qa">Q&A形式</option>
        <option value="executive">エグゼクティブサマリー</option>
        <option value="meeting">会議議事録</option>
      </select>
    </div>

    <div>
      <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Geminiモデル</label>
      <select id="model" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
        <option value="gemini-1.5-flash" selected>Gemini 1.5 Flash (高速)</option>
        <option value="gemini-1.5-pro">Gemini 1.5 Pro (高性能)</option>
        <option value="gemini-pro">Gemini Pro (旧モデル)</option>
      </select>
    </div>
  </div>

  <div class="mt-4">
    <button id="summarizeBtn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
      要約する
    </button>
  </div>

  <div id="loading" class="hidden mt-4">
    <div class="flex justify-center">
      <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span class="ml-2 text-indigo-500">処理中...</span>
    </div>
  </div>

  <div id="error" class="hidden mt-4 p-3 bg-red-50 text-red-700 rounded"></div>

  <div id="result" class="hidden mt-4">
    <h2 class="text-lg font-medium text-gray-900 mb-2">要約結果</h2>
    <div id="summary" class="p-4 bg-gray-50 rounded border border-gray-200 whitespace-pre-wrap"></div>
    <div id="info" class="mt-2 text-xs text-gray-500"></div>

    <div class="mt-4">
      <button id="copyBtn" class="py-1 px-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        コピー
      </button>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const summarizeBtn = document.getElementById('summarizeBtn');
        const text = document.getElementById('text');
        const format = document.getElementById('format');
        const model = document.getElementById('model');
        const loading = document.getElementById('loading');
        const error = document.getElementById('error');
        const result = document.getElementById('result');
        const summary = document.getElementById('summary');
        const info = document.getElementById('info');
        const copyBtn = document.getElementById('copyBtn');

        summarizeBtn.addEventListener('click', async function() {
            // 入力チェック
            if (!text.value.trim()) {
                error.textContent = '要約するテキストを入力してください';
                error.classList.remove('hidden');
                return;
            }

            // UI更新
            loading.classList.remove('hidden');
            error.classList.add('hidden');
            result.classList.add('hidden');

            try {
                // APIリクエスト
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        text: text.value,
                        format: format.value,
                        model: model.value,
                        language: 'ja'
                    })
                });

                const data = await response.json();

                // ローディング非表示
                loading.classList.add('hidden');

                if (data.success) {
                    // 結果表示
                    summary.textContent = data.summary;

                    // 情報表示
                    let infoText = `モデル: ${data.model}, 処理時間: ${data.processing_time}秒`;
                    if (data.chunks_count) {
                        infoText += `, チャンク数: ${data.chunks_count}`;
                    }
                    if (data.warning) {
                        infoText += `\n警告: ${data.warning}`;
                    }
                    info.textContent = infoText;

                    result.classList.remove('hidden');
                } else {
                    // エラー表示
                    error.textContent = data.error || '要約処理中にエラーが発生しました';
                    error.classList.remove('hidden');
                }
            } catch (e) {
                // 例外処理
                loading.classList.add('hidden');
                error.textContent = '通信エラー: ' + e.message;
                error.classList.remove('hidden');
            }
        });

        // コピーボタン
        copyBtn.addEventListener('click', function() {
            const textToCopy = summary.textContent;
            navigator.clipboard.writeText(textToCopy).then(function() {
                const originalText = copyBtn.textContent;
                copyBtn.textContent = 'コピーしました！';
                setTimeout(function() {
                    copyBtn.textContent = originalText;
                }, 2000);
            }).catch(function(err) {
                console.error('クリップボードへのコピーに失敗しました', err);
            });
        });
    });
</script>
</body>
</html>