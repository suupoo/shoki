import os
import time
import glob
import logging
import re
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
from datetime import datetime
import nltk
from nltk.tokenize import sent_tokenize
from collections import Counter

# ロギング設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger(__name__)

# 環境変数から設定を読み込む
INTERVAL = int(os.environ.get('INTERVAL', '60'))
MAX_LENGTH = int(os.environ.get('MAX_LENGTH', '200'))
MIN_LENGTH = int(os.environ.get('MIN_LENGTH', '50'))
SUMMARY_RATIO = float(os.environ.get('SUMMARY_RATIO', '0.3'))
TRIGGER_EXTENSION = os.environ.get('TRIGGER_EXTENSION', 'txt')
OUTPUT_DIR = os.environ.get('OUTPUT_DIR', '/app/output_files')
SUMMARY_DIR = os.environ.get('SUMMARY_DIR', '/app/summary_files')

def split_japanese_sentences(text):
    """
    日本語テキストを文に分割する
    """
    # 句点で区切る基本的な方法
    sentences = re.split(r'[。．.!?！？]+', text)
    # 空の文を削除
    sentences = [s.strip() for s in sentences if s.strip()]
    return sentences

def get_important_sentences(sentences, ratio=0.3):
    """
    文の重要度を評価して上位の文を選択する
    """
    if not sentences:
        return []

    # 文の数に応じた選択数（少なくとも1つ）
    num_to_select = max(1, int(len(sentences) * ratio))

    # 単語の頻度を計算
    all_text = " ".join(sentences)
    words = re.findall(r'\w+', all_text.lower())
    word_freq = Counter(words)

    # 各文のスコアを計算
    sentence_scores = []
    for i, sentence in enumerate(sentences):
        words = re.findall(r'\w+', sentence.lower())
        score = sum(word_freq[word] for word in words) / max(1, len(words))

        # 文書の冒頭と末尾の文に加重
        if i == 0:
            score *= 1.5  # 最初の文は重要な場合が多い
        elif i == len(sentences) - 1:
            score *= 1.2  # 最後の文も重要な場合がある

        sentence_scores.append((sentence, score, i))

    # スコア順にソート
    sentence_scores.sort(key=lambda x: x[1], reverse=True)

    # 上位の文を選択
    selected = sentence_scores[:num_to_select]

    # 元の順序に戻す
    selected.sort(key=lambda x: x[2])

    return [s[0] for s in selected]

def simple_summarize(text, ratio=SUMMARY_RATIO, max_length=MAX_LENGTH):
    """
    シンプルなアルゴリズムでテキストを要約する
    """
    if len(text) < MIN_LENGTH:
        logger.info(f"テキストが短すぎるため、要約は不要です ({len(text)} < {MIN_LENGTH})")
        return text

    try:
        # 日本語っぽい文字が含まれているか確認
        is_japanese = bool(re.search(r'[\u3040-\u30ff\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff\uff66-\uff9f]', text))

        if is_japanese:
            # 日本語の文分割
            sentences = split_japanese_sentences(text)
        else:
            # 英語などの文分割
            sentences = sent_tokenize(text)

        # 重要な文を抽出
        important_sentences = get_important_sentences(sentences, ratio)

        # 結果のテキストを作成
        if is_japanese:
            summary = "。".join(important_sentences) + "。"
        else:
            summary = " ".join(important_sentences)

        # 最大長を超える場合は切り詰め
        if len(summary) > max_length:
            summary = summary[:max_length] + "..."

        return summary

    except Exception as e:
        logger.error(f"要約エラー: {e}")
        # エラーが発生した場合は、最初と最後の文だけを含める
        if len(sentences) > 1:
            return sentences[0] + "..." + sentences[-1]
        return text[:MIN_LENGTH] + "..." if len(text) > MIN_LENGTH else text

