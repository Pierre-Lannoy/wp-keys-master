# Changelog
All notable changes to **Keys Master** are documented in this *changelog*.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and **Keys Master** adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2024-05-28

### Added
- [BC] To enable installation on more heterogeneous platforms, the plugin now adapts its internal logging mode to already loaded libraries.

### Changed
- Updated DecaLog SDK from version 4.1.0 to version 5.0.0.

### Fixed
- PHP error with some plugins like Woocommerce Paypal Payments.

## [1.12.0] - 2024-05-07

### Changed
- The plugin now adapts its requirements to the PSR-3 loaded version.

## [1.11.2] - 2024-05-04

### Fixed
- PHP error when DecaLog is not installed.

## [1.11.1] - 2024-05-04

### Changed
- Updated DecaLog SDK from version 3.0.0 to version 4.1.0.
- Minimal required WordPress version is now 6.2.

## [1.11.0] - 2024-03-02

### Added
- Compatibility with WordPress 6.5.

### Changed
- Minimal required WordPress version is now 6.1.
- Minimal required PHP version is now 8.1.

## [1.10.0] - 2023-10-25

### Added
- Compatibility with WordPress 6.4.

### Fixed
- With PHP 8.2, in some edge cases, deprecation warnings may be triggered when viewing analytics.

## [1.9.0] - 2023-07-12

### Added
- Compatibility with WordPress 6.3.

### Changed
- The color for `shmop` test in Site Health is now gray to not worry to much about it (was previously orange).

