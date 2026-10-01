<?php
/**
 * Endpoint Manager Object Module - Sec Advanced
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

require_once(__DIR__ . "/PageBase.class.php");

class AboutPage extends \PageBase {
	public function showPage() {
		return load_view(__DIR__ . '/views/about.php');
	}

	public function ajaxRequest($req, &$setting) { return false; }

    public function ajaxHandler($command) {}

	public function doConfigPageInit($command) {}

	public function getActionBar($request) {}
}
