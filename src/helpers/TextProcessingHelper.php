<?php
/**
 * テキスト処理ヘルパー
 *
 * テキストの分割や処理に関するヘルパー関数
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
        $chunks = array_merge($chunks, splitLargeParagraph($paragraph, $maxChunkSize));
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
  $results = processChunks($chunks, $processFunction, $processArgs);

  // 結果のマージ
  return mergeProcessedResults($results);
}

/**
 * 大きな段落を文単位で分割する
 *
 * @param string $paragraph 分割する段落
 * @param int $maxChunkSize 最大チャンクサイズ
 * @return array チャンクの配列
 */
function splitLargeParagraph($paragraph, $maxChunkSize) {
  $chunks = [];
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
    $chunks[] = $subChunk;
  }

  return $chunks;
}

/**
 * チャンクを処理する
 *
 * @param array $chunks 処理するチャンク配列
 * @param callable $processFunction 処理関数
 * @param array $processArgs 追加の引数
 * @return array 処理結果の配列
 */
function processChunks($chunks, $processFunction, $processArgs) {
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

  return $results;
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
      $reSummarizeResult = summarizeTextMain($combinedSummary, 'ja', $apiKey, $model);
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