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
        add_action('woocommerce_coupon_options_usage_restriction', array($this, 'add_role_restriction_fields'), 10);
        add_action('woocommerce_coupon_options_save', array($this, 'save_role_restriction_fields'), 10, 1);
        add_filter('woocommerce_coupon_is_valid', array($this, 'validate_coupon_based_on_roles'), 10, 3);
    }

    public function add_role_restriction_fields()
    {
        global $post;
        $roles = get_editable_roles();

        echo '<div class="options_group">';
        wp_nonce_field('runthings_save_roles', 'runthings_roles_nonce');

        $is_first = true;
        foreach ($roles as $key => $role) {
            $label_text = $is_first ? esc_html__('Allowed roles', 'runthings-wc-coupon-role-restrict') : '';
            woocommerce_wp_checkbox(
                array(
                    'id' => self::META_KEY_PREFIX . esc_attr($key),
                    'label' => esc_html($label_text),
                    'description' => esc_html($role['name']),
                    'desc_tip' => false,
                    'value' => esc_attr(get_post_meta($post->ID, self::META_KEY_PREFIX . $key, true)),
                )
            );
            $is_first = false;
        }

        echo '</div>';
    }

    public function save_role_restriction_fields($post_id)
    {
        if (!isset($_POST['runthings_roles_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['runthings_roles_nonce'])), 'runthings_save_roles')) {
            return;
        }

        $roles = get_editable_roles();

        foreach ($roles as $key => $role) {
            $meta_key = self::META_KEY_PREFIX . esc_attr($key);
            $checkbox_value = wc_bool_to_string(isset($_POST[$meta_key]));
            update_post_meta($post_id, $meta_key, sanitize_text_field($checkbox_value));
        }
    }

    public function validate_coupon_based_on_roles($valid, $coupon, $discount)
    {
        if (!$valid) {
            return $valid;
        }

        $roles = $this->get_all_roles();
        $user = wp_get_current_user();
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
            throw new Exception(esc_html__('Sorry, this coupon is not valid for your account type.', 'runthings-wc-coupon-role-restrict'));
            return false;
        }

        return $role_valid;
    }

    /**
     * Mimics the get_editable_roles() function in WordPress core, as its an admin-only function.
     */
    private function get_all_roles()
    {
        global $wp_roles;
        return isset($wp_roles) ? $wp_roles->get_names() : array();
    }
}

new Runthings_WC_Coupon_Role_Restrict();
