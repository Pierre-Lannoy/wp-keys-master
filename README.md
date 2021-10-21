# Keys Master
[![version](https://badgen.net/github/release/Pierre-Lannoy/wp-keys-master/)](https://wordpress.org/plugins/keys-master/)
[![php](https://badgen.net/badge/php/7.2+/green)](https://wordpress.org/plugins/keys-master/)
[![wordpress](https://badgen.net/badge/wordpress/5.6+/green)](https://wordpress.org/plugins/keys-master/)
[![license](https://badgen.net/github/license/Pierre-Lannoy/wp-keys-master/)](/license.txt)

__Keys Master__ is a powerful application passwords manager for WordPress with role-based usage control and full analytics reporting about passwords usages. It relies on the "application password" core feature introduced in WordPress 5.6. and add it extra features and controls.

See [WordPress directory page](https://wordpress.org/plugins/keys-master/) or [official website](https://perfops.one/keys-master). 

You can limit usage of application passwords, on a per role basis:

* maximum passwords per user;
* specific usage: none (blocks usage), only authentication and revocation or full management (with password creation).

For each roles defined on your site, you can define a period during which a password can be unused before auto-revocation.

__Keys Master__ can report the following main items and metrics:

* KPIs: authentication success, number, creations and revocations of passwords, adoption and usage rate;
* channels breakdown;
* clients breakdown (requires the free [Device Detector](https://wordpress.org/plugins/device-detector/) plugin);
* countries breakdown (requires the free [IP Locator](https://wordpress.org/plugins/ip-locator/) plugin);
* site breakdowns in multisites environments.

> __Keys Master__ is part of [PerfOps One](https://perfops.one/), a suite of free and open source WordPress plugins dedicated to observability and operations performance.

__Keys Master__ is a free and open source plugin for WordPress. It integrates many other free and open source works (as-is or modified). Please, see 'about' tab in the plugin settings to see the details.

## WP-CLI

__Keys Master__ implements a set of WP-CLI commands. For a full help on these commands, please read [this guide](WP-CLI.md).

## Hooks

__Keys Master__ introduces some filters and actions to allow plugin customization. Please, read the [hooks reference](HOOKS.md) to learn more about them.

## Installation

1. From your WordPress dashboard, visit _Plugins | Add New_.
2. Search for 'Keys Master'.
3. Click on the 'Install Now' button.

You can now activate __Keys Master__ from your _Plugins_ page.

## Support

For any technical issue, or to suggest new idea or feature, please use [GitHub issues tracker](https://github.com/Pierre-Lannoy/wp-keys-master/issues). Before submitting an issue, please read the [contribution guidelines](CONTRIBUTING.md).

Alternatively, if you have usage questions, you can open a discussion on the [WordPress support page](https://wordpress.org/support/plugin/keys-master/). 

## Contributing

Before submitting an issue or a pull request, please read the [contribution guidelines](CONTRIBUTING.md).

> ⚠️ The `master` branch is the current development state of the plugin. If you want a stable, production-ready version, please pick the last official [release](https://github.com/Pierre-Lannoy/wp-keys-master/releases).

## Smoke tests
[![WP compatibility](https://plugintests.com/plugins/keys-master/wp-badge.svg)](https://plugintests.com/plugins/keys-master/latest)
[![PHP compatibility](https://plugintests.com/plugins/keys-master/php-badge.svg)](https://plugintests.com/plugins/keys-master/latest)