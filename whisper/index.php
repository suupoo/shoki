<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>メモ助 - 議事録自動生成システム</title>
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
    <h1 class="text-4xl font-bold text-gray-800">メモ<span class="text-indigo-600">助</span></h1>
    <p class="text-gray-600 mt-2">AIが議事録を自動生成・要約します</p>
  </header>

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
                <option value="small">small（中程度）</option>
                <option value="medium" selected>medium（標準）</option>
                <option value="large">large（高精度・高負荷）</option>
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

          <!-- 要約オプション -->
          <div class="flex items-center">
            <input type="checkbox" id="summarize" name="options[summarize]" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
            <label for="summarize" class="ml-2 block text-sm text-gray-700">
              自動要約機能を有効にする（AI要約を生成）
            </label>
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
          <button type="button" data-tab="summary" class="result-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
            要約
          </button>
          <button type="button" data-tab="segments" class="result-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
            セグメント
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

        <!-- 要約結果 -->
        <div id="summaryTab" class="result-content hidden">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-medium text-gray-900">議事録要約</h2>
              <div class="flex space-x-2">
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
            <div id="summaryContent" class="prose max-w-none p-3 border border-gray-300 rounded bg-gray-50 min-h-[200px]"></div>
            <div id="summaryNotAvailable" class="hidden text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">要約は利用できません</h3>
              <p class="mt-1 text-sm text-gray-500">文字起こし時に「自動要約機能」を有効にしてください。</p>
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
      <p>© 2025 メモ助 - 議事録自動生成システム</p>
      <p class="mt-1">OpenAI Whisper および rinna/japanese-gpt-neox-3.6bモデルを使用しています</p>
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

        // 結果タブ関連の要素
        const resultTabs = document.querySelectorAll('.result-tab');
        const resultContents = document.querySelectorAll('.result-content');
        const transcriptionText = document.getElementById('transcriptionText');
        const transcriptionInfo = document.getElementById('transcriptionInfo');
        const summaryContent = document.getElementById('summaryContent');
        const summaryNotAvailable = document.getElementById('summaryNotAvailable');
        const segmentsTableBody = document.getElementById('segmentsTableBody');

        // コピー・ダウンロードボタン
        const copyTranscription = document.getElementById('copyTranscription');
        const downloadTranscription = document.getElementById('downloadTranscription');
        const copySummary = document.getElementById('copySummary');
        const downloadSummary = document.getElementById('downloadSummary');
        const exportSegments = document.getElementById('exportSegments');
        const downloadAllBtn = document.getElementById('downloadAllBtn');

        // グローバル変数
        let currentResults = null;

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
                        if (document.getElementById('summarize').checked) {
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

            // 要約結果
            if (data.summary) {
                summaryContent.innerHTML = formatMarkdown(data.summary);
                summaryContent.classList.remove('hidden');
                summaryNotAvailable.classList.add('hidden');
            } else {
                summaryContent.classList.add('hidden');
                summaryNotAvailable.classList.remove('hidden');
            }

            // Markdownファイルがある場合
            if (data.markdown_file) {
                const downloadSummaryBtn = document.getElementById('downloadSummary');
                downloadSummaryBtn.setAttribute('data-file', data.markdown_file);
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
            // 箇条書きの処理
            let formatted = text.replace(/^- (.+)$/gm, '<li>$1</li>');
            if (formatted.includes('<li>')) {
                formatted = '<ul class="list-disc pl-5 space-y-1">' + formatted + '</ul>';
            }

            // 改行の処理
            formatted = formatted.replace(/\n\n/g, '<br><br>');

            return formatted;
        }

        // コピーボタンの処理
        copyTranscription.addEventListener('click', () => {
            transcriptionText.select();
            document.execCommand('copy');
            showToast('文字起こし結果をコピーしました');
        });

        copySummary.addEventListener('click', () => {
            const textToCopy = summaryContent.innerText;
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('要約をコピーしました');
        });

        // ダウンロードボタンの処理
        downloadTranscription.addEventListener('click', () => {
            if (currentResults && currentResults.text) {
                downloadText(currentResults.text, 'transcription.txt');
            }
        });

        downloadSummary.addEventListener('click', () => {
            if (currentResults && currentResults.summary) {
                const markdownContent = currentResults.markdown || currentResults.summary;
                downloadText(markdownContent, 'summary.md');
            }
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
    });
</script>
</body>
</html>