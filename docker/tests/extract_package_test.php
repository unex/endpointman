<?php
/**
 * Test epm_system::extract_package() without FreePBX.
 *
 * Run it in the php:8.2-cli image, from the root of the repository:
 *
 *   docker run --rm -v "$PWD":/app:ro -w /app php:8.2-cli \
 *       php docker/tests/extract_package_test.php
 *
 * The script makes each tarball itself. GNU tar makes the normal ones. A
 * small ustar writer below makes the ones that GNU tar does not make on
 * purpose: absolute paths, '..' paths and links that point anywhere.
 *
 * Exit status 0 means that all checks passed.
 */

namespace {
    if (!function_exists('_')) {
        function _($s) { return $s; }
    }
    function dbug($s) {}
}

namespace {

require __DIR__ . '/../../lib/epm_system.class.php';

use FreePBX\modules\epm_system;

$failures = 0;
$checks = 0;

function check($ok, $label, $detail = '') {
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "ok    $label\n";
    } else {
        $failures++;
        echo "FAIL  $label" . ($detail !== '' ? "\n      $detail" : '') . "\n";
    }
}

function tmpdir($tag) {
    $dir = sys_get_temp_dir() . '/epmtest-' . $tag . '-' . bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    return $dir;
}

function sh($cmd) {
    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        throw new RuntimeException("$cmd: " . implode("\n", $out));
    }
}

/**
 * One ustar header and its data.
 */
function ustar_entry($name, $flag, $data = '', $link = '') {
    $h = str_pad($name, 100, "\0");
    $h .= str_pad(sprintf('%07o', $flag === '5' ? 0755 : 0644), 8, "\0");
    $h .= str_pad(sprintf('%07o', 0), 8, "\0");
    $h .= str_pad(sprintf('%07o', 0), 8, "\0");
    $h .= str_pad(sprintf('%011o', strlen($data)), 12, "\0");
    $h .= str_pad(sprintf('%011o', 0), 12, "\0");
    $h .= '        ';
    $h .= $flag;
    $h .= str_pad($link, 100, "\0");
    $h .= "ustar\0" . '00';
    $h = str_pad($h, 512, "\0");
    $sum = array_sum(unpack('C*', $h));
    $h = substr_replace($h, sprintf('%06o', $sum) . "\0 ", 148, 8);
    $pad = (512 - strlen($data) % 512) % 512;
    return $h . $data . str_repeat("\0", $pad);
}

function ustar_tgz($path, $entries) {
    $tar = '';
    foreach ($entries as $e) {
        $tar .= ustar_entry($e[0], $e[1], $e[2] ?? '', $e[3] ?? '');
    }
    $tar .= str_repeat("\0", 1024);
    file_put_contents($path, gzencode($tar));
}

/**
 * Make a brand tree with GNU tar.
 *
 * @param array $files relative path => content
 * @param array $opts  extra tar options
 */
function gnu_tgz($path, $files, $opts = '', $compress = true) {
    $src = tmpdir('src');
    foreach ($files as $rel => $content) {
        $full = $src . '/' . $rel;
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $content);
    }
    $top = implode(' ', array_map('escapeshellarg', array_unique(array_map(function ($p) {
        return explode('/', $p)[0];
    }, array_keys($files)))));
    sh('tar ' . ($compress ? '-czf ' : '-cf ') . escapeshellarg($path) . ' ' . $opts . ' -C ' . escapeshellarg($src) . ' ' . $top);
    sh('rm -rf ' . escapeshellarg($src));
}

/**
 * Extract into a fresh directory. Return the result, the error and the
 * directory.
 */
function run_extract($archive, $dest = null) {
    $sys = new epm_system();
    $dest = $dest ?? tmpdir('dest');
    $err = array();
    $ok = $sys->extract_package($archive, $dest, $err);
    return array($ok, $err['extract_package'] ?? '', $dest);
}

function leftovers($dest) {
    return glob($dest . '/.epm-extract-*', GLOB_NOSORT) ?: array();
}

