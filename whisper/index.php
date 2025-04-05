<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Whisper 文字起こしテスト</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center mb-8">Whisper 文字起こしテスト</h1>

  <div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
    <form action="api.php" method="post" enctype="multipart/form-data" class="space-y-4">
      <div>
        <label for="audio_file" class="block text-sm font-medium text-gray-700 mb-1">音声ファイル</label>
        <input type="file" id="audio_file" name="audio_file" accept=".mp3,.wav,.m4a,.ogg"
               class="block w-full text-sm text-gray-500
                                 file:mr-4 file:py-2 file:px-4
                                 file:rounded-md file:border-0
                                 file:text-sm file:font-semibold
                                 file:bg-blue-50 file:text-blue-700
                                 hover:file:bg-blue-100" required>
        <p class="mt-1 text-sm text-gray-500">MP3, WAV, M4A, OGG形式（最大500MB）</p>
      </div>

      <div>
        <label for="model_size" class="block text-sm font-medium text-gray-700 mb-1">モデルサイズ</label>
        <select id="model_size" name="options[model_size]"
                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300
                                   focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
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
                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300
                                   focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
          <option value="ja" selected>日本語</option>
          <option value="en">英語</option>
          <option value="zh">中国語</option>
          <option value="ko">韓国語</option>
          <option value="fr">フランス語</option>
          <option value="de">ドイツ語</option>
          <option value="es">スペイン語</option>
        </select>
      </div>

      <div class="pt-2">
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
          文字起こしを実行
        </button>
      </div>
    </form>
  </div>

  <div class="mt-8 text-center text-sm text-gray-600">
    <p>このフォームはAPIのテスト用です。本番環境では適切なUIを実装してください。</p>
    <p class="mt-2">処理には数分かかる場合があります。大きなファイルの場合はタイムアウトする可能性があります。</p>
  </div>
</div>

<script>
    // フォーム送信時の処理
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();

        // 送信ボタンを無効化
        const submitButton = document.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = '処理中...';
        submitButton.classList.add('bg-blue-400');
        submitButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');

        // 処理中メッセージを表示
        const processingDiv = document.createElement('div');
        processingDiv.className = 'mt-4 p-3 bg-blue-50 text-blue-700 rounded';
        processingDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>文字起こし処理を実行中です。この処理には数分かかる場合があります。ブラウザを閉じないでください。</span>
                </div>
            `;
        document.querySelector('form').appendChild(processingDiv);

        // フォームを送信
        fetch('api.php', {
            method: 'POST',
            body: new FormData(this)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('APIリクエストに失敗しました');
                }
                return response.json();
            })
            .then(data => {
                // 処理結果を表示
                const resultDiv = document.createElement('div');
                resultDiv.className = 'mt-6 p-4 bg-white rounded-lg shadow-md';

                if (!data.error) {
                    resultDiv.innerHTML = `
                        <h2 class="text-lg font-semibold text-gray-800 mb-2">文字起こし結果</h2>
                        <div class="mb-3">
                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">言語: ${data.language}</span>
                            <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">処理時間: ${data.processing_time}秒</span>
                        </div>

                        <div class="mt-4">
                            <textarea class="w-full h-64 p-3 border border-gray-300 rounded font-mono text-sm" readonly>${data.text}</textarea>
                        </div>

                        <div class="mt-4">
                            <h3 class="text-md font-medium text-gray-700 mb-2">セグメント情報</h3>
                            <div class="overflow-auto max-h-64">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left">開始</th>
                                            <th class="px-3 py-2 text-left">終了</th>
                                            <th class="px-3 py-2 text-left">テキスト</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${data.segments.map(segment => `
                                            <tr>
                                                <td class="px-3 py-2">${segment.start.toFixed(2)}秒</td>
                                                <td class="px-3 py-2">${segment.end.toFixed(2)}秒</td>
                                                <td class="px-3 py-2">${segment.text}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <h2 class="text-lg font-semibold text-red-600 mb-2">エラーが発生しました</h2>
                        <p class="text-gray-700">${data.error || 'APIリクエスト中に不明なエラーが発生しました。'}</p>
                    `;
                }

                // 結果を表示
                const formContainer = document.querySelector('form').parentNode;
                formContainer.parentNode.insertBefore(resultDiv, formContainer.nextSibling);

                // 処理中の表示を削除
                processingDiv.remove();

                // 送信ボタンを再有効化
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                submitButton.classList.remove('bg-blue-400');
                submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            })
            .catch(error => {
                // エラー表示
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mt-4 p-3 bg-red-50 text-red-700 rounded';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-3 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>${error.message}</span>
                    </div>
                `;

                // 処理中の表示を削除
                processingDiv.remove();
                document.querySelector('form').appendChild(errorDiv);

                // 送信ボタンを再有効化
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                submitButton.classList.remove('bg-blue-400');
                submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            });
    });
</script>
</body>
</html>