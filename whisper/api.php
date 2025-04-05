<?php
/**
 * OpenAI Whisper PHP APIラッパー
 *
 * PHPからWhisperにアクセスするためのAPIエンドポイント
 */

// エラーレポート設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ログファイルの設定
$logFile = '/data/logs/whisper.log';
if (!file_exists(dirname($logFile))) {
  mkdir(dirname($logFile), 0755, true);
}

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

    // 一時ディレクトリの作成
    $tmpDir = '/tmp/whisper_' . uniqid();
    if (!file_exists($tmpDir)) {
      mkdir($tmpDir, 0755, true);
    }

    // 処理に必要なスクリプトを作成
    $pythonScript = $tmpDir . '/transcribe.py';
    file_put_contents($pythonScript, createPythonScript($modelSize, $language));
    chmod($pythonScript, 0755);

    // 出力JSONファイルのパス
    $outputJson = $tmpDir . '/output.json';

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

    // 一時ファイルとディレクトリの削除
    unlink($pythonScript);
    unlink($outputJson);
    rmdir($tmpDir);

    // 結果を返却
    logMessage("文字起こしが完了しました。処理時間: {$result['processing_time']}秒");
    header('Content-Type: application/json');
    echo json_encode($result);

  } catch (Exception $e) {
    // エラー処理
    logMessage("エラー: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
  }

  exit;
}

// 無効なリクエスト
header('Content-Type: application/json');
http_response_code(400);
echo json_encode([
    'error' => '無効なリクエストです'
]);
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

# 必要な情報のみ抽出
output = {
    "text": result["text"],
    "segments": result["segments"],
    "language": result.get("language", "{$language}")
}

# 結果をJSONファイルに保存
with open(output_path, "w", encoding="utf-8") as f:
    json.dump(output, f, ensure_ascii=False, indent=2)

print("文字起こしが完了しました")
sys.exit(0)
PYTHON;
}