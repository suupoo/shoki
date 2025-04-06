# 帰属表示（Attribution）

このドキュメントは、「メモ助：議事録自動生成システム」で使用されているサードパーティのソフトウェア、モデル、ライブラリおよびその他のリソースの帰属情報を記載しています。

## AI モデル

### OpenAI Whisper

- **ソース**: https://github.com/openai/whisper
- **ライセンス**: MIT License
- **用途**: 音声認識・文字起こし機能

### rinna/japanese-gpt-neox-3.6b-instruction-ppo

- **ソース**: https://huggingface.co/rinna/japanese-gpt-neox-3.6b-instruction-ppo
- **ライセンス**: Creative Commons Attribution-ShareAlike 4.0 International License (CC BY-SA 4.0)
- **用途**: 議事録要約機能
- **特記事項**: このモデルを使用した派生物（要約機能）は、CC BY-SA 4.0ライセンスの条件に従います。

## ライブラリとフレームワーク

### Tailwind CSS

- **ソース**: https://tailwindcss.com/
- **ライセンス**: MIT License
- **用途**: UIデザイン

### Font Awesome

- **ソース**: https://fontawesome.com/
- **ライセンス**: Font Awesome Free License
- **用途**: UIアイコン

### PyTorch

- **ソース**: https://pytorch.org/
- **ライセンス**: BSD-3-Clause License
- **用途**: AIモデルのバックエンド

### Transformers (Hugging Face)

- **ソース**: https://github.com/huggingface/transformers
- **ライセンス**: Apache License 2.0
- **用途**: 要約モデルの実行

## 帰属表示要件について

本プロジェクトを使用、改変、または配布する場合は、上記のサードパーティコンポーネントのライセンス条件に従ってください。特に、rinna/japanese-gpt-neox-3.6b-instruction-ppoモデルを使用した部分については、CC BY-SA 4.0ライセンスの条件（Attribution および ShareAlike）を遵守する必要があります。

具体的には：

1. **表示（Attribution）**: rinnaのモデルを使用していることを明記してください。
2. **継承（ShareAlike）**: このモデルを含む部分を改変して配布する場合、その部分は同じCC BY-SA 4.0ライセンスで公開する必要があります。