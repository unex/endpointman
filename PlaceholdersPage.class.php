<?php
/**
 * Endpoint Manager Object Module - Sec Placeholders
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

require_once(__DIR__ . "/PageBase.class.php");

/**
 * A static reference table of the template placeholders.
 *
 * The view is a fixed list that bootstrapTable shows. There is no state, no
 * query and nothing to dispatch, so each handler below is empty.
 */
class PlaceholdersPage extends \PageBase {
	public function showPage() {
		return load_view(__DIR__ . '/views/placeholders.php');
	}

	public function ajaxRequest($req, &$setting) { return false; }

	public function ajaxHandler($command) {}

	public function doConfigPageInit($command) {}

	public function getActionBar($request) {}
}
