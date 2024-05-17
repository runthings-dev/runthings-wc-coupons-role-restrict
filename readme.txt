=== Coupons Role Restriction for WooCommerce ===
Contributors: runthingsdev
Donate link: https://runthings.dev
Tags: woocommerce, coupons, user roles, role restriction, discount
Tested up to: 6.5.3
Requires at least: 4.7
Requires PHP: 5.4
Requires WooCommerce: 3.0
Stable tag: 0.5.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Restrict the usage of WooCommerce coupons based on user roles.

== Description ==

This plugin allows you to restrict the usage of WooCommerce coupons based on user roles. You can specify which roles are allowed or excluded from using a coupon, providing more control over your discount strategies.

= Features =
* Restrict coupon usage based on user roles.
* Select roles using a user-friendly Select2 interface.
* Option to specify both allowed and excluded roles.
* Compatible with WooCommerce 5.0 and above.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/runthings-wc-coupons-role-restrict` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Coupons and edit or create a coupon.
4. In the "Usage restriction" tab, you will see the options to select allowed and excluded roles for the coupon.

== Frequently Asked Questions ==

= How do I restrict a coupon to specific roles? =
Edit the coupon and go to the "Usage restriction" tab. In the "Roles" section, select the roles allowed to use the coupon. If you want to exclude specific roles, select them in the "Excluded roles" section.

= What happens if a role is both allowed and excluded? =
If a role is both allowed and excluded, the exclusion will take precedence, and users with that role will not be able to use the coupon.

= Can I use this plugin with other WooCommerce coupon restrictions? =
Yes, this plugin works alongside other WooCommerce coupon restrictions such as minimum spend, maximum spend, and product restrictions.

== Screenshots ==

1. Coupon settings page with role restriction fields.
![Screenshot 1](assets/screenshot-1.png)

== Changelog ==

= 0.5.0 =
* Initial release.

== Upgrade Notice ==

= 0.5.0 =
Initial release of the plugin. No upgrade steps required.

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see [http://www.gnu.org/licenses/gpl-3.0.html](http://www.gnu.org/licenses/gpl-3.0.html).
