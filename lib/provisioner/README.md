# Bundled provisioner library

These are the phone drivers. They ship with the module, and the module does
not download them.

| | |
| --- | --- |
| Upstream | <https://github.com/unex/provisioner> |
| Commit | `aad8a36c8d64264f1604fe43342fa1f51bf6bd78` |
| Contents | `endpoint/base.php`, one `base.php` for each brand, one `phone.php` for each family, `endpoint/global_template_data.json` |
| Size | 62 PHP files, about 390 KB |

## Why the drivers ship with the module

Before 17.0.0.2, these files came over HTTP. `provisioner_net.tgz` contained
`autoload.php` and `endpoint/base.php`, and each brand tarball contained the
drivers of that brand. The module unpacked them into `_ep_phone_modules/`,
and `DevicesPage::prepare_configs()` loaded them with `require` and called
their methods. Thus the package server supplied code that ran as the web
server user. Only an MD5 sum from the same server checked it.

Code and data are now separate. The drivers ship here. The templates that
they read still come from brand packages, and
`epm_system::extract_package()` unpacks those packages without PHP files.
Brands can change without a module release, and the module never runs
downloaded code.

## What a brand package contains

A package supplies only data: `brand_data.json`, `family_data.json`, the
template files, and phone resources such as `.cfg`, `.xml`, `.wav` and
`.jpg`. The extraction discards each `.php` file in a tarball.

Thus a **new** brand or family needs its `phone.php` here, in a module
release. Existing families can change without a release, because model
lists, template data and config templates are all data.

## Update the library

1. Copy the PHP files and `endpoint/global_template_data.json` from an
   upstream checkout. Keep the directory layout:

   ```sh
   cd /path/to/provisioner
   for f in $(git ls-files endpoint | grep '\.php$'); do
       mkdir -p /path/to/endpointman/lib/provisioner/$(dirname $f)
       cp $f /path/to/endpointman/lib/provisioner/$(dirname $f)/
   done
   cp endpoint/global_template_data.json /path/to/endpointman/lib/provisioner/endpoint/
   ```

2. Write the upstream commit into the table above.
3. Apply the local patches below again.

## Local patches

The library needs these changes to run on PHP 8.2:

| File | Change |
| --- | --- |
| `endpoint/base.php` | Declares `public $processor_info`. The class reads the property and checks it with `isset()` in three places, but upstream does not declare it. An assignment is then a PHP 8.2 deprecation. |
| `endpoint/base.php` | Checks the result of `arraysearchrecursive()` before it uses it as an index. The function returns FALSE when a config file uses a variable that the family does not define. An index on FALSE is a PHP 8 warning, and FreePBX turns it into an exception. |
| `endpoint/cisco/{linksysata,spa,spa5xx}/phone.php` | `htmlentities($x, ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML401)`. This sets the flags that PHP 8.1 made the default, as `make compat` requires. |

`autoload.php` does **not** come from upstream. It is written for this
layout. It finds classes only in this directory, and it refuses a class name
that points outside it.
