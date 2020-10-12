# p0wny@shell:~# -- Single-file PHP Shell

p0wny@shell:~# is a very basic, single-file, PHP shell. It can be used to quickly execute commands on a server when pentesting a PHP application. Use it with caution: this script represents a security risk for the server.

**Features:**

* Command history (using arrow keys `↑` `↓`)
* Auto-completion of command and file names (using `Tab` key)
* Navigate on the remote file-system (using `cd` command)
* Upload a file to the server (using `upload <destination_file_name>` command)
* Download a file from the server (using `download <file_name>` command)

**WARNING:** THIS SCRIPT IS A SECURITY HOLE. **DO NOT** UPLOAD IT ON A SERVER UNTIL YOU KNOW WHAT YOU ARE DOING!

![Screenshot](./screenshot.png)


**Demo with Docker:**

        docker build -t p0wny .
        docker run -it -p 8080:80 -d p0wny
        # open with your browser http://127.0.0.1:8080/shell.php

## Changelog

* **2019-06-07:** Adds the `clear` command to clear the terminal (@izharaazmi #12)
* **2018-12-15:** File upload and download feature (@Oshawk #5)
* **2018-06-01:**
  * Auto-completion of command and file names (@lo001 #2)
  * Adaptation to mobile devices (responsive) (@lo001 #2)
  * Improved handling of stderr (@lo001 #2)
* **2018-05-30:**
  * ES5 compatibility (@lo00l #1)
  * Dependency to JQuery removed (@lo00l #1)
  * Command history using arrow keys (@lo00l #1)
  * Keep the command field focused when pressing the tab key
* **2017-10-30:** CSS: invalid color fixed
* **2016-11-10:** Initial release
