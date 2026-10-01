<?PHP

/**
 * HTTP and HTTPS provisioning endpoint.
 *
 * Phones use http://<pbx>/provisioning/p.php/ and add the name of their own
 * file, which contains the MAC. The request thus comes as PATH_INFO:
 *
 *   GET /provisioning/p.php/001565aabbcc.cfg
 *
 * PATH_INFO needs no web server configuration. A rewrite rule in .htaccess
 * needs AllowOverride, which Debian does not grant by default. The settings
 * page shows this URL.
 *
 * The phone does not authenticate, because it has no credential. Thus only
 * the source address controls access (see lib/acl.inc.php). Behind a reverse
 * proxy in `trusted_proxies`, the source address and the scheme are the
 * values that the proxy sends.
 */

function getMethod() {
    $method = $_SERVER['REQUEST_METHOD'];
    $override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : (isset($_GET['method']) ? $_GET['method'] : '');
    if ($method == 'POST' && strtoupper($override) == 'PUT') {
        $method = 'PUT';
    } elseif ($method == 'POST' && strtoupper($override) == 'DELETE') {
        $method = 'DELETE';
    }
    return $method;
}

/**
 * Refuse the request and write a log entry.
 *
 * The body is the same for each refusal. A phone cannot read it, and other
 * clients must not learn if the transport setting or the allowlist refused.
 *
 * @param string      $reason the text to log, never sent to the client
 * @param string|null $client the forwarded client address, when a trusted
 *                            proxy sent the request
 * @return void does not return
 */
function epm_provision_deny($reason, $client = null) {
    $addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
    if ($client !== null && $client !== '' && $client !== $addr) {
        // Behind a trusted proxy, log the client and the proxy.
        $addr = $client . ' via ' . $addr;
    }
    $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';
    $line = sprintf('Endpointman: provisioning refused for %s (%s): %s', $addr, $uri, $reason);

    // Write to freepbx_security.log, next to the authentication failures. An
    // admin who looks for access to device configs looks there. Do not use
    // freepbx_log(FPBX_LOG_SECURITY, ...). FreePBX's Logger::logWrite() has no
    // break statements, so each level falls through to INFO, and SECURITY is not
    // a case. That call writes a refused fetch to freepbx.log as a routine entry.
    if (function_exists('freepbx_log_security')) {
        freepbx_log_security($line);
    } else {
        freepbx_log(FPBX_LOG_WARNING, $line);
    }

    header('HTTP/1.1 403 Forbidden', true, 403);
    echo "<h1>" . _("Error 403 Forbidden") . "</h1>";
    echo _("Access denied!");
    die();
}

$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

$epm = FreePBX::create()->Endpointman;

$server_type = $epm->configmod->get("server_type");

// First, check the transport setting. The settings page offers file, http
// and https, so allow both web values.
if (($server_type !== 'http') && ($server_type !== 'https')) {
    epm_provision_deny('transport is ' . var_export($server_type, true) . ', not http/https');
}

// Next, find the client. Behind a reverse proxy, the client is the address
// that the proxy sends. Use it only for proxies in trusted_proxies, because
// any client can send an X-Forwarded-For header. An empty allowlist denies
// all requests, and the settings page shows a warning about it.
$trusted_proxies = $epm->configmod->get("trusted_proxies");
$client = epm_acl_client_ip($_SERVER, $trusted_proxies);
$acl = $epm->configmod->get("provision_acl");
if (!epm_acl_match($client, $acl)) {
    epm_provision_deny('not in provision_acl', $client);
}

// Then check the protocol. An admin who selects HTTPS selects it because
// these files contain SIP secrets. Thus refuse plain HTTP. A backend behind a
// TLS proxy sees plain HTTP, so believe X-Forwarded-Proto from a trusted proxy
// (see epm_acl_request_is_tls()).
if ($server_type === 'https' && !epm_acl_request_is_tls($_SERVER, $trusted_proxies)) {
    epm_provision_deny('transport is https but the request arrived over plaintext', $client);
}

define('PROVISIONER_BASE', $epm->PHONE_MODULES_PATH);

$provis_ip = $epm->configmod->get("srvip");

if (((getMethod() == 'PUT') OR (getMethod() == 'POST'))) {
    //write log files or other files to drive. not sussed out yet completely.

    /* PUT data comes in on the stdin stream */
    //$putdata = fopen("php://input", "r");

    /* Open a file for writing */
    //$fp = fopen($endpoint->global_cfg['config_location'] . $_SERVER['REDIRECT_URL'], "a");

    /* Read the data 1 KB at a time
    and write to the file */
    //while ($data = fread($putdata, 1024))
    //    fwrite($fp, $data);

    /* Close the streams */
    //fclose($fp);
    //fclose($putdata);
    header('HTTP/1.1 200 OK', true, 200);
    die();
}

// Answer HEAD as GET. The web server drops the body. Polycom phones send a
// HEAD for each log file before they send it with PUT.
if (getMethod() == "GET" || getMethod() == "HEAD") {
    // The requested file is PATH_INFO, not the full URI. basename(REQUEST_URI)
    // puts the query string into the file name. basename() also keeps the lookup
    // inside config_location, because it removes each directory part, so `../`
    // cannot pass.
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    if ($path_info === '') {
        // No PATH_INFO: a request for p.php itself, or a setup that still uses the
        // old rewrite. Use the path part of the URI, so that the second case gives a
        // 404 and not the wrong file.
        $path_info = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }
    $filename = basename($path_info);

    $web_path = ($server_type === 'https' ? 'https://' : 'http://') . $_SERVER["SERVER_NAME"] . dirname($_SERVER["SCRIPT_NAME"]) . '/p.php/';

    # Firmware Linksys/SPA504G-7.4.3a is broken and MUST be upgraded.
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (preg_match('/7\.4\.3a/', $user_agent)) {
        $str = '<flat-profile><Upgrade_Enable group="Provisioning/Firmware_Upgrade">Yes</Upgrade_Enable>';
        $str .= '<Upgrade_Rule group="Provisioning/Firmware_Upgrade">http://' . $provis_ip . '/current.bin</Upgrade_Rule></flat-profile>';
        echo $str;
        exit;
    }

    if ($filename === '' || $filename === 'p.php') {
        header("HTTP/1.0 404 Not Found", true, 404);
        echo "<h1>" . _("Error 404 Not Found") . "</h1>";
        echo _("File not Found!");
        die();
    }

    # Just moved this Block of code up to fix the provisioning for Snom Phones
    //
    // The drivers ship with the module in lib/provisioner/. Only their data is in
    // _ep_phone_modules/.
    require_once(__DIR__ . '/../lib/provisioner/endpoint/base.php');

    $data = Provisioner_Globals::dynamic_global_files(strtolower($filename), $epm->configmod->get("config_location"), $web_path);
    if ($data !== FALSE) {
        echo $data;
    }
    else {
        header("HTTP/1.0 404 Not Found", true, 404);
        echo "<h1>" . _("Error 404 Not Found") . "</h1>";
        echo _("File not Found!");
        die();
    }
}
else {
    epm_provision_deny('method ' . getMethod() . ' not allowed', $client);
}
