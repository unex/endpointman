<?php
namespace FreePBX\modules;

class epm_system {
    /**
     * Fixes the display are special strings so we can visible see them instead of them being transformed
     * @param string $contents a string of course
     * @return string fixed string
     */
    function display_htmlspecialchars($contents) {
        $contents = str_replace("&amp;", "&amp;amp;", $contents);
        $contents = str_replace("&lt;", "&amp;lt;", $contents);
        $contents = str_replace("&gt;", "&amp;gt;", $contents);
        $contents = str_replace("&quot;", "&amp;quot;", $contents);
        $contents = str_replace("&#039;", "&amp;#039;", $contents);
        return($contents);
    }
    /**
     * Does a TFTP Check by connecting to $host looking for $filename
     * @author http://www.php.net/manual/en/function.socket-create.php#43057
     * @param string $host
     * @param string $filename
     * @return mixed file contents
     */
    function tftp_fetch($host, $filename) {
        //first off let's check if this is installed or disabled
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        // create the request packet
        $packet = chr(0) . chr(1) . $filename . chr(0) . 'octet' . chr(0);
        // UDP is connectionless, so we just send on it.
        socket_sendto($socket, $packet, strlen($packet), 0x100, $host, 69);

        $buffer = '';
        $port = '';
        $ret = '';
        $time = time();
        do {
            $new_time = time() - $time;
            if ($new_time > 5) {
                break;
            }
            // $buffer and $port both come back with information for the ack
            // 516 = 4 bytes for the header + 512 bytes of data
            socket_recvfrom($socket, $buffer, 516, 0, $host, $port);

            // add the block number from the data packet to the ack packet
            $packet = chr(0) . chr(4) . substr($buffer, 2, 2);
            // send ack
            socket_sendto($socket, $packet, strlen($packet), 0, $host, $port);

            // append the data to the return variable
            // for large files this function should take a file handle as an arg
            $ret .= substr($buffer, 4);
        } while (strlen($buffer) == 516);  // the first non-full packet is the last.
        return $ret;
    }

    /**
     * The RecursiveIteratorIterator must be told to provide children (files and subdirectories) before parents with its CHILD_FIRST constant.
     * Using RecursiveIteratorIterator is the only way PHP is able to see hidden files.
     * @author http://www.webcheatsheet.com/PHP/working_with_directories.php
     * @param string $dir Full Directory path to delete
     * @version 2.11
     */
    function rmrf($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        // SKIP_DOTS: without it, the iterator returns "." and ".." for each
        // directory. rmdir() on these gives a warning for each entry, and FreePBX
        // turns each warning into an exception.
        $iterator = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ok = true;
        foreach (new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) {
                $ok = @rmdir($file->getPathname()) && $ok;
            } else {
                $ok = @unlink($file->getPathname()) && $ok;
            }
        }
        //Remove parent path as the last step
        $ok = @rmdir($dir) && $ok;

