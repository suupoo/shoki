<?php
/**
 * Gemini API ヘルパー
 *
 * Google Gemini APIを使用して文章の補正と要約を生成するためのヘルパー関数
 */

/**
 * 長いテキストを分割して処理する関数
 *
 * @param string $text 処理する長いテキスト
 * @param int $maxChunkSize 各チャンクの最大文字数
 * @param callable $processFunction 各チャンクを処理する関数
 * @param array $processArgs 処理関数に渡す追加の引数
 * @return array 結果配列
 */
function processLongText($text, $maxChunkSize = 4000, $processFunction, $processArgs = []) {
  // テキストが短い場合はそのまま処理
  if (mb_strlen($text) <= $maxChunkSize) {
    return call_user_func_array($processFunction, array_merge([$text], $processArgs));
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

  // 各チャンクを処理して結果を取得
  $results = [];
  foreach ($chunks as $index => $chunk) {
    // APIレート制限を考慮して少し待機
    if ($index > 0) {
      usleep(500000); // 0.5秒待機
    }

    try {
      $chunkResult = call_user_func_array($processFunction, array_merge([$chunk], $processArgs));
      $results[] = $chunkResult;
    } catch (Exception $e) {
      // エラーログの記録
      error_log("チャンク処理エラー: " . $e->getMessage());

      // 最大3回までリトライ
      $retryCount = 0;
      $maxRetries = 3;
      $success = false;

      while ($retryCount < $maxRetries && !$success) {
        $retryCount++;
        usleep(1000000 * $retryCount); // 1秒 × リトライ回数 待機

        try {
          $chunkResult = call_user_func_array($processFunction, array_merge([$chunk], $processArgs));
          $results[] = $chunkResult;
          $success = true;
        } catch (Exception $e) {
          error_log("リトライ {$retryCount} 失敗: " . $e->getMessage());

          // 最終リトライでも失敗した場合
          if ($retryCount == $maxRetries) {
            // 部分的な結果を返すため、エラーメッセージを含むダミー結果を作成
            $results[] = [
                'success' => false,
                'error' => "チャンク {$index} の処理に失敗: " . $e->getMessage(),
                'partial_text' => substr($chunk, 0, 100) . '...',
            ];
          }
        }
      }
    }
  }

  // 結果のマージ
  return mergeProcessedResults($results);
}

/**
 * 複数チャンクの処理結果をマージする
 *
 * @param array $results 処理結果の配列
 * @return array マージされた結果
 */
function mergeProcessedResults($results) {
  // 少なくとも1つの成功した結果があるか確認
  $hasSuccess = false;
  foreach ($results as $result) {
    if (isset($result['success']) && $result['success']) {
      $hasSuccess = true;
      break;
    }
  }

  // 成功した結果がない場合はエラーを返す
  if (!$hasSuccess) {
    return [
        'success' => false,
        'error' => '全てのテキストチャンクの処理に失敗しました'
    ];
  }

  // 成功した結果をマージ
  $mergedResult = [
      'success' => true,
      'corrected_text' => '',
      'summary' => ''
  ];

  $correctedTexts = [];
  $summaries = [];

  foreach ($results as $result) {
    if (isset($result['success']) && $result['success']) {
      if (isset($result['corrected_text'])) {
        $correctedTexts[] = $result['corrected_text'];
      }
      if (isset($result['summary'])) {
        $summaries[] = $result['summary'];
      }
    }
  }

  // 補正テキストのマージ
  if (!empty($correctedTexts)) {
    $mergedResult['corrected_text'] = implode("\n\n", $correctedTexts);
  }

  // 要約のマージ
  if (!empty($summaries)) {
    // 要約が多すぎる場合は再要約
    $combinedSummary = implode("\n\n", $summaries);
    if (mb_strlen($combinedSummary) > 4000) {
      // 要約の要約を作成（再帰的に処理）
      global $apiKey, $model;
      $reSummarizeResult = summarizeText($combinedSummary, 'ja', $apiKey, $model);
      if ($reSummarizeResult['success']) {
        $mergedResult['summary'] = $reSummarizeResult['summary'];
      } else {
        // 再要約に失敗した場合は元の要約を短くカット
        $mergedResult['summary'] = mb_substr($combinedSummary, 0, 4000) . "...\n(要約が長すぎるため一部省略されました)";
      }
    } else {
      $mergedResult['summary'] = $combinedSummary;
    }
  }

  return $mergedResult;
}

/**
 * テキストから重要な部分を抽出する関数
 *
 * @param string $text 元のテキスト
 * @param int $maxLength 抽出結果の最大長
 * @return string 重要部分だけを抽出したテキスト
 */
function extractImportantParts($text, $maxLength = 8000) {
  // 文字数が超えていない場合はそのまま返す
  if (mb_strlen($text) <= $maxLength) {
    return $text;
  }

  // 重要なキーワードのリスト
  $keywords = ['重要', '注意', 'ポイント', '結論', '決定', 'タスク', '課題', '目標',
      '要約', 'まとめ', '総括', '方針', '戦略', '確認', '合意', '提案'];

  // 段落に分割
  $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

  $importantParts = [];
  $importantPartsLength = 0;

  // 1. 最初の段落は通常重要（導入部分）
  if (!empty($paragraphs)) {
    $firstParagraph = array_shift($paragraphs);
    $importantParts[] = $firstParagraph;
    $importantPartsLength += mb_strlen($firstParagraph);
  }

  // 2. 最後の段落も通常重要（結論やまとめ）
  $lastParagraph = null;
  if (!empty($paragraphs)) {
    $lastParagraph = array_pop($paragraphs);
    // 後で追加するのでここではカウントしない
  }

  // 3. キーワードを含む段落を抽出
  foreach ($paragraphs as $paragraph) {
    $isImportant = false;

    // キーワードチェック
    foreach ($keywords as $keyword) {
      if (mb_stripos($paragraph, $keyword) !== false) {
        $isImportant = true;
        break;
      }
    }

    // 箇条書きの段落も重要と判断
    if (!$isImportant && preg_match('/^(\s*[・\-\*]|\d+\.)/', $paragraph)) {
      $isImportant = true;
    }

    if ($isImportant) {
      $paragraphLength = mb_strlen($paragraph);
      if ($importantPartsLength + $paragraphLength <= $maxLength - mb_strlen($lastParagraph ?? '')) {
        $importantParts[] = $paragraph;
        $importantPartsLength += $paragraphLength;
      }
    }
  }

  // 最後の段落を追加
  if ($lastParagraph) {
    $lastParagraphLength = mb_strlen($lastParagraph);
    if ($importantPartsLength + $lastParagraphLength <= $maxLength) {
      $importantParts[] = $lastParagraph;
      $importantPartsLength += $lastParagraphLength;
    } else {
      // 最後の段落が入りきらない場合、少なくとも冒頭部分を含める
      $availableLength = $maxLength - $importantPartsLength;
      if ($availableLength > 100) { // 少なくとも100文字は含める
        $importantParts[] = mb_substr($lastParagraph, 0, $availableLength - 3) . '...';
      }
    }
  }

  // 残り容量があれば、まだ追加されていない段落からランダムに選択
  $remainingParagraphs = array_diff($paragraphs, $importantParts);

  if (!empty($remainingParagraphs) && $importantPartsLength < $maxLength) {
    shuffle($remainingParagraphs); // ランダム化

    foreach ($remainingParagraphs as $paragraph) {
      $paragraphLength = mb_strlen($paragraph);
      if ($importantPartsLength + $paragraphLength <= $maxLength) {
        $importantParts[] = $paragraph;
        $importantPartsLength += $paragraphLength;
      }
    }
  }

  // 重要な部分を結合
  return implode("\n\n", $importantParts);
}

/**
 * 文章を補正して要約する（テキスト分割処理対応版）
 *
 * @param string $text 補正・要約するテキスト
 * @param string $language 言語コード（例: 'ja', 'en'）
 * @param string|null $apiKey Google Gemini API キー
 * @param string|null $model Gemini モデル名
 * @param string $format 要約フォーマット（'standard', 'bullet', 'headline', 'qa', 'executive'）
 * @return array 結果（成功時は ['success' => true, 'corrected_text' => '補正テキスト', 'summary' => '要約テキスト']、失敗時は ['success' => false, 'error' => 'エラーメッセージ']）
 */
function correctAndSummarizeText(string $text, string $language = 'ja', string $apiKey = null, string $model = null, $format = 'standard'): array
{
  // APIキーのチェック
  if (empty($apiKey)) {
    return [
        'success' => false,
        'error' => 'APIキーが設定されていません'
    ];
  }

  // モデル名の取得（指定がなければ環境変数から、それもなければデフォルト値）
  $model = $model ?: (getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro');

  // グローバル変数としてAPIキーとモデルを設定（テキスト分割処理用）
  global $apiKey, $model;

  // テキストの長さをチェック
  if (empty($text) || strlen($text) < 50) {
    return [
        'success' => false,
        'error' => 'テキストが短すぎるため処理できません'
    ];
  }

  // テキストが長すぎる場合は重要な部分を抽出
  $maxInputLength = 30000; // モデルに依存する適切な値に設定
  if (mb_strlen($text) > $maxInputLength) {
    $text = extractImportantParts($text, $maxInputLength);
  }

  // テキストが分割処理必要かどうかをチェック
  $maxChunkSize = 4000; // チャンクサイズの設定
  if (mb_strlen($text) > $maxChunkSize) {
    // 処理関数を定義（クロージャ）
    $processFunction = function($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel, $chunkFormat) {
      return _processSingleChunk($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel, $chunkFormat);
    };

    // テキスト分割処理を実行
    return processLongText($text, $maxChunkSize, $processFunction, [$language, $apiKey, $model, $format]);
  } else {
    // 通常の処理
    return _processSingleChunk($text, $language, $apiKey, $model, $format);
  }
}

/**
 * 単一チャンクの文章を補正して要約する（内部関数）
 */
function _processSingleChunk($text, $language, $apiKey, $model, $format) {
  // フォーマット情報の取得
  $formats = getAvailableSummaryFormats();
  $formatInfo = $formats[$format] ?? $formats['standard']; // 不明なフォーマットの場合はデフォルト
  $formatSuffix = $formatInfo['prompt_suffix'];

  // 言語に応じたプロンプトの準備
  $prompts = [
      'ja' => "以下は音声認識によって生成された文字起こしテキストです。このテキストには認識エラーや不自然な表現、句読点の問題などがある可能性があります。

まず、このテキストを自然な日本語に補正してください。補正の際は、明らかな認識ミスを修正し、文法的に正しく、読みやすい文章にしてください。
次に、補正したテキストの要約を作成してください。{$formatSuffix}

補正と要約の両方をJSON形式ではなく、以下のフォーマットで出力してください：

==== 補正テキスト ====
（補正された文章をここに記載）

==== 要約 ====
（要約をここに記載）

元のテキスト:
{$text}",

      'en' => "Below is a transcription text generated by speech recognition. This text may contain recognition errors, unnatural expressions, punctuation issues, etc.

First, please correct this text into natural English. When making corrections, fix obvious recognition errors, make it grammatically correct, and ensure it's easy to read.
Next, create a summary of the corrected text. {$formatSuffix}

Please provide both the correction and summary in the following format, NOT in JSON:

==== CORRECTED TEXT ====
(corrected text goes here)

==== SUMMARY ====
(summary goes here)

Original text:
{$text}"
  ];


  // デフォルトのプロンプト
  $prompt = $prompts[$language] ?? $prompts['en'];

  try {
    // Gemini APIエンドポイント
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    // APIリクエストデータ
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

    // cURLセッションの初期化
    $ch = curl_init($url);

    // cURLオプションの設定
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // APIリクエストの実行
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // cURLセッションの終了
    curl_close($ch);

    // エラーチェック
    if (!empty($error)) {
      throw new Exception('API通信エラー: ' . $error);
    }

    if ($httpCode != 200) {
      throw new Exception('APIレスポンスエラー: HTTPコード ' . $httpCode);
    }

    // レスポンスのデコード
    $result = json_decode($response, true);

    // レスポンス構造の確認
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
      throw new Exception('APIレスポンス形式エラー');
    }

    // レスポンステキストの取得
    $responseText = $result['candidates'][0]['content']['parts'][0]['text'];

    // セクション分割して取得
    if (preg_match('/==== 補正テキスト ====\s*([\s\S]*?)\s*==== 要約 ====/i', $responseText, $matches)) {
      $correctedText = trim($matches[1]);

      if (preg_match('/==== 要約 ====\s*([\s\S]*?)$/i', $responseText, $sumMatches)) {
        $summary = trim($sumMatches[1]);

        if (!empty($correctedText) && !empty($summary)) {
          return [
              'success' => true,
              'corrected_text' => $correctedText,
              'summary' => $summary
          ];
        }
      }
    }

    // JSON部分の抽出を試みる（旧形式のレスポンス）
    if (preg_match('/\{.*\}/s', $responseText, $matches)) {
      $jsonText = $matches[0];
      $jsonData = json_decode($jsonText, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSONパースエラー: ' . json_last_error_msg());
      }

      if (!isset($jsonData['corrected_text']) || !isset($jsonData['summary'])) {
        throw new Exception('レスポンスに必要なフィールドがありません');
      }

      return [
          'success' => true,
          'corrected_text' => $jsonData['corrected_text'],
          'summary' => $jsonData['summary']
      ];
    }

    // 通常の処理（セクション分割とJSONで取得できなかった場合）
    $lines = explode("\n", $responseText);
    $correctedText = '';
    $summary = '';
    $inCorrected = false;
    $inSummary = false;

    foreach ($lines as $line) {
      if (stripos($line, 'CORRECTED TEXT') !== false || stripos($line, '補正テキスト') !== false) {
        $inCorrected = true;
        $inSummary = false;
        continue;
      } else if (stripos($line, 'SUMMARY') !== false || stripos($line, '要約') !== false) {
        $inCorrected = false;
        $inSummary = true;
        continue;
      }

      if ($inCorrected) {
        $correctedText .= $line . "\n";
      } else if ($inSummary) {
        $summary .= $line . "\n";
      }
    }

    // 引用符や余分な文字を削除
    $correctedText = trim($correctedText);
    $summary = trim($summary);

    if (empty($correctedText) || empty($summary)) {
      throw new Exception('レスポンスから必要な情報を抽出できませんでした。応答: ' . substr($responseText, 0, 200) . '...');
    }

    return [
        'success' => true,
        'corrected_text' => $correctedText,
        'summary' => $summary
    ];
  } catch (Exception $e) {
    error_log('Gemini API エラー: ' . $e->getMessage());
    return [
        'success' => false,
        'error' => $e->getMessage()
    ];
  }
}

/**
 * 既に補正済みのテキストを要約する関数
 *
 * @param string $text 要約するテキスト（すでに補正済みの文章）
 * @param string $language 言語コード（例: 'ja', 'en'）
 * @param string|null $apiKey Google Gemini API キー
 * @param string|null $model Gemini モデル名
 * @param string $format 要約フォーマット（'standard', 'bullet', 'headline', 'qa', 'executive', 'meeting'）
 * @return array 結果（成功時は ['success' => true, 'summary' => '要約テキスト']、失敗時は ['success' => false, 'error' => 'エラーメッセージ']）
 */
function summarizeOnlyText(string $text, string $language = 'ja', string $apiKey = null, string $model = null, $format = 'standard'): array
{
  // APIキーのチェック
  if (empty($apiKey)) {
    return [
        'success' => false,
        'error' => 'APIキーが設定されていません'
    ];
  }

  // モデル名の取得（指定がなければ環境変数から、それもなければデフォルト値）
  $model = $model ?: (getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro');

  // グローバル変数としてAPIキーとモデルを設定（テキスト分割処理用）
  global $apiKey, $model;

  // テキストの長さをチェック
  if (empty($text) || strlen($text) < 50) {
    return [
        'success' => false,
        'error' => 'テキストが短すぎるため要約できません'
    ];
  }

  // フォーマット情報の取得
  $formats = getAvailableSummaryFormats();
  $formatInfo = $formats[$format] ?? $formats['standard']; // 不明なフォーマットの場合はデフォルト
  $formatSuffix = $formatInfo['prompt_suffix'];

  // テキストが長すぎる場合は重要な部分を抽出
  $maxInputLength = 30000; // モデルに依存する適切な値に設定
  if (mb_strlen($text) > $maxInputLength) {
    $text = extractImportantParts($text, $maxInputLength);
  }

  // テキストが分割処理必要かどうかをチェック
  $maxChunkSize = 4000; // チャンクサイズの設定
  if (mb_strlen($text) > $maxChunkSize) {
    // 処理関数を定義（クロージャ）
    $processFunction = function($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel, $chunkFormat) {
      return _summarizeOnlySingleChunk($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel, $chunkFormat);
    };

    // テキスト分割処理を実行
    return processLongText($text, $maxChunkSize, $processFunction, [$language, $apiKey, $model, $format]);
  } else {
    // 通常の処理
    return _summarizeOnlySingleChunk($text, $language, $apiKey, $model, $format);
  }
}

/**
 * 単一チャンクの文章のみを要約する（内部関数）
 */
function _summarizeOnlySingleChunk($text, $language, $apiKey, $model, $format) {
  try {
    // フォーマット情報の取得
    $formats = getAvailableSummaryFormats();
    $formatInfo = $formats[$format] ?? $formats['standard']; // 不明なフォーマットの場合はデフォルト
    $formatSuffix = $formatInfo['prompt_suffix'];

    // 言語に応じたプロンプトの準備
    $prompts = [
        'ja' => "以下のテキストを要約してください。{$formatSuffix}\n\nJSONではなく、通常のテキスト形式で出力してください。\n\n対象テキスト:\n{$text}",
        'en' => "Summarize the following text. {$formatSuffix}\n\nPlease output as normal text, not in JSON format.\n\nTarget text:\n{$text}",
    ];

    // デフォルトのプロンプト
    $prompt = $prompts[$language] ?? $prompts['en'];

    // Gemini APIエンドポイント
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    // APIリクエストデータ
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

    // cURLセッションの初期化
    $ch = curl_init($url);

    // cURLオプションの設定
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // APIリクエストの実行
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // cURLセッションの終了
    curl_close($ch);

    // エラーチェック
    if (!empty($error)) {
      throw new Exception('API通信エラー: ' . $error);
    }

    if ($httpCode != 200) {
      throw new Exception('APIレスポンスエラー: HTTPコード ' . $httpCode);
    }

    // レスポンスのデコード
    $result = json_decode($response, true);

    // レスポンス構造の確認
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
      throw new Exception('APIレスポンス形式エラー');
    }

    // 要約テキストの取得
    $summary = $result['candidates'][0]['content']['parts'][0]['text'];

    // JSON形式で返ってきた場合は抽出を試みる
    if (strpos($summary, '{') === 0 && strpos($summary, '}') !== false) {
      $jsonData = json_decode($summary, true);
      if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['summary'])) {
        $summary = $jsonData['summary'];
      }
    }

    return [
        'success' => true,
        'summary' => $summary
    ];
  } catch (Exception $e) {
    error_log('Gemini API エラー: ' . $e->getMessage());
    return [
        'success' => false,
        'error' => $e->getMessage()
    ];
  }
}

