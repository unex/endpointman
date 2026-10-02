<?php
/**
 * Endpoint Manager: fwconsole command
 *
 * This is a Symfony command in `Console/`, declared in module.xml under
 * `<console>`. Run it as `fwconsole endpointman` or `fwconsole epm`.
 *
 * The command does tasks that are difficult or not possible in the web UI. It
 * rebuilds the provisioning files for all devices in one step, which is
 * necessary after a restore, because a backup does not contain /tftpboot. It
 * also shows the device and package lists over SSH.
 *
 * @package Endpointman
 */

namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Endpointman extends Command {

	protected function configure() {
		$this->setName('endpointman')
			->setAliases(array('epm'))
			->setDescription(_('OSS Endpoint Manager'))
			->setDefinition(array(
				new InputOption('list', 'l', InputOption::VALUE_NONE, _('List the phones')),
				new InputOption('brands', 'b', InputOption::VALUE_NONE, _('List the installed brands')),
				new InputOption('rebuild', 'r', InputOption::VALUE_OPTIONAL, _('Write the configs of the phone with this MAC address. Without a MAC address, write the configs of all phones.'), false),
				new InputOption('reboot', null, InputOption::VALUE_NONE, _('With --rebuild, also restart each phone')),
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$epm = \FreePBX::create()->Endpointman;

		if ($input->getOption('list')) {
			return $this->listDevices($epm, $output);
		}
		if ($input->getOption('brands')) {
			return $this->listBrands($epm, $output);
		}

		// VALUE_OPTIONAL with a false default gives three cases. Absent is false, a
		// bare --rebuild is null, and --rebuild=<mac> is the string.
		$rebuild = $input->getOption('rebuild');
		if ($rebuild !== false) {
			return $this->rebuild($epm, $output, $rebuild, (bool) $input->getOption('reboot'));
		}

		$output->writeln('<error>' . _('Nothing to do. Pass --list, --brands or --rebuild.') . '</error>');
		$output->writeln(_('Run `fwconsole endpointman --help` for the full list.'));
		return self::FAILURE;
	}

	/**
	 * Print each device and its extensions.
	 *
	 * @param object          $epm    the Endpointman BMO object
	 * @param OutputInterface $output
	 * @return int
	 */
	private function listDevices($epm, OutputInterface $output) {
		$sth = $epm->db->query(
			'SELECT m.id, m.mac, b.name AS brand, ml.model, t.name AS template' .
			' FROM endpointman_mac_list m' .
			' LEFT JOIN endpointman_model_list ml ON ml.id = m.model' .
			' LEFT JOIN endpointman_brand_list b ON b.id = ml.brand' .
			' LEFT JOIN endpointman_template_list t ON t.id = m.template_id' .
			' ORDER BY b.name, m.mac'
		);
		$devices = $sth->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($devices)) {
			$output->writeln('<info>' . _('There are no phones.') . '</info>');
			return self::SUCCESS;
		}

		$lines = $epm->db->prepare(
			'SELECT `line`, `ext`, `description` FROM endpointman_line_list WHERE mac_id = ? ORDER BY `line`'
		);

		$rows = array();
		foreach ($devices as $device) {
			$lines->execute(array($device['id']));
			$extensions = array();
			foreach ($lines->fetchAll(\PDO::FETCH_ASSOC) as $line) {
				$label = $line['ext'];
				if ($line['description'] !== '' && $line['description'] !== null) {
					$label .= ' (' . $line['description'] . ')';
				}
				$extensions[] = $line['line'] . ': ' . $label;
			}
			$rows[] = array(
				$device['mac'],
				$device['brand'],
				$device['model'],
				$device['template'] === null ? _('none') : $device['template'],
				implode(', ', $extensions),
			);
		}

		$table = new Table($output);
		$table->setHeaders(array(_('MAC'), _('Brand'), _('Model'), _('Template'), _('Lines')));
		$table->setRows($rows);
		$table->render();
		// FreePBX has no _n(), so use a text that needs no plural form.
		$output->writeln(sprintf(_('Phones: %s'), count($rows)));
		return self::SUCCESS;
	}

	/**
	 * Print the brand packages, and show if the data of each package is on disk.
	 *
	 * The on-disk column helps after a restore. The database can show a brand as
	 * installed while its package directory is absent. In that state, config
	 * generation fails with a missing driver.
	 *
	 * @param object          $epm
	 * @param OutputInterface $output
	 * @return int
	 */
	private function listBrands($epm, OutputInterface $output) {
		$sth = $epm->db->query(
			'SELECT b.name, b.directory, b.cfg_ver, b.installed,' .
			' (SELECT COUNT(*) FROM endpointman_model_list ml WHERE ml.brand = b.id AND ml.enabled = 1) AS models' .
			' FROM endpointman_brand_list b ORDER BY b.name'
		);
		$brands = $sth->fetchAll(\PDO::FETCH_ASSOC);

		$root = rtrim($epm->PHONE_MODULES_PATH, '/') . '/endpoint/';
		$rows = array();
		foreach ($brands as $brand) {
			if (!$brand['installed']) {
				continue;
			}
			$present = is_dir($root . $brand['directory']);
			$rows[] = array(
				$brand['name'],
				$brand['directory'],
				$brand['cfg_ver'],
				$brand['models'],
				$present ? _('yes') : '<error>' . _('MISSING') . '</error>',
			);
		}

		if (empty($rows)) {
			$output->writeln('<info>' . _('No brand packages are installed.') . '</info>');
			return self::SUCCESS;
		}

		$table = new Table($output);
		$table->setHeaders(array(_('Brand'), _('Directory'), _('Version'), _('Enabled models'), _('On disk')));
		$table->setRows($rows);
		$table->render();
		return self::SUCCESS;
	}

	/**
	 * Make the provisioning files again for one device or for all devices.
	 *
	 * @param object          $epm
	 * @param OutputInterface $output
	 * @param string|null     $mac    one MAC address, or null for every device
	 * @param bool            $reboot also send the reboot, as the "rebuild and
	 *                                reboot" action of the UI does
	 * @return int
	 */
	private function rebuild($epm, OutputInterface $output, $mac, $reboot) {
		// Write as the web server user, not as root. The web UI cannot overwrite a
		// config file that root made, so the next rebuild from the browser fails
		// with "permission denied". prepare_configs() also makes a new copy with a
		// time stamp in config_bkup/ on each rebuild. If root owns that copy, a web
		// rebuild fails on copy(). The fwconsole backup and moduleadmin commands
		// change to AMPASTERISKWEBUSER for the same reason.
		if (function_exists('posix_getuid') && posix_getuid() === 0) {
			$webuser = $epm->config->get('AMPASTERISKWEBUSER');
			$info = posix_getpwnam($webuser);
			if (empty($info)) {
				$output->writeln('<error>' . sprintf(_('%s is not a valid user. The command does not write configs as root.'), $webuser) . '</error>');
				return self::FAILURE;
			}
			posix_setgid($info['gid']);
			posix_setuid($info['uid']);
		}

		$devices = $epm->_get_page('devices');

		if ($mac !== null && $mac !== '') {
			$mac = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $mac));
			$sth = $epm->db->prepare('SELECT id, mac FROM endpointman_mac_list WHERE mac = ?');
			$sth->execute(array($mac));
		} else {
			$sth = $epm->db->query('SELECT id, mac FROM endpointman_mac_list ORDER BY mac');
		}
		$targets = $sth->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($targets)) {
			$output->writeln('<error>' . _('No phone has this MAC address.') . '</error>');
			return self::FAILURE;
		}

		$output->writeln(sprintf(
			_('Writing configs to %s'),
			$epm->configmod->get('config_location')
		));

		$failed = 0;
		foreach ($targets as $target) {
			$devices->error = array();

			$info = $devices->get_phone_info($target['id']);
			$written = $info ? $devices->prepare_configs($info, $reboot) : false;

			if ($written) {
				$output->writeln(sprintf(_('  %s ok'), $target['mac']));
				continue;
			}

			$failed++;
			$reason = empty($devices->error) ? _('unknown error') : implode('; ', array_map('strip_tags', $devices->error));
			$output->writeln('<error>' . sprintf(_('  %s failed: %s'), $target['mac'], $reason) . '</error>');
		}

		$output->writeln(sprintf(
			$reboot ? _('Wrote the configs of %s of %s phones, and restarted them.') : _('Wrote the configs of %s of %s phones.'),
			count($targets) - $failed,
			count($targets)
		));
		return $failed ? self::FAILURE : self::SUCCESS;
	}
}
