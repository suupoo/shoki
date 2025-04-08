<?php
/**
 * OpenAI Whisper PHP APIラッパー
 *
 * PHPからWhisperにアクセスするためのAPIエンドポイント
 * 文字起こし機能を提供
 */

// Gemini API ヘルパーの読み込み
require_once __DIR__ . '/gemini_helper.php';

// エラーレポート設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ディレクトリ定義
$dataDir = '/data';
$uploadsDir = $dataDir . '/uploads';
$processedDir = $dataDir . '/processed';
$exportsDir = $dataDir . '/exports';
$logsDir = $dataDir . '/logs';
$configDir = $dataDir . '/config';
$cacheDir = $dataDir . '/cache';
$scriptsDir = __DIR__ . '/scripts';
$zipDir = $dataDir . '/archives';

// 必要なディレクトリの作成
foreach ([$uploadsDir, $processedDir, $exportsDir, $logsDir, $configDir, $cacheDir, $zipDir] as $dir) {
  if (!file_exists($dir)) {
    mkdir($dir, 0755, true);
  }
}

// ログファイルの設定
$logFile = $logsDir . '/shoki.log';

// ヘルスチェックエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['health'])) {
  header('Content-Type: application/json');
  echo json_encode([
      'status' => 'healthy',
      'model_size' => getenv('MODEL_SIZE') ?: 'medium',
      'language' => getenv('LANGUAGE') ?: 'ja',
      'timestamp' => date('Y-m-d H:i:s')
  ]);
  exit;
}