/**
 * 文章を要約する（テキスト分割処理対応版）
 *
 * @param string $text 要約するテキスト
 * @param string $language 言語コード（例: 'ja', 'en'）
 * @param string|null $apiKey Google Gemini API キー
 * @param string|null $model Gemini モデル名
 * @return array 結果（成功時は ['success' => true, 'summary' => '要約テキスト']、失敗時は ['success' => false, 'error' => 'エラーメッセージ']）
 */
function summarizeText(string $text, string $language = 'ja', string $apiKey = null, string $model = null) {
  // APIキーのチェック
  if (empty($apiKey)) {
    return [
        'success' => false,
        'error' => 'APIキーが設定されていません'
    ];
  }

  // モデル名の取得（指定がなければ環境変数から、それもなければデフォルト値）
  $model = $model ?: (getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro');

  // グローバル変数としてAPIキーとモデルを設定（テキスト分割処理用）
  global $apiKey, $model;

  // テキストの長さをチェック
  if (empty($text) || strlen($text) < 50) {
    return [
        'success' => false,
        'error' => 'テキストが短すぎるため要約できません'
    ];
  }

  // テキストが長すぎる場合は重要な部分を抽出
  $maxInputLength = 30000; // モデルに依存する適切な値に設定
  if (mb_strlen($text) > $maxInputLength) {
    $text = extractImportantParts($text, $maxInputLength);
  }

  // テキストが分割処理必要かどうかをチェック
  $maxChunkSize = 4000; // チャンクサイズの設定
  if (mb_strlen($text) > $maxChunkSize) {
    // 処理関数を定義（クロージャ）
    $processFunction = function($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel) {
      return _summarizeSingleChunk($chunkText, $chunkLanguage, $chunkApiKey, $chunkModel);
    };

    // テキスト分割処理を実行
    return processLongText($text, $maxChunkSize, $processFunction, [$language, $apiKey, $model]);
  } else {
    // 通常の処理
    return _summarizeSingleChunk($text, $language, $apiKey, $model);
  }
}

/**
 * 単一チャンクの文章を要約する（内部関数）
 */
function _summarizeSingleChunk($text, $language, $apiKey, $model) {
  try {
    // 言語に応じたプロンプトの準備
    $prompts = [
        'ja' => "以下のテキストを要約してください。簡潔で重要なポイントを含む要約を作成してください。返答はJSON形式ではなく、通常のテキスト形式で出力してください。\n\n{$text}",
        'en' => "Summarize the following text. Create a concise summary that includes the important points. Please output as normal text, not in JSON format.\n\n{$text}",
    ];

    // デフォルトのプロンプト
    $prompt = $prompts[$language] ?? $prompts['en'];

    // Gemini APIエンドポイント
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    // APIリクエストデータ
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

    // cURLセッションの初期化
    $ch = curl_init($url);

    // cURLオプションの設定
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // APIリクエストの実行
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // cURLセッションの終了
    curl_close($ch);

    // エラーチェック
    if (!empty($error)) {
      throw new Exception('API通信エラー: ' . $error);
    }

    if ($httpCode != 200) {
      throw new Exception('APIレスポンスエラー: HTTPコード ' . $httpCode);
    }

    // レスポンスのデコード
    $result = json_decode($response, true);

    // レスポンス構造の確認
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
      throw new Exception('APIレスポンス形式エラー');
    }

    // 要約テキストの取得
    $summary = $result['candidates'][0]['content']['parts'][0]['text'];

    return [
        'success' => true,
        'summary' => $summary
    ];
  } catch (Exception $e) {
    error_log('Gemini API エラー: ' . $e->getMessage());
    return [
        'success' => false,
        'error' => $e->getMessage()
    ];
  }
}

