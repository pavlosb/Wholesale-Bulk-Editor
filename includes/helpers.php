<?php
if (!defined('ABSPATH')) exit;

// Get all wholesale roles
function wbe_get_wholesale_roles() {
    $terms = get_terms([
        'taxonomy' => 'wholesale_user_roles',
        'hide_empty' => false,
    ]);
    $roles = [];
    foreach ($terms as $term) {
        $roles[$term->slug] = [
            'roleKey' => $term->slug,
            'roleName' => $term->name,
        ];
    }
    return $roles;
}

// For brand filter, use WooCommerce native brand taxonomy
function wbe_get_brands() {
    return get_terms([
        'taxonomy' => 'product_brand',
        'hide_empty' => false,
    ]);
}