### Fixed
- In some edge-cases, detecting idle time may produce PHP warnings (thanks to [YR Chen](https://github.com/stevapple)).

## [1.8.2] - 2023-04-07

### Fixed
- Auto expiration can throw an exception while forced in cron or ajax (thanks to [YR Chen](https://github.com/stevapple)).

## [1.8.1] - 2023-03-02

### Fixed
- [SEC003] CSRF vulnerability / [CVE-2023-27444](https://www.cve.org/CVERecord?id=CVE-2023-27444) (thanks to [Mika](https://patchstack.com/database/researcher/5ade6efe-f495-4836-906d-3de30c24edad) from [Patchstack](https://patchstack.com)).

## [1.8.0] - 2023-02-23

The developments of PerfOps One suite, of which this plugin is a part, is now sponsored by [Hosterra](https://hosterra.eu).

Hosterra is a web hosting company I founded in late 2022 whose purpose is to propose web services operating in a European data center that is water and energy efficient and ensures a first step towards GDPR compliance.

This sponsoring is a way to keep PerfOps One plugins suite free, open source and independent.

### Added
- Compatibility with WordPress 6.2.

### Changed
- Improved loading by removing unneeded jQuery references in public rendering (thanks to [Kishorchand](https://github.com/Kishorchandth)).

### Fixed
- In some edge-cases, detecting IP may produce PHP deprecation warnings (thanks to [YR Chen](https://github.com/stevapple)).

## [1.7.0] - 2022-10-06

### Added
- Compatibility with WordPress 6.1.
- [WPCLI] The results of `wp apwd` commands are now logged in [DecaLog](https://wordpress.org/plugins/decalog/).

### Changed
- Improved ephemeral cache in analytics.
- [WPCLI] The results of `wp apwd` commands are now prefixed by the product name.

### Fixed
- [SEC002] Moment.js library updated to 2.29.4 / [Regular Expression Denial of Service (ReDoS)](https://github.com/moment/moment/issues/6012).

## [1.6.0] - 2022-04-20

### Added
- Compatibility with WordPress 6.0.

### Changed
- Site Health page now presents a much more realistic test about object caching.
- Updated DecaLog SDK from version 2.0.2 to version 3.0.0.

### Fixed
- [SEC001] Moment.js library updated to 2.29.2 / [CVE-2022-24785](https://github.com/advisories/GHSA-8hfj-j24r-96c4).

## [1.5.1] - 2022-01-17

### Fixed
- The Site Health page may launch deprecated tests.

## [1.5.0] - 2022-01-17

### Added
- Compatibility with PHP 8.1.

### Changed
- Updated DecaLog SDK from version 2.0.0 to version 2.0.2.
- Updated PerfOps One library from 2.2.1 to 2.2.2.
- Refactored cache mechanisms to fully support Redis and Memcached.
- Improved bubbles display when width is less than 500px (thanks to [Pat Ol](https://profiles.wordpress.org/pasglop/)).
- The tables headers have now a better contrast (thanks to [Paul Bonaldi](https://profiles.wordpress.org/bonaldi/)).

### Fixed
- Object caching method may be wrongly detected in Site Health status (thanks to [freshuk](https://profiles.wordpress.org/freshuk/)).
- The console menu may display an empty screen (thanks to [Renaud Pacouil](https://www.laboiteare.fr)).
- There may be name collisions with internal APCu cache.
- An innocuous Mysql error may be triggered at plugin activation.

## [1.4.0] - 2021-12-07

### Added
- Compatibility with WordPress 5.9.
- New button in settings to install recommended plugins.
- The available hooks (filters and actions) are now described in `HOOKS.md` file.

### Changed
- Improved update process on high-traffic sites to avoid concurrent resources accesses.
- Better publishing frequency for metrics.
- Updated labels and links in plugins page.
- X axis for graphs have been redesigned and are more accurate.
- Updated the `README.md` file.

### Fixed
- Country translation with i18n module may be wrong.

## [1.3.0] - 2021-09-07

### Added
- It's now possible to hide the main PerfOps One menu via the `poo_hide_main_menu` filter or each submenu via the `poo_hide_analytics_menu`, `poo_hide_consoles_menu`, `poo_hide_insights_menu`, `poo_hide_tools_menu`, `poo_hide_records_menu` and `poo_hide_settings_menu` filters (thanks to [Jan Thiel](https://github.com/JanThiel)).

### Changed
- Updated DecaLog SDK from version 1.2.0 to version 2.0.0.

### Fixed
- There may be name collisions for some functions if version of WordPress is lower than 5.6.
- The main PerfOps One menu is not hidden when it doesn't contain any items (thanks to [Jan Thiel](https://github.com/JanThiel)).
- In some very special conditions, the plugin may be in the default site language rather than the user's language.
- The PerfOps One menu builder is not compatible with Admin Menu Editor plugin (thanks to [dvokoun](https://wordpress.org/support/users/dvokoun/)).

## [1.2.1] - 2021-08-11

### Changed
- New redesigned UI for PerfOps One plugins management and menus (thanks to [Loïc Antignac](https://github.com/webaxones), [Paul Bonaldi](https://profiles.wordpress.org/bonaldi/), [Axel Ducoron](https://github.com/aksld), [Laurent Millet](https://profiles.wordpress.org/wplmillet/), [Samy Rabih](https://github.com/samy) and [Raphaël Riehl](https://github.com/raphaelriehl) for their invaluable help).
- There's now a `perfopsone_advanced_controls` filter to display advanced plugin settings.

### Fixed
- In some conditions, the plugin may be in the default site language rather than the user's language.

## [1.2.0] - 2021-06-22

### Added
- Compatibility with WordPress 5.8.
- Integration with DecaLog SDK.
- Traces and metrics collation and publication.
- New option, available via settings page and wp-cli, to disable/enable metrics collation.

### Changed
- [WP-CLI] `apwd status` command now displays DecaLog SDK version too.

## [1.1.0] - 2021-02-24

### Added
- Compatibility with WordPress 5.7.

### Changed
- Consistent reset for settings.
- There are some typos in the changelog.
- [WP_CLI] `apwd` command have now a definition and all synopsis are up to date.

### Fixed
- In Site Health section, Opcache status may be wrong (or generates PHP warnings) if OPcache API usage is restricted.

## [1.0.4] - 2021-01-05

### Changed
- Improved translation loading.

### Fixed
- PHP notice while displaying usage text (thanks to [Axel Ducoron](https://github.com/aksld)).

## [1.0.3] - 2020-12-07

### Changed
- [WP-CLI] Main command is now named `apwd`.
- Integrates improvements packed with RC3/RC4, Keys Master is now ready for WordPress 5.6 release.

### Fixed
- There are some typos in the documentation.
- Some strings are not translatable.

## [1.0.2] - 2020-12-04

### Changed
- [WP-CLI] Adds help section and documentation in the `readme.txt` file.

### Fixed
- There are some typos in the `readme.txt` file.
- Dates are erroneous in changelog.

## [1.0.1] - 2020-11-25

### Changed
- The specified compatibility is now WordPress 5.6 or higher.

## [1.0.0] - 2020-11-24

Initial release