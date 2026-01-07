=== Coupons Role Restriction for WooCommerce ===
Contributors: runthingsdev
Tags: woocommerce, coupons, user roles, role restriction, discount
Tested up to: 6.9
Requires at least: 6.4
Requires PHP: 7.4
Requires WooCommerce: 8.0
Stable tag: 1.1.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Restrict the usage of WooCommerce coupons based on user roles.

== Description ==

This plugin allows you to restrict the usage of WooCommerce coupons based on user roles, including guest users. 

You can specify which roles (including guests) are allowed or excluded from using a coupon, providing more control over your discount strategies.

= Features =
* Restrict coupon usage based on user roles.
* Option to specify both allowed and excluded roles.
* Support for guest users with a "Customer Is A Guest" pseudo-role.
* Customize the error message via a filter.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/runthings-wc-coupons-role-restrict` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce area of the admin panel, and look under Marketing > Coupons and edit or create a coupon.
4. In the "Usage restriction" tab, you will see the options to select allowed and excluded roles for the coupon, including the "Customer Is A Guest" pseudo-role.

== Frequently Asked Questions ==

= Why was this plugin created? =
This plugin was created to provide a more secure option for restricting coupon usage.

The default email usage restriction for coupons in WooCommerce is based on the unverified billing email address field, which can be freely set by users.

By using role restrictions, you can ensure that only verified and authorized users in specific roles—or guests, when appropriate—can use certain coupons, making it a secure option.

My personal motivation was to have 100% discount coupons that could be used only be staff or developers, without the possibility of this being exploited by somebody putting a fake staff email into the billing email address field.

= How do I restrict a coupon to specific roles? =
Edit the coupon and go to the "Usage restriction" tab. 

In the "Roles" section, select the roles allowed to use the coupon. 

If you want to exclude specific roles, select them in the "Excluded roles" section. For guest users, select or exclude the "Customer Is A Guest" pseudo-role.

= What happens if a role is both allowed and excluded? =
If a role is both allowed and excluded, the exclusion will take precedence, and users with that role will not be able to use the coupon.

= How do I allow a coupon for guests only? =
To restrict a coupon to guests only, select the "Customer Is A Guest" pseudo-role in the "Roles" section and leave all other roles unselected.

= Can I use this plugin with other WooCommerce coupon restrictions? =
Yes, this plugin works alongside other WooCommerce coupon restrictions such as minimum spend, maximum spend, and product restrictions.

== Screenshots ==

1. Coupon settings page with role restriction fields.
2. Coupon role selection field.
3. Example denied coupon usage due to invalid role.

== Changelog ==

= 1.1.3 - 6th January 2026 =
* Fixed missing "And" separator in the coupon usage restriction panel to match WooCommerce core styling.
* Move plugin directory assets to .wordpress-org/ folder.

= 1.1.2 - 17th December 2024 =
* Bump tested up to 6.9.

= 1.1.1 - 24th June 2024 =
* Bump WordPress tested up to field to support 6.8 branch.

= 1.1.0 - 17th November 2024 =
* Introduced "Customer Is A Guest" pseudo-role, enabling role restrictions to target guest users.
* Fixed a bug which would auto exclude the guest when any role was set as excluded.
* Added support for passing a context object to the `runthings_wc_coupons_role_restrict_error_message` filter.
* Context includes additional information such as `coupon`, `is_guest`, `user`, `allowed_roles`, `excluded_roles`, and `effective_allowed_roles`.
* Improved documentation for filters, including usage examples and detailed context information.
* Updated screenshots.

= 1.0.1 - 15th July 2024 =
* Fix code snippet formatting in documentation.

= 1.0.0 - 11th July 2024 =
* Initial release.
* Restrict coupons by role.
* Allow coupons by role.
* Filter `runthings_wc_coupons_role_restrict_error_message` to customise error message.

== Upgrade Notice ==

= 1.1.3 =
Fixed missing "And" separator in the coupon usage restriction panel to match WooCommerce core styling.

= 1.1.2 =
Bump tested up to 6.9.

= 1.1.1 =
Bump WordPress tested up to field to support 6.8 branch.

== Filters ==

#### runthings_wc_coupons_role_restrict_error_message

This filter allows customization of the error message shown when a coupon is not valid for the user's account type.

For detailed documentation and advanced examples, see the [full documentation on GitHub](https://github.com/runthings-dev/runthings-wc-coupons-role-restrict#filters).

##### Parameters:

1. **`$message`** (`string`): The default error message, e.g., `"Sorry, this coupon is not valid for your account type."`.
2. **`$context`** (`array`): Additional context for the error, including the coupon, user roles, and guest status.

##### `$context` object format:

The `$context` array contains the following keys:
- **`coupon`** (`WC_Coupon`): The coupon object being validated.
- **`is_guest`** (`bool`): Whether the current user is a guest (not logged in).
- **`user`** (`WP_User`): The current user object. For guests, this will be an empty user object.
- **`allowed_roles`** (`array`): Roles explicitly allowed to use the coupon, in the format `[role_id => role_name]`.
- **`excluded_roles`** (`array`): Roles explicitly excluded from using the coupon, in the format `[role_id => role_name]`.
- **`effective_allowed_roles`** (`array`): The final calculated roles allowed to use the coupon, after considering exclusions, in the format `[role_id => role_name]`. This is the set of roles that can use the coupon.

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see [http://www.gnu.org/licenses/gpl-3.0.html](http://www.gnu.org/licenses/gpl-3.0.html).

Icon - Discount by Gregor Cresnar, from Noun Project, https://thenounproject.com/browse/icons/term/discount/ (CC BY 3.0)

Icon - restriction by Puspito, from Noun Project, https://thenounproject.com/browse/icons/term/restriction/ (CC BY 3.0)