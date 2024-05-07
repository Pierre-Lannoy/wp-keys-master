=== Keys Master ===
Contributors: PierreLannoy, hosterra
Tags: application password, authentication, rest-api, security, xml-rpc
Requires at least: 6.2
Requires PHP: 8.1
Tested up to: 6.5
Stable tag: 1.12.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Powerful application passwords manager for WordPress with role-based usage control and full analytics reporting capabilities.

== Description ==

**Keys Master** is a powerful application passwords manager for WordPress with role-based usage control and full analytics reporting about passwords usages. It relies on the "application password" core feature introduced in WordPress 5.6. and add it extra features and controls.

You can limit usage of application passwords, on a per role basis:

* maximum passwords per user;
* specific usage: none (blocks usage), only authentication and revocation or full management (with password creation).

For each roles defined on your site, you can define a period during which a password can be unused before auto-revocation.

**Keys Master** can report the following main items and metrics:

* KPIs: authentication success, number, creations and revocations of passwords, adoption and usage rate;
* channels breakdown;
* clients breakdown (requires the free [Device Detector](https://wordpress.org/plugins/device-detector/) plugin);
* countries breakdown (requires the free [IP Locator](https://wordpress.org/plugins/ip-locator/) plugin);
* site breakdowns in multisites environments.

**Keys Master** supports a set of WP-CLI commands to:
    
* manage WordPress application passwords (list, create and revoke) - see `wp help apwd password` for details;
* toggle on/off main settings - see `wp help apwd settings` for details;
* modify operations mode - see `wp help apwd mode` for details;
* display passwords statistics - see `wp help apwd analytics` for details.

For a full help on WP-CLI commands in Keys Master, please [read this guide](https://perfops.one/keys-master-wpcli).

> **Keys Master** is part of [PerfOps One](https://perfops.one/), a suite of free and open source WordPress plugins dedicated to observability and operations performance.

**Keys Master** is a free and open source plugin for WordPress. It integrates many other free and open source works (as-is or modified). Please, see 'about' tab in the plugin settings to see the details.

= Support =

This plugin is free and provided without warranty of any kind. Use it at your own risk, I'm not responsible for any improper use of this plugin, nor for any damage it might cause to your site. Always backup all your data before installing a new plugin.

Anyway, I'll be glad to help you if you encounter issues when using this plugin. Just use the support section of this plugin page.

= Privacy =

This plugin, as any piece of software, is neither compliant nor non-compliant with privacy laws and regulations. It is your responsibility to use it - by activating the corresponding options or services - with respect for the personal data of your users and applicable laws.

This plugin doesn't set any cookie in the user's browser.

This plugin doesn't handle personally identifiable information (PII).

= Donation =

If you like this plugin or find it useful and want to thank me for the work done, please consider making a donation to [La Quadrature Du Net](https://www.laquadrature.net/en) or the [Electronic Frontier Foundation](https://www.eff.org/) which are advocacy groups defending the rights and freedoms of citizens on the Internet. By supporting them, you help the daily actions they perform to defend our fundamental freedoms!

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'.
2. Search for 'Keys Master'.
3. Click on the 'Install Now' button.
4. Activate Keys Master.

= From WordPress.org =

1. Download Keys Master.
2. Upload the `keys-master` directory to your `/wp-content/plugins/` directory, using your favorite method (ftp, sftp, scp, etc...).
3. Activate Keys Master from your Plugins page.

= Once Activated =

1. Visit 'PerfOps One > Control Center > Keys Master' in the left-hand menu of your WP Admin to adjust settings.
2. Enjoy!

== Frequently Asked Questions ==

= What are the requirements for this plugin to work? =

You need at least **WordPress 5.6** and **PHP 7.2**.

= Can this plugin work on multisite? =

Yes. It is designed to work on multisite too. Network Admins can use all the plugin features. Sites Admins have no access to the plugin features.

= Where can I get support? =

Support is provided via the official [WordPress page](https://wordpress.org/support/plugin/keys-master/).

= Where can I report a bug? =
 
You can report bugs and suggest ideas via the [GitHub issue tracker](https://github.com/Pierre-Lannoy/wp-keys-master/issues) of the plugin.

== Changelog ==

Please, see [full changelog](https://perfops.one/keys-master-changelog).

== Upgrade Notice ==

== Screenshots ==

1. Application Passwords Management
2. Main Analytics Dashboard
3. Application Passwords Options Per Roles
4. Plugin Options
