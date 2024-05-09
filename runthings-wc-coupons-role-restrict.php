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
 */
/*
Copyright 2024 Matthew Harris

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if (!defined('WPINC')) {
    die;
}

class Runthings_WC_Coupon_Role_Restrict
{

    public function __construct()
    {
        add_action('woocommerce_coupon_options_usage_restriction', array($this, 'add_role_restriction_fields'), 10, 0);
        add_action('woocommerce_coupon_options_save', array($this, 'save_role_restriction_fields'), 10, 1);
        add_filter('woocommerce_coupon_is_valid', array($this, 'validate_coupon_based_on_roles'), 10, 3);
    }

    public function add_role_restriction_fields()
    {
        global $post;
        $roles = get_editable_roles();

        echo '<div class="options_group">';

        $is_first = true;
        foreach ($roles as $key => $role) {
            $label_text = $is_first ? 'Allowed roles' : '';
            woocommerce_wp_checkbox(array(
                'id' => 'runthings_allowed_user_roles_' . esc_attr($key),
                'label' => $label_text,
                'description' => $role['name'],
                'desc_tip' => false,
                'value' => get_post_meta($post->ID, 'runthings_allowed_user_roles_' . $key, true)
            ));
            $is_first = false;
        }

        echo '</div>';
    }

    public function save_role_restriction_fields($post_id)
    {
        $roles = get_editable_roles();

        foreach ($roles as $key => $role) {
            $checkbox_value = isset($_POST['runthings_allowed_user_roles_' . $key]) ? 'yes' : 'no';
            update_post_meta($post_id, 'runthings_allowed_user_roles_' . $key, $checkbox_value);
        }
    }

    public function validate_coupon_based_on_roles($valid, $coupon, $discount)
    {
        if (!$valid) {
            return $valid;
        }

        $roles = get_editable_roles();
        $user = wp_get_current_user();
        $role_valid = false;
        $any_role_selected = false;

        foreach ($roles as $key => $role) {
            $role_setting = get_post_meta($coupon->get_id(), 'runthings_allowed_user_roles_' . $key, true);
            if ($role_setting === 'yes') {
                $any_role_selected = true;
                if (in_array($key, $user->roles)) {
                    $role_valid = true;
                    break;
                }
            }
        }

        // If no roles are selected on the coupon, skip the role check
        if (!$any_role_selected) {
            return $valid;
        }

        // If roles are selected but none match the user's roles, invalidate the coupon
        if (!$role_valid && $any_role_selected) {
            throw new Exception('Sorry, this coupon is not valid for your account type.');
            return false;
        }

        return $role_valid;
    }
}

new Runthings_WC_Coupon_Role_Restrict();