/**
 * 利用可能な要約フォーマットを定義
 * @return array
 */
function getAvailableSummaryFormats(): array
{
  return [
      'standard' => [
          'name' => '標準',
          'description' => '通常の段落形式の要約',
          'prompt_suffix' => '文章を要約して段落形式で出力してください。'
      ],
      'bullet' => [
          'name' => '箇条書き',
          'description' => '要点を箇条書きでまとめた要約',
          'prompt_suffix' => '文章を要約して、重要なポイントを箇条書き（- で始まる行）形式で出力してください。'
      ],
      'headline' => [
          'name' => '見出し形式',
          'description' => '見出しと説明文の形式でまとめた要約',
          'prompt_suffix' => '文章を要約して、主要な話題を「## 見出し」形式で示し、各見出しの下に簡潔な説明を追加してください。'
      ],
      'qa' => [
          'name' => 'Q&A形式',
          'description' => '質問と回答の形式でまとめた要約',
          'prompt_suffix' => '文章の内容に基づいて、重要なポイントを質問と回答の形式でまとめてください。各質問は「Q:」で始め、回答は「A:」で始めてください。'
      ],
      'executive' => [
          'name' => 'エグゼクティブサマリー',
          'description' => '意思決定者向けの簡潔な要約',
          'prompt_suffix' => 'ビジネス文書のエグゼクティブサマリーとして、目的、結論、推奨事項を含む簡潔な要約を作成してください。'
      ],
      'meeting' => [
          'name' => '会議議事録',
          'description' => '会議の開催日、参加者、内容、タスクを含む議事録形式',
          'prompt_suffix' => '以下の会議の文字起こしから、適切な会議議事録をMarkdown形式で作成してください。JSONではなく、以下のようなMarkdown形式で出力してください：
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
      ],
  ];
}