        // Return the error. Do not hide it. If the web server cannot delete the files
        // of a brand, a removal must not report success. Otherwise the next install
        // fails on the old directory with an error about the package server, not
        // about permissions.
        return $ok;
    }

    /**
    * Uses which to find executables that asterisk can run/use
    * @version 2.11
    * @param string $exec Executable to find
    * @package epm_system
    */
    function find_exec($exec) {
        $o = exec('which '.$exec);
        if($o) {
            if(file_exists($o) && is_executable($o)) {
                return($o);
            } else {
                return('');
            }
        } else {
            return('');
        }
    }

    /**
    * Find out if a process listens on a UDP port.
    *
    * Use `ss` from iproute2 first, and `netstat` when `ss` is not available.
    * Debian 12, and thus FreePBX 17, does not install net-tools.
    *
    * Both tools print the local address and then whitespace: `0.0.0.0:69   `
    * from ss, `0.0.0.0:69` from netstat. Thus one pattern reads both.
    *
    * @param int $port UDP port to look for
    * @return bool|null TRUE listening, FALSE not, NULL when neither tool is
    *                   installed
    * @package epm_system
    */
    function udp_port_listening($port) {
        foreach (array('ss' => '-luan', 'netstat' => '-luan --numeric-ports') as $exec => $args) {
            $bin = $this->find_exec($exec);
            if ($bin === '') {
                continue;
            }
            $out = shell_exec(escapeshellcmd($bin) . ' ' . $args . ' 2>/dev/null');
            // NULL means that the command did not run. Only a string is an answer.
            if (!is_string($out)) {
                continue;
            }
            return (bool) preg_match('/:' . ((int) $port) . '\s/', $out);
        }
        return NULL;
    }

    /**
    * Get the IPv4 neighbor table of the host, as a map of IP address to MAC
    * address.
    *
    * Use `ip neigh show` from iproute2 first, and `arp -an` when `ip` is not
    * available. Debian 12 does not install net-tools. The lines of the two tools
    * have the same two values in a different order:
    *
    *   ip:   192.168.1.5 dev eth0 lladdr 00:15:65:aa:bb:cc REACHABLE
    *   arp:  ? (192.168.1.5) at 00:15:65:aa:bb:cc [ether] on eth0
    *
    * Thus the function reads the first IPv4 address and the first MAC of each
    * line. A line without a MAC (arp <incomplete>, ip FAILED) has no device, so
    * the function skips it.
    *
    * @param string $exec Configured path to use first, if it is executable
    * @return array|null ip => mac, empty when the table is empty, NULL when
    *                    neither `ip` nor `arp` is installed
    * @package epm_system
    */
    function neighbour_table($exec = '') {
        $candidates = array();
        if (($exec !== '') && file_exists($exec) && is_executable($exec)) {
            $candidates[] = $exec;
        }
        foreach (array('ip', 'arp') as $name) {
            $bin = $this->find_exec($name);
            if ($bin !== '') {
                $candidates[] = $bin;
            }
        }

        foreach ($candidates as $bin) {
            $args = (basename($bin) === 'ip') ? 'neigh show' : '-an';
            $out = shell_exec(escapeshellcmd($bin) . ' ' . $args . ' 2>/dev/null');
            if (!is_string($out)) {
                continue;
            }
            $table = array();
            foreach (explode("\n", $out) as $line) {
                if (!preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $line, $ip)) {
                    continue;
                }
                if (!preg_match('/([0-9a-f]{2}(?::[0-9a-f]{2}){5})/i', $line, $mac)) {
                    continue;
                }
                $table[$ip[1]] = strtolower($mac[1]);
            }
            // The first tool that runs gives the answer, also when its table is empty.
            // An empty neighbor table is a valid result, not a failure.
            return $table;
        }
        return NULL;
    }

    /**
    * Downloads a file and places it in the destination defined
    * @version 2.11
    * @param string $url_file URL of File
    * @param string $destination_file Destination of file
    * @package epm_system
    */
    function download_file($url_file, $destination_file, &$error = array()) {
			$dir = dirname($destination_file);
			if(!file_exists($dir)) {
				mkdir($dir);
			}
        //Determine if file_get_contents_url exists which is the default FreePBX Standard for downloading straight files
        if(function_exists('file_get_contents_url')) {
            $contents = file_get_contents_url($url_file);
        } else {
            //I really hope we NEVER get here.
            $contents = file_get_contents($url_file);
            if (!preg_match('/200/', $http_response_header[0])) {
                $error['download_file'] = "Unknown Error in Download_file";
                return false;
            }
        }
        //If contents are emtpy then we failed. Or something is wrong
        if(!empty($contents)) {
            $dirname = dirname($destination_file);
            if (!file_exists($dirname)) {
                mkdir($dirname);
            }
            if (!is_writable($dirname)) {
                $error['download_file'] = "Directory '" . $dirname . "' is not writable! Unable to download files";
                return false;
            }
            file_put_contents($destination_file, $contents);
            //check file placement
            if (!file_exists($destination_file)) {
                $error['download_file'] = "File Doesn't Exist in '" . $dirname . "'. Unable to download files";
                return false;
            }
            return true;
        } else {
            $error['download_file'] = "Contents of Remote file are blank! URL:".$url_file;
            return false;
        }
    }

    /**
    * Downloads a file and places it in the destination defined with progress
    * @version 2.11
    * @param string $url_file URL of File
    * @param string $destination_file Destination of file
    * @package epm_system
    */
    function download_file_with_progress_bar($url_file, $destination_file, &$error = array()) {
	    set_time_limit(0);

	    // The progress bar watches a file that wget fills in the background. Without
	    // wget there is nothing to watch. FreePBX 17 does not require wget, and
	    // minimal Debian images do not have it. Thus use the plain download, which
	    // gets the same bytes without the bar.
	    $wget = $this->find_exec('wget');
	    if ($wget === '') {
		    dbug("wget not found; falling back to download_file() for " . $url_file);
		    return $this->download_file($url_file, $destination_file, $error);
	    }

	    $headers = get_headers($url_file, 1);
	    if ($headers === false) {
		    $error['download_file'] = "Unable to read the headers of ".$url_file;
		    return false;
	    }
	    // With $associative = true, get_headers() returns an array for a header that
	    // is in more than one response, for example after a redirect. Use the last
	    // value, which is from the URL that served the file. Without Content-Length,
	    // $size stays 0 and the bar shows 0% until the end. A division by zero is a
	    // fatal error in PHP 8.
	    $status = is_array($headers[0]) ? end($headers[0]) : $headers[0];
	    $size = $headers['Content-Length'] ?? 0;
	    $size = (int) (is_array($size) ? end($size) : $size);
	    $randnumid = sprintf("%08d", mt_rand(1,99999999));

	    $dir = dirname($destination_file);
	    if(!file_exists($dir)) {
		    mkdir($dir, 0775, true);
	    }
	    // wget runs in the background and sends its output to /dev/null. A
	    // permission failure thus leaves no record, and the poll loop below never
	    // sees the file. The caller then reports "Can't Find Downloaded File!" and
	    // points to the package server, not to the directory. Check the directory
	    // here, while a useful message is still possible.
	    if (!is_writable($dir)) {
		    $error['download_file'] = sprintf(_("The web server cannot write to '%s', so it cannot download %s. Check the owner. 'fwconsole chown' sets the correct owner."), $dir, basename($destination_file));
		    return false;
	    }

	    if (preg_match('/200/', $status)) {
		    // Escape both arguments. $url_file contains the package server that the
		    // admin sets, and run_in_background() sends the full string to a shell.
		    $cmd = escapeshellcmd($wget) . " --no-cache " . escapeshellarg($url_file) . " -O " . escapeshellarg($destination_file);
		    dbug($cmd);
		    $pid = $this->run_in_background($cmd);

		    echo sprintf("<div>"._("Downloading %s ...")."</div>", basename($destination_file));
		    echo sprintf("<div id='DivProgressBar_%d' class='progress' style='width:100%%'>", $randnumid);
		    echo "<div class='progress-bar progress-bar-striped' role='progressbar' aria-valuenow='0' aria-valuemin='0' aria-valuemax='100' style='width:0%'>0% ("._("Complete").")</div>";
		    echo "</div>";
		    usleep('300');
		    while ($this->is_process_running($pid)) {
			    $done = file_exists($destination_file) ? filesize($destination_file) : 0;
			    $out  = $size > 0 ? 100 * round($done / $size, 2) : 0;
?>
			    <script type="text/javascript">
			    $('#DivProgressBar_<?php echo $randnumid; ?> .progress-bar')
				    .css('width', <?php echo $out ?>+'%')
				    .attr('aria-valuenow', <?php echo $out ?>)
				    .text("<?php echo $out ?>% (<?php echo _("Complete") ?>)");
			    </script>
<?php
			    usleep('500');
			    // fwconsole has no output buffer, and a flush of an absent buffer gives a
			    // warning.
			    if (ob_get_level() > 0) {
				    ob_end_flush();
			    }
			    flush();
			    ob_start();
			    clearstatcache(); // make sure PHP actually checks dest. file size
		    }
?>
		    <script type="text/javascript">
		    $('#DivProgressBar_<?php echo $randnumid; ?> .progress-bar').css('width', '100%').attr('aria-valuenow', '100').text("100% (<?php echo _("Success") ?>)");
		    </script>
<?php
		    return true;
	    } else {

		    echo sprintf("<div>"._("Downloading %s ...")."</div>", basename($destination_file));
		    echo "<div class='progress' style='width:100%'>";
		    echo "<div class='progress-bar progress-bar-danger progress-bar-striped' role='progressbar' aria-valuenow='100' aria-valuemin='0' aria-valuemax='100' style='width:100%'>0% ("._("Error: ").$status."!)</div>";
		    echo "</div>";

		    return false;
	    }
    }

    /**
     * Taken from http://www.php.net/manual/en/function.array-search.php#69232
     * search haystack for needle and return an array of the key path, FALSE otherwise.
     * if NeedleKey is given, return only for this key mixed ArraySearchRecursive(mixed Needle,array Haystack[,NeedleKey[,bool Strict[,array Path]]])
     * @author ob (at) babcom (dot) biz
     * @param mixed $Needle
     * @param array $Haystack
     * @param mixed $NeedleKey
     * @param bool $Strict
     * @param array $Path
     * @return array
     * @package epm_system
     */
    public function arraysearchrecursive($Needle, $Haystack, $NeedleKey="", $Strict=false, $Path=array()) {
        if (!is_array($Haystack))
            return false;
        foreach ($Haystack as $Key => $Val) {
            if (is_array($Val) &&
                    $SubPath = $this->arraysearchrecursive($Needle, $Val, $NeedleKey, $Strict, $Path)) {
                $Path = array_merge($Path, Array($Key), $SubPath);
                return $Path;
            } elseif ((!$Strict && $Val == $Needle &&
                    $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key)) ||
                    ($Strict && $Val === $Needle &&
                    $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key))) {
                $Path[] = $Key;
                return $Path;
            }
        }
        return false;
    }

    /**
    * Extract a downloaded package.
    *
    * The function escapes its arguments and checks the result of tar.
    *
    * By default, the function does not extract code. Brand tarballs contain the
    * `base.php` and `phone.php` files of the provisioner library next to the
    * templates. The module ships its own copies (see `provisioner/`) and loads
    * only those. Thus a package cannot put PHP where the module runs it, and the
    * package server supplies only data.
    *
    * @param string $archive     tarball to unpack
    * @param string $destination directory to unpack into
    * @param array  $error       set on failure
    * @param bool   $allow_code  true to extract PHP too (no caller uses this)
    * @return bool
    * @package epm_system
    */
    function extract_package($archive, $destination, &$error = array(), $allow_code = false) {
        if (!file_exists($archive)) {
            $error['extract_package'] = sprintf(_("Package '%s' does not exist."), $archive);
            return false;
        }
        if (!is_dir($destination) || !is_writable($destination)) {
            $error['extract_package'] = sprintf(_("Cannot write into '%s'."), $destination);
            return false;
        }

        $tar = $this->find_exec('tar');
        if ($tar === '') {
            $error['extract_package'] = _("Cannot find tar.");
            return false;
        }

        $exclude = '';
        if (!$allow_code) {
            foreach (array('*.php', '*.phar', '*.phtml', '*.php3', '*.php4', '*.php5', '*.php7', '*.phps', '*.inc') as $glob) {
                $exclude .= ' --exclude=' . escapeshellarg($glob);
            }
        }

        $cmd = escapeshellcmd($tar) . ' -xf ' . escapeshellarg($archive)
             . $exclude . ' -C ' . escapeshellarg($destination) . ' 2>&1';
        exec($cmd, $output, $status);
        dbug($cmd . ' -> ' . $status);

        if ($status !== 0) {
            $error['extract_package'] = sprintf(_("Extracting '%s' failed: %s"), basename($archive), implode(' ', $output));
            return false;
        }
        return true;
    }

    /**
    * Send process to run in background
    * @version 2.11
    * @param string $command the command to run
    * @param integer $Priority the Priority of the command to run
    * @return int $PID process id
    * @package epm_system
    */
    function run_in_background($Command, $Priority = 0) {
        return($Priority ? shell_exec("nohup nice -n $Priority $Command 2> /dev/null & echo $!") : shell_exec("nohup $Command > /dev/null 2> /dev/null & echo $!"));
    }

    /**
    * Check if process is running in background
    * @version 2.11
    * @param string $PID proccess ID
    * @return bool true or false
    * @package epm_system
    */
    function is_process_running($PID) {
        exec("ps $PID", $ProcessState);
        return(count($ProcessState) >= 2);
    }



	/**
	 * Get the directory for downloads.
	 *
	 * sys_get_temp_dir() gives the answer. The method stays because six callers
	 * use it.
	 *
	 * @return string temp directory, no trailing slash
	 */
	function sys_get_temp_dir() {
        return rtrim(\sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    }
}
