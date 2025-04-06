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
    parser.add_argument('--model', type=str, default="rinna/japanese-gpt-neox-3.6b",
                        help='使用するモデル (デフォルト: rinna/japanese-gpt-neox-3.6b)')
    return parser.parse_args()

def load_model(model_name="rinna/japanese-gpt-neox-3.6b"):
    """要約モデルの読み込み"""
    try:
        # モデルのキャッシュディレクトリを設定
        cache_dir = os.environ.get('XDG_CACHE_HOME', '/data/cache')
        os.makedirs(cache_dir, exist_ok=True)

        # モデルとトークナイザの読み込み
        logger.info(f"モデル {model_name} の読み込みを開始します")

        # トークナイザーの読み込み
        tokenizer = AutoTokenizer.from_pretrained(
            model_name,
            cache_dir=cache_dir,
            use_fast=False  # 互換性のために fast tokenizer を無効化
        )

        # 8GB RAM制限に対応するための設定
        model = AutoModelForCausalLM.from_pretrained(
            model_name,
            cache_dir=cache_dir,
            torch_dtype=torch.float16,  # 半精度で読み込み
            device_map="auto",          # 利用可能なデバイスに自動配置
            low_cpu_mem_usage=True,     # メモリ使用量を抑制
            trust_remote_code=True      # リモートコードを信頼（必要な場合）
        )

        logger.info("モデルの読み込みが完了しました")
        return tokenizer, model

    except Exception as e:
        logger.error(f"モデルの読み込み中にエラーが発生しました: {str(e)}")
        raise

def fallback_summary(text):
    """モデルが利用できない場合のフォールバック要約機能"""
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

def summarize_text(text, tokenizer, model, max_length=512, temperature=0.7):
    """テキストの要約を行う関数"""
    try:
        logger.info("テキスト要約を開始します")

        # テキストが長すぎる場合は分割して処理
        if len(text) > 4000:
            logger.info("テキストが長いため分割処理します")
            text = text[:4000] + "..."

        # プロンプトの作成（指示形式で要約を促す）
        prompt = f"以下の文章を要約してください。箇条書きで重要なポイントを抽出し、簡潔にまとめてください。\n\n{text}"

        # 入力の準備
        inputs = tokenizer(prompt, return_tensors="pt")

        # GPUがある場合はGPUに転送
        if torch.cuda.is_available():
            inputs = inputs.to("cuda")
            model = model.to("cuda")
            logger.info("GPUを使用します")
        else:
            logger.info("CPUを使用します")

        # テキスト生成
        with torch.no_grad():
            try:
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
            except RuntimeError as e:
                logger.error(f"生成中にエラーが発生: {e}")
                logger.info("フォールバック機能に切り替えます")
                return fallback_summary(text)

    except Exception as e:
        logger.error(f"要約処理中にエラーが発生しました: {str(e)}")
        return fallback_summary(text)

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

        try:
            # モデルの読み込み
            tokenizer, model = load_model(args.model)

            # テキストの要約
            summary = summarize_text(
                text,
                tokenizer,
                model,
                max_length=args.max_length,
                temperature=args.temperature
            )
        except Exception as model_error:
            logger.error(f"モデル処理でエラーが発生: {str(model_error)}")
            logger.info("フォールバック要約を使用します")
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