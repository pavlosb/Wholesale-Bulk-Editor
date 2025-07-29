<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_wbe_save_wholesale_prices', function() {
    check_ajax_referer('wbe_nonce', 'nonce');
    $product_id = absint($_POST['product_id']);
    $prices = $_POST['prices'];
    $meta_key = 'wholesale_multi_user_pricing';

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    if (!$product_id || !is_array($prices)) {
        wp_send_json_error(['message' => 'Invalid data.']);
    }
    $meta = get_post_meta($product_id, $meta_key, true);
    if (!is_array($meta)) $meta = [];

    $product = wc_get_product($product_id);
    $is_variation = $product && $product->is_type('variation');

    $roles = wbe_get_wholesale_roles();
    foreach ($prices as $slug => $val) {
        $found = false;
        foreach ($meta as $role_id => $role_array) {
            if (isset($role_array['slug']) && $role_array['slug'] === $slug) {
                if ($is_variation) {
                    if ($val !== '') {
                        $meta[$role_id][$product_id] = [
                            'wholesaleprice' => $val,
                            'qty' => isset($role_array[$product_id]['qty']) ? $role_array[$product_id]['qty'] : 1,
                            'step' => isset($role_array[$product_id]['step']) ? $role_array[$product_id]['step'] : 1,
                        ];
                        $meta[$role_id]['discount_type'] = 'fixed';
                    } else {
                        unset($meta[$role_id][$product_id]);
                    }
                } else {
                    if ($val !== '') {
                        $meta[$role_id]['wholesale_price'] = $val;
                        $meta[$role_id]['discount_type'] = 'fixed';
                    } else {
                        unset($meta[$role_id]['wholesale_price']);
                    }
                }
                $found = true;
                break;
            }
        }
        if (!$found && $val !== '') {
            if ($is_variation) {
                $meta[] = [
                    'slug' => $slug,
                    'discount_type' => 'fixed',
                    $product_id => [
                        'wholesaleprice' => $val,
                        'qty' => 1,
                        'step' => 1,
                    ],
                ];
            } else {
                $meta[] = [
                    'slug' => $slug,
                    'discount_type' => 'fixed',
                    'wholesale_price' => $val,
                ];
            }
        }
    }
    update_post_meta($product_id, $meta_key, $meta);
    wp_send_json_success(['message' => 'Wholesale prices updated.']);
});
