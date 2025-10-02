# Static Deploy

[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/staticweb-io/static-deploy)

A WordPress plugin for static site generation and
deployment.

Static Deploy is a fork of
[Leon Stafford's](https://github.com/leonstafford)
[WP2Static](https://github.com/elementor/wp2static)
with many
[improvements.](https://github.com/staticweb-io/static-deploy/blob/develop/CHANGELOG.md)
After development of WP2Static stalled for several
years, we forked it and continue to maintain it.

## Installation

Download a zip file from the
[releases page](https://github.com/staticweb-io/static-deploy/releases).
and install it in your WordPress site.

**Advanced:** If you have
[Nix](https://docs.determinate.systems/determinate-nix/#getting-started)
installed, you can build from source via
`nix build github:staticweb-io/static-deploy#plugin`.
This will create a zip file at `result/static-deploy.zip`
which you can then install in your WordPress site.

## Support

Please
[open an issue](https://github.com/staticweb-io/static-deploy/issues/new)
for new support questions.

## Documentation

A
[DeepWiki](https://deepwiki.com/staticweb-io/static-deploy)
is available.

## Migrating from WP2Static

Install the plugin and run
`wp static-deploy import_wp2static_options --all`.
This will import all the core plugin options and the S3
addon options. You will still need to enable deployment
addons and import options for any other deployment addons
if you have them.

## Development

Development requires installing
[Nix](https://docs.determinate.systems/determinate-nix/#getting-started).

After checking out this repository and making changes,
you can build the plugin with your changes by running
`nix build .#plugin`.
This will create a zip file at `result/static-deploy.zip`.

There is a slightly different build for the WordPress.org repo.
This has a few differences like getting updates from WordPress.org instead of from GitHub.
You can build it with `nix build .#pluginWpOrg`.

If you make changes to composer.json, or composer.lock,
you will need to update the vendorHashes in flake.nix
by running
`nix develop ./dev -c bin/update-hashes`.

You can run the development environment via
`cd dev && nix run`.
This starts MySQL, PHP-FPM, and Nginx running WordPress
with this plugin installed.
The WordPress site is available at
`http://localhost:8888`
with credentials "user" and "pass".

The `release` dir contains the `constants.php` files that are used for each build.
[Rector](https://github.com/rectorphp/rector) rewrites the code for each build based on the defined constants.

### Testing

To run the tests, run `nix flake check ./dev` from
within this repository.

Test results are available on the
[actions page](https://github.com/staticweb-io/static-deploy/actions).
