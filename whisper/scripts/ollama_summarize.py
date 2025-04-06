#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Ollama APIを使用した議事録要約スクリプト (エンコーディング修正版)
"""

import sys
import json
import os
import argparse
import logging
import requests
import time
import traceback

# ロギング設定
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler('/data/logs/ollama_debug.log', encoding='utf-8')
    ]
)
logger = logging.getLogger('ollama_summarizer')

def setup_arg_parser():
    """コマンドライン引数の設定"""
    parser = argparse.ArgumentParser(description='Ollamaを使用して文字起こしテキストの要約を行います')
    parser.add_argument('input_path', help='要約する文字起こしJSONファイルのパス')
    parser.add_argument('output_path', help='要約結果を出力するJSONファイルのパス')
    parser.add_argument('--api_url', type=str, default="http://host.docker.internal:11434/api/generate",
                       help='Ollama API URL (デフォルト: http://host.docker.internal:11434/api/generate)')
    parser.add_argument('--model', type=str, default="llama3",
                       help='使用するOllamaモデル名 (デフォルト: llama3)')
    parser.add_argument('--temperature', type=float, default=0.3,
                       help='生成時の温度パラメータ (デフォルト: 0.3)')
    return parser.parse_args()

def fallback_summary(text):
    """APIが利用できない場合のフォールバック要約機能"""
    logger.info("フォールバック要約機能を使用します")

    # テキストを単純に分割して短縮
    sentences = text.split("。")
    total_sentences = len(sentences)

    # テキスト長に応じた文の選択（単純な抽出要約）
    if total_sentences <= 5:
        selected = sentences
    else:
        # 冒頭、中間、末尾から重要な文を選択（単純な戦略）
        selected = [sentences[0]]  # 冒頭文は常に含める

        # テキストの中間部分から選択
        mid_start = total_sentences // 4
        mid_end = 3 * total_sentences // 4
        step = max(1, (mid_end - mid_start) // 3)
        for i in range(mid_start, mid_end, step):
            if i < len(sentences):
                selected.append(sentences[i])

        # 最後の文も含める
        if sentences[-1] not in selected and len(sentences) > 0:
            selected.append(sentences[-1])

    # 要約を箇条書き形式で整形
    summary_points = []
    for sentence in selected:
        sentence = sentence.strip()
        if sentence:
            # 長すぎる文は短くする
            if len(sentence) > 100:
                shortened = sentence[:97] + "..."
                summary_points.append(shortened)
            else:
                summary_points.append(sentence)

    # 箇条書き形式で返す
    summary = "\n".join([f"- {point}" for point in summary_points])
    return summary

def dump_debug_info(method, url, headers, payload, response=None, error=None):
    """デバッグ情報をログファイルに出力"""
    debug_info = {
        "method": method,
        "url": url,
        "headers": headers,
        "payload": payload
    }

    if response:
        try:
            debug_info["response_status"] = response.status_code
            debug_info["response_text"] = response.text[:1000]  # 最初の1000文字だけ
        except:
            debug_info["response_error"] = "レスポンス情報の取得に失敗"

    if error:
        debug_info["error"] = str(error)

    logger.debug(f"API呼び出しデバッグ情報: {json.dumps(debug_info, ensure_ascii=False, indent=2)}")

def summarize_with_ollama(text, api_url, model="llama3", temperature=0.3):
    """Ollama APIを使用してテキストを要約する関数"""
    try:
        logger.info(f"Ollama API ({api_url}) を使用した要約を開始します (モデル: {model}, 温度: {temperature})")

        # テキストが長すぎる場合は分割して処理
        if len(text) > 10000:
            logger.info(f"テキストが長いため分割処理します (元の長さ: {len(text)}文字)")
            text = text[:10000] + "..."

        logger.debug(f"要約対象テキスト (先頭200文字): {text[:200]}...")

        # プロンプトの作成
        prompt = "あなたは音声文字起こしの専門家です。以下の文字起こしテキストを意味の通じる自然な議事録に変換してください。\n\n"
        prompt += "## 課題:\n"
        prompt += "文字起こしされたテキストには、以下のような問題が含まれています：\n"
        prompt += "- 言い淀みや繰り返し\n"
        prompt += "- 不完全な文や中断された発言\n"
        prompt += "- 文法的に不自然な表現\n"
        prompt += "- 音声認識の誤り\n\n"
        prompt += "## 指示:\n"
        prompt += "1. 文字起こしテキストを読みやすく自然な日本語に書き直してください\n"
        prompt += "2. 内容を要約するのではなく、元の発言の意図を明確にした上で完全な文章に整えてください\n"
        prompt += "3. 言い淀み、繰り返し、不要な間投詞を取り除いてください\n"
        prompt += "4. 複数の話者がいる場合は、できるだけ話者を特定し、会話の流れを明確にしてください\n"
        prompt += "5. 議論のトピックごとに段落に分けてください\n"
        prompt += "6. 重要なポイントや結論は太字で強調してください（**重要ポイント**のような形式）\n"
        prompt += "7. 決定事項やアクションアイテムがあれば、箇条書きリストとして整理してください\n\n"
        prompt += "## 出力形式:\n"
        prompt += "整えた議事録には以下の要素を含めてください：\n"
        prompt += "1. 会議の概要（推測可能な場合）\n"
        prompt += "2. 主な参加者（特定可能な場合）\n"
        prompt += "3. 主要な議題とその内容（段落分けして）\n"
        prompt += "4. 決定事項とアクションアイテム（該当する場合）\n\n"
        prompt += f"## 文字起こしテキスト:\n{text}\n\n"
        prompt += "------------------\n"
        prompt += "上記の文字起こしテキストを意味の通じる自然な議事録に変換してください。以下の点に注意してください：\n"
        prompt += "- 単なる箇条書き要約ではなく、自然な文章として再構築してください\n"
        prompt += "- 元の発言の意図を尊重しつつ、読みやすく整えてください\n"
        prompt += "- 「えー」「あの」などの言い淀みや無意味な繰り返しは除去してください\n"
        prompt += "- 文字起こしをそのまま返すのではなく、必ず整形・編集してください"

        logger.debug(f"プロンプト長: {len(prompt)}文字")

        # Ollama APIリクエストの準備
        headers = {
            "Content-Type": "application/json"
        }

        payload = {
            "model": model,
            "prompt": prompt,
            "stream": False,
            "options": {
                "temperature": temperature,
                "num_predict": 2048  # 生成する最大トークン数
            }
        }

        # APIリクエスト（タイムアウトを設定し、再試行機能を実装）
        max_retries = 3
        retry_delay = 5  # 秒

        for attempt in range(max_retries):
            try:
                logger.info(f"API呼び出し試行 {attempt+1}/{max_retries}")

                # API呼び出し前のデバッグ情報
                dump_debug_info("POST", api_url, headers, payload)

                # API呼び出し
                response = requests.post(
                    api_url,
                    headers=headers,
                    json=payload,
                    timeout=300  # 5分のタイムアウト
                )

                # レスポンスのデバッグ情報
                dump_debug_info("POST", api_url, headers, payload, response)

                # レスポンスのチェック
                response.raise_for_status()  # エラーステータスコードでは例外を発生

                result = response.json()
                logger.debug(f"API応答: {json.dumps(result, ensure_ascii=False)[:1000]}...")

                # 応答の抽出
                if "response" in result:
                    summary = result["response"].strip()

                    # レスポンスが元のテキストとほぼ同じでないことを確認
                    if len(summary) < len(text) * 0.8:  # 要約が元テキストの80%未満の長さ
                        logger.info("Ollama APIでの要約が完了しました")
                        logger.debug(f"要約 (先頭200文字): {summary[:200]}...")
                        return summary
                    else:
                        logger.warning("返された要約が長すぎます。フォールバックを使用します。")
                        return fallback_summary(text)
                else:
                    logger.warning("APIレスポンスに予期しない形式: response フィールドがありません")
                    logger.debug(f"受信したAPIレスポンス: {result}")
                    raise ValueError("API応答から要約を抽出できません")

            except requests.exceptions.RequestException as e:
                logger.error(f"API呼び出し中にエラーが発生しました: {str(e)}")
                dump_debug_info("POST", api_url, headers, payload, error=e)

                if attempt < max_retries - 1:
                    logger.warning(f"API呼び出し中にエラーが発生しました (試行 {attempt+1}/{max_retries}): {str(e)}")
                    logger.info(f"{retry_delay}秒後に再試行します...")
                    time.sleep(retry_delay)
                else:
                    logger.error(f"API呼び出しが {max_retries} 回失敗しました: {str(e)}")
                    logger.error(traceback.format_exc())
                    return fallback_summary(text)

        # すべての再試行が失敗した場合
        logger.error("最大再試行回数を超えました")
        return fallback_summary(text)

    except Exception as e:
        logger.error(f"Ollama APIでの要約処理中にエラーが発生しました: {str(e)}")
        logger.error(traceback.format_exc())
        logger.info("フォールバック要約に切り替えます")
        return fallback_summary(text)

def format_summary_markdown(summary, segments=None):
    """要約をMarkdown形式に整形する関数"""
    md_content = "# 議事録\n\n"

    # 要約テキストを追加（すでに整形されている可能性があるため、そのまま使用）
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

        logger.info(f"テキスト長: {len(text)}文字, 最初の100文字: {text[:100]}...")

        # Ollamaを使用した要約
        summary = summarize_with_ollama(
            text,
            args.api_url,
            model=args.model,
            temperature=args.temperature
        )

        # 要約が元のテキストとほぼ同じでないことを確認
        if summary == text:
            logger.warning("要約が元のテキストと同じです。フォールバック要約を使用します。")
            summary = fallback_summary(text)

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
        logger.error(traceback.format_exc())
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