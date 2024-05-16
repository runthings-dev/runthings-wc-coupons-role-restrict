<?php

/**
 * Plugin Name: Coupons Role Restriction for WooCommerce
 * Plugin URI: https://runthings.dev
 * Description: Restrict the usage of coupons based on user roles.
 * Version: 0.5.0
 * Author: runthingsdev
 * Author URI: https://runthings.dev/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: WooCommerce
 * Text Domain: runthings-wc-coupon-role-restrict
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

if (!defined('WPINC')) {
    die;
}

class Runthings_WC_Coupon_Role_Restrict
{

    const META_KEY_PREFIX = 'runthings_wc_role_restrict_allowed_roles_';

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_select2'));
        add_action('woocommerce_coupon_options_usage_restriction', array($this, 'add_role_restriction_fields'), 10);
        add_action('woocommerce_coupon_options_save', array($this, 'save_role_restriction_fields'), 10, 1);
        add_filter('woocommerce_coupon_is_valid', array($this, 'validate_coupon_based_on_roles'), 10, 3);
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('runthings-wc-coupon-role-restrict', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueues the Select2 library for the coupon role restriction fields.
     */
    public function enqueue_select2()
    {
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.min.js', array('jquery'), '4.0.13');
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', array(), '4.0.13');
    }

    /**
     * Adds role restriction fields to the coupon options.
     */
    public function add_role_restriction_fields()
    {
        global $post;
        $roles = get_editable_roles();

        echo '<div class="options_group">';
        wp_nonce_field('runthings_save_roles', 'runthings_roles_nonce');

        $allowed_roles = array();
        foreach ($roles as $key => $role) {
            $allowed_roles[] = array(
                'id'   => $key,
                'text' => $role['name'],
            );
        }

        $selected_roles = array();
        foreach ($roles as $key => $role) {
            if (get_post_meta($post->ID, self::META_KEY_PREFIX . $key, true) === 'yes') {
                $selected_roles[] = $key;
            }
        }

?>
        <p class="form-field">
            <label for="<?php echo esc_attr(self::META_KEY_PREFIX . 'allowed_roles'); ?>"><?php esc_html_e('Allowed roles', 'runthings-wc-coupon-role-restrict'); ?></label>
            <select id="<?php echo esc_attr(self::META_KEY_PREFIX . 'allowed_roles'); ?>" name="<?php echo esc_attr(self::META_KEY_PREFIX . 'allowed_roles'); ?>[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e('Any role', 'runthings-wc-coupon-role-restrict'); ?>">
                <?php
                foreach ($allowed_roles as $role) {
                    echo '<option value="' . esc_attr($role['id']) . '"' . (in_array($role['id'], $selected_roles, true) ? ' selected="selected"' : '') . '>' . esc_html($role['text']) . '</option>';
                }
                ?>
            </select>
            <?php echo wc_help_tip(esc_html__('Select the roles allowed to use this coupon.', 'runthings-wc-coupon-role-restrict')); ?>
        </p>
<?php

        echo '</div>';
    }

    /**
     * Saves role restriction fields for the coupon.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_role_restriction_fields($post_id)
    {
        if (!isset($_POST['runthings_roles_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['runthings_roles_nonce'])), 'runthings_save_roles')) {
            return;
        }

        $roles = get_editable_roles();

        // Reset all role meta
        foreach ($roles as $key => $role) {
            delete_post_meta($post_id, self::META_KEY_PREFIX . esc_attr($key));
        }

        // Save selected roles
        if (isset($_POST[self::META_KEY_PREFIX . 'allowed_roles'])) {
            $selected_roles = array_map('sanitize_text_field', wp_unslash($_POST[self::META_KEY_PREFIX . 'allowed_roles']));
            foreach ($selected_roles as $role) {
                update_post_meta($post_id, self::META_KEY_PREFIX . esc_attr($role), 'yes');
            }
        }
    }

    /**
     * Validates the coupon based on user roles.
     *
     * @param bool        $valid    Whether the coupon is valid.
     * @param WC_Coupon   $coupon   The coupon being validated.
     * @param WC_Discount $discount The discount object.
     * @return bool Whether the coupon is valid.
     */
    public function validate_coupon_based_on_roles($valid, $coupon, $discount)
    {
        if (!$valid) {
            return $valid;
        }

        $roles = self::get_all_roles();
        $user  = wp_get_current_user();
        $role_valid = false;
        $any_role_selected = false;

        foreach ($roles as $key => $role) {
            $role_setting = get_post_meta($coupon->get_id(), self::META_KEY_PREFIX . $key, true);
            $role_allowed = wc_string_to_bool($role_setting);
            if ($role_allowed) {
                $any_role_selected = true;
                if (in_array($key, $user->roles, true)) {
                    $role_valid = true;
                    break;
                }
            }
        }

        if (!$any_role_selected) {
            return $valid;
        }

        if (!$role_valid && $any_role_selected) {
            $coupon_code = sanitize_text_field($coupon->get_code());
            $user_roles = implode(', ', array_map('sanitize_text_field', $user->roles));
            error_log('Coupon validation failed for user role. Coupon code: ' . $coupon_code . '. User roles: ' . $user_roles, 0);
            throw new Exception(esc_html__('Sorry, this coupon is not valid for your account type.', 'runthings-wc-coupon-role-restrict'));
            return false;
        }

        return $role_valid;
    }

    /**
     * Gets all roles available in the system.
     * Mimics the get_editable_roles() function in WordPress core, as its an admin-only function.
     *
     * @return array An array of role names.
     */
    private static function get_all_roles()
    {
        global $wp_roles;
        return isset($wp_roles) ? $wp_roles->get_names() : array();
    }
}

new Runthings_WC_Coupon_Role_Restrict();
