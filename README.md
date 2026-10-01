# endpointman

OSS Endpoint Manager for [FreePBX](https://www.freepbx.org/) is a module that
provisions desk phones from the FreePBX admin GUI. It downloads phone
definitions for each brand and keeps a template for each phone model. It
writes the config files that a phone gets over TFTP or HTTP when it starts.

This is a fork of the Sangoma OSS module for FreePBX 17. The community
maintains it, and Sangoma does **not** support it. For a supported endpoint
manager, see the commercial module of Sangoma.

## Requirements

- FreePBX 17 on Debian 12
- PHP 8.2
- Asterisk 21 or later. The module reads the registration state from
  `chan_pjsip`. Asterisk 21 removed `chan_sip`, so the module does not
  support it.
- `iproute2` (`ss`, `ip`), which Debian 12 includes. The module uses
  `net-tools` when it is present, but does not need it.

## The two parts

- **This repository** is the front end: the FreePBX module, its pages, the
  template editor and the config generator.
- **[unex/provisioner](https://github.com/unex/provisioner)** is the back end:
  the phone definitions and templates for each brand. The module downloads
  them from `https://unex.github.io/provisioner/` as data. The module never
  runs PHP from a download.

## Install

1. Download the release tarball (`endpointman-<version>.tgz`).
2. Unpack it into the FreePBX modules directory, and install the module:

   ```sh
   tar xzf endpointman-<version>.tgz -C /var/www/html/admin/modules/
   fwconsole ma install endpointman
   fwconsole reload
   ```

You can also upload the tarball in **Admin → Module Admin → Upload modules**.
The module shows under **Settings → OSS Endpoint Manager**. The tarball is not
signed, so the FreePBX dashboard shows an "unsigned module" notice. This is
normal.

An upgrade from 16.x converts the stored settings and blob columns. The first
`fwconsole reload` after the upgrade can print one line:
`grep: /etc/asterisk/sip_notify_endpointman.conf: No such file or directory`.
An old symlink causes it. The line does not show again.

## Set up phones

1. On **Settings → OSS Endpoint Manager → Settings**, set **IP address of
   phone server**. A new install has no value, and configs without it do not
   work.
2. On the **Package Manager** page, install the brand of your phones, and
   enable the models.
3. On the **Extension Mapping** page, add each phone with its MAC address,
   model and extension.
4. If the FreePBX firewall is on, put the phone network into a zone that
   allows HTTP and SIP, for example **Internal**. The default **External**
   zone blocks both.
5. For HTTP provisioning, set **Configuration Type** to `http` on the
   Settings page.
6. Set the provisioning server of the phone, or use DHCP option 66. For HTTP,
   use `http://<pbx>/provisioning/p.php`.

The HTTP endpoint serves configs only to addresses in the provisioning
allowlist. The default allows the private address ranges.

## Develop

`docker/README.md` describes a FreePBX 17 test stack for the module. It also
describes the `make` targets for lint, PHP 8.2 compatibility scans, packages
and release tarballs.

## Issues

Report bugs and feature requests at
[github.com/unex/endpointman/issues](https://github.com/unex/endpointman/issues).
Report problems with the definition or template of a phone at
[unex/provisioner](https://github.com/unex/provisioner/issues).

## License

GPLv3 or later. See [LICENSE](LICENSE).
