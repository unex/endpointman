<?php

/**
 * IP allowlist for the HTTP provisioning transport.
 *
 * `provisioning/p.php` serves device configuration files, and these files
 * contain SIP secrets. The phone does not authenticate. It has no credential
 * and only asks for the file with its MAC in the name. Thus the only control
 * is the source address of the request. `provision_acl` holds the list of
 * allowed addresses.
 *
 * Separate the entries with commas, spaces or newlines. Each entry is a bare
 * address or a CIDR block, IPv4 or IPv6:
 *
 *   127.0.0.1/32, ::1/128, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
 *
 * A new install gets this default. install.php also sets it when it upgrades
 * an install that has no ACL. Phones on a private LAN continue to work after
 * the upgrade. A request from a routed address gets a 403.
 *
 * **An empty list denies all requests.** Otherwise a cleared field publishes
 * each SIP secret on the PBX. The settings page shows this next to the field.
 *
 * The match uses the packed binary form from inet_pton(), so IPv4 and IPv6
 * use the same code. It compares the first N bits, where N is the prefix
 * length. An address matches only an entry of its own family. The exception
 * is an IPv4-mapped IPv6 address (::ffff:192.0.2.1). A dual-stack listener
 * reports this form for an IPv4 client, so the code changes it to IPv4 first.
 *
 * @license MPL / GPLv2 / LGPL
 * @package Endpointman
 */

/**
 * Change an IPv4-mapped IPv6 address to plain IPv4, and pack the result.
 *
 * @param string $ip a printable address
 * @return string|false the packed 4-byte or 16-byte form, or false if not valid
 */
function epm_acl_pton($ip) {
	$ip = trim((string) $ip);
	if ($ip === '') { return false; }

	// An IPv6 scope ID ("fe80::1%eth0") is not part of the address.
	$pct = strpos($ip, '%');
	if ($pct !== false) { $ip = substr($ip, 0, $pct); }

	$packed = @inet_pton($ip);
	if ($packed === false) { return false; }

	// ::ffff:a.b.c.d has 10 zero bytes, two 0xff bytes, then the IPv4 address.
	// REMOTE_ADDR has this form for an IPv4 client on a dual-stack listener. An
	// admin who writes 192.168.0.0/16 expects it to match.
	if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
		return substr($packed, 12);
	}

	return $packed;
}

/**
 * Split an ACL string into its entries, and discard empty entries.
 *
 * @param string $acl the stored setting
 * @return string[] one entry per element, whitespace trimmed
 */
function epm_acl_split($acl) {
	$parts = preg_split('/[\s,;]+/', (string) $acl, -1, PREG_SPLIT_NO_EMPTY);
	return is_array($parts) ? $parts : array();
}

/**
 * Parse one entry into a packed network address and a prefix length.
 *
 * A bare address is a host route, /32 for IPv4 and /128 for IPv6. This is
 * what a single address means.
 *
 * @param string $entry an address or CIDR block
 * @return array|false array(packed network, prefix bits), or false if invalid
 */
function epm_acl_parse_entry($entry) {
	$entry = trim((string) $entry);
	if ($entry === '') { return false; }

	$bits = null;
	$slash = strrpos($entry, '/');
	if ($slash !== false) {
		$bits = substr($entry, $slash + 1);
		$entry = substr($entry, 0, $slash);
		// ctype_digit rejects "8 ", "+8" and "", and also all other non-digits.
		if (!ctype_digit($bits)) { return false; }
		$bits = (int) $bits;
	}

	$packed = epm_acl_pton($entry);
	if ($packed === false) { return false; }

	$width = strlen($packed) * 8;
	if ($bits === null) { $bits = $width; }
	if ($bits < 0 || $bits > $width) { return false; }

	return array($packed, $bits);
}

/**
 * Find out if an address is in an allowlist.
 *
 * @param string $ip  the address to test, usually $_SERVER['REMOTE_ADDR']
 * @param string $acl the stored `provision_acl` setting
 * @return bool true only if an entry matches. An empty or invalid list
 *              denies, and an invalid $ip also denies.
 */
function epm_acl_match($ip, $acl) {
	$addr = epm_acl_pton($ip);
	if ($addr === false) { return false; }

	foreach (epm_acl_split($acl) as $entry) {
		$parsed = epm_acl_parse_entry($entry);
		if ($parsed === false) { continue; }
		list($net, $bits) = $parsed;

		// The families differ. An IPv4 address cannot be in an IPv6 block, and an
		// IPv6 address cannot be in an IPv4 block.
		if (strlen($net) !== strlen($addr)) { continue; }

		// /0 matches all addresses and has no bytes to compare.
		if ($bits === 0) { return true; }

		$whole = intdiv($bits, 8);
		$rest  = $bits % 8;

		if ($whole > 0 && strncmp($net, $addr, $whole) !== 0) { continue; }

		if ($rest === 0) { return true; }

		// Compare the first $rest bits of the next byte.
		$mask = 0xff << (8 - $rest) & 0xff;
		if ((ord($net[$whole]) & $mask) === (ord($addr[$whole]) & $mask)) {
			return true;
		}
	}

	return false;
}

