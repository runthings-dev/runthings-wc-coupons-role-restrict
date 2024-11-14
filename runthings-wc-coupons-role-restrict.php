<?php

/**
 * Plugin Name: Coupons Role Restriction for WooCommerce
 * Plugin URI: https://runthings.dev/wordpress-plugins/wc-coupons-role-restrict/
 * Description: Restrict the usage of coupons based on user roles.
 * Version: 1.0.1
 * Author: runthingsdev
 * Author URI: https://runthings.dev/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: runthings-wc-coupons-role-restrict
 * Domain Path: /languages
 */

/*
Copyright 2024 Matthew Harris

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

namespace Runthings\WCCouponsRoleRestrict;

use Exception;
use WC_Coupon;
use WC_Discounts;

if (!defined('WPINC')) {
    die;
}

class CouponsRoleRestrict
{
    const PLUGIN_VERSION = '1.0.1';
    const ALLOWED_META_KEY_PREFIX = 'runthings_wc_role_restrict_allowed_roles_';
    const EXCLUDED_META_KEY_PREFIX = 'runthings_wc_role_restrict_excluded_roles_';
    const GUEST_ROLE = 'runthings_wc_coupons_role_restrict_guest';

    public function __construct()
    {
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'admin_notice_wc_inactive']);
            return;
        }

        if ($this->is_guest_role_conflicting()) {
            add_action('admin_notices', [$this, 'admin_notice_role_conflict']);
            return;
        }

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_select2']);
        add_action('woocommerce_coupon_options_usage_restriction', [$this, 'add_role_restriction_fields'], 10);
        add_action('woocommerce_coupon_options_save', [$this, 'save_role_restriction_fields'], 10, 1);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon_based_on_roles'], 10, 3);
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active(): bool
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true) ||
            (is_multisite() && array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', [])));
    }

    /**
     * Check if the guest role identifier is already in use.
     *
     * @return bool True if the guest role conflicts, false otherwise.
     */
    private function is_guest_role_conflicting(): bool
    {
        $roles = wp_roles()->get_names();
        return array_key_exists(self::GUEST_ROLE, $roles);
    }

    /**
     * Display an admin notice if WooCommerce is inactive.
     */
    public function admin_notice_wc_inactive(): void
    {
        echo '<div class="error"><p>';
        esc_html_e('Coupons Role Restriction for WooCommerce requires WooCommerce to be active. Please install and activate WooCommerce.', 'runthings-wc-coupons-role-restrict');
        echo '</p></div>';
    }

    /**
     * Display an admin notice if the guest role identifier conflicts with an existing role.
     */
    public function admin_notice_role_conflict(): void
    {
        echo '<div class="error"><p>';
        printf(
            /* translators: %s: guest role identifier */
            esc_html__('Coupons Role Restriction for WooCommerce could not be activated because the %s conflicts with an existing role. Please resolve this conflict and reactivate the plugin.', 'runthings-wc-coupons-role-restrict'),
            '<abbr title="' . esc_attr(self::GUEST_ROLE) . '">' . esc_html__('guest role', 'runthings-wc-coupons-role-restrict') . '</abbr>'
        );
        echo '</p></div>';
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain('runthings-wc-coupons-role-restrict', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueues the Select2 library for the coupon role restriction fields.
     */
    public function enqueue_select2(): void
    {
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.min.js', ['jquery'], self::PLUGIN_VERSION, true);
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', [], self::PLUGIN_VERSION);
    }

    /**
     * Adds role restriction fields to the coupon options.
     */
    public function add_role_restriction_fields(): void
    {
        global $post;
        $roles = self::get_all_roles();

        echo '<div class="options_group">';
        wp_nonce_field('runthings_save_roles', 'runthings_roles_nonce');

        $allowed_roles = [];
        foreach ($roles as $role_id => $role_text) {
            $allowed_roles[] = [
                'id'   => $role_id,
                'text' => $role_text,
            ];
        }

        $selected_allowed_roles = [];
        $selected_excluded_roles = [];
        foreach ($roles as $key => $role) {
            if (get_post_meta($post->ID, self::ALLOWED_META_KEY_PREFIX . $key, true) === 'yes') {
                $selected_allowed_roles[] = $key;
            }
            if (get_post_meta($post->ID, self::EXCLUDED_META_KEY_PREFIX . $key, true) === 'yes') {
                $selected_excluded_roles[] = $key;
            }
        }
?>
        <p class="form-field">
            <label for="<?php echo esc_attr(self::ALLOWED_META_KEY_PREFIX); ?>"><?php esc_html_e('Roles', 'runthings-wc-coupons-role-restrict'); ?></label>
            <select id="<?php echo esc_attr(self::ALLOWED_META_KEY_PREFIX); ?>" name="<?php echo esc_attr(self::ALLOWED_META_KEY_PREFIX); ?>[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e('Any role', 'runthings-wc-coupons-role-restrict'); ?>">
                <?php
                foreach ($allowed_roles as $role) {
                    echo '<option value="' . esc_attr($role['id']) . '"' . (in_array($role['id'], $selected_allowed_roles, true) ? ' selected="selected"' : '') . '>' . esc_html($role['text']) . '</option>';
                }
                ?>
            </select>
            <?php
            // reason: wc_help_tip already escapes the output
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wc_help_tip(__('Select the roles allowed to use this coupon.', 'runthings-wc-coupons-role-restrict'));
            ?>
        </p>

        <p class="form-field">
            <label for="<?php echo esc_attr(self::EXCLUDED_META_KEY_PREFIX); ?>"><?php esc_html_e('Excluded roles', 'runthings-wc-coupons-role-restrict'); ?></label>
            <select id="<?php echo esc_attr(self::EXCLUDED_META_KEY_PREFIX); ?>" name="<?php echo esc_attr(self::EXCLUDED_META_KEY_PREFIX); ?>[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e('No roles', 'runthings-wc-coupons-role-restrict'); ?>">
                <?php
                foreach ($allowed_roles as $role) {
                    echo '<option value="' . esc_attr($role['id']) . '"' . (in_array($role['id'], $selected_excluded_roles, true) ? ' selected="selected"' : '') . '>' . esc_html($role['text']) . '</option>';
                }
                ?>
            </select>
            <?php
            // reason: wc_help_tip already escapes the output
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wc_help_tip(__('Select the roles excluded from using this coupon.', 'runthings-wc-coupons-role-restrict'));
            ?>
        </p>
<?php
        echo '</div>';
    }

    /**
     * Saves role restriction fields for the coupon.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_role_restriction_fields(int $post_id): void
    {
        if (!isset($_POST['runthings_roles_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['runthings_roles_nonce'])), 'runthings_save_roles')) {
            return;
        }

        $roles = self::get_all_roles();

        // Reset all role meta
        foreach ($roles as $key => $role) {
            delete_post_meta($post_id, self::ALLOWED_META_KEY_PREFIX . esc_attr($key));
            delete_post_meta($post_id, self::EXCLUDED_META_KEY_PREFIX . esc_attr($key));
        }

        // Save selected allowed roles
        if (isset($_POST[self::ALLOWED_META_KEY_PREFIX])) {
            $selected_allowed_roles = array_map('sanitize_text_field', wp_unslash($_POST[self::ALLOWED_META_KEY_PREFIX]));
            foreach ($selected_allowed_roles as $role) {
                update_post_meta($post_id, self::ALLOWED_META_KEY_PREFIX . esc_attr($role), 'yes');
            }
        }

        // Save selected excluded roles
        if (isset($_POST[self::EXCLUDED_META_KEY_PREFIX])) {
            $selected_excluded_roles = array_map('sanitize_text_field', wp_unslash($_POST[self::EXCLUDED_META_KEY_PREFIX]));
            foreach ($selected_excluded_roles as $role) {
                update_post_meta($post_id, self::EXCLUDED_META_KEY_PREFIX . esc_attr($role), 'yes');
            }
        }
    }

    /**
     * Validates the coupon based on user roles.
     *
     * @param bool         $valid    Whether the coupon is valid.
     * @param WC_Coupon    $coupon   The coupon being validated.
     * @param WC_Discounts $discounts The discount object.
     * @return bool Whether the coupon is valid.
     */
    public function validate_coupon_based_on_roles(bool $valid, WC_Coupon $coupon, WC_Discounts $discounts): bool
    {
        if (!$valid) {
            return $valid;
        }

        $roles = self::get_all_roles();
        $user = wp_get_current_user();
        $user_is_guest = !$user->exists();
        $role_valid = true;
        $any_restriction_applied = false;

        foreach ($roles as $key => $role) {
            // Check for allowed roles
            $role_setting = get_post_meta($coupon->get_id(), self::ALLOWED_META_KEY_PREFIX . $key, true);
            $role_allowed = wc_string_to_bool($role_setting);

            if ($role_allowed) {
                $any_restriction_applied = true;
                if (($key === self::GUEST_ROLE && $user_is_guest) || in_array($key, $user->roles, true)) {
                    $role_valid = true;
                }
            }

            // Check for excluded roles
            $excluded_role_setting = get_post_meta($coupon->get_id(), self::EXCLUDED_META_KEY_PREFIX . $key, true);
            $role_excluded = wc_string_to_bool($excluded_role_setting);

            if ($role_excluded) {
                $any_restriction_applied = true;

                // Skip guest if not explicitly excluded
                if ($user_is_guest && $key !== self::GUEST_ROLE) {
                    continue;
                }

                // Invalidate for explicit guest exclusion or matched excluded role
                if (($key === self::GUEST_ROLE && $user_is_guest) || in_array($key, $user->roles, true)) {
                    $role_valid = false;
                    break;
                }
            }
        }

        // Final role validation state logging
        if (!$any_restriction_applied) {
            return $valid;
        }

        if (!$role_valid) {
            $coupon_code = sanitize_text_field($coupon->get_code());
            $user_roles = $user_is_guest ? __('Guest', 'runthings-wc-coupons-role-restrict') : implode(', ', array_map('sanitize_text_field', $user->roles));

            wc_get_logger()->error('Coupon validation failed for user role. Coupon code: ' . $coupon_code . '. User roles: ' . $user_roles, ['source' => 'runthings-wc-coupons-role-restrict']);

            $error_message = apply_filters('runthings_wc_coupons_role_restrict_error_message', __('Sorry, this coupon is not valid for your account type.', 'runthings-wc-coupons-role-restrict'));
            throw new Exception(esc_html($error_message));

            return false;
        }

        return $role_valid;
    }


    /**
     * Gets all roles available in the system, including the guest role as the first entry.
     * Mimics the get_editable_roles() function in WordPress core, as it's an admin-only function.
     *
     * @return array An array of role names, with the guest role as the first entry.
     */
    private static function get_all_roles(): array
    {
        global $wp_roles;

        // Get all roles, or an empty array if none are available
        $roles = isset($wp_roles) ? $wp_roles->get_names() : [];

        // Prepend the guest role
        return [self::GUEST_ROLE => esc_html__('Customer Is A Guest', 'runthings-wc-coupons-role-restrict')] + $roles;
    }
}

new CouponsRoleRestrict();
