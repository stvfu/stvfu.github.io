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
                flex-direction: column; /* Stack elements vertically */
            }
            #control-panel {
                height: 40px; /* Set the height of the control panel */
                padding: 10px;
                background: #333;
                color: #fff;
                display: flex;
                align-items: center;
            }
            #main-container {
                display: flex;
                flex-direction: row; /* Side by side elements for the main container */
                height: calc(100vh - 100px); /* Subtract the height of the control panel */
            }
            #editor-container {
                width: 70%; /* Set the width of the editor container */
                padding: 10px;
                overflow-y: auto;
            }
            #file-browser {
                width: 30%; /* Set the width of the file browser */
                padding: 10px;
                overflow-y: auto;
                border-left: 1px solid #ccc;
            }
            .CodeMirror {
                height: 100%; /* Fill the height of its container */
                width: 100%; /* Fill the width of its container */
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
                    return; // cancel
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
                    alert(data); // save res
                })
                .catch(error => console.error('Error:', error));
            }

            function listFiles() {
                fetch('list_files.php')
                .then(response => response.json())
                .then(data => {
                    const fileBrowser = document.getElementById('file-browser');
                    fileBrowser.innerHTML = ''; // Clear previous content
                    data.forEach(item => {
                        const entry = document.createElement('div');
                        entry.textContent = item;
                        fileBrowser.appendChild(entry);
                    });
                })
                .catch(error => console.error('Error:', error));
            }

	    function convertTabsToSpaces() {
                var code = editor.getValue();
                var spaces = "    "; // Define the number of spaces for each tab
                var newCode = code.replace(/\t/g, spaces);
                editor.setValue(newCode);
            }

            function removeTrailingSpaces() {
                var code = editor.getValue();
                var newCode = code.replace(/[ \t]+$/gm, '');
                editor.setValue(newCode);
            }

            // Call listFiles on page load
            window.onload = listFiles;
        </script>
    </body>
</html>

