# Local development environment

This is a FreePBX 17 test stack for the module, and PHP 8.2 tools that run
without the stack.

## Services

| Service | Image | Purpose |
| --- | --- | --- |
| `freepbx` | `escomputers/freepbx:17-nofail2ban` | FreePBX 17.0.21, PHP 8.2, Apache, Debian 12 |
| `db` | `mariadb:10.11` | `asterisk` and `asteriskcdrdb` |
| `php` | `php:8.2-cli` | `php -l` |
| `qa` | `jakzal/phpqa:1.122.10-php8.2` | PHPStan, PHPCS (PHPCompatibility comes separately, see below) |

This is a **development stack, not a PBX**. It does not include RTP,
iptables NAT, fail2ban, postfix or the static-IP network of upstream. An
admin GUI module does not need them, and they all need root on the host.

## Prerequisites

- Docker with the Compose plugin.
- A user in the `docker` group. The `Makefile` runs `docker compose` without
  `sudo`.
- GNU gettext (`xgettext`, `msgmerge`, `msgfmt`) on the host for
  `make i18n`. All other tasks run in a container.

## Two modes

### Package mode (default)

This mode builds a real module tarball. The **FreePBX installer** unpacks and
installs it, as for a user.

```sh
make up          # start the containers
make install     # one time: run the FreePBX installer (a few minutes)
make deploy      # build the tarball, copy it in, run fwconsole ma install
```

Then, **one time**, open <http://localhost:8080> in a browser and complete the
two setup screens. First make the admin user, then select a locale. FreePBX
has no admin account after `make install`, and you cannot reach the GUI
before you complete these screens. `fwconsole` cannot do this step.

The FreePBX installer exits 1 also when it succeeds ("You have successfully
installed FreePBX"). Thus `make install` ignores the status and checks for
`/etc/freepbx.conf`. This is normal, not a failure.

`make deploy` is the full edit and test loop. It is slower than a bind
mount, but it is the only way to find packaging errors. Examples are a file
that you did not add, or a path that works only because of a file in your
working tree.

### Live mode

This mode bind-mounts the repository into
`/var/www/html/admin/modules/endpointman` for a fast loop.

```sh
make up-live
make install
make module-install
# edit, then:
make module-reload
```

**Disadvantages.** FreePBX sees the full working tree (`.git`, `docker/`,
`Makefile`, scratch files), which does not ship. Packaging errors thus stay
hidden. Also, FreePBX changes the owner of module directories to its
runtime user. On a bind mount, this changes the owner of your **real git
working tree**, and you lose write access to your repository. To recover,
run:

```sh
make fix-perms
```

Use live mode for fast changes. Then test with `make deploy` before you
trust a result.

## Packages

```sh
make package         # build/endpointman-<version>.tgz from the working tree
make package-verify  # build, then list each file that ships
make package-release # build/endpointman-<version>-release.tgz from `git archive HEAD`
```

The version comes from the first `<version>` in `module.xml`. The working
tree build reads its exclusions from `docker/package-exclude.txt`. The
release build reads them from `.gitattributes` (`export-ignore`).

The tarball has the layout of official FreePBX modules: one top-level
`endpointman/` directory, which you extract into
`/var/www/html/admin/modules/`.

There are two differences from a module that Sangoma publishes:

- **No `module.sig`.** Official modules include a signed manifest of sha256
  hashes. FreePBX blocks an install only for a **revoked** signature. An
  unsigned module only causes a dashboard notice. `make install` sets
  `DEVEL 1`, which also stops FreePBX from downloading an official copy over
  your local copy.
- **Two builds.** `make package` copies the working tree, so `make deploy`
  ships the edit that you did not commit yet. `make package-release` builds
  the same module from `git archive HEAD` and refuses to run on a dirty tree.
  Publish from this build: it cannot ship a stray file, and you can build it
  again from a tag. The two builds read their file lists from different
  places (`docker/package-exclude.txt` and `export-ignore` in
  `.gitattributes`). Thus `package-release` compares the two lists, and it
  fails and shows the file if they differ.

## Static analysis

These targets need Docker but not the FreePBX stack:

```sh
make lint            # php -l on all files
make compat-deps     # one time: install the PHPCompatibility tools
make compat          # PHP 8.2 compatibility scan (runs compat-deps first)
make stan            # PHPStan
make audit           # lint and compat
```

`make compat` exits non-zero when it finds a problem, so `make audit` also
does. This is the behavior of phpcs, not a fault of the target.

## Tests

```sh
make test            # run each docker/tests/*_test.php in the php service
```

The tests need Docker but not the FreePBX stack:

- `extract_package_test.php` makes tarballs and gives them to
  `epm_system::extract_package()`. It checks that a package of data files
  extracts, and that the module refuses PHP, scripts, programs, links, `..`
  paths and absolute paths. It also checks the cleanup of an old data tree.

## Other targets

```sh
make shell           # bash in the FreePBX container
make logs            # show the FreePBX logs
make down            # stop, keep the data
make destroy         # stop and delete all volumes
make i18n            # make i18n/endpointman.pot again, then merge and compile each locale
make codemirror      # get the vendored CodeMirror files again (version: CM_VERSION in the Makefile)
make fix-perms       # get ownership back after live mode changed it
```

`make help` lists each target with a one-line description.

You need `make install` only one time. The `fpbx_var` and `fpbx_etc` volumes
keep the installed state of FreePBX.

## Limits of this stack

- **Asterisk 21, not 22.** FreePBX 17 ships Asterisk 22 by default. For this
  module, the difference is small. Asterisk 21 removed `chan_sip`, so this
  image tests the change that matters: the registration state in
  `views/devices.php` and the SIP port lookup in `DevicesPage.class.php` read
  PJSIP only.
- **The image does not have `iproute2`.** Install `ss` and `ip` into the
  running container manually. `make down && make up` removes them again.
  The TFTP check and device discovery then report that neither tool exists.
  A stock Debian 12 has both.
- **No SIP or RTP.** The stack publishes only port 8080 (HTTP). A real phone
  cannot register without more port mappings and host firewall rules.
- **No transport tests.** TFTP and the `/provisioning` HTTP endpoint need
  more ports and a writable `/tftpboot`. Test them on a real FreePBX.
- The upstream image is from 2026-05-11 (FreePBX 17.0.21). Run
  `fwconsole ma upgradeall` in the container if you need a newer version.