// 要約エンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['summarize'])) {
  // 開始時間を記録
  $startTime = microtime(true);

  // リクエストのログ記録
  logMessage("文字起こしリクエストを受信しました");

  try {
    // 音声ファイルのチェック
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('音声ファイルがアップロードされていないか、エラーが発生しました');
    }

    $audioFile = $_FILES['audio_file'];
    $tempPath = $audioFile['tmp_name'];
    $originalName = $audioFile['name'];

    // ファイルサイズのチェック
    $maxSize = 500 * 1024 * 1024; // 500MB
    if ($audioFile['size'] > $maxSize) {
      throw new Exception("ファイルサイズが大きすぎます。最大サイズは500MBです。");
    }

    // ファイル形式のチェック
    $allowedExtensions = ['mp3', 'wav', 'm4a', 'ogg'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
      throw new Exception("サポートされていないファイル形式です。対応形式: MP3, WAV, M4A, OGG");
    }

    // オプションの取得（修正: 配列チェックを追加）
    $options = [];
    if (isset($_POST['options']) && is_string($_POST['options'])) {
      $options = json_decode($_POST['options'], true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("オプション形式が無効です: " . json_last_error_msg());
        $options = [];
      }
    } else if (isset($_POST['options']) && is_array($_POST['options'])) {
      // options が既に配列の場合はそのまま使用
      $options = $_POST['options'];
    }

    // 要約オプションの取得
    $summarizeOptions = [];
    if (isset($_POST['summarize_options']) && is_string($_POST['summarize_options'])) {
      $summarizeOptions = json_decode($_POST['summarize_options'], true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("要約オプション形式が無効です: " . json_last_error_msg());
        $summarizeOptions = [];
      }
    } else if (isset($_POST['summarize_options']) && is_array($_POST['summarize_options'])) {
      // 既に配列の場合はそのまま使用
      $summarizeOptions = $_POST['summarize_options'];
    }

    // 要約を行うかどうかのフラグ
    $doSummarize = isset($summarizeOptions['enabled']) ? (bool)$summarizeOptions['enabled'] : false;

    // モデルサイズと言語の設定
    $modelSize = $options['model_size'] ?? (getenv('MODEL_SIZE') ?: 'medium');
    $language = $options['language'] ?? (getenv('LANGUAGE') ?: 'ja');

    // 話者ラベル
    $speakerLabels = [];
    if (isset($options['speaker_labels'])) {
      if (is_string($options['speaker_labels'])) {
        $speakerLabels = json_decode($options['speaker_labels'], true) ?: [];
      } else if (is_array($options['speaker_labels'])) {
        $speakerLabels = $options['speaker_labels'];
      }
    }

    // セッションID（一意の識別子）の生成
    $timestamp = date('YmdHis');
    $sessionId = uniqid('session_');

    // データディレクトリ内に作業用ディレクトリを作成
    $workDir = $dataDir . '/scripts/' . $sessionId;
    if (!file_exists($workDir)) {
      $mkdirResult = mkdir($workDir, 0777, true);
      if (!$mkdirResult) {
        logMessage("エラー: 作業ディレクトリの作成に失敗しました: {$workDir}");
        throw new Exception("作業ディレクトリの作成に失敗しました");
      }
      logMessage("作業ディレクトリを作成しました: {$workDir}");
    }

    // 作業ディレクトリの権限を設定
    chmod($workDir, 0777);

    // Pythonスクリプトを作成
    $pythonScript = $workDir . '/transcribe.py';
    $scriptContent = createPythonScript($modelSize, $language);

    // スクリプトを書き込み
    $bytesWritten = file_put_contents($pythonScript, $scriptContent);
    if ($bytesWritten === false) {
      logMessage("エラー: スクリプトファイルの書き込みに失敗しました");
      throw new Exception("スクリプトファイルの書き込みに失敗しました");
    }
    logMessage("スクリプトファイルを作成しました: {$pythonScript} ({$bytesWritten} バイト)");

    // 実行権限を付与
    chmod($pythonScript, 0777);

    // 出力JSONファイルのパス
    $outputJson = $workDir . '/output.json';

    // Pythonスクリプトの実行（仮想環境を使用）
    logMessage("音声ファイル {$originalName} の文字起こしを開始します");
    logMessage("モデルサイズ: {$modelSize}, 言語: {$language}");

    // 仮想環境のPythonを使用
    $pythonPath = '/opt/venv/bin/python3';
    $command = "{$pythonPath} {$pythonScript} " . escapeshellarg($tempPath) . " " . escapeshellarg($outputJson);
    logMessage("実行コマンド: {$command}");

    // コマンド実行
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    // エラーチェック
    if ($returnCode !== 0) {
      $errorOutput = implode("\n", $output);
      logMessage("文字起こし処理中にエラーが発生しました: " . $errorOutput);
      throw new Exception("文字起こし処理中にエラーが発生しました");
    }

    // 結果JSONの読み込み
    if (!file_exists($outputJson)) {
      throw new Exception("文字起こし結果ファイルが見つかりません");
    }

    $result = json_decode(file_get_contents($outputJson), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception("文字起こし結果の解析に失敗しました: " . json_last_error_msg());
    }

    // 処理時間の計算
    $processingTime = microtime(true) - $startTime;
    $result['processing_time'] = round($processingTime, 2);

    // 話者ラベルの追加
    if (!empty($speakerLabels)) {
      $result['speaker_labels'] = $speakerLabels;
    }

    // セッションIDの追加
    $result['session_id'] = $sessionId;

    // ファイル名の設定
    $audioFilename = "audio_{$sessionId}.{$extension}";
    $transcriptionFilename = "transcription_{$sessionId}.json";
    $textFilename = "transcription_{$sessionId}.txt";

    // 元の音声ファイルを保存
    $audioPath = $uploadsDir . '/' . $audioFilename;
    move_uploaded_file($tempPath, $audioPath);
    $result['audio_file'] = $audioFilename;

    // 文字起こし結果をJSONとして保存
    $transcriptionPath = $processedDir . '/' . $transcriptionFilename;
    file_put_contents($transcriptionPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $result['transcription_file'] = $transcriptionFilename;

    // 文字起こし結果をテキストとして保存
    $textPath = $processedDir . '/' . $textFilename;
    file_put_contents($textPath, $result['text']);
    $result['text_file'] = $textFilename;

    // 要約処理を行う場合
    if ($doSummarize) {
      try {
        logMessage("要約処理を開始します");

        // 要約オプションの取得
        $correctText = isset($summarizeOptions['correct']) ? (bool)$summarizeOptions['correct'] : true;
        $format = $summarizeOptions['format'] ?? 'standard';
        $model = $summarizeOptions['model'] ?? (getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash');
        $apiKey = getenv('GEMINI_API_KEY');

        if (empty($apiKey)) {
          logMessage("要約処理をスキップします: APIキーが設定されていません");
          $result['summary_error'] = 'APIキーが設定されていません';
        } else {
          // 要約の実行
          if ($correctText) {
            $summaryResult = correctAndSummarizeText($result['text'], $language, $apiKey, $model, $format);
          } else {
            $summaryResult = summarizeText($result['text'], $language, $apiKey, $model);
          }

          if ($summaryResult['success']) {
            if (isset($summaryResult['corrected_text'])) {
              $result['corrected_text'] = $summaryResult['corrected_text'];

              // 補正テキストをファイルとして保存
              $correctedFilename = "corrected_{$sessionId}.txt";
              $correctedPath = $processedDir . '/' . $correctedFilename;
              file_put_contents($correctedPath, $summaryResult['corrected_text']);
              $result['corrected_file'] = $correctedFilename;
            }

            if (isset($summaryResult['summary'])) {
              $result['summary'] = $summaryResult['summary'];

              // 要約をファイルとして保存
              $summaryFilename = "summary_{$sessionId}.txt";
              $summaryPath = $processedDir . '/' . $summaryFilename;
              file_put_contents($summaryPath, $summaryResult['summary']);
              $result['summary_file'] = $summaryFilename;
            }

            $result['summary_model'] = $model;
            $result['summary_format'] = $format;
            logMessage("要約処理が完了しました");
          } else {
            logMessage("要約処理に失敗しました: " . $summaryResult['error']);
            $result['summary_error'] = $summaryResult['error'];
          }
        }
      } catch (Exception $e) {
        logMessage("要約処理中にエラーが発生しました: " . $e->getMessage());
        $result['summary_error'] = $e->getMessage();
      }
    }

    // ZIPファイルの作成
    try {
      $zipFilename = "shoki_{$sessionId}.zip";
      $zipPath = $zipDir . '/' . $zipFilename;

      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        // 音声ファイルを追加
        $zip->addFile($audioPath, $audioFilename);

        // 文字起こし結果を追加
        $zip->addFile($transcriptionPath, $transcriptionFilename);
        $zip->addFile($textPath, $textFilename);

        // 補正テキストと要約を追加（存在する場合）
        if (isset($result['corrected_file'])) {
          $zip->addFile($processedDir . '/' . $result['corrected_file'], $result['corrected_file']);
        }

        if (isset($result['summary_file'])) {
          $zip->addFile($processedDir . '/' . $result['summary_file'], $result['summary_file']);
        }

        // READMEファイルを追加
        $readmeContent = "# SHOKI - 処理結果\n\n";
        $readmeContent .= "処理日時: " . date('Y-m-d H:i:s') . "\n";
        $readmeContent .= "言語: " . $result['language'] . "\n";
        $readmeContent .= "モデルサイズ: " . $modelSize . "\n";
        $readmeContent .= "処理時間: " . $result['processing_time'] . "秒\n\n";
        $readmeContent .= "## ファイル一覧\n\n";
        $readmeContent .= "- $audioFilename - 元の音声ファイル\n";
        $readmeContent .= "- $textFilename - 文字起こしテキスト\n";
        $readmeContent .= "- $transcriptionFilename - 文字起こし詳細情報（JSON）\n";

        if (isset($result['corrected_file'])) {
          $readmeContent .= "- " . $result['corrected_file'] . " - 補正テキスト\n";
        }

        if (isset($result['summary_file'])) {
          $readmeContent .= "- " . $result['summary_file'] . " - 要約\n";
        }

        $tempReadmePath = $workDir . '/README.md';
        file_put_contents($tempReadmePath, $readmeContent);
        $zip->addFile($tempReadmePath, 'README.md');

        $zip->close();
        $result['zip_file'] = $zipFilename;
        logMessage("ZIPファイルを作成しました: {$zipFilename}");
      } else {
        logMessage("ZIPファイル作成に失敗しました");
      }
    } catch (Exception $e) {
      logMessage("ZIPファイル作成中にエラーが発生しました: " . $e->getMessage());
    }

    // 一時ファイルとディレクトリの削除
    unlink($pythonScript);
    unlink($outputJson);
    if (isset($tempReadmePath) && file_exists($tempReadmePath)) {
      unlink($tempReadmePath);
    }
    rmdir($workDir);

    // 結果を返却
    logMessage("文字起こしが完了しました。処理時間: {$result['processing_time']}秒");
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    // エラー処理
    logMessage("エラー: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
  }

  exit;
}

// ZIPダウンロードエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download']) && isset($_GET['file'])) {
  $filename = basename($_GET['file']);
  $filepath = $zipDir . '/' . $filename;

  if (file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
  } else {
    header('HTTP/1.0 404 Not Found');
    echo json_encode(['error' => 'ファイルが見つかりません']);
    exit;
  }
}

// 文字起こしエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 開始時間を記録
  $startTime = microtime(true);

  // リクエストのログ記録
  logMessage("文字起こしリクエストを受信しました");

  try {
    // 音声ファイルのチェック
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('音声ファイルがアップロードされていないか、エラーが発生しました');
    }

    $audioFile = $_FILES['audio_file'];
    $tempPath = $audioFile['tmp_name'];
    $originalName = $audioFile['name'];

    // ファイルサイズのチェック
    $maxSize = 500 * 1024 * 1024; // 500MB
    if ($audioFile['size'] > $maxSize) {
      throw new Exception("ファイルサイズが大きすぎます。最大サイズは500MBです。");
    }

    // ファイル形式のチェック
    $allowedExtensions = ['mp3', 'wav', 'm4a', 'ogg'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
      throw new Exception("サポートされていないファイル形式です。対応形式: MP3, WAV, M4A, OGG");
    }

    // オプションの取得（修正: 配列チェックを追加）
    $options = [];
    if (isset($_POST['options']) && is_string($_POST['options'])) {
      $options = json_decode($_POST['options'], true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("オプション形式が無効です: " . json_last_error_msg());
        $options = [];
      }
    } else if (isset($_POST['options']) && is_array($_POST['options'])) {
      // options が既に配列の場合はそのまま使用
      $options = $_POST['options'];
    }

    // モデルサイズと言語の設定
    $modelSize = $options['model_size'] ?? (getenv('MODEL_SIZE') ?: 'medium');
    $language = $options['language'] ?? (getenv('LANGUAGE') ?: 'ja');

    // 話者ラベル
    $speakerLabels = [];
    if (isset($options['speaker_labels'])) {
      if (is_string($options['speaker_labels'])) {
        $speakerLabels = json_decode($options['speaker_labels'], true) ?: [];
      } else if (is_array($options['speaker_labels'])) {
        $speakerLabels = $options['speaker_labels'];
      }
    }

    // セッションID（一意の識別子）の生成
    $timestamp = date('YmdHis');
    $sessionId = uniqid('session_');

    // データディレクトリ内に作業用ディレクトリを作成
    $workDir = $dataDir . '/scripts/' . $sessionId;
    if (!file_exists($workDir)) {
      $mkdirResult = mkdir($workDir, 0777, true);
      if (!$mkdirResult) {
        logMessage("エラー: 作業ディレクトリの作成に失敗しました: {$workDir}");
        throw new Exception("作業ディレクトリの作成に失敗しました");
      }
      logMessage("作業ディレクトリを作成しました: {$workDir}");
    }

    // 作業ディレクトリの権限を設定
    chmod($workDir, 0777);

    // Pythonスクリプトを作成
    $pythonScript = $workDir . '/transcribe.py';
    $scriptContent = createPythonScript($modelSize, $language);

    // スクリプトを書き込み
    $bytesWritten = file_put_contents($pythonScript, $scriptContent);
    if ($bytesWritten === false) {
      logMessage("エラー: スクリプトファイルの書き込みに失敗しました");
      throw new Exception("スクリプトファイルの書き込みに失敗しました");
    }
    logMessage("スクリプトファイルを作成しました: {$pythonScript} ({$bytesWritten} バイト)");

    // 実行権限を付与
    chmod($pythonScript, 0777);

    // 出力JSONファイルのパス
    $outputJson = $workDir . '/output.json';

    // Pythonスクリプトの実行（仮想環境を使用）
    logMessage("音声ファイル {$originalName} の文字起こしを開始します");
    logMessage("モデルサイズ: {$modelSize}, 言語: {$language}");

    // 仮想環境のPythonを使用
    $pythonPath = '/opt/venv/bin/python3';
    $command = "{$pythonPath} {$pythonScript} " . escapeshellarg($tempPath) . " " . escapeshellarg($outputJson);
    logMessage("実行コマンド: {$command}");

    // コマンド実行
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    // エラーチェック
    if ($returnCode !== 0) {
      $errorOutput = implode("\n", $output);
      logMessage("文字起こし処理中にエラーが発生しました: " . $errorOutput);
      throw new Exception("文字起こし処理中にエラーが発生しました");
    }

    // 結果JSONの読み込み
    if (!file_exists($outputJson)) {
      throw new Exception("文字起こし結果ファイルが見つかりません");
    }

    $result = json_decode(file_get_contents($outputJson), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception("文字起こし結果の解析に失敗しました: " . json_last_error_msg());
    }

    // 処理時間の計算
    $processingTime = microtime(true) - $startTime;
    $result['processing_time'] = round($processingTime, 2);

    // 話者ラベルの追加
    if (!empty($speakerLabels)) {
      $result['speaker_labels'] = $speakerLabels;
    }

    // セッションIDの追加
    $result['session_id'] = $sessionId;

    // ファイル名の設定
    $audioFilename = "audio_{$sessionId}.{$extension}";
    $transcriptionFilename = "transcription_{$sessionId}.json";
    $textFilename = "transcription_{$sessionId}.txt";

    // 元の音声ファイルを保存
    $audioPath = $uploadsDir . '/' . $audioFilename;
    move_uploaded_file($tempPath, $audioPath);
    $result['audio_file'] = $audioFilename;

    // 文字起こし結果をJSONとして保存
    $transcriptionPath = $processedDir . '/' . $transcriptionFilename;
    file_put_contents($transcriptionPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $result['transcription_file'] = $transcriptionFilename;

    // 文字起こし結果をテキストとして保存
    $textPath = $processedDir . '/' . $textFilename;
    file_put_contents($textPath, $result['text']);
    $result['text_file'] = $textFilename;

    // ZIPファイルの作成
    try {
      $zipFilename = "shoki_{$sessionId}.zip";
      $zipPath = $zipDir . '/' . $zipFilename;

      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        // 音声ファイルを追加
        $zip->addFile($audioPath, $audioFilename);

        // 文字起こし結果を追加
        $zip->addFile($transcriptionPath, $transcriptionFilename);
        $zip->addFile($textPath, $textFilename);

        // READMEファイルを追加
        $readmeContent = "# SHOKI - 処理結果\n\n";
        $readmeContent .= "処理日時: " . date('Y-m-d H:i:s') . "\n";
        $readmeContent .= "言語: " . $result['language'] . "\n";
        $readmeContent .= "モデルサイズ: " . $modelSize . "\n";
        $readmeContent .= "処理時間: " . $result['processing_time'] . "秒\n\n";
        $readmeContent .= "## ファイル一覧\n\n";
        $readmeContent .= "- $audioFilename - 元の音声ファイル\n";
        $readmeContent .= "- $textFilename - 文字起こしテキスト\n";
        $readmeContent .= "- $transcriptionFilename - 文字起こし詳細情報（JSON）\n";

        $tempReadmePath = $workDir . '/README.md';
        file_put_contents($tempReadmePath, $readmeContent);
        $zip->addFile($tempReadmePath, 'README.md');

        $zip->close();
        $result['zip_file'] = $zipFilename;
        logMessage("ZIPファイルを作成しました: {$zipFilename}");
      } else {
        logMessage("ZIPファイル作成に失敗しました");
      }
    } catch (Exception $e) {
      logMessage("ZIPファイル作成中にエラーが発生しました: " . $e->getMessage());
    }

    // 一時ファイルとディレクトリの削除
    unlink($pythonScript);
    unlink($outputJson);
    if (isset($tempReadmePath) && file_exists($tempReadmePath)) {
      unlink($tempReadmePath);
    }
    rmdir($workDir);

    // 結果を返却
    logMessage("文字起こしが完了しました。処理時間: {$result['processing_time']}秒");
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    // エラー処理
    logMessage("エラー: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
  }

  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['list_transcriptions'])) {
  header('Content-Type: application/json');

  try {
    // processedディレクトリからJSONファイルを取得
    $files = glob($processedDir . '/transcription_*.json');
    $transcriptions = [];

    foreach ($files as $file) {
      $fileContent = file_get_contents($file);
      if ($fileContent) {
        $data = json_decode($fileContent, true);
        if ($data) {
          // ファイル名から日付とIDを抽出
          $filename = basename($file);
          preg_match('/transcription_(.+)\.json/', $filename, $matches);
          $id = $matches[1] ?? '';

          // 結果を配列に追加
          $transcriptions[] = [
              'id' => $id,
              'file_name' => $data['audio_file'] ?? basename($file),
              'date' => date('Y-m-d H:i:s', filemtime($file)),
              'duration' => isset($data['segments']) && !empty($data['segments']) ?
                  (end($data['segments'])['end'] ?? null) : null,
              'language' => $data['language'] ?? 'ja'
          ];
        }
      }
    }

    // 日付順に並び替え（新しい順）
    usort($transcriptions, function($a, $b) {
      return strtotime($b['date']) - strtotime($a['date']);
    });

    echo json_encode([
        'success' => true,
        'transcriptions' => $transcriptions
    ], JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
  }

  exit;
}

// 特定の文字起こしデータを読み込むエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['load_transcription'])) {
  header('Content-Type: application/json');

  try {
    $id = $_GET['load_transcription'];

    // IDのサニタイズ
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $id)) {
      throw new Exception('無効なIDです');
    }

    $file = $processedDir . '/transcription_' . $id . '.json';

    if (!file_exists($file)) {
      throw new Exception('ファイルが見つかりません');
    }

    $fileContent = file_get_contents($file);
    $data = json_decode($fileContent, true);

    if (!$data) {
      throw new Exception('ファイルの解析に失敗しました');
    }

    // 補正テキストと要約を取得（ある場合）
    $correctedFile = $processedDir . '/corrected_' . $id . '.txt';
    if (file_exists($correctedFile)) {
      $data['corrected_text'] = file_get_contents($correctedFile);
    }

    $summaryFile = $processedDir . '/summary_' . $id . '.txt';
    if (file_exists($summaryFile)) {
      $data['summary'] = file_get_contents($summaryFile);
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
  }

  exit;
}