function data_files() {
    return array(
        'acme/brand_data.json'               => '{"data":{"brands":{"directory":"acme"}}}',
        'acme/fam/family_data.json'          => '{"data":{"id":"1"}}',
        'acme/fam/$mac_reg.cfg'              => "reg.1.address=\"{\$username}\"\n",
        'acme/fam/cfg$mac'                   => "<config/>\n",
        'acme/fam/$mac'                      => "x\n",
        'acme/fam/ht70x'                     => "x\n",
        'acme/fam/sip.XML'                   => "<?xml version=\"1.0\"?><a/>\n",
        'acme/fam/ring.WAV'                  => "RIFF\0\0\0\0WAVE",
        'acme/fam/bg.jpg'                    => "\xff\xd8\xff\xe0",
        'acme/fam/app.log'                   => '',
        'acme/fam/settings.htm'              => "<html></html>\n",
        'acme/fam/a.txt'                     => "text\n",
        'acme/fam/b.ini'                     => "[x]\n",
        'acme/fam/c.cnf'                     => "x\n",
        'acme/fam/d.conf'                    => "x\n",
        'acme/fam/e.DAT'                     => "x\n",
        'acme/fam/f.csv'                     => "a,b\n",
        'acme/fam/g.png'                     => "\x89PNG",
        'acme/fam/h.gif'                     => "GIF89a",
        'acme/fam/i.bmp'                     => "BM",
        'acme/fam/j.mp3'                     => "ID3",
        'acme/fam/k.html'                    => "<p></p>\n",
        'acme/fam/firmware.bin'              => str_repeat("\x01\x02\x03", 400000),
        'acme/fam/y0000000000$suffix.cfg'    => "#!version:1.0.0.1\naccount.1.enable = 1\n",
    );
}

$work = tmpdir('work');

// --- Accepted packages --------------------------------------------------------

gnu_tgz("$work/good.tgz", data_files());
list($ok, $err, $dest) = run_extract("$work/good.tgz");
check($ok, 'accept a tarball of data files', $err);
check(is_file("$dest/acme/fam/cfg\$mac") && is_file("$dest/acme/fam/firmware.bin"), 'extract the files into the destination');
check(empty(leftovers($dest)), 'remove the private directory after success');

gnu_tgz("$work/plain.tar", data_files(), '', false);
list($ok, $err) = run_extract("$work/plain.tar");
check($ok, 'accept an uncompressed tar', $err);

$long = 'acme/' . str_repeat('d', 90) . '/' . str_repeat('e', 60) . '/template.xml';
gnu_tgz("$work/long-gnu.tgz", array($long => '<a/>'), '--format=gnu');
list($ok, $err, $dest) = run_extract("$work/long-gnu.tgz");
check($ok && is_file("$dest/$long"), 'accept a long name in GNU format', $err);

gnu_tgz("$work/long-pax.tgz", array($long => '<a/>'), '--format=pax');
list($ok, $err, $dest) = run_extract("$work/long-pax.tgz");
check($ok && is_file("$dest/$long"), 'accept a long name in pax format', $err);

// A manual upload made with "tar czf x.tgz -C dir ." has a ./ prefix.
$src = tmpdir('dot');
mkdir("$src/acme/fam", 0755, true);
file_put_contents("$src/acme/brand_data.json", '{}');
file_put_contents("$src/acme/fam/a.cfg", 'x');
sh('tar czf ' . escapeshellarg("$work/dot.tgz") . ' -C ' . escapeshellarg($src) . ' .');
list($ok, $err, $dest) = run_extract("$work/dot.tgz");
check($ok && is_file("$dest/acme/fam/a.cfg"), 'accept a ./ prefix', $err);

// A second extraction replaces the old directory, so that no unchecked file
// stays next to the new ones.
$dest = tmpdir('replace');
mkdir("$dest/acme", 0755);
file_put_contents("$dest/acme/stale.php", '<?php echo 1;');
list($ok, $err) = run_extract("$work/good.tgz", $dest);
check($ok && !file_exists("$dest/acme/stale.php"), 'replace an old directory of the same name', $err);

// --- Refused packages ---------------------------------------------------------

