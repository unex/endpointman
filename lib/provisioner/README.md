# Bundled provisioner library

These are the phone drivers. They live only in this repository. The module
does not download them, and the upstream repository holds no PHP.

| | |
| --- | --- |
| Upstream (data only) | <https://github.com/unex/provisioner> |
| Data commit | `aad8a36c8d64264f1604fe43342fa1f51bf6bd78` |
| Contents | `endpoint/base.php`, one `base.php` for each brand, one `phone.php` for each family, `endpoint/global_template_data.json` |
| Size | 62 PHP files, about 390 KB |

The data commit names the last upstream data that a test of these drivers
used.

## Why the drivers ship with the module

Before 17.0.0.2, these files came over HTTP. `provisioner_net.tgz` contained
`autoload.php` and `endpoint/base.php`, and each brand tarball contained the
drivers of that brand. The module unpacked them into `_ep_phone_modules/`,
and `DevicesPage::prepare_configs()` loaded them with `require` and called
their methods. Thus the package server supplied code that ran as the web
server user. Only an MD5 sum from the same server checked it.

Code and data are now separate. The drivers ship here. The templates that
they read come from brand packages. Brands can change without a module
release, and the module never runs downloaded code.

## What a brand package contains

A package supplies only data: `brand_data.json`, `family_data.json`, the
template files, and phone resources such as `.cfg`, `.xml`, `.wav` and
`.jpg`. The package server publishes the packages under
`https://unex.github.io/provisioner/v4/`.

`epm_system::extract_package()` refuses the full package, and names the
path, when one of these is true:

- An entry is not a regular file or a directory, for example a symlink or a
  hard link.
- A path contains `..` or starts with `/`.
- A file name is not on the allowlist.
- A file starts with `\x7fELF`, `MZ` or `#!`. The Yealink header
  `#!version:` is the only exception.
- A file contains `<?php` (in any case) or `<?=`.

The allowlist is `epm_system::PACKAGE_EXTENSIONS` and
`epm_system::PACKAGE_NAMES`:

- Extensions, in any case: `json xml cfg jpg png gif bmp wav mp3 log htm html
  txt ini cnf conf dat bin csv`.
- Exact names without an extension: `cfg$mac`, `$mac`, `ht70x`.

A download and a manual upload use the same rules. The build of the package
server applies the same rules too, so change both lists together. The
module sets each extracted file to 0664.

Thus a **new** brand or family needs its `phone.php` here, in a module
release. Existing families can change without a release, because model
lists, template data and config templates are all data.

## Update the library

The drivers change only in this repository. Do not copy PHP from an old
provisioner checkout. Those copies do not have the local patches below.

1. Change the driver in `lib/provisioner/endpoint/`.
2. If the change needs new data, for example a new template variable or a
   new file in `configuration_files`, make the data change in
   `unex/provisioner` first.
3. Test the driver with the packages that the upstream build makes.
4. Write the upstream commit of that data into the table above.
5. If you change a file that has a local patch, keep the patch, and update
   its row below.

## Local patches

These changes differ from the last drivers that upstream published:

| File | Change |
| --- | --- |
| `endpoint/base.php` | Declares `public $processor_info`. The class reads the property and checks it with `isset()` in three places, but upstream does not declare it. An assignment is then a PHP 8.2 deprecation. |
| `endpoint/base.php` | Checks the result of `arraysearchrecursive()` before it uses it as an index. The function returns FALSE when a config file uses a variable that the family does not define. An index on FALSE is a PHP 8 warning, and FreePBX turns it into an exception. |
| `endpoint/aastra/base.php` | Runs `anacrypt` only from the path in the `anacrypt_location` setting, with the key in `anacrypt_key`. Upstream ran the program from the data tree, where a brand package can put a file. `epm_system::anacrypt_problem()` checks the path before each run: absolute, outside `_ep_phone_modules/`, executable, and not writable by the web server user. The program runs in a private temp directory, and the driver escapes each argument. `DevicesPage::prepare_configs()` sets the two values as properties, not in `$settings`, so a template cannot print them. |
| `endpoint/cisco/{linksysata,spa,spa5xx}/phone.php` | `htmlentities($x, ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML401)`. This sets the flags that PHP 8.1 made the default, as `make compat` requires. |

`autoload.php` does **not** come from upstream. It is written for this
layout. It finds classes only in this directory, and it refuses a class name
that points outside it.