// 無効なリクエスト
header('Content-Type: application/json');
http_response_code(400);
echo json_encode([
    'error' => '無効なリクエストです'
], JSON_UNESCAPED_UNICODE);
exit;

/**
 * ログメッセージを記録
 */
function logMessage($message) {
  global $logFile;
  $timestamp = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
}

/**
 * 文字起こし用のPythonスクリプトを生成
 */
function createPythonScript($modelSize, $language) {
  return <<<PYTHON
#!/usr/bin/env python3
import sys
import json
import os
import whisper
import torch

# キャッシュディレクトリをデータディレクトリ内に変更（権限問題解決）
os.environ['XDG_CACHE_HOME'] = '/data/cache'

# コマンドライン引数の取得
if len(sys.argv) != 3:
    print("使用方法: python3 transcribe.py <音声ファイルパス> <出力JSONパス>")
    sys.exit(1)

audio_path = sys.argv[1]
output_path = sys.argv[2]

# キャッシュディレクトリが存在することを確認
cache_dir = os.environ['XDG_CACHE_HOME']
if not os.path.exists(cache_dir):
    os.makedirs(cache_dir, exist_ok=True)

# モデルのロード
model = whisper.load_model("{$modelSize}")

# 文字起こしを実行
result = model.transcribe(
    audio_path,
    language="{$language}",
    verbose=False
)

# セグメント情報をJSONシリアライズ可能な形式に変換
formatted_segments = []
for segment in result["segments"]:
    formatted_segment = {
        "id": int(segment.get("id", 0)),
        "start": float(segment["start"]),
        "end": float(segment["end"]),
        "text": segment["text"]
    }
    # 必要に応じて他のフィールドも追加
    formatted_segments.append(formatted_segment)

# 必要な情報のみ抽出
output = {
    "text": result["text"],
    "segments": formatted_segments,
    "language": result.get("language", "{$language}")
}

# 結果をJSONファイルに保存
with open(output_path, "w", encoding="utf-8") as f:
    json.dump(output, f, ensure_ascii=False, indent=2)

print("文字起こしが完了しました")
sys.exit(0)
PYTHON;
}