<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SHOKI - 音声文字起こしシステム</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
      @keyframes bounce {
          0%, 100% { transform: translateY(0); }
          50% { transform: translateY(-10px); }
      }
      .bounce {
          animation: bounce 2s infinite;
      }
      .gradient-bg {
          background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
      }
      .card-shadow {
          box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">
<div class="container mx-auto px-4 py-8">
  <!-- ヘッダー -->
  <header class="text-center mb-10">
    <div class="inline-block p-2 rounded-full bg-indigo-100 mb-3">
      <svg class="w-12 h-12 text-indigo-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z"></path>
      </svg>
    </div>
    <h1 class="text-4xl font-bold text-gray-800"><span class="text-indigo-600">SHOKI</span></h1>
    <p class="text-gray-600 mt-2">AIが音声を文字起こしします</p>
  </header>

  <div class="max-w-3xl mx-auto mt-6 mb-10">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold text-gray-800">ツールとリソース</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- 要約ツールへのリンク -->
      <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow p-4 border border-indigo-100">
        <div class="flex items-start">
          <div class="bg-indigo-100 rounded-full p-2 mr-3">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <h3 class="font-medium text-indigo-900">テキスト要約ツール</h3>
            <p class="text-sm text-indigo-700 mt-1 mb-3">文字起こしとは別に、任意のテキストをAIで要約できます</p>
            <a href="summarize.php" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800">
              ツールを開く
              <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
              </svg>
            </a>
          </div>
        </div>
      </div>

      <!-- 過去の履歴へのリンク -->
      <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg shadow p-4 border border-green-100">
        <div class="flex items-start">
          <div class="bg-green-100 rounded-full p-2 mr-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <div>
            <h3 class="font-medium text-green-900">処理履歴</h3>
            <p class="text-sm text-green-700 mt-1 mb-3">過去に処理した文字起こし結果を表示します</p>
            <button id="showHistoryBtn" class="inline-flex items-center text-sm font-medium text-green-600 hover:text-green-800">
              履歴を表示
              <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 過去の処理履歴セクション -->
    <div id="historySection" class="mt-6 bg-white rounded-xl shadow-md p-5 hidden">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium text-gray-900">過去の文字起こし履歴</h2>
        <button id="refreshHistoryBtn" class="p-1 text-gray-400 hover:text-gray-600 rounded">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
        </button>
      </div>

      <div id="historyLoading" class="text-center py-4 hidden">
        <svg class="animate-spin h-6 w-6 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm text-gray-500 mt-2">履歴を読み込み中...</p>
      </div>

      <div id="historyEmpty" class="text-center py-6 hidden">
        <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-gray-500 mt-2">まだ文字起こし履歴がありません</p>
      </div>

      <div id="historyError" class="text-center py-4 hidden">
        <svg class="w-10 h-10 mx-auto text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-500 mt-2" id="historyErrorMessage">履歴の読み込み中にエラーが発生しました</p>
      </div>

      <!-- 履歴リスト -->
      <div id="historyList" class="divide-y divide-gray-200">
        <!-- 履歴項目がJSで追加される -->
      </div>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="max-w-3xl mx-auto">
    <!-- メインカード -->
    <div class="gradient-bg rounded-xl card-shadow p-1">
      <div class="bg-white rounded-lg p-6">
        <form id="transcriptionForm" action="api.php" method="post" enctype="multipart/form-data" class="space-y-6">

          <!-- ファイルアップロードセクション -->
          <div class="border-2 border-dashed border-indigo-200 rounded-lg p-6 text-center" id="dropZone">
            <input type="file" id="audio_file" name="audio_file" accept=".mp3,.wav,.m4a,.ogg" class="hidden" required>
            <div class="mb-4">
              <svg class="mx-auto h-12 w-12 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
              </svg>
            </div>
            <label for="audio_file" class="cursor-pointer">
              <span class="block font-medium text-indigo-600 mb-1">クリックまたはドラッグ&ドロップ</span>
              <span class="text-sm text-gray-500">MP3, WAV, M4A, OGG形式（最大500MB）</span>
            </label>
            <div id="fileInfo" class="mt-4 hidden">
              <div class="flex items-center justify-center">
                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span id="fileName" class="text-sm text-gray-700"></span>
              </div>
            </div>
          </div>

          <!-- オプションセクション -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="model_size" class="block text-sm font-medium text-gray-700 mb-1">モデルサイズ</label>
              <select id="model_size" name="options[model_size]"
                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                <option value="tiny">tiny（軽量・低精度）</option>
                <option value="base">base（やや軽量・やや低精度）</option>
                <option value="small" selected>small（中程度）</option>
                <option value="medium">medium（標準）</option>
<!--                <option value="large">large（高精度・高負荷）</option>-->
              </select>
            </div>

            <div>
              <label for="language" class="block text-sm font-medium text-gray-700 mb-1">言語</label>
              <select id="language" name="options[language]"
                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                <option value="ja" selected>日本語</option>
                <option value="en">英語</option>
                <option value="zh">中国語</option>
                <option value="ko">韓国語</option>
                <option value="fr">フランス語</option>
                <option value="de">ドイツ語</option>
                <option value="es">スペイン語</option>
              </select>
            </div>
          </div>

          <!-- 要約オプションセクション -->
          <div class="border rounded-lg p-4 bg-blue-50 mt-4">
            <div class="flex items-center mb-2">
              <svg class="h-5 w-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <h3 class="text-sm font-medium text-blue-900">要約オプション</h3>
            </div>

            <div class="mb-3">
              <label class="flex items-center">
                <input type="checkbox" id="enableSummarize" name="summarize_options[enabled]" class="form-checkbox h-4 w-4 text-blue-600">
                <span class="ml-2 text-sm text-gray-700">文字起こし後に自動要約する</span>
              </label>
              <p class="text-xs text-gray-600 mt-1">オンにすると、文字起こし完了後に自動的に要約を生成します</p>
            </div>

            <div class="mb-3">
              <label class="flex items-center">
                <input type="checkbox" id="enableAutoCorrection" name="summarize_options[correct]" class="form-checkbox h-4 w-4 text-blue-600" checked>
                <span class="ml-2 text-sm text-gray-700">AIによる文章補正を有効にする</span>
              </label>
              <p class="text-xs text-gray-600 mt-1">オンにすると、文字起こし結果を自然な文章に補正した上で要約します</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
              <div>
                <label for="summaryFormat" class="block text-xs font-medium text-gray-700">要約フォーマット</label>
                <select id="summaryFormat" name="summarize_options[format]" class="mt-1 block w-full pl-3 pr-10 py-1 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  <option value="standard">標準 (段落形式)</option>
                  <option value="bullet">箇条書き</option>
                  <option value="headline">見出し形式</option>
                  <option value="qa">Q&A形式</option>
                  <option value="executive">エグゼクティブサマリー</option>
                  <option value="meeting">会議議事録</option>
                </select>
              </div>

              <div>
                <label for="summarizeModel" class="block text-xs font-medium text-gray-700">Geminiモデル</label>
                <select id="summarizeModel" name="summarize_options[model]" class="mt-1 block w-full pl-3 pr-10 py-1 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                  <option value="gemini-1.5-flash" selected>Gemini 1.5 Flash (高速)</option>
                  <option value="gemini-1.5-pro">Gemini 1.5 Pro (高性能)</option>
                  <option value="gemini-pro">Gemini Pro (旧モデル)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- 話者ラベル機能 -->
          <div class="border rounded-lg p-4 bg-indigo-50">
            <div class="flex items-center mb-2">
              <svg class="h-5 w-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              <h3 class="text-sm font-medium text-indigo-900">話者ラベル（オプション）</h3>
            </div>
            <p class="text-xs text-gray-600 mb-3">議事録に含まれる話者を指定すると、セグメント表示時に話者名が表示されます。</p>

            <div id="speakerLabels" class="space-y-2">
              <div class="flex items-center speaker-entry">
                <input type="text" name="speaker_labels[]" placeholder="話者名（例: 山田さん）"
                       class="flex-1 px-3 py-1 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
              </div>
            </div>

            <button type="button" id="addSpeakerBtn" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 flex items-center">
              <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              話者を追加
            </button>
          </div>

          <!-- 送信ボタン -->
          <div>
            <button type="submit" id="submitBtn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-md font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
              <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              処理を開始
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 処理中のローディング表示 -->
    <div id="processingContainer" class="hidden mt-8 p-8 text-center">
      <div class="inline-block p-3 rounded-full bg-indigo-100 mb-4 bounce">
        <svg class="w-10 h-10 text-indigo-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
      </div>
      <h2 class="text-xl font-bold text-gray-800 mb-2">処理中です...</h2>
      <p class="text-gray-600 max-w-md mx-auto" id="processingMessage">音声を分析しています。大きなファイルの場合は数分かかることがあります。</p>
      <div class="mt-4 relative pt-1">
        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-indigo-200">
          <div id="progressBar" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 w-0 transition-all duration-300"></div>
        </div>
      </div>
    </div>

    <!-- 結果表示エリア -->
    <div id="resultsContainer" class="hidden mt-8">
      <!-- 結果タブ -->
      <div class="border-b border-gray-200">
        <nav class="flex -mb-px" aria-label="Tabs">
          <button type="button" data-tab="transcription" class="result-tab border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
            文字起こし
          </button>
          <button type="button" data-tab="segments" class="result-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
            セグメント
          </button>
          <button type="button" data-tab="summary" class="result-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
            要約
          </button>
        </nav>
      </div>

      <!-- タブコンテンツ -->
      <div class="py-4">
        <!-- 文字起こし結果 -->
        <div id="transcriptionTab" class="result-content">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-medium text-gray-900">文字起こし結果</h2>
              <div class="flex space-x-2">
                <button id="copyTranscription" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm flex items-center">
                  <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                  </svg>
                  コピー
                </button>
                <button id="downloadTranscription" class="px-3 py-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded text-sm flex items-center">
                  <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                  </svg>
                  ダウンロード
                </button>
              </div>
            </div>
            <textarea id="transcriptionText" class="w-full h-64 p-3 border border-gray-300 rounded font-mono text-sm" readonly></textarea>
            <div class="mt-4 text-sm text-gray-500">
              <span id="transcriptionInfo"></span>
            </div>
          </div>
        </div>

        <!-- セグメント結果 -->
        <div id="segmentsTab" class="result-content hidden">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-medium text-gray-900">セグメント情報</h2>
              <button id="exportSegments" class="px-3 py-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded text-sm flex items-center">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                エクスポート
              </button>
            </div>
            <div class="overflow-auto max-h-96">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">開始</th>
                  <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">終了</th>
                  <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">話者</th>
                  <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">テキスト</th>
                </tr>
                </thead>
                <tbody id="segmentsTableBody" class="bg-white divide-y divide-gray-200"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- 要約結果 -->
        <div id="summaryTab" class="result-content hidden">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-medium text-gray-900">文章補正と要約</h2>
              <div class="flex space-x-2">
                <button id="generateSummary" class="px-3 py-1 bg-green-100 hover:bg-green-200 text-green-700 rounded text-sm flex items-center">
                  <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                  </svg>
                  生成
                </button>
                <button id="copySummary" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm flex items-center">
                  <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                  </svg>
                  コピー
                </button>
                <button id="downloadSummary" class="px-3 py-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded text-sm flex items-center">
                  <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                  </svg>
                  ダウンロード
                </button>
              </div>
            </div>

            <!-- 補正オプション -->
            <div class="mb-4 bg-gray-50 p-3 rounded border border-gray-200">
              <label class="flex items-center">
                <input type="checkbox" id="enableCorrection" class="form-checkbox h-4 w-4 text-indigo-600" checked>
                <span class="ml-2 text-sm text-gray-700">AIによる文章補正を有効にする</span>
              </label>
              <p class="text-xs text-gray-500 mt-1">オンにすると、文字起こし結果を自然な文章に補正した上で要約します。より正確な結果が得られますが、処理時間が長くなります。</p>
            </div>

            <!-- 補正オプションの下にモデル選択を追加 -->
            <div class="mb-4 bg-gray-50 p-3 rounded border border-gray-200">
              <label class="flex items-center">
                <input type="checkbox" id="enableCorrection" class="form-checkbox h-4 w-4 text-indigo-600" checked>
                <span class="ml-2 text-sm text-gray-700">AIによる文章補正を有効にする</span>
              </label>
              <p class="text-xs text-gray-500 mt-1">オンにすると、文字起こし結果を自然な文章に補正した上で要約します。より正確な結果が得られますが、処理時間が長くなります。</p>

              <!-- フォーマット選択ドロップダウン -->
              <div class="mt-3">
                <label for="summaryFormat" class="block text-xs font-medium text-gray-700">要約フォーマット</label>
                <select id="summaryFormat" class="mt-1 block w-full pl-3 pr-10 py-1 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  <option value="meeting">会議議事録</option>
                  <option value="standard">標準 (段落形式)</option>
                  <option value="bullet">箇条書き</option>
                  <option value="headline">見出し形式</option>
                  <option value="qa">Q&A形式</option>
                  <option value="executive">エグゼクティブサマリー</option>
                </select>
              </div>

              <!-- モデル選択ドロップダウン -->
              <div class="mt-3">
                <label for="geminiModel" class="block text-xs font-medium text-gray-700">Geminiモデル</label>
                <select id="geminiModel" class="mt-1 block w-full pl-3 pr-10 py-1 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  <option value="gemini-1.5-pro">Gemini 1.5 Pro (高性能・低速)</option>
                  <option value="gemini-1.5-flash">Gemini 1.5 Flash (中性能・高速)</option>
                  <option value="gemini-1.0-pro">Gemini 1.0 Pro (旧モデル)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">注: 環境変数の設定がある場合は環境変数が優先されます</p>
              </div>
            </div>

            <!-- ローディングインジケーター -->
            <div id="summaryLoading" class="hidden">
              <div class="flex justify-center items-center py-8">
                <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-600">処理中...</span>
              </div>
            </div>

            <!-- 補正結果表示エリア -->
            <div id="correctedTextSection" class="mb-6 hidden">
              <h3 class="text-sm font-medium text-gray-700 mb-2">補正された文章</h3>
              <div class="relative">
                <div id="correctedTextContent" class="prose prose-indigo max-w-none p-4 bg-gray-50 rounded border border-gray-200 text-sm"></div>
                <button id="copyCorrectedText" class="absolute top-2 right-2 p-1 bg-white rounded border border-gray-200 text-gray-500 hover:bg-gray-100">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                  </svg>
                </button>
              </div>
            </div>

            <!-- 要約結果表示エリア -->
            <div id="summarySection" class="hidden">
              <h3 class="text-sm font-medium text-gray-700 mb-2">要約</h3>
              <div id="summaryContent" class="prose prose-indigo max-w-none p-4 bg-gray-50 rounded border border-gray-200 text-sm"></div>
            </div>

            <!-- APIキーなしの場合のメッセージ -->
            <div id="summaryApiKeyMissing" class="hidden text-center py-8 text-gray-500">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
              <p class="mt-2">Gemini APIキーが設定されていません。</p>
              <p class="text-sm">補正・要約機能を使用するには、.envファイルに有効なGEMINI_API_KEYを設定してください。</p>
            </div>

            <!-- エラーメッセージ -->
            <div id="summaryError" class="hidden text-center py-8 text-red-500">
              <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="mt-2" id="summaryErrorMessage">処理中にエラーが発生しました。</p>
            </div>

            <!-- 要約がまだない場合のメッセージ -->
            <div id="summaryNotAvailable" class="text-center py-8 text-gray-500">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <p class="mt-2">処理はまだ生成されていません。</p>
              <p class="text-sm">「生成」ボタンをクリックして文字起こし内容から補正と要約を生成できます。</p>
            </div>

            `<div class="mt-2 flex justify-end">
              <button id="regenerateSummaryBtn" class="px-3 py-1 bg-green-50 hover:bg-green-100 text-green-600 rounded text-sm flex items-center mr-2">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                要約のみ再生成
              </button>
            </div>`
          </div>
        </div>
      </div>

      <!-- アクションボタン -->
      <div class="mt-6 flex justify-between">
        <button id="resetBtn" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
          <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          新しいファイルを処理
        </button>
        <button id="downloadAllBtn" class="flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
          <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
          </svg>
          すべてZIPでダウンロード
        </button>
      </div>
    </div>

    <!-- フッター情報 -->
    <div class="mt-12 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
      <p>© 2025 SHOKI - 音声文字起こしシステム</p>
      <p class="mt-1">OpenAI Whisperモデルを使用しています</p>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM要素の取得
        const form = document.getElementById('transcriptionForm');
        const audioFileInput = document.getElementById('audio_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const dropZone = document.getElementById('dropZone');
        const addSpeakerBtn = document.getElementById('addSpeakerBtn');
        const speakerLabels = document.getElementById('speakerLabels');
        const processingContainer = document.getElementById('processingContainer');
        const processingMessage = document.getElementById('processingMessage');
        const progressBar = document.getElementById('progressBar');
        const resultsContainer = document.getElementById('resultsContainer');
        const resetBtn = document.getElementById('resetBtn');
        const submitBtn = document.getElementById('submitBtn');
        const regenerateSummaryBtn = document.getElementById('regenerateSummaryBtn');

        // 結果タブ関連の要素
        const resultTabs = document.querySelectorAll('.result-tab');
        const resultContents = document.querySelectorAll('.result-content');
        const transcriptionText = document.getElementById('transcriptionText');
        const transcriptionInfo = document.getElementById('transcriptionInfo');
        const segmentsTableBody = document.getElementById('segmentsTableBody');

        // 履歴表示関連の要素を取得
        const showHistoryBtn = document.getElementById('showHistoryBtn');
        const refreshHistoryBtn = document.getElementById('refreshHistoryBtn');
        const historySection = document.getElementById('historySection');
        const historyLoading = document.getElementById('historyLoading');
        const historyEmpty = document.getElementById('historyEmpty');
        const historyError = document.getElementById('historyError');
        const historyErrorMessage = document.getElementById('historyErrorMessage');
        const historyList = document.getElementById('historyList');

        // 履歴表示ボタンのイベント
        if (showHistoryBtn) {
            showHistoryBtn.addEventListener('click', () => {
                historySection.classList.toggle('hidden');

                // 初回表示時に履歴を読み込む
                if (!historySection.classList.contains('hidden') && historyList.children.length === 0) {
                    loadTranscriptionHistory();
                }
            });
        }

        // 履歴更新ボタンのイベント
        if (refreshHistoryBtn) {
            refreshHistoryBtn.addEventListener('click', loadTranscriptionHistory);
        }

        // 要約関連の要素
        const summaryContent = document.getElementById('summaryContent');
        const correctedTextContent = document.getElementById('correctedTextContent');
        const correctedTextSection = document.getElementById('correctedTextSection');
        const summarySection = document.getElementById('summarySection');
        const summaryNotAvailable = document.getElementById('summaryNotAvailable');
        const summaryApiKeyMissing = document.getElementById('summaryApiKeyMissing');
        const summaryError = document.getElementById('summaryError');
        const summaryErrorMessage = document.getElementById('summaryErrorMessage');
        const summaryLoading = document.getElementById('summaryLoading');
        const generateSummary = document.getElementById('generateSummary');
        const copySummary = document.getElementById('copySummary');
        const copyCorrectedText = document.getElementById('copyCorrectedText');
        const downloadSummary = document.getElementById('downloadSummary');
        const enableCorrection = document.getElementById('enableCorrection');
        const geminiModel = document.getElementById('geminiModel');
        // コピー・ダウンロードボタン
        const copyTranscription = document.getElementById('copyTranscription');
        const downloadTranscription = document.getElementById('downloadTranscription');
        const exportSegments = document.getElementById('exportSegments');
        const downloadAllBtn = document.getElementById('downloadAllBtn');

        // グローバル変数
        let currentResults = null;
        let hasSummary = false;
        let hasCorrectedText = false;

        // ファイルアップロードの処理
        audioFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileName.textContent = this.files[0].name;
                fileInfo.classList.remove('hidden');
                dropZone.classList.add('border-indigo-300', 'bg-indigo-50');
            }
        });

        // ドラッグ&ドロップの処理
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.classList.add('border-indigo-400', 'bg-indigo-100');
        }

        function unhighlight() {
            dropZone.classList.remove('border-indigo-400', 'bg-indigo-100');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            audioFileInput.files = files;

            if (files && files[0]) {
                fileName.textContent = files[0].name;
                fileInfo.classList.remove('hidden');
            }
        }

        // 話者ラベルの追加
        addSpeakerBtn.addEventListener('click', function() {
            const newSpeaker = document.createElement('div');
            newSpeaker.className = 'flex items-center speaker-entry';
            newSpeaker.innerHTML = `
          <input type="text" name="speaker_labels[]" placeholder="話者名（例: 山田さん）"
                 class="flex-1 px-3 py-1 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
          <button type="button" class="remove-speaker ml-2 text-gray-400 hover:text-red-500">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        `;

            newSpeaker.querySelector('.remove-speaker').addEventListener('click', function() {
                speakerLabels.removeChild(newSpeaker);
            });

            speakerLabels.appendChild(newSpeaker);
        });

        // タブ切り替え
        resultTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // アクティブタブのスタイル変更
                resultTabs.forEach(t => {
                    t.classList.remove('border-indigo-500', 'text-indigo-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                tab.classList.remove('border-transparent', 'text-gray-500');
                tab.classList.add('border-indigo-500', 'text-indigo-600');

                // コンテンツの表示切り替え
                const tabName = tab.getAttribute('data-tab');
                resultContents.forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(tabName + 'Tab').classList.remove('hidden');
            });
        });

        // フォーム送信処理
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // ファイルチェック
            if (!audioFileInput.files || !audioFileInput.files[0]) {
                alert('音声ファイルを選択してください');
                return;
            }

            // UIの更新
            form.parentNode.parentNode.classList.add('hidden');
            processingContainer.classList.remove('hidden');

            // FormDataの作成
            const formData = new FormData(form);

            // 話者ラベルの処理
            const speakerEntries = document.querySelectorAll('.speaker-entry input');
            const speakerLabelsArray = [];
            speakerEntries.forEach(entry => {
                if (entry.value.trim()) {
                    speakerLabelsArray.push(entry.value.trim());
                }
            });

            // 話者ラベルがある場合はJSONに変換して追加
            if (speakerLabelsArray.length > 0) {
                formData.set('options[speaker_labels]', JSON.stringify(speakerLabelsArray));
            }

            // プログレスバーのシミュレーション
            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 5;
                    progressBar.style.width = `${progress}%`;

                    // 処理メッセージの更新
                    if (progress < 20) {
                        processingMessage.textContent = '音声ファイルを解析しています...';
                    } else if (progress < 50) {
                        processingMessage.textContent = 'Whisperモデルで文字起こし処理中...';
                    } else if (progress < 80) {
                        if (document.getElementById('enableCorrection') && document.getElementById('enableCorrection').checked) {
                            processingMessage.textContent = '文字起こし完了、要約を生成中...';
                        } else {
                            processingMessage.textContent = '文字起こし処理をまとめています...';
                        }
                    }
                }
            }, 1000);

            // APIリクエスト
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';

                    if (!response.ok) {
                        throw new Error('APIリクエストに失敗しました');
                    }
                    return response.json();
                })
                .then(data => {
                    // 結果の保存
                    currentResults = data;

                    // 処理終了表示
                    setTimeout(() => {
                        processingContainer.classList.add('hidden');
                        resultsContainer.classList.remove('hidden');
                        displayResults(data);
                    }, 500);
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    processingMessage.textContent = `エラーが発生しました: ${error.message}`;
                    processingContainer.classList.add('text-red-500');

                    // リセットボタンの表示
                    const errorResetBtn = document.createElement('button');
                    errorResetBtn.className = 'mt-4 px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200';
                    errorResetBtn.textContent = 'やり直す';
                    errorResetBtn.addEventListener('click', resetForm);
                    processingContainer.appendChild(errorResetBtn);
                });
        });

        // 結果の表示
        function displayResults(data) {
            // 文字起こし結果
            if (data.text) {
                transcriptionText.value = data.text;
                transcriptionInfo.textContent = `処理時間: ${data.processing_time}秒 | 言語: ${data.language || 'ja'}`;
            }

            // 補正テキストと要約結果がある場合（既に生成されている場合）
            if (data.corrected_text) {
                correctedTextContent.innerHTML = formatMarkdown(data.corrected_text);
                correctedTextSection.classList.remove('hidden');
                hasCorrectedText = true;
            } else {
                // 補正テキストがまだない場合
                correctedTextSection.classList.add('hidden');
                hasCorrectedText = false;
            }

            if (data.summary) {
                summaryContent.innerHTML = formatMarkdown(data.summary);
                summarySection.classList.remove('hidden');
                summaryNotAvailable.classList.add('hidden');
                summaryApiKeyMissing.classList.add('hidden');
                summaryError.classList.add('hidden');
                hasSummary = true;
            } else {
                // 要約がまだない場合
                summarySection.classList.add('hidden');
                summaryNotAvailable.classList.remove('hidden');
                summaryApiKeyMissing.classList.add('hidden');
                summaryError.classList.add('hidden');
                hasSummary = false;
            }

            // セグメント情報
            if (data.segments && data.segments.length > 0) {
                // 話者ラベルの取得
                const speakerLabels = data.speaker_labels || [];
                const speakerCount = speakerLabels.length;

                // セグメントテーブルの作成
                segmentsTableBody.innerHTML = '';
                data.segments.forEach((segment, index) => {
                    const row = document.createElement('tr');

                    // 時間のフォーマット (mm:ss.ms)
                    const startTime = formatTime(segment.start);
                    const endTime = formatTime(segment.end);

                    // 話者の決定 (単純な輪番制)
                    let speaker = '';
                    if (speakerCount > 0) {
                        const speakerIndex = index % speakerCount;
                        speaker = speakerLabels[speakerIndex];
                    }

                    row.innerHTML = `
              <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${startTime}</td>
              <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">${endTime}</td>
              <td class="px-3 py-2 whitespace-nowrap text-sm font-medium ${speaker ? 'text-indigo-600' : 'text-gray-400'}">${speaker || '不明'}</td>
              <td class="px-3 py-2 text-sm text-gray-500">${segment.text}</td>
            `;

                    segmentsTableBody.appendChild(row);
                });
            } else {
                // セグメント情報がない場合
                segmentsTableBody.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">セグメント情報がありません</td></tr>';
            }
        }

        // 時間のフォーマット関数
        function formatTime(seconds) {
            const min = Math.floor(seconds / 60);
            const sec = Math.floor(seconds % 60);
            const ms = Math.floor((seconds % 1) * 10);
            return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}.${ms}`;
        }

        // マークダウンのフォーマット関数
        function formatMarkdown(text) {
            if (!text) return '';

            // 箇条書きの処理
            let formatted = text.replace(/^- (.+)$/gm, '<li>$1</li>');
            if (formatted.includes('<li>')) {
                formatted = '<ul class="list-disc pl-5 space-y-1">' + formatted + '</ul>';
            }

            // 改行の処理
            formatted = formatted.replace(/\n\n/g, '<br><br>');
            formatted = formatted.replace(/\n/g, '<br>');

            return formatted;
        }

        // コピーボタンの処理
        copyTranscription.addEventListener('click', () => {
            transcriptionText.select();
            document.execCommand('copy');
            showToast('文字起こし結果をコピーしました');
        });

        // ダウンロードボタンの処理
        downloadTranscription.addEventListener('click', () => {
            if (currentResults && currentResults.text) {
                downloadText(currentResults.text, 'transcription.txt');
            }
        });

        // 要約の生成
        generateSummary.addEventListener('click', async () => {
            // 文字起こし結果がない場合
            if (!currentResults || !currentResults.text) {
                showToast('文字起こし結果がありません');
                return;
            }

            // ローディング表示
            summaryContent.innerHTML = '';
            correctedTextContent.innerHTML = '';
            summarySection.classList.add('hidden');
            correctedTextSection.classList.add('hidden');
            summaryNotAvailable.classList.add('hidden');
            summaryApiKeyMissing.classList.add('hidden');
            summaryError.classList.add('hidden');
            summaryLoading.classList.remove('hidden');

            try {
                // 補正フラグの取得
                const correctText = enableCorrection.checked;

                // フォーマット選択の取得
                const selectedFormat = document.getElementById('summaryFormat').value;

                // モデル選択を取得
                const selectedModel = geminiModel ? geminiModel.value : null;

                // 要約APIの呼び出し
                const response = await fetch('api.php?summarize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        text: currentResults.text,
                        language: currentResults.language || 'ja',
                        correct: correctText,
                        model: selectedModel,
                        format: selectedFormat
                    })
                });

                const result = await response.json();

                // ローディング非表示
                summaryLoading.classList.add('hidden');

                if (response.ok && result.success) {
                    // 補正テキストがある場合は表示
                    if (result.corrected_text) {
                        correctedTextContent.innerHTML = formatMarkdown(result.corrected_text);
                        correctedTextSection.classList.remove('hidden');
                        currentResults.corrected_text = result.corrected_text;
                        hasCorrectedText = true;
                    }

                    // 要約の表示
                    if (result.summary) {
                        summaryContent.innerHTML = formatMarkdown(result.summary);
                        summarySection.classList.remove('hidden');
                        currentResults.summary = result.summary;
                        hasSummary = true;
                    }

                    // 使用されたモデルを表示（オプション）
                    if (result.model) {
                        const modelInfo = document.createElement('div');
                        modelInfo.className = 'text-xs text-gray-500 mt-2';
                        modelInfo.textContent = `使用モデル: ${result.model}`;
                        summarySection.appendChild(modelInfo);
                    }

                    // タブの自動選択
                    const summaryTab = document.querySelector('[data-tab="summary"]');
                    if (summaryTab) {
                        summaryTab.click();
                    }

                    showToast(correctText ? '補正と要約が完了しました' : '要約が完了しました');
                } else if (result.error && result.error.includes('APIキー')) {
                    // APIキーがない場合
                    summaryApiKeyMissing.classList.remove('hidden');
                } else {
                    // その他のエラー
                    summaryErrorMessage.textContent = result.error || '処理中にエラーが発生しました';
                    summaryError.classList.remove('hidden');
                }
            } catch (error) {
                // 例外発生時
                summaryLoading.classList.add('hidden');
                summaryErrorMessage.textContent = '通信エラーが発生しました: ' + error.message;
                summaryError.classList.remove('hidden');
            }
        });

        // 要約のみを再度生成するボタンの処理
        regenerateSummaryBtn.addEventListener('click', async () => {
            // 文字起こし結果がない場合
            if (!currentResults || !currentResults.text) {
                showToast('文字起こし結果がありません');
                return;
            }

            // 補正済みテキストがない場合
            if (!hasCorrectedText) {
                showToast('先に補正テキストを生成してください');
                return;
            }

            // ローディング表示
            summaryContent.innerHTML = '';
            summarySection.classList.add('hidden');
            summaryNotAvailable.classList.add('hidden');
            summaryApiKeyMissing.classList.add('hidden');
            summaryError.classList.add('hidden');
            summaryLoading.classList.remove('hidden');

            try {
                // フォーマット選択の取得
                const selectedFormat = document.getElementById('summaryFormat').value;

                // モデル選択を取得
                const selectedModel = geminiModel ? geminiModel.value : null;

                // 要約APIの呼び出し（要約のみのモード）
                const response = await fetch('api.php?summarize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        text: currentResults.text,
                        corrected_text: currentResults.corrected_text,
                        language: currentResults.language || 'ja',
                        model: selectedModel,
                        format: selectedFormat,
                        summarize_only: true
                    })
                });

                const result = await response.json();

                // ローディング非表示
                summaryLoading.classList.add('hidden');

                if (response.ok && result.success) {
                    // 要約の表示
                    if (result.summary) {
                        summaryContent.innerHTML = formatMarkdown(result.summary);
                        summarySection.classList.remove('hidden');
                        currentResults.summary = result.summary;
                        hasSummary = true;
                    }

                    // 使用されたモデルを表示（オプション）
                    if (result.model) {
                        const modelInfo = document.createElement('div');
                        modelInfo.className = 'text-xs text-gray-500 mt-2';
                        modelInfo.textContent = `使用モデル: ${result.model}`;
                        summarySection.appendChild(modelInfo);
                    }

                    showToast('要約が再生成されました');
                } else if (result.error && result.error.includes('APIキー')) {
                    // APIキーがない場合
                    summaryApiKeyMissing.classList.remove('hidden');
                } else {
                    // その他のエラー
                    summaryErrorMessage.textContent = result.error || '処理中にエラーが発生しました';
                    summaryError.classList.remove('hidden');
                }
            } catch (error) {
                // 例外発生時
                summaryLoading.classList.add('hidden');
                summaryErrorMessage.textContent = '通信エラーが発生しました: ' + error.message;
                summaryError.classList.remove('hidden');
            }
        });

        // 要約コピーボタン
        copySummary.addEventListener('click', () => {
            if (!hasSummary) {
                showToast('要約がありません');
                return;
            }

            const textToCopy = summaryContent.innerText;
            copyTextToClipboard(textToCopy);
            showToast('要約をコピーしました');
        });

        // 要約ダウンロードボタン
        downloadSummary.addEventListener('click', () => {
            if (!currentResults) {
                showToast('データがありません');
                return;
            }

            let content = '';
            let filename = 'shoki_output.txt';

            if (hasCorrectedText && hasSummary) {
                content = `# 補正テキスト\n\n${currentResults.corrected_text}\n\n# 要約\n\n${currentResults.summary}`;
                filename = 'shoki_corrected_and_summary.md';
            } else if (hasCorrectedText) {
                content = currentResults.corrected_text;
                filename = 'shoki_corrected.txt';
            } else if (hasSummary) {
                content = currentResults.summary;
                filename = 'shoki_summary.txt';
            } else {
                showToast('ダウンロードするデータがありません');
                return;
            }

            downloadText(content, filename);
        });

        // クリップボードにテキストをコピーする関数
        function copyTextToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        // 補正テキストのコピーボタン
        copyCorrectedText.addEventListener('click', () => {
            if (!hasCorrectedText) {
                showToast('補正テキストがありません');
                return;
            }

            const textToCopy = correctedTextContent.innerText;
            copyTextToClipboard(textToCopy);
            showToast('補正テキストをコピーしました');
        });

        exportSegments.addEventListener('click', () => {
            if (currentResults && currentResults.segments) {
                const csvContent = segmentsToCSV(currentResults.segments, currentResults.speaker_labels);
                downloadText(csvContent, 'segments.csv');
            }
        });

        // すべてダウンロードボタン
        downloadAllBtn.addEventListener('click', () => {
            // ZIPファイルのダウンロード機能
            if (currentResults && currentResults.zip_file) {
                window.location.href = `api.php?download=true&file=${currentResults.zip_file}`;
            } else {
                alert('ダウンロード可能なZIPファイルがありません。');
            }
        });
        // リセットボタンの処理
        resetBtn.addEventListener('click', resetForm);

        function resetForm() {
            form.reset();
            fileInfo.classList.add('hidden');
            dropZone.classList.remove('border-indigo-300', 'bg-indigo-50');

            // 話者ラベルをリセット
            const speakerEntries = document.querySelectorAll('.speaker-entry');
            speakerEntries.forEach((entry, index) => {
                if (index > 0) { // 最初の1つは残す
                    entry.remove();
                } else {
                    entry.querySelector('input').value = '';
                }
            });

            // 結果をクリア
            transcriptionText.value = '';
            summaryContent.innerHTML = '';
            segmentsTableBody.innerHTML = '';

            // 表示切替
            resultsContainer.classList.add('hidden');
            processingContainer.classList.add('hidden');
            form.parentNode.parentNode.classList.remove('hidden');

            // プログレスバーをリセット
            progressBar.style.width = '0%';
            processingMessage.textContent = '音声を分析しています。大きなファイルの場合は数分かかることがあります。';
            processingContainer.classList.remove('text-red-500');

            // エラー表示時に追加されたボタンを削除
            const errorResetBtn = processingContainer.querySelector('button');
            if (errorResetBtn) {
                processingContainer.removeChild(errorResetBtn);
            }

            // 現在の結果をクリア
            currentResults = null;
        }

        // セグメントをCSVに変換
        function segmentsToCSV(segments, speakerLabels = []) {
            const headers = ['開始時間', '終了時間', '話者', 'テキスト'];
            const rows = [headers.join(',')];

            segments.forEach((segment, index) => {
                // 話者の決定
                let speaker = '不明';
                if (speakerLabels && speakerLabels.length > 0) {
                    speaker = speakerLabels[index % speakerLabels.length];
                }

                const startTime = formatTime(segment.start);
                const endTime = formatTime(segment.end);
                const escapedText = `"${segment.text.replace(/"/g, '""')}"`;

                rows.push([startTime, endTime, speaker, escapedText].join(','));
            });

            return rows.join('\n');
        }

        // テキストファイルのダウンロード
        function downloadText(text, filename) {
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // トースト通知の表示
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg transform transition-all duration-300 ease-out opacity-0 translate-y-2';
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('opacity-0', 'translate-y-2');
                toast.classList.add('opacity-100', 'translate-y-0');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        // 文字起こし履歴を読み込む関数
        function loadTranscriptionHistory() {
            // 状態をリセット
            historyLoading.classList.remove('hidden');
            historyEmpty.classList.add('hidden');
            historyError.classList.add('hidden');
            historyList.innerHTML = '';

            // processed ディレクトリ内のJSONファイルを取得するAPIリクエスト
            fetch('api.php?list_transcriptions=1')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('APIレスポンスエラー');
                    }
                    return response.json();
                })
                .then(data => {
                    historyLoading.classList.add('hidden');

                    if (data.success) {
                        const transcriptions = data.transcriptions || [];

                        // 履歴がない場合
                        if (transcriptions.length === 0) {
                            historyEmpty.classList.remove('hidden');
                            return;
                        }

                        // 履歴を表示
                        transcriptions.forEach(item => {
                            const historyItem = createHistoryItem(item);
                            historyList.appendChild(historyItem);
                        });
                    } else {
                        throw new Error(data.error || '履歴の取得に失敗しました');
                    }
                })
                .catch(error => {
                    historyLoading.classList.add('hidden');
                    historyErrorMessage.textContent = error.message;
                    historyError.classList.remove('hidden');
                });
        }

        // 履歴項目のHTML要素を作成する関数
        function createHistoryItem(item) {
            const date = new Date(item.date);
            const formattedDate = date.toLocaleString('ja-JP');

            const div = document.createElement('div');
            div.className = 'py-4 flex items-center hover:bg-gray-50 rounded';

            let durationText = '';
            if (item.duration) {
                const minutes = Math.floor(item.duration / 60);
                const seconds = item.duration % 60;
                durationText = `${minutes}分${seconds}秒`;
            }

            div.innerHTML = `
      <div class="mr-4 flex-shrink-0">
        <svg class="h-10 w-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-900 truncate">${item.file_name || '名称なし'}</p>
        <p class="text-sm text-gray-500">${formattedDate} ${durationText ? `・ ${durationText}` : ''}</p>
      </div>
      <div>
        <button class="load-history-btn px-3 py-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded text-sm" data-id="${item.id}">
          読み込む
        </button>
      </div>
    `;

            // 読み込みボタンのイベントを追加
            const loadBtn = div.querySelector('.load-history-btn');
            loadBtn.addEventListener('click', () => {
                loadTranscriptionData(item.id);
            });

            return div;
        }

        // 選択した履歴データを読み込む関数
        function loadTranscriptionData(id) {
            // ローディング表示
            historySection.classList.add('hidden');
            processingContainer.classList.remove('hidden');
            form.parentNode.parentNode.classList.add('hidden');

            // APIリクエスト
            fetch(`api.php?load_transcription=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('APIレスポンスエラー');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // 過去のデータを表示
                        processingContainer.classList.add('hidden');
                        resultsContainer.classList.remove('hidden');

                        // グローバル変数に結果を保存
                        currentResults = data.data;

                        // 結果を表示
                        displayResults(data.data);
                    } else {
                        throw new Error(data.error || 'データの読み込みに失敗しました');
                    }
                })
                .catch(error => {
                    processingContainer.classList.add('hidden');
                    showToast('エラー: ' + error.message);
                    form.parentNode.parentNode.classList.remove('hidden');
                });
        }
    });
</script>
</body>
</html>