import os
import time
import whisper
import torch
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import logging
from datetime import datetime

# ロギング設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger(__name__)

# 環境変数から設定を読み込む
MODEL_SIZE = os.environ.get('MODEL_SIZE', 'large-v2')
LANGUAGE = os.environ.get('LANGUAGE', 'ja')
INTERVAL = int(os.environ.get('INTERVAL', '60'))
AUDIO_DIR = os.environ.get('AUDIO_DIR', '/app/audio_files')
OUTPUT_DIR = os.environ.get('OUTPUT_DIR', '/app/output_files')
OUTPUT_FORMAT = os.environ.get('OUTPUT_FORMAT', 'txt')
DEVICE = os.environ.get('DEVICE', 'cuda' if torch.cuda.is_available() else 'cpu')

# 言語設定 (None の場合は自動検出)
LANGUAGE = None if LANGUAGE.lower() == 'auto' else LANGUAGE

# サポートする音声ファイル拡張子
AUDIO_EXTENSIONS = ['.mp3', '.wav', '.m4a', '.mp4', '.mpeg', '.mpga', '.webm']

def load_model():
    """Whisperモデルをロードする"""
    logger.info(f"Loading Whisper model: {MODEL_SIZE} on {DEVICE}")
    return whisper.load_model(MODEL_SIZE, device=DEVICE)

def transcribe_file(model, audio_path):
    """音声ファイルを文字起こしする"""
    try:
        logger.info(f"Transcribing: {os.path.basename(audio_path)}")
        result = model.transcribe(audio_path, language=LANGUAGE)
        return result
    except Exception as e:
        logger.error(f"Transcription error: {e}")
        return None

def save_transcription(result, audio_path):
    """文字起こし結果を保存する"""
    if result is None:
        return

    base_name = os.path.splitext(os.path.basename(audio_path))[0]
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    output_path = os.path.join(OUTPUT_DIR, f"{base_name}_{timestamp}")

    # テキスト形式で保存
    if OUTPUT_FORMAT == 'txt' or OUTPUT_FORMAT == 'all':
        with open(f"{output_path}.txt", "w", encoding="utf-8") as f:
            f.write(result["text"])
        logger.info(f"Saved text transcription: {os.path.basename(output_path)}.txt")

    # SRT形式で保存
    if OUTPUT_FORMAT == 'srt' or OUTPUT_FORMAT == 'all':
        from whisper.utils import WriteSRT
        with open(f"{output_path}.srt", "w", encoding="utf-8") as f:
            writer = WriteSRT(f)
            writer.write_result(result)
        logger.info(f"Saved SRT transcription: {os.path.basename(output_path)}.srt")

    # VTT形式で保存
    if OUTPUT_FORMAT == 'vtt' or OUTPUT_FORMAT == 'all':
        from whisper.utils import WriteVTT
        with open(f"{output_path}.vtt", "w", encoding="utf-8") as f:
            writer = WriteVTT(f)
            writer.write_result(result)
        logger.info(f"Saved VTT transcription: {os.path.basename(output_path)}.vtt")

    # JSON形式で保存
    if OUTPUT_FORMAT == 'json' or OUTPUT_FORMAT == 'all':
        import json
        with open(f"{output_path}.json", "w", encoding="utf-8") as f:
            json.dump(result, f, indent=2, ensure_ascii=False)
        logger.info(f"Saved JSON transcription: {os.path.basename(output_path)}.json")

class AudioHandler(FileSystemEventHandler):
    def __init__(self, model):
        self.model = model
        self.processed_files = set()
        # 既存ファイルのリストを取得
        self.scan_existing_files()

    def scan_existing_files(self):
        """既存の音声ファイルをスキャン"""
        for filename in os.listdir(AUDIO_DIR):
            file_path = os.path.join(AUDIO_DIR, filename)
            if os.path.isfile(file_path) and self.is_audio_file(file_path):
                if file_path not in self.processed_files:
                    logger.info(f"Found existing file: {filename}")
                    self.process_audio_file(file_path)

    def is_audio_file(self, file_path):
        """音声ファイルかどうか判定"""
        _, ext = os.path.splitext(file_path.lower())
        return ext in AUDIO_EXTENSIONS

    def on_created(self, event):
        """ファイル作成イベントの処理"""
        if not event.is_directory and self.is_audio_file(event.src_path):
            logger.info(f"New file detected: {os.path.basename(event.src_path)}")
            # ファイルが完全に書き込まれるまで少し待機
            time.sleep(2)
            self.process_audio_file(event.src_path)

    def process_audio_file(self, file_path):
        """音声ファイルを処理"""
        if file_path in self.processed_files:
            return

        # 文字起こし実行
        result = transcribe_file(self.model, file_path)
        if result:
            # 結果を保存
            save_transcription(result, file_path)
            self.processed_files.add(file_path)

def main():
    """メイン関数"""
    # ディレクトリ確認
    os.makedirs(AUDIO_DIR, exist_ok=True)
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    logger.info("Starting Whisper Auto Transcription")
    logger.info(f"Model: {MODEL_SIZE}, Language: {LANGUAGE if LANGUAGE else 'auto'}, Device: {DEVICE}")
    logger.info(f"Watching directory: {AUDIO_DIR}")
    logger.info(f"Output directory: {OUTPUT_DIR}")
    logger.info(f"Output format: {OUTPUT_FORMAT}")
    logger.info(f"Scan interval: {INTERVAL} seconds")

    # モデルをロード
    model = load_model()

    # ファイル監視の設定
    event_handler = AudioHandler(model)
    observer = Observer()
    observer.schedule(event_handler, path=AUDIO_DIR, recursive=False)
    observer.start()

    try:
        while True:
            # 定期的に既存ファイルをスキャン (新しく追加されたファイルを検出するため)
            event_handler.scan_existing_files()
            time.sleep(INTERVAL)
    except KeyboardInterrupt:
        observer.stop()

    observer.join()

if __name__ == "__main__":
    main()