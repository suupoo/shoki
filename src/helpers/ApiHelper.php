<?php
/**
 * API通信ヘルパー
 *
 * Gemini APIとの通信を処理するヘルパー関数
 */

/**
 * Gemini APIにリクエストを送信する共通関数
 *
 * @param string $prompt APIに送信するプロンプト
 * @param string $apiKey Gemini API キー
 * @param string $model Gemini モデル名
 * @return array API応答結果
 * @throws Exception API通信に失敗した場合
 */
function callGeminiAPI($prompt, $apiKey, $model) {
  $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

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

  return $result;
}

/**
 * 要約のフォーマット情報を取得する
 *
 * @return array フォーマット定義
 */
function getSummaryFormats() {
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

/**
 * APIのレスポンスから補正テキストと要約を抽出する
 *
 * @param string $responseText APIレスポンステキスト
 * @return array ['corrected_text' => '補正テキスト', 'summary' => '要約テキスト']
 * @throws Exception 抽出に失敗した場合
 */
function extractCorrectionAndSummary($responseText) {
  // セクション分割して取得
  if (preg_match('/==== (補正テキスト|CORRECTED TEXT) ====\s*([\s\S]*?)\s*==== (要約|SUMMARY) ====/i', $responseText, $matches)) {
    $correctedText = trim($matches[2]);

    if (preg_match('/==== (要約|SUMMARY) ====\s*([\s\S]*?)$/i', $responseText, $sumMatches)) {
      $summary = trim($sumMatches[2]);

      if (!empty($correctedText) && !empty($summary)) {
        return [
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
      'corrected_text' => $correctedText,
      'summary' => $summary
  ];
}