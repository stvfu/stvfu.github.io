<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Text Editor with File Browser and Dark Theme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        #control-panel {
            height: 40px;
            padding: 10px;
            background: #333;
            color: #fff;
            display: flex;
            align-items: center;
        }
        #main-container {
            display: flex;
            flex-direction: row;
            height: calc(100vh - 100px);
        }
        #editor-container {
            width: 70%;
            padding: 10px;
            overflow-y: auto;
        }
        #file-browser {
            width: 30%;
            padding: 10px;
            overflow-y: auto;
            border-left: 1px solid #ccc;
        }
        .CodeMirror {
            height: 100%;
            width: 100%;
        }
        .file-entry {
            cursor: pointer;
            padding: 5px;
        }
        .file-entry:hover {
            background-color: #ddd;
        }
    </style>
</head>
<body>
    <div id="control-panel">
        <input type="text" id="textbox_file" placeholder="Enter file name...">
        <button onclick="loadFile()">Load</button>
        <button onclick="saveFile()">Save</button>
        <button onclick="convertTabsToSpaces()">Tab to Space</button>
        <button onclick="removeTrailingSpaces()">No tail Space</button>
    </div>
    <div id="main-container">
        <div id="editor-container">
            <textarea id="textbox_code"></textarea>
        </div>
        <div id="file-browser">
            <!-- File browser will be populated here -->
        </div>
    </div>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("textbox_code"), {
            lineNumbers: true,
            mode: "text/x-csrc",
            theme: "monokai"
        });

        function loadFile() {
            var fileName = document.getElementById('textbox_file').value;
            if (fileName.indexOf('..') !== -1) {
                alert('Invalid file name.');
                return;
            }
            fetch('load_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'filename=' + encodeURIComponent(fileName)
            })
            .then(response => response.text())
            .then(data => {
                editor.setValue(data);
            })
            .catch(error => console.error('Error:', error));
        }

        function saveFile() {
            var confirmSave = confirm("Are you sure you want to save the file?");
            if (!confirmSave) {
                return;
            }

            var fileName = document.getElementById('textbox_file').value;
            if (fileName.indexOf('..') !== -1) {
                alert('Invalid file name.');
                return;
            }
            var fileContent = editor.getValue();
            fetch('save_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'filename=' + encodeURIComponent(fileName) + '&content=' + encodeURIComponent(fileContent)
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
            })
            .catch(error => console.error('Error:', error));
        }

        function listFiles(dir = '') {
            fetch('list_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'dir=' + encodeURIComponent(dir)
            })
            .then(response => response.json())
            .then(data => {
                const fileBrowser = document.getElementById('file-browser');
                fileBrowser.innerHTML = '';
                if (data.error) {
                    alert(data.error);
                    return;
                }
                // Add a link to go up one directory level
                if (dir !== '') {
                    const upEntry = document.createElement('div');
                    upEntry.textContent = '../';
                    upEntry.className = 'file-entry';
                    upEntry.onclick = () => {
                        const parentDir = dir.split('/').slice(0, -1).join('/');
                        listFiles(parentDir);
                    };
                    fileBrowser.appendChild(upEntry);
                }
                data.forEach(item => {
                    const entry = document.createElement('div');
                    entry.textContent = item.isDir ? item.name + '/' : item.name;
                    entry.className = 'file-entry';
                    entry.onclick = () => {
                        if (item.isDir) {
                            listFiles(item.path);
                        } else {
                            document.getElementById('textbox_file').value = item.path;
                            loadFile();
                        }
                    };
                    fileBrowser.appendChild(entry);
                });
            })
            .catch(error => console.error('Error:', error));
        }

        function convertTabsToSpaces() {
            var code = editor.getValue();
            var spaces = "    ";
            var newCode = code.replace(/\t/g, spaces);
            editor.setValue(newCode);
        }

        function removeTrailingSpaces() {
            var code = editor.getValue();
            var newCode = code.replace(/[ \t]+$/gm, '');
            editor.setValue(newCode);
        }

        window.onload = () => listFiles();
    </script>
</body>
</html>