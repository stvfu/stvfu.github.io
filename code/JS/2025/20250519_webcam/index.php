<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>鏡頭影像處理</title>
    <style>
        body {
            display: flex;
            justify-content: space-between;
            padding: 20px;
        }
        .left, .right {
            width: 45%;
        }
        video, canvas {
            display: block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="left">
        <video id="video" width="320" height="240" autoplay></video>
        <canvas id="grayCanvas" width="320" height="240"></canvas>
        <canvas id="binaryCanvas" width="320" height="240"></canvas>
    </div>
    <div class="right">
        <button id="startButton">開啟鏡頭</button>
        <label for="threshold">二值化程度: <span id="thresholdValue">128</span></label>
        <input type="range" id="threshold" min="0" max="255" value="128">
    </div>

    <script>
        const video = document.getElementById('video');
        const grayCanvas = document.getElementById('grayCanvas');
        const binaryCanvas = document.getElementById('binaryCanvas');
        const startButton = document.getElementById('startButton');
        const thresholdInput = document.getElementById('threshold');
        const thresholdValue = document.getElementById('thresholdValue');
        const grayContext = grayCanvas.getContext('2d');
        const binaryContext = binaryCanvas.getContext('2d');

        startButton.addEventListener('click', () => {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    video.srcObject = stream;
                })
                .catch(err => {
                    console.error('Error accessing webcam: ', err);
                    alert('無法訪問攝像頭，請檢查瀏覽器權限和設置。');
                });
        });

        video.addEventListener('play', () => {
            function draw() {
                grayContext.drawImage(video, 0, 0, grayCanvas.width, grayCanvas.height);
                binaryContext.drawImage(video, 0, 0, binaryCanvas.width, binaryCanvas.height);

                const grayImageData = grayContext.getImageData(0, 0, grayCanvas.width, grayCanvas.height);
                const binaryImageData = binaryContext.getImageData(0, 0, binaryCanvas.width, binaryCanvas.height);

                for (let i = 0; i < grayImageData.data.length; i += 4) {
                    const r = grayImageData.data[i];
                    const g = grayImageData.data[i + 1];
                    const b = grayImageData.data[i + 2];
                    const gray = 0.299 * r + 0.587 * g + 0.114 * b;

                    grayImageData.data[i] = gray;
                    grayImageData.data[i + 1] = gray;
                    grayImageData.data[i + 2] = gray;

                    const threshold = parseInt(thresholdInput.value);
                    const binary = gray >= threshold ? 255 : 0;

                    binaryImageData.data[i] = binary;
                    binaryImageData.data[i + 1] = binary;
                    binaryImageData.data[i + 2] = binary;
                }

                grayContext.putImageData(grayImageData, 0, 0);
                binaryContext.putImageData(binaryImageData, 0, 0);

                requestAnimationFrame(draw);
            }

            draw();
        });

        thresholdInput.addEventListener('input', () => {
            thresholdValue.textContent = thresholdInput.value;
        });
    </script>
</body>
</html>