class TextFileHandler(FileSystemEventHandler):
    def __init__(self):
        self.processed_files = set()
        # 既存ファイルのリストを取得
        self.scan_existing_files()

    def scan_existing_files(self):
        """既存のテキストファイルをスキャン"""
        for filename in glob.glob(os.path.join(OUTPUT_DIR, f"*.{TRIGGER_EXTENSION}")):
            if os.path.isfile(filename) and filename not in self.processed_files:
                logger.info(f"既存のファイルを検出: {os.path.basename(filename)}")
                self.process_text_file(filename)

    def on_created(self, event):
        """ファイル作成イベントの処理"""
        if not event.is_directory and event.src_path.endswith(f".{TRIGGER_EXTENSION}"):
            logger.info(f"新しいファイルを検出: {os.path.basename(event.src_path)}")
            # ファイルが完全に書き込まれるまで少し待機
            time.sleep(2)
            self.process_text_file(event.src_path)

    def on_modified(self, event):
        """ファイル変更イベントの処理"""
        if not event.is_directory and event.src_path.endswith(f".{TRIGGER_EXTENSION}"):
            # 既に処理済みのファイルは無視
            if event.src_path in self.processed_files:
                return

            logger.info(f"ファイルの変更を検出: {os.path.basename(event.src_path)}")
            # ファイルが完全に書き込まれるまで少し待機
            time.sleep(2)
            self.process_text_file(event.src_path)

    def process_text_file(self, file_path):
        """テキストファイルを処理"""
        try:
            # ファイルの存在確認
            if not os.path.exists(file_path):
                logger.warning(f"ファイルが見つかりません: {file_path}")
                return

            # ファイルが空でないか確認
            if os.path.getsize(file_path) == 0:
                logger.warning(f"ファイルが空です: {file_path}")
                return

            # ファイルを読み込む
            with open(file_path, 'r', encoding='utf-8') as f:
                text = f.read()

            # テキストが十分な長さを持っているか確認
            if len(text) < MIN_LENGTH:
                logger.info(f"テキストが短すぎるため、要約はスキップします: {os.path.basename(file_path)}")
                return

            # 要約を実行
            logger.info(f"テキストを要約中: {os.path.basename(file_path)} ({len(text)} 文字)")
            summary = simple_summarize(text)

            # 要約結果を保存
            basename = os.path.basename(file_path)
            filename_without_ext = os.path.splitext(basename)[0]
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            summary_filename = f"{filename_without_ext}_summary_{timestamp}.txt"
            summary_path = os.path.join(SUMMARY_DIR, summary_filename)

            with open(summary_path, 'w', encoding='utf-8') as f:
                f.write(summary)

            logger.info(f"要約を保存しました: {summary_filename} ({len(summary)} 文字)")

            # 処理済みリストに追加
            self.processed_files.add(file_path)

        except Exception as e:
            logger.error(f"ファイル処理エラー: {e}")

def main():
    """メイン関数"""
    # ディレクトリ確認
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    os.makedirs(SUMMARY_DIR, exist_ok=True)

    logger.info("シンプルテキスト要約サービスを起動")
    logger.info(f"出力ディレクトリ監視中: {OUTPUT_DIR}")
    logger.info(f"要約ファイル保存先: {SUMMARY_DIR}")
    logger.info(f"対象ファイル拡張子: .{TRIGGER_EXTENSION}")
    logger.info(f"監視間隔: {INTERVAL} 秒")
    logger.info(f"要約設定: 最大長={MAX_LENGTH}文字, 最小長={MIN_LENGTH}文字, 要約率={SUMMARY_RATIO}")

    # ファイル監視の設定
    event_handler = TextFileHandler()
    observer = Observer()
    observer.schedule(event_handler, path=OUTPUT_DIR, recursive=False)
    observer.start()

    try:
        while True:
            # 定期的に既存ファイルをスキャン
            event_handler.scan_existing_files()
            time.sleep(INTERVAL)
    except KeyboardInterrupt:
        observer.stop()

    observer.join()

if __name__ == "__main__":
    main()