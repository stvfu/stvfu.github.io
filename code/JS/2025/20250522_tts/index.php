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
        textarea {
            width: 80%;
            height: 100px;
            padding: 10px;
            font-size: 16px;
            margin-bottom: 20px;
        }
        select {
            width: 300px;
            padding: 10px;
            font-size: 16px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            margin: 10px;
            cursor: pointer;
        }
        .section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <h1>語音合成</h1>
    <textarea id="textInput" placeholder="輸入文字"></textarea>
    <div class="section">
        <h2>中文</h2>
        <select id="voiceSelectZh"></select>
        <button onclick="synthesizeSpeech('zh-TW', 'voiceSelectZh')">中文語音合成</button>
    </div>
    <div class="section">
        <h2>英文</h2>
        <select id="voiceSelectEn"></select>
        <button onclick="synthesizeSpeech('en-US', 'voiceSelectEn')">英文語音合成</button>
    </div>
    <div class="section">
        <h2>日文</h2>
        <select id="voiceSelectJa"></select>
        <button onclick="synthesizeSpeech('ja-JP', 'voiceSelectJa')">日文語音合成</button>
    </div>

    <script>
        const synth = window.speechSynthesis;

        // 等待語音引擎載入完成後初始化聲線選單
        window.speechSynthesis.onvoiceschanged = () => {
            const voices = synth.getVoices();
            populateVoiceOptions('zh-TW', 'voiceSelectZh', voices);
            populateVoiceOptions('en-US', 'voiceSelectEn', voices);
            populateVoiceOptions('ja-JP', 'voiceSelectJa', voices);
        };

        // 將可用的聲線加入到指定的下拉選單
        function populateVoiceOptions(language, selectId, voices) {
            const select = document.getElementById(selectId);
            select.innerHTML = ''; // 清空選單
            let foundGoogleVoice = false;
            voices
                .filter(voice => voice.lang === language)
                .forEach(voice => {
                    const option = document.createElement('option');
                    option.value = voice.name;
                    option.textContent = voice.name;
                    select.appendChild(option);
                    if (voice.name.includes("Google")) {
                        option.selected = true;
                        foundGoogleVoice = true;
                    }
                });
            if (!foundGoogleVoice && select.options.length > 0) {
                select.options[0].selected = true; // 如果沒有 Google 聲線，選擇第一個
            }
        }

        // 語音合成功能
        function synthesizeSpeech(language, selectId) {
            const text = document.getElementById('textInput').value;
            if (!text) {
                alert('請輸入文字！');
                return;
            }

            const select = document.getElementById(selectId);
            const selectedVoiceName = select.value;

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = language;

            // 設定選擇的聲線
            const voices = synth.getVoices();
            const selectedVoice = voices.find(voice => voice.name === selectedVoiceName);
            if (selectedVoice) {
                utterance.voice = selectedVoice;
            }

            synth.speak(utterance);
        }
    </script>
</body>
</html>