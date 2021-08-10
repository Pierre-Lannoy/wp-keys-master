# Changelog
All notable changes to **Keys Master** are documented in this *changelog*.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and **Keys Master** adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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