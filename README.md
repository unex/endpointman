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
  the phone definitions and templates for each brand. Its package server,
  `https://unex.github.io/provisioner/v4/`, publishes only data files. The
  phone drivers ship with this module, in `lib/provisioner/`.

The module refuses a brand package that holds any file that is not data: a
program, a script, PHP, a link, or a file name that is not on its allowlist.
The message names the file. A manual upload follows the same rules.
`lib/provisioner/README.md` gives the full list.

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

An upgrade from 17.0.0.8 or earlier changes the package server from
`https://unex.github.io/provisioner/` to the `/v4/` URL. A package server
that you set yourself does not change. The upgrade also removes the old
driver files and the Aastra program `anacrypt` from `_ep_phone_modules/`, and
names each file that it removes.

An upgrade from 16.x converts the stored settings and blob columns. The first
`fwconsole reload` after the upgrade can print one line:
`grep: /etc/asterisk/sip_notify_endpointman.conf: No such file or directory`.
An old symlink causes it. The line does not show again.

## Set up phones

The module is in **Settings → OSS Endpoint Manager**. It has five tabs:
Phones, Brands, Templates, Settings and About.

1. On the **Settings** tab, set **Server address**. A new install has no
   value, and configs without it do not work.
2. On the **Brands** tab, install the brand of your phones. Click the brand,
   and enable the models of your phones.
3. On the **Phones** tab, click **Add phone**. Type the MAC address, and
   select the model and the extension. **Find phones** finds the phones on
   the network, and **All phones → Import from CSV** adds many phones.
4. If the FreePBX firewall is on, put the phone network into a zone that
   allows HTTP and SIP, for example **Internal**. The default **External**
   zone blocks both.
5. For HTTP provisioning, set **Provisioning type** to **Web (HTTP)** on the
   Settings tab.
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
