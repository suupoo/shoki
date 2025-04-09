<?php
/**
 * Gemini API ヘルパー
 *
 * Google Gemini APIを使用して文章の補正と要約を生成するためのヘルパー関数
 */

// ヘルパーファイルを読み込み
require_once __DIR__ . '/helpers/TextProcessingHelper.php';
require_once __DIR__ . '/helpers/ApiHelper.php';
require_once __DIR__ . '/helpers/SummaryHelper.php';

// グローバル変数（テキスト分割処理用）
$apiKey = null;
$model = null;

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
  return correctAndSummarizeTextMain($text, $language, $apiKey, $model, $format);
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
  return summarizeOnlyTextMain($text, $language, $apiKey, $model, $format);
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
function summarizeText(string $text, string $language = 'ja', string $apiKey = null, string $model = null)
{
  return summarizeTextMain($text, $language, $apiKey, $model);
}

/**
 * 利用可能な要約フォーマットを取得
 *
 * @return array 要約フォーマットの配列
 */
function getAvailableSummaryFormats(): array
{
  return getSummaryFormats();
}