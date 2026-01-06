<?php

/**
 * Plugin Name: Coupons Role Restriction for WooCommerce
 * Plugin URI: https://runthings.dev/wordpress-plugins/wc-coupons-role-restrict/
 * Description: Restrict the usage of coupons based on user roles.
 * Version: 1.1.2
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
use WP_User;
use WC_Coupon;
use WC_Discounts;

if (!defined('WPINC')) {
    die;
}

class CouponsRoleRestrict
{
    const PLUGIN_VERSION = '1.1.2';
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
        $roles = self::get_all_site_roles();

        echo '<div class="options_group">';
        echo '<div class="hr-section hr-section-coupon_restrictions">' . esc_html__('And', 'runthings-wc-coupons-role-restrict') . '</div>';
        wp_nonce_field('runthings_save_roles', 'runthings_roles_nonce');

        $allowed_roles = [];
        foreach ($roles as $role_id => $role_text) {
            $allowed_roles[] = [
                'id' => $role_id,
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

        $roles = self::get_all_site_roles();

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
            // should never occur, as fail throws an exception, but just in case
            return $valid;
        }

        $coupon_meta = $this->get_normalised_meta($coupon);
        $roles = self::get_all_site_roles();
        $user = wp_get_current_user();

        if (!$this->is_user_in_allowed_roles($roles, $user, $coupon_meta)) {
            return $this->handle_coupon_failure($coupon, $coupon_meta, $user);
        }

        if ($this->is_user_in_excluded_roles($roles, $user, $coupon_meta)) {
            return $this->handle_coupon_failure($coupon, $coupon_meta, $user);
        }

        return true;
    }

    /**
     * Checks if the user passes the allowed roles criteria.
     * If no roles are set, all users are allowed.
     * 
     * @param array    $roles  An array of all roles available in the system.
     * @param WP_User  $user   The user to check.
     * @param WC_Coupon $coupon The coupon being validated.
     * @return bool Whether the user passes the allowed roles criteria.
     */
    private function is_user_in_allowed_roles(array $roles, WP_User $user, array $coupon_meta): bool
    {
        $user_is_guest = !$user->exists();
        $any_allowed_roles_set = false;

        foreach ($roles as $key => $role) {
            $allowed_meta_key = self::ALLOWED_META_KEY_PREFIX . $key;
            $role_setting = $coupon_meta[$allowed_meta_key] ?? '';
            $role_allowed = wc_string_to_bool($role_setting);

            if ($role_allowed) {
                $any_allowed_roles_set = true;

                if (($key === self::GUEST_ROLE && $user_is_guest)) {
                    return true; // User matches allowed guest status
                }

                if (in_array($key, $user->roles, true)) {
                    return true; // User matches an allowed role
                }
            }
        }

        return !$any_allowed_roles_set; // If no allowed roles are set, everyone is allowed
    }

    /**
     * Checks if the user passes the excluded roles criteria.
     * If no roles are set, no users are allowed.
     * 
     * @param array    $roles  An array of all roles available in the system.
     * @param WP_User  $user   The user to check.
     * @param WC_Coupon $coupon The coupon being validated.
     * @return bool Whether the user passes the excluded roles criteria.
     */
    private function is_user_in_excluded_roles(array $roles, WP_User $user, array $coupon_meta): bool
    {
        $user_is_guest = !$user->exists();

        foreach ($roles as $key => $role) {
            $excluded_meta_key = self::EXCLUDED_META_KEY_PREFIX . $key;
            $role_setting = $coupon_meta[$excluded_meta_key] ?? '';
            $role_excluded = wc_string_to_bool($role_setting);

            if ($role_excluded) {
                if (($key === self::GUEST_ROLE && $user_is_guest)) {
                    return true; // User matches excluded guest status
                }

                if (in_array($key, $user->roles, true)) {
                    return true; // User matches an excluded role
                }
            }
        }

        return false; // User does not match any excluded role
    }

    /**
     * Handles a coupon validation failure.
     *
     * @param WC_Coupon $coupon The coupon that failed validation.
     * @param WP_User   $user   The user that failed validation.
     * @return bool Always returns false.
     * @throws Exception WooCommerce uses exceptions to signal coupon validation failure.
     */
    private function handle_coupon_failure(WC_Coupon $coupon, array $coupon_meta, WP_User $user): bool
    {
        $user_is_guest = !$user->exists();

        $roles = self::get_all_site_roles();
        $role_restrictions = $this->collect_role_restrictions($coupon_meta, $roles);

        $error_context = [
            'coupon' => $coupon,
            'is_guest' => $user_is_guest,
            'guest_role_id' => self::GUEST_ROLE,
            'user' => $user,
            'allowed_roles' => $role_restrictions['allowed_roles'],
            'excluded_roles' => $role_restrictions['excluded_roles'],
            'effective_allowed_roles' => $role_restrictions['effective_allowed_roles'],
        ];

        $coupon_code = sanitize_text_field($coupon->get_code());
        $user_roles = $user_is_guest
            ? __('Guest', 'runthings-wc-coupons-role-restrict')
            : implode(', ', array_map('sanitize_text_field', $user->roles));

        wc_get_logger()->error('Coupon validation failed for user role. Coupon code: ' . $coupon_code . '. User roles: ' . $user_roles, ['source' => 'runthings-wc-coupons-role-restrict']);

        $error_message = apply_filters('runthings_wc_coupons_role_restrict_error_message', __('Sorry, this coupon is not valid for your account type.', 'runthings-wc-coupons-role-restrict'), $error_context);
        throw new Exception(esc_html($error_message));

        return false;
    }

    /**
     * Collect role restrictions for the coupon.
     *
     * @param array  $coupon_meta All meta data for the coupon.
     * @param array  $roles All possible roles, including guest.
     * @return array Allowed and excluded roles in id/name pairs.
     */
    private function collect_role_restrictions(array $coupon_meta, array $roles): array
    {
        $allowed_roles = [];
        $excluded_roles = [];
        $effective_allowed_roles = $roles;

        foreach ($roles as $key => $role_name) {
            $allowed_meta_key = self::ALLOWED_META_KEY_PREFIX . $key;
            $excluded_meta_key = self::EXCLUDED_META_KEY_PREFIX . $key;

            if (!empty($coupon_meta[$allowed_meta_key]) && wc_string_to_bool($coupon_meta[$allowed_meta_key])) {
                $allowed_roles[$key] = $role_name;
            }
            if (!empty($coupon_meta[$excluded_meta_key]) && wc_string_to_bool($coupon_meta[$excluded_meta_key])) {
                $excluded_roles[$key] = $role_name;
            }
        }

        // Remove excluded roles from the effective roles
        $effective_allowed_roles = array_diff_key($effective_allowed_roles, $excluded_roles);

        // If there are explicitly allowed roles, limit effective roles to those
        if (!empty($allowed_roles)) {
            $effective_allowed_roles = array_intersect_key($effective_allowed_roles, $allowed_roles);
        }

        return [
            'allowed_roles' => $allowed_roles,
            'excluded_roles' => $excluded_roles,
            'effective_allowed_roles' => $effective_allowed_roles,
        ];
    }

    /**
     * Get all meta for a coupon, and flatten any single-value arrays.
     * 
     * @param WC_Coupon $coupon The coupon to get meta for.
     * @return array The normalised meta data.
     */
    private function get_normalised_meta(WC_Coupon $coupon): array
    {
        $coupon_meta_raw = get_post_meta($coupon->get_id());

        $coupon_meta = array_map(function ($meta_value) {
            return is_array($meta_value) && count($meta_value) === 1 ? $meta_value[0] : $meta_value;
        }, $coupon_meta_raw);

        return $coupon_meta;
    }

    /**
     * Gets all roles available in the system, including the guest role as the first entry.
     * Based on the get_editable_roles() function in WordPress core, as it's an admin-only function.
     *
     * @return array An array of role names, with the guest role as the first entry.
     */
    private static function get_all_site_roles(): array
    {
        global $wp_roles;

        // Get all roles, or an empty array if none are available
        $roles = isset($wp_roles) ? $wp_roles->get_names() : [];

        // Prepend the guest role
        return [self::GUEST_ROLE => esc_html__('Customer Is A Guest', 'runthings-wc-coupons-role-restrict')] + $roles;
    }
}

new CouponsRoleRestrict();
