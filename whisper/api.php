<?php
/**
 * OpenAI Whisper PHP APIラッパー
 *
 * PHPからWhisperにアクセスするためのAPIエンドポイント
 * 文字起こしと要約機能を提供
 */

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
$logFile = $logsDir . '/whisper.log';

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

// 要約エンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['summarize'])) {
  // 開始時間を記録
  $startTime = microtime(true);

  // リクエストのログ記録
  logMessage("要約リクエストを受信しました");

  try {
    // 入力JSONの取得
    $inputJson = file_get_contents('php://input');
    $data = json_decode($inputJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception('無効なJSON形式です: ' . json_last_error_msg());
    }

    // 文字起こしテキストの確認
    if (empty($data['text'])) {
      throw new Exception('文字起こしテキストが提供されていません');
    }

    // 一時ファイルの作成
    $tmpDir = '/tmp/summarize_' . uniqid();
    if (!file_exists($tmpDir)) {
      mkdir($tmpDir, 0755, true);
    }

    // 入力ファイルの作成
    $inputFilePath = $tmpDir . '/transcription.json';
    file_put_contents($inputFilePath, $inputJson);

    // 出力ファイルのパス
    $outputFilePath = $tmpDir . '/summary.json';

    // スクリプトパスの確認と作成
    if (!file_exists($scriptsDir)) {
      mkdir($scriptsDir, 0755, true);
    }

    $pythonScript = $scriptsDir . '/summarize.py';
    if (!file_exists($pythonScript)) {
      file_put_contents($pythonScript, createSummarizeScript());
      chmod($pythonScript, 0755);
    }

    // Pythonスクリプトの実行
    logMessage("文字起こしテキストの要約を開始します");

    // 仮想環境のPythonを使用
    $pythonPath = '/opt/venv/bin/python3';
    $command = "{$pythonPath} {$pythonScript} " .
        escapeshellarg($inputFilePath) . " " .
        escapeshellarg($outputFilePath);

    logMessage("実行コマンド: {$command}");

    // コマンド実行
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    // エラーチェック
    if ($returnCode !== 0) {
      $errorOutput = implode("\n", $output);
      logMessage("要約処理中にエラーが発生しました: " . $errorOutput);
      throw new Exception("要約処理中にエラーが発生しました");
    }

    // 結果JSONの読み込み
    if (!file_exists($outputFilePath)) {
      throw new Exception("要約結果ファイルが見つかりません");
    }

    $result = json_decode(file_get_contents($outputFilePath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception("要約結果の解析に失敗しました: " . json_last_error_msg());
    }

    // タイムスタンプの設定
    $timestamp = date('YmdHis');
    $sessionId = uniqid('session_');

    // エクスポートとしてMarkdownファイルを保存
    $exportFilename = "summary_{$timestamp}.md";
    $exportPath = $exportsDir . '/' . $exportFilename;

    if (isset($result['markdown'])) {
      file_put_contents($exportPath, $result['markdown']);
      $result['export_file'] = $exportFilename;

      // ZIPファイル作成（要約のみの場合）
      $zipFilename = "memo_summary_{$timestamp}.zip";
      $zipPath = $zipDir . '/' . $zipFilename;

      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($exportPath, $exportFilename);
        $zip->close();
        $result['zip_file'] = $zipFilename;
        logMessage("要約のZIPファイルを作成しました: {$zipFilename}");
      } else {
        logMessage("要約のZIPファイル作成に失敗しました");
      }
    }

    // 処理時間の計算
    $processingTime = microtime(true) - $startTime;
    $result['processing_time'] = round($processingTime, 2);

    // セッションIDを追加
    $result['session_id'] = $sessionId;

    // 一時ファイルとディレクトリの削除
    unlink($inputFilePath);
    unlink($outputFilePath);
    rmdir($tmpDir);

    // 結果を返却
    logMessage("要約が完了しました。処理時間: {$result['processing_time']}秒");
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

    // 要約オプション
    $doSummarize = isset($options['summarize']) ? (bool)$options['summarize'] : false;

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

    // 要約処理のオプションがある場合
    $summaryFilename = null;
    $markdownFilename = null;

    if ($doSummarize) {
      try {
        logMessage("要約処理を開始します");

        // スクリプトパスの確認と作成
        if (!file_exists($scriptsDir)) {
          mkdir($scriptsDir, 0755, true);
        }

        $summarizeScript = $scriptsDir . '/summarize.py';
        if (!file_exists($summarizeScript)) {
          file_put_contents($summarizeScript, createSummarizeScript());
          chmod($summarizeScript, 0755);
        }

        // 要約スクリプトの実行
        $summaryOutputJson = $tmpDir . '/summary.json';
        $summarizeCommand = "{$pythonPath} {$summarizeScript} " .
            escapeshellarg($outputJson) . " " .
            escapeshellarg($summaryOutputJson);

        logMessage("要約コマンド: {$summarizeCommand}");

        // コマンド実行
        $summaryOutput = [];
        $summaryReturnCode = 0;
        exec($summarizeCommand . " 2>&1", $summaryOutput, $summaryReturnCode);

        // 要約結果の処理
        if ($summaryReturnCode === 0 && file_exists($summaryOutputJson)) {
          $summaryResult = json_decode(file_get_contents($summaryOutputJson), true);

          if (json_last_error() === JSON_ERROR_NONE) {
            // 要約結果をマージ
            $result['summary'] = $summaryResult['summary'];

            // 要約テキストを保存
            $summaryFilename = "summary_{$sessionId}.txt";
            $summaryPath = $processedDir . '/' . $summaryFilename;
            file_put_contents($summaryPath, $summaryResult['summary']);
            $result['summary_file'] = $summaryFilename;

            // Markdownを保存
            if (isset($summaryResult['markdown'])) {
              $markdownFilename = "summary_{$sessionId}.md";
              $markdownPath = $exportsDir . '/' . $markdownFilename;
              file_put_contents($markdownPath, $summaryResult['markdown']);
              $result['markdown_file'] = $markdownFilename;
            }

            logMessage("要約処理が完了しました");
          } else {
            logMessage("要約結果の解析に失敗しました: " . json_last_error_msg());
            $result['summary_error'] = "要約結果の解析に失敗しました";
          }
        } else {
          $errorOutput = implode("\n", $summaryOutput);
          logMessage("要約処理中にエラーが発生しました: " . $errorOutput);
          $result['summary_error'] = "要約処理中にエラーが発生しました";
        }

        // 要約用の一時ファイルを削除
        if (file_exists($summaryOutputJson)) {
          unlink($summaryOutputJson);
        }

      } catch (Exception $e) {
        logMessage("要約エラー: " . $e->getMessage());
        $result['summary_error'] = $e->getMessage();
      }
    }

    // ZIPファイルの作成
    try {
      $zipFilename = "memo_assistant_{$sessionId}.zip";
      $zipPath = $zipDir . '/' . $zipFilename;

      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        // 音声ファイルを追加
        $zip->addFile($audioPath, $audioFilename);

        // 文字起こし結果を追加
        $zip->addFile($transcriptionPath, $transcriptionFilename);
        $zip->addFile($textPath, $textFilename);

        // 要約ファイルがある場合は追加
        if ($summaryFilename && file_exists($processedDir . '/' . $summaryFilename)) {
          $zip->addFile($processedDir . '/' . $summaryFilename, $summaryFilename);
        }

        // Markdownファイルがある場合は追加
        if ($markdownFilename && file_exists($exportsDir . '/' . $markdownFilename)) {
          $zip->addFile($exportsDir . '/' . $markdownFilename, $markdownFilename);
        }

        // READMEファイルを追加
        $readmeContent = "# メモ助 - 処理結果\n\n";
        $readmeContent .= "処理日時: " . date('Y-m-d H:i:s') . "\n";
        $readmeContent .= "言語: " . $result['language'] . "\n";
        $readmeContent .= "モデルサイズ: " . $modelSize . "\n";
        $readmeContent .= "処理時間: " . $result['processing_time'] . "秒\n\n";
        $readmeContent .= "## ファイル一覧\n\n";
        $readmeContent .= "- $audioFilename - 元の音声ファイル\n";
        $readmeContent .= "- $textFilename - 文字起こしテキスト\n";
        $readmeContent .= "- $transcriptionFilename - 文字起こし詳細情報（JSON）\n";

        if ($summaryFilename) {
          $readmeContent .= "- $summaryFilename - 要約テキスト\n";
        }

        if ($markdownFilename) {
          $readmeContent .= "- $markdownFilename - Markdown形式の要約\n";
        }

        $tempReadmePath = $tmpDir . '/README.md';
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
    rmdir($tmpDir);

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

/**
 * 要約用のPythonスクリプトを生成
 */
function createSummarizeScript() {
  // summarize.pyのコード内容を返す
  return <<<PYTHON
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
議事録要約スクリプト
rinna/japanese-gpt-neox-3.6b-instruction-ppoモデルを使用した文字起こしテキストの要約
"""

import sys
import json
import os
import argparse
import logging
from transformers import AutoTokenizer, AutoModelForCausalLM
import torch

# ロギング設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger('summarizer')

def setup_arg_parser():
    """コマンドライン引数の設定"""
    parser = argparse.ArgumentParser(description='文字起こしテキストの要約を行います')
    parser.add_argument('input_path', help='要約する文字起こしJSONファイルのパス')
    parser.add_argument('output_path', help='要約結果を出力するJSONファイルのパス')
    parser.add_argument('--max_length', type=int, default=512, 
                        help='要約の最大トークン長 (デフォルト: 512)')
    parser.add_argument('--temperature', type=float, default=0.7, 
                        help='生成時の温度パラメータ (デフォルト: 0.7)')
    return parser.parse_args()

def load_model():
    """要約モデルの読み込み"""
    try:
        # モデルのキャッシュディレクトリを設定
        cache_dir = os.environ.get('XDG_CACHE_HOME', '/data/cache')
        os.makedirs(cache_dir, exist_ok=True)
        
        # モデルとトークナイザの読み込み
        logger.info("モデルの読み込みを開始します")
        model_name = "rinna/japanese-gpt-neox-3.6b-instruction-ppo"  # 軽量化版の使用
        
        tokenizer = AutoTokenizer.from_pretrained(
            model_name,
            cache_dir=cache_dir,
            use_fast=True
        )
        
        # 8GB RAM制限に対応するための設定
        model = AutoModelForCausalLM.from_pretrained(
            model_name,
            cache_dir=cache_dir,
            torch_dtype=torch.float16,  # 半精度で読み込み
            device_map="auto",          # 利用可能なデバイスに自動配置
            low_cpu_mem_usage=True      # メモリ使用量を抑制
        )
        
        logger.info("モデルの読み込みが完了しました")
        return tokenizer, model
    
    except Exception as e:
        logger.error(f"モデルの読み込み中にエラーが発生しました: {str(e)}")
        raise

def summarize_text(text, tokenizer, model, max_length=512, temperature=0.7):
    """テキストの要約を行う関数"""
    try:
        logger.info("テキスト要約を開始します")
        
        # プロンプトの作成（指示形式で要約を促す）
        prompt = f"以下の文章を要約してください。箇条書きで重要なポイントを抽出し、簡潔にまとめてください。\n\n{text}"
        
        # 入力の準備
        inputs = tokenizer(prompt, return_tensors="pt")
        
        # GPUがある場合はGPUに転送
        if torch.cuda.is_available():
            inputs = inputs.to("cuda")
            model = model.to("cuda")
        
        # テキスト生成
        with torch.no_grad():
            outputs = model.generate(
                **inputs,
                max_length=len(inputs["input_ids"][0]) + max_length,
                temperature=temperature,
                do_sample=True,
                pad_token_id=tokenizer.eos_token_id
            )
        
        # 生成されたテキストをデコード
        generated_text = tokenizer.decode(outputs[0], skip_special_tokens=True)
        
        # プロンプトの部分を除去して要約部分だけを取得
        summary = generated_text.replace(prompt, "").strip()
        
        logger.info("テキスト要約が完了しました")
        return summary
    
    except Exception as e:
        logger.error(f"要約処理中にエラーが発生しました: {str(e)}")
        raise

def format_summary_markdown(summary, segments=None):
    """要約をMarkdown形式に整形する関数"""
    md_content = "# 議事録要約\n\n"
    
    # 要約テキストを追加
    md_content += "## 要点\n\n"
    
    # 箇条書きでない場合は箇条書きに変換
    if not any(line.strip().startswith('- ') for line in summary.split('\n') if line.strip()):
        points = [line for line in summary.split('\n') if line.strip()]
        for point in points:
            md_content += f"- {point}\n"
    else:
        md_content += summary
    
    # セグメント情報がある場合は詳細セクションを追加
    if segments and len(segments) > 0:
        md_content += "\n\n## 詳細内容\n\n"
        
        current_time = 0
        for segment in segments:
            start_time = segment.get('start', current_time)
            text = segment.get('text', '').strip()
            
            if text:
                # 時間を分:秒形式に変換
                minutes = int(start_time) // 60
                seconds = int(start_time) % 60
                time_str = f"{minutes:02d}:{seconds:02d}"
                
                md_content += f"**[{time_str}]** {text}\n\n"
                current_time = segment.get('end', current_time)
    
    return md_content

def main():
    """メイン関数"""
    args = setup_arg_parser()
    
    try:
        # 入力ファイルの読み込み
        logger.info(f"入力ファイル {args.input_path} を読み込みます")
        with open(args.input_path, 'r', encoding='utf-8') as f:
            input_data = json.load(f)
        
        # テキストの取得
        text = input_data.get('text', '')
        if not text:
            raise ValueError("文字起こしテキストが空です")
        
        # モデルの読み込み
        tokenizer, model = load_model()
        
        # テキストの要約
        summary = summarize_text(
            text, 
            tokenizer, 
            model, 
            max_length=args.max_length,
            temperature=args.temperature
        )
        
        # Markdown形式の要約を作成
        segments = input_data.get('segments', [])
        markdown_summary = format_summary_markdown(summary, segments)
        
        # 出力データの作成
        output_data = {
            'summary': summary,
            'markdown': markdown_summary,
            'original_text': text,
            'segments': segments
        }
        
        # 結果の保存
        logger.info(f"要約結果を {args.output_path} に保存します")
        with open(args.output_path, 'w', encoding='utf-8') as f:
            json.dump(output_data, f, ensure_ascii=False, indent=2)
        
        logger.info("要約処理が正常に完了しました")
        sys.exit(0)
    
    except Exception as e:
        logger.error(f"エラーが発生しました: {str(e)}")
        # エラー情報をJSONで出力
        error_data = {
            'error': str(e),
            'status': 'failed'
        }
        
        try:
            with open(args.output_path, 'w', encoding='utf-8') as f:
                json.dump(error_data, f, ensure_ascii=False, indent=2)
        except Exception as write_error:
            logger.error(f"エラー情報の書き込みに失敗しました: {str(write_error)}")
        
        sys.exit(1)

if __name__ == "__main__":
    main()
PYTHON;
}