/**
 * Validate and clean an ACL before you store it.
 *
 * The function writes each entry from its parsed form, so the stored list is
 * always canonical. For example, `192.168.1.5/24` becomes `192.168.1.0/24`,
 * and a bare address gets its prefix.
 *
 * @param string      $acl   the value that the admin typed
 * @param string|null $error set to a message when the return is false
 * @return string|false the normalized list, comma-separated, or false
 */
function epm_acl_normalize($acl, &$error = null) {
	$error = null;
	$out = array();

	foreach (epm_acl_split($acl) as $entry) {
		$parsed = epm_acl_parse_entry($entry);
		if ($parsed === false) {
			$error = sprintf(_("Not a valid address or CIDR block: %s"), $entry);
			return false;
		}
		list($net, $bits) = $parsed;

		// Set the host bits to zero. Then the stored entry is the network, not an
		// address in it.
		$len = strlen($net);
		for ($i = 0; $i < $len; $i++) {
			$keep = $bits - ($i * 8);
			if ($keep >= 8) { continue; }
			$mask = $keep <= 0 ? 0 : (0xff << (8 - $keep) & 0xff);
			$net[$i] = chr(ord($net[$i]) & $mask);
		}

		$printable = @inet_ntop($net);
		if ($printable === false) {
			$error = sprintf(_("Not a valid address or CIDR block: %s"), $entry);
			return false;
		}

		$canonical = $printable . '/' . $bits;
		if (!in_array($canonical, $out, true)) { $out[] = $canonical; }
	}

	return implode(', ', $out);
}

/**
 * Find the address of the client when a reverse proxy is in front of the PBX.
 *
 * Behind a TLS proxy, each request comes from the address of the proxy. A
 * match of REMOTE_ADDR against `provision_acl` then tests the proxy, not the
 * phone. When REMOTE_ADDR is in `trusted_proxies`, the client comes from
 * X-Forwarded-For. The function reads the list from the right and skips each
 * hop that is a trusted proxy. The first other address is the client.
 *
 * A client can put values in the header itself. These values are to the left
 * of the value of the trusted proxy, so the function does not reach them.
 *
 * When `trusted_proxies` is empty, which is the default, the function ignores
 * the header. REMOTE_ADDR is then the client.
 *
 * @param array  $server          $_SERVER
 * @param string $trusted_proxies the stored setting, with the same syntax as the ACL
 * @return string the client address, or '' if there is none
 */
function epm_acl_client_ip(array $server, $trusted_proxies) {
	$remote = isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
	if ($remote === '' || !epm_acl_match($remote, $trusted_proxies)) {
		return $remote;
	}

	$forwarded = isset($server['HTTP_X_FORWARDED_FOR']) ? (string) $server['HTTP_X_FORWARDED_FOR'] : '';
	$hops = epm_acl_split($forwarded);
	for ($i = count($hops) - 1; $i >= 0; $i--) {
		// Return a hop that is not valid as it is. epm_acl_match() then refuses it.
		// This is correct for a header that a proxy did not clean.
		if (!epm_acl_match($hops[$i], $trusted_proxies)) {
			return $hops[$i];
		}
	}

	// There is no header, or each hop is a proxy. The proxy itself is the client.
	return $remote;
}

/**
 * Find out if this request came over TLS.
 *
 * For a direct request, read $_SERVER['HTTPS']. Behind a trusted proxy, the
 * backend sees plain HTTP. The proxy sends the protocol of the client in
 * X-Forwarded-Proto. Any client can send this header, so believe it only
 * when REMOTE_ADDR is in `trusted_proxies`.
 *
 * @param array  $server          $_SERVER
 * @param string $trusted_proxies the stored setting
 * @return bool
 */
function epm_acl_request_is_tls(array $server, $trusted_proxies) {
	if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
		return true;
	}

	$remote = isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
	if ($remote === '' || !epm_acl_match($remote, $trusted_proxies)) {
		return false;
	}

	$proto = isset($server['HTTP_X_FORWARDED_PROTO']) ? (string) $server['HTTP_X_FORWARDED_PROTO'] : '';
	// Each proxy in a chain adds a value, so the first value is from the client.
	$parts = epm_acl_split($proto);
	return isset($parts[0]) && strtolower($parts[0]) === 'https';
}