$refuse = array(
    'a PHP file' => array('gnu', array('acme/brand_data.json' => '{}', 'acme/fam/phone.php' => '<?php echo 1;'), 'acme/fam/phone.php'),
    'a PHP file in upper case' => array('gnu', array('acme/brand_data.json' => '{}', 'acme/fam/phone.PHP' => 'x'), 'acme/fam/phone.PHP'),
    'a shell script' => array('gnu', array('acme/brand_data.json' => '{}', 'acme/fam/run.sh' => "echo hi\n"), 'acme/fam/run.sh'),
    'a file without an extension' => array('gnu', array('acme/aastra/anacrypt' => 'x'), 'acme/aastra/anacrypt'),
    'a dot file' => array('gnu', array('acme/.htaccess' => 'x'), 'acme/.htaccess'),
    'an ELF file with a data name' => array('gnu', array('acme/fam/fw.bin' => "\x7fELF\x01\x01\x01"), 'acme/fam/fw.bin'),
    'an MZ file with a data name' => array('gnu', array('acme/fam/fw.dat' => "MZ\x90\x00"), 'acme/fam/fw.dat'),
    'a #! file with a data name' => array('gnu', array('acme/fam/run.txt' => "#!/bin/sh\nid\n"), 'acme/fam/run.txt'),
    'a #! file that is almost a Yealink header' => array('gnu', array('acme/fam/run.cfg' => "#!versio\n"), 'acme/fam/run.cfg'),
    'a <?php tag in a template' => array('gnu', array('acme/fam/a.xml' => "<a><?php system('id'); ?></a>"), 'acme/fam/a.xml'),
    'a <?PHP tag in upper case' => array('gnu', array('acme/fam/a.cfg' => "x <?PHP x"), 'acme/fam/a.cfg'),
    'a <?= tag' => array('gnu', array('acme/fam/a.htm' => "<p><?= 1 ?></p>"), 'acme/fam/a.htm'),
    'a PHP tag after the first chunk' => array('gnu', array('acme/fam/a.bin' => str_repeat('A', 1048574) . '<?php'), 'acme/fam/a.bin'),
    'a long PHP name in GNU format' => array('gnu', array(substr($long, 0, -4) . '.php' => 'x'), substr($long, 0, -4) . '.php', '--format=gnu'),
    'a long PHP name in pax format' => array('gnu', array(substr($long, 0, -4) . '.php' => 'x'), substr($long, 0, -4) . '.php', '--format=pax'),
    'a symlink' => array('ustar', array(array('acme/', '5'), array('acme/link.xml', '2', '', '/etc/passwd')), 'acme/link.xml'),
    'a hard link' => array('ustar', array(array('acme/', '5'), array('acme/hard.xml', '1', '', '/etc/passwd')), 'acme/hard.xml'),
    'a ../ path' => array('ustar', array(array('acme/../../evil.xml', '0', '<a/>')), 'acme/../../evil.xml'),
    'an absolute path' => array('ustar', array(array('/tmp/evil.xml', '0', '<a/>')), '/tmp/evil.xml'),
    'a device node' => array('ustar', array(array('acme/dev.xml', '3')), 'acme/dev.xml'),
    'a FIFO' => array('ustar', array(array('acme/fifo.xml', '6')), 'acme/fifo.xml'),
);

$n = 0;
foreach ($refuse as $label => $spec) {
    $archive = "$work/refuse-" . (++$n) . '.tgz';
    if ($spec[0] === 'gnu') {
        gnu_tgz($archive, $spec[1], $spec[3] ?? '');
    } else {
        ustar_tgz($archive, $spec[1]);
    }
    list($ok, $err, $dest) = run_extract($archive);
    $empty = (count(scandir($dest)) === 2);
    check(!$ok && strpos($err, $spec[2]) !== false && $empty,
        "refuse $label, name the path, leave no files",
        'result=' . var_export($ok, true) . " message=$err files=" . implode(',', array_diff(scandir($dest), array('.', '..'))));
}

// A symlink that GNU tar makes. The listing must see type 2 in its header.
$src = tmpdir('sym');
mkdir("$src/acme", 0755);
file_put_contents("$src/acme/a.xml", '<a/>');
symlink('/etc/passwd', "$src/acme/b.xml");
sh('tar czf ' . escapeshellarg("$work/gnu-symlink.tgz") . ' -C ' . escapeshellarg($src) . ' acme');
list($ok, $err, $dest) = run_extract("$work/gnu-symlink.tgz");
check(!$ok && strpos($err, 'acme/b.xml') !== false && count(scandir($dest)) === 2, 'refuse a symlink that GNU tar made', $err);

