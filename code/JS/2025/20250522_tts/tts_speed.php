<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>語音合成</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
        }
        textarea, select, input[type=range], button {
            width: 300px;
            padding: 10px;
            margin: 10px 0;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        label {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>語音合成</h1>
    <textarea id="textInput" placeholder="輸入文字"></textarea>
    <label for="rate">語速 (0.1 - 10):</label>
    <input type="range" id="rate" min="0.1" max="10" value="1" step="0.1">
    <label for="pitch">音調 (0 - 2):</label>
    <input type="range" id="pitch" min="0" max="2" value="1" step="0.1">
    <label for="volume">音量 (0 - 1):</label>
    <input type="range" id="volume" min="0" max="1" value="1" step="0.1">
    <button onclick="synthesizeSpeech()">合成語音</button>

    <script>
        const synth = window.speechSynthesis;

        function synthesizeSpeech() {
            const text = document.getElementById('textInput').value;
            if (!text) {
                alert('請輸入文字！');
                return;
            }

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.voice = synth.getVoices().find(voice => voice.lang.startsWith('zh-TW')); // 預設選擇中文聲線

            // 從滑動條獲取參數
            utterance.rate = parseFloat(document.getElementById('rate').value);
            utterance.pitch = parseFloat(document.getElementById('pitch').value);
            utterance.volume = parseFloat(document.getElementById('volume').value);

            synth.speak(utterance);
        }
    </script>
</body>
</html>