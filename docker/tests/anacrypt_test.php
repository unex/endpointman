<?php
/**
 * Test the anacrypt path check and the Aastra driver that uses it.
 *
 * Run it in the php:8.2-cli image, from the root of the repository:
 *
 *   docker run --rm -v "$PWD":/app:ro -w /app php:8.2-cli \
 *       php docker/tests/anacrypt_test.php
 *
 * The check refuses each file that the current user can write, and root can
 * write each file. Thus the script makes its files as root, and then runs
 * the checks again as www-data.
 *
 * A shell script takes the place of anacrypt. It writes security.tuz for -i,
 * and <name>.tuz for a file, in its working directory.
 *
 * Exit status 0 means that all checks passed.
 */

if (!function_exists('_')) {
    function _($s) { return $s; }
}

require __DIR__ . '/../../lib/epm_system.class.php';
require __DIR__ . '/../../lib/provisioner/endpoint/base.php';
require __DIR__ . '/../../lib/provisioner/endpoint/aastra/base.php';

use FreePBX\modules\epm_system;

$base = '/tmp/epm-anacrypt-test';

if (posix_getuid() === 0) {
    exec('rm -rf ' . escapeshellarg($base));
    $fake = "#!/bin/sh\n"
          . "if [ \"\$1\" = \"-i\" ]; then printf 'SEC:%s' \"\$3\" > security.tuz; exit 0; fi\n"
          . "b=\$(basename \"\$1\"); b=\${b%.*}\n"
          . "{ printf 'ENC:%s:' \"\$3\"; cat \"\$1\"; } > \"\$b.tuz\"\n";
    foreach (array('bin', 'spaced dir', 'open', 'data/endpoint/aastra') as $d) {
        mkdir("$base/$d", 0755, true);
    }
    chmod("$base/open", 0777);
    foreach (array("bin/anacrypt", "spaced dir/it's anacrypt", 'open/anacrypt', 'data/endpoint/aastra/anacrypt', 'bin/world') as $f) {
        file_put_contents("$base/$f", $fake);
        chmod("$base/$f", 0755);
    }
    chmod("$base/bin/world", 0777);
    file_put_contents("$base/bin/noexec", $fake);
    chmod("$base/bin/noexec", 0644);
    symlink("$base/data/endpoint/aastra/anacrypt", "$base/bin/link");
    symlink("$base/bin/anacrypt", "$base/bin/goodlink");

    passthru('su -s /bin/sh www-data -c ' . escapeshellarg('php ' . escapeshellarg(__FILE__)), $rc);
    exec('rm -rf ' . escapeshellarg($base));
    exit($rc);
}

$failures = 0;
$checks = 0;
function check($ok, $label, $detail = '') {
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . ((!$ok && $detail !== '') ? "\n      $detail" : '') . "\n";
    if (!$ok) {
        $failures++;
    }
}

$data = "$base/data/";
$cases = array(
    array("$base/bin/anacrypt", '', 'accept a root-owned program'),
    array("$base/spaced dir/it's anacrypt", '', 'accept a path with a space and a quote'),
    array("$base/bin/goodlink", '', 'accept a symlink to a good program'),
    array('', 'No path', 'refuse an empty path'),
    array('bin/anacrypt', 'absolute', 'refuse a relative path'),
    array("$base/bin/missing", 'not a file', 'refuse a missing file'),
    array("$base/bin", 'not a file', 'refuse a directory'),
    array("$base/bin/noexec", 'not executable', 'refuse a file that is not executable'),
    array("$base/data/endpoint/aastra/anacrypt", '_ep_phone_modules', 'refuse a program in the data tree'),
    array("$base/bin/link", '_ep_phone_modules', 'refuse a symlink into the data tree'),
    array("$base/open/anacrypt", 'can write', 'refuse a program in a directory that www-data can write'),
    array("$base/bin/world", 'can write', 'refuse a program that www-data can write'),
);
foreach ($cases as $c) {
    $problem = epm_system::anacrypt_problem($c[0], $data);
    $ok = ($c[1] === '') ? ($problem === '') : (strpos($problem, $c[1]) !== false);
    check($ok, $c[2], "problem: '$problem'");
}

// The driver runs the program in a private directory and escapes the key.
$key = "k'e\"y\$(id)`id`";
$driver = new endpoint_aastra_base();
$driver->root_dir = $data;
$driver->mac = '0011AABBCCDD';
$driver->anacrypt_location = "$base/spaced dir/it's anacrypt";
$driver->anacrypt_key = $key;
check($driver->enable_encryption() === true, 'enable encryption with a good program');
$out = $driver->encrypt_files(array('$mac.cfg' => "sip line1 auth name: 1001\n"));
check(is_array($out)
    && ($out['security.tuz'] ?? null) === "SEC:$key"
    && ($out['0011AABBCCDD.tuz'] ?? null) === "ENC:$key:sip line1 auth name: 1001\n",
    'encrypt a file and pass the key as one literal argument', var_export($out, true));
check(empty(glob(sys_get_temp_dir() . '/epm_anacrypt_*')), 'remove the private directory');
check(!file_exists("$data/endpoint/aastra/security.tuz") && !is_dir("$data/endpoint/aastra/secure_temp"), 'write nothing into the data tree');

$driver->anacrypt_location = "$base/data/endpoint/aastra/anacrypt";
check($driver->enable_encryption() === false && $driver->encrypt_files(array('$mac.cfg' => 'x')) === false, 'never run the program in the data tree');

$driver->anacrypt_location = '';
check($driver->encrypt_files(array('$mac.cfg' => 'x')) === false, 'never run a program when the path is empty');

echo "\n$checks checks, $failures failed\n";
exit($failures === 0 ? 0 : 1);