// A file that is not a tar archive.
file_put_contents("$work/junk.tgz", gzencode(random_bytes(4096)));
list($ok, $err, $dest) = run_extract("$work/junk.tgz");
check(!$ok && strpos($err, 'junk.tgz') !== false, 'refuse a file that is not a tar archive', $err);

// A refused package must not change a directory that is already there.
$dest = tmpdir('keep');
mkdir("$dest/acme", 0755);
file_put_contents("$dest/acme/keep.xml", '<a/>');
list($ok, $err) = run_extract("$work/refuse-1.tgz", $dest);
check(!$ok && is_file("$dest/acme/keep.xml") && empty(leftovers($dest)), 'keep the old files and remove the private directory on refusal', $err);

// The message names the first path and counts the others.
gnu_tgz("$work/many.tgz", array('acme/a.php' => 'x', 'acme/b.php' => 'x', 'acme/c.php' => 'x'));
list($ok, $err) = run_extract("$work/many.tgz");
check(!$ok && strpos($err, 'fail the check: 2.') !== false, 'count the other refused entries', $err);

// The Yealink header is not an interpreter line.
gnu_tgz("$work/yealink.tgz", array('yealink/t2xv71/$mac.cfg' => "#!version:1.0.0.1\naccount.1.enable = 1\n"));
list($ok, $err, $dest) = run_extract("$work/yealink.tgz");
check($ok && is_file("$dest/yealink/t2xv71/\$mac.cfg"), 'accept a Yealink .cfg that starts with #!version:1.0.0.1', $err);

// --- The cleanup of an old data tree -------------------------------------------

// The data tree of an earlier version: drivers, anacrypt and a symlink next
// to the data. The cleanup removes only the files that are not data.
$tree = tmpdir('tree');
$old = array(
    'aastra/anacrypt'           => "\x7fELF\x01",
    'aastra/base.php'           => '<?php',
    'aastra/security.tuz'       => 'x',
    'aastra/aap9xxx6xxx/phone.php' => '<?php',
    'yealink/t2xv71/$mac.cfg'   => "#!version:1.0.0.1\n",
    'aastra/aap9xxx6xxx/$mac.cfg' => 'x',
    'polycom/spipm/cfg$mac'     => 'x',
    'polycom/spipm/$mac'        => 'x',
    'grandstream/ht70x'         => 'x',
    'polycom/ptx151/a.bin'      => 'x',
    'polycom/spipm/b.DAT'       => 'x',
    'global_template_data.json' => '{}',
);
foreach ($old as $rel => $content) {
    if (!is_dir(dirname("$tree/$rel"))) {
        mkdir(dirname("$tree/$rel"), 0755, true);
    }
    file_put_contents("$tree/$rel", $content);
}
symlink('/etc/passwd', "$tree/polycom/link.xml");
$sys = new epm_system();
$result = $sys->remove_non_data_files($tree);
$removed = array_map(function ($p) use ($tree) { return substr($p, strlen($tree) + 1); }, $result['removed']);
sort($removed);
$expect = array('aastra/aap9xxx6xxx/phone.php', 'aastra/anacrypt', 'aastra/base.php', 'aastra/security.tuz', 'polycom/link.xml');
check($removed === $expect && empty($result['failed']), 'remove the drivers, anacrypt and links from an old tree', implode(',', $removed));
$kept = true;
foreach ($old as $rel => $content) {
    if (epm_system::package_name_allowed($rel) && !is_file("$tree/$rel")) {
        $kept = false;
    }
}
check($kept && is_file('/etc/passwd'), 'keep each data file of the old tree');

// --- The allowlist alone ------------------------------------------------------

foreach (array('a.json', 'A.JSON', 'x/y/$mac_reg.cfg', 'cfg$mac', '$mac', 'ht70x', 'p.Bin') as $name) {
    check(epm_system::package_name_allowed($name), "allow the name $name");
}
foreach (array('a.php', 'a.phtml', 'a.phar', 'a.inc', 'a.sh', 'anacrypt', '.htaccess', '.json', 'CFG$MAC', 'a.tuz', 'a.json.php', 'a') as $name) {
    check(!epm_system::package_name_allowed($name), "refuse the name $name");
}

sh('rm -rf ' . escapeshellarg(sys_get_temp_dir()) . '/epmtest-*');

echo "\n$checks checks, $failures failed\n";
exit($failures === 0 ? 0 : 1);

}
