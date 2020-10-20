# Keys Master
[![version](https://badgen.net/github/release/Pierre-Lannoy/wp-keys-master/)](https://wordpress.org/plugins/keys-master/)
[![php](https://badgen.net/badge/php/7.2+/green)](https://wordpress.org/plugins/keys-master/)
[![wordpress](https://badgen.net/badge/wordpress/5.2+/green)](https://wordpress.org/plugins/keys-master/)
[![license](https://badgen.net/github/license/Pierre-Lannoy/wp-keys-master/)](/license.txt)

__Keys Master__ is a powerful sessions manager for WordPress with a multi-criteria sessions limiter and full analytics reporting about logins, logouts and account creation.

See [WordPress directory page](https://wordpress.org/plugins/keys-master/). 

You can limit concurrent sessions, on a per role basis for the following criteria:

* count per user;
* count per IP adresses;
* count per country (requires the free [GeoIP Detection](https://wordpress.org/plugins/geoip-detect/) plugin);
* count per device classes and types, client types, browser or OS (requires the free [Device Detector](https://wordpress.org/plugins/device-detector/) plugin).

For each roles defined on your site, you can also block login based on private/public IP ranges, and define idle times for sessions auto-termination.

__Keys Master__ can report the following main items and metrics:

* KPIs: login success, active sessions, cleaned sessions, active users, turnover and spam sessions;
* active and cleaned sessions details;
* users and sessions variations;
* moves distribution;
* login/logout breakdowns;
* password resets;

__Keys Master__ is a free and open source plugin for WordPress. It integrates many other free and open source works (as-is or modified). Please, see 'about' tab in the plugin settings to see the details.

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