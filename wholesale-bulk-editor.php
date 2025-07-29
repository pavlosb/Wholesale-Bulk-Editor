<?php
/*
Plugin Name: Wholesale Bulk Editor (pagination, native brand filter, sticky header fix)
Description: Bulk editor for WooCommerce wholesale prices with pagination, native WooCommerce brand filter, and reliable sticky table header.
Version: 2.1
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Wholesale Bulk Editor',
        'Wholesale Bulk Editor',
        'manage_woocommerce',
        'wholesale-bulk-editor',
        'wbe_render_bulk_editor'
    );
});

add_action( 'admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'wholesale-bulk-editor') {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'WBE_AJAX', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wbe_nonce'),
        ));
    }
});

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

// Use WooCommerce native brand taxonomy (since Woo 8.3+)
define('WBE_BRAND_TAX', 'product_brand');

function wbe_render_bulk_editor() {
    $meta_key = 'wholesale_multi_user_pricing';
    $roles = wbe_get_wholesale_roles();
    if (empty($roles)) {
        echo '<div class="notice notice-warning"><p>No wholesale roles found. Please check Wholesale for WooCommerce plugin settings.</p></div>';
        return;
    }

    // Pagination setup
    $posts_per_page = 20;
    $current_page = max(1, intval($_GET['wbe_paged'] ?? 1));

    // Filters
    $search = $_GET['wbe_search'] ?? '';
    $category = $_GET['wbe_category'] ?? '';
    $brand = $_GET['wbe_brand'] ?? '';

    // For brands, use 'product_brand'
    $brands = get_terms([
        'taxonomy' => WBE_BRAND_TAX,
        'hide_empty' => false,
    ]);
    ?>
    <div class="wrap">
        <h1>Wholesale Bulk Editor</h1>
        <form method="get" id="wbe-filters" style="margin-bottom: 12px;">
            <input type="hidden" name="page" value="wholesale-bulk-editor" />
            <input type="text" name="wbe_search" value="<?php echo esc_attr($search); ?>" placeholder="Search products..." style="min-width:180px;" />
            <?php
            $categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ]);
            ?>
            <select name="wbe_category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($category, $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="wbe_brand">
                <option value="">All Brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?php echo esc_attr($b->slug); ?>" <?php selected($brand, $b->slug); ?>><?php echo esc_html($b->name); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button">Filter</button>
        </form>
        <div class="wbe-sticky-container">
        <table class="wp-list-table widefat fixed striped products wbe-sticky-header" style="margin-top:20px;">
            <thead>
                <tr>
                    <th>Product/Variation</th>
                    <th>SKU</th>
                    <th>Regular Price</th>
                    <th>Enable all</th>
                    <?php foreach ($roles as $role): ?>
                        <th><?php echo esc_html($role['roleName']); ?> Price</th>
                    <?php endforeach; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $args = [
                'post_type'      => 'product',
                'posts_per_page' => $posts_per_page,
                'paged'          => $current_page,
                'post_status'    => 'publish',
            ];
            if (!empty($search)) {
                $args['s'] = sanitize_text_field($search);
            }
            $tax_query = [];
            if (!empty($category)) {
                $tax_query[] = [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($category),
                ];
            }
            if (!empty($brand)) {
                $tax_query[] = [
                    'taxonomy' => WBE_BRAND_TAX,
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($brand),
                ];
            }
            if (!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }
            $products = new WP_Query($args);
            if ($products->have_posts()):
                while ($products->have_posts()): $products->the_post();
                    $product = wc_get_product(get_the_ID());
                    if (!$product) continue;

                    if ($product->is_type('simple')) {
                        wbe_output_editor_row($product, $roles, $meta_key);
                    } elseif ($product->is_type('variable')) {
                        ?>
                        <tr class="wbe-row-parent" style="background:#efefef;font-weight:bold;">
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>" target="_blank">
                                    <?php echo esc_html($product->get_name()); ?>
                                </a>
                                <span style="color:#999; margin-left:10px;">(Parent Variable Product)</span>
                            </td>
                            <td><?php echo esc_html($product->get_sku()); ?></td>
                            <td><?php echo wc_price($product->get_regular_price()); ?></td>
                            <td></td>
                            <?php foreach ($roles as $slug => $role): ?>
                                <td></td>
                            <?php endforeach; ?>
                            <td></td>
                        </tr>
                        <?php
                        $variation_ids = $product->get_children();
                        foreach ($variation_ids as $variation_id) {
                            $variation = wc_get_product($variation_id);
                            if ($variation) {
                                wbe_output_editor_row($variation, $roles, $meta_key, $product);
                            }
                        }
                    }
                endwhile;
                wp_reset_postdata();
            else:
                echo '<tr><td colspan="'.(5+count($roles)).'">No products found.</td></tr>';
            endif;
            ?>
            </tbody>
        </table>
        </div>
        <?php
        // Pagination controls
        $total_products = $products->found_posts ?? 0;
        $total_pages = max(1, ceil($total_products / $posts_per_page));
        if ($total_pages > 1): ?>
            <div style="margin:18px 0 6px 0; display: flex; justify-content: center; align-items: center; gap: 10px;">
                <?php
                // Retain all filters in pagination links
                $base_url = admin_url('admin.php?page=wholesale-bulk-editor');
                $qs = $_GET;
                ?>
                <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                <?php if ($current_page > 1): ?>
                    <a class="button" href="<?php
                        $qs['wbe_paged'] = $current_page - 1;
                        echo esc_url($base_url . '&' . http_build_query($qs));
                    ?>">« Previous</a>
                <?php endif; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a class="button" href="<?php
                        $qs['wbe_paged'] = $current_page + 1;
                        echo esc_url($base_url . '&' . http_build_query($qs));
                    ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <style>
    .wp-list-table td input.wbe-wholesale-input { width: 80px; }
    .wbe-row-parent { background: #f7f7f7; font-weight: bold; }
    .wbe-row-variation { background: #fcfcfc; }
    .wholesale-feedback { margin-left: 8px; font-weight: bold; }
    .wholesale-feedback.success { color: green; }
    .wholesale-feedback.error { color: red; }
    .wp-list-table td .wbe-copy-wrapper {
        display: flex;
        gap: 1px;
        align-items: center;
        flex-wrap: nowrap;
        margin-top: 3px;
    }
    .wp-list-table td .wbe-wholesale-input {
        min-width: 70px;
        box-sizing: border-box;
    }
    .wp-list-table td .wbe-role-checkbox {
        margin-right: 0;
    }
    .wp-list-table td .wbe-copy-across {
        padding: 2px 6px;
        font-size: 14px;
        background: none;
        border: none;
        cursor: pointer;
    }
    .wp-list-table td .wbe-copy-across .dashicons {
        font-size: 18px;
        line-height: 1;
        color: #2271b1;
    }
    .wp-list-table td .wbe-copy-across:hover .dashicons {
        color: #135e96;
    }
    /* Sticky table header fix */
    .wbe-sticky-container {
        overflow-x: auto;
        max-height: 70vh;
    }
    .wbe-sticky-header thead th {
        position: sticky;
        top: 32px; /* WP admin bar height */
        z-index: 20;
        background: #fff;
        box-shadow: 0 2px 3px rgba(0,0,0,0.03);
    }
    @media (max-width: 900px) {
        .wbe-sticky-header thead th {
            top: 55px;
        }
    }
    </style>
    <script>
    jQuery(function($){
        // Enable all checkbox functionality
        $('.wp-list-table').on('change', '.wbe-enable-all-checkbox', function(){
            var $row = $(this).closest('tr');
            var checked = $(this).is(':checked');
            $row.find('.wbe-role-checkbox').each(function(){
                $(this).prop('checked', checked).trigger('change');
            });
        });

        // Handle per-role enable/disable
        $('.wp-list-table').on('change', '.wbe-role-checkbox', function(){
            var $checkbox = $(this);
            var $input = $checkbox.closest('td').find('.wbe-wholesale-input');
            if ($checkbox.is(':checked')) {
                $input.prop('disabled', false);
            } else {
                $input.prop('disabled', true).val('');
            }
        });

        // Copy-across button
        $('.wp-list-table').on('click', '.wbe-copy-across', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.closest('.wbe-copy-wrapper').find('.wbe-wholesale-input');
            var val = $input.val();
            var $row = $btn.closest('tr');
            $row.find('.wbe-role-checkbox:checked').each(function(){
                var $in = $(this).closest('td').find('.wbe-wholesale-input');
                $in.val(val);
            });
        });

        // Save button
        $('.wbe-save-row').on('click', function(e){
            e.preventDefault();
            var $row = $(this).closest('tr');
            var product_id = $(this).data('product_id');
            var prices = {};
            $row.find('.wbe-role-checkbox').each(function(){
                var role = $(this).data('role');
                var $input = $(this).closest('td').find('.wbe-wholesale-input');
                if ($(this).is(':checked')) {
                    prices[role] = $input.val();
                } else {
                    prices[role] = '';
                }
            });
            var $btn = $(this);
            var $feedback = $row.find('.wholesale-feedback');
            $btn.prop('disabled', true).text('Saving...');
            $feedback.removeClass('success error').text('');
            $.post(WBE_AJAX.ajax_url, {
                action: 'wbe_save_wholesale_prices',
                nonce: WBE_AJAX.nonce,
                product_id: product_id,
                prices: prices
            }, function(resp){
                if(resp.success){
                    $btn.text('Saved!');
                    $feedback.addClass('success').text('Saved!');
                    setTimeout(function(){ $btn.text('Save').prop('disabled', false); $feedback.text(''); }, 1200);
                } else {
                    $btn.text('Error!');
                    $feedback.addClass('error').text(resp.data && resp.data.message ? resp.data.message : 'Error!');
                    setTimeout(function(){ $btn.text('Save').prop('disabled', false); $feedback.text(''); }, 1800);
                }
            });
        });
    });
    </script>
    <?php
}

function wbe_output_editor_row($product, $roles, $meta_key, $parent = null) {
    $product_id = $product->get_id();
    $is_variation = $product->is_type('variation');
    $meta = get_post_meta($product_id, $meta_key, true);
    if (!is_array($meta)) $meta = [];

    if ($is_variation && $parent) {
        $display_name = esc_html($product->get_name());
    } else {
        $edit_link = get_edit_post_link($product_id, '');
        $display_name = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($product->get_name()) . '</a>';
    }
    ?>
    <tr class="<?php echo $is_variation ? 'wbe-row-variation' : 'wbe-row-parent'; ?>">
        <td><?php echo $display_name; ?></td>
        <td><?php echo esc_html($product->get_sku()); ?></td>
        <td><?php echo wc_price($product->get_regular_price()); ?></td>
        <!-- Enable all checkbox -->
        <td style="text-align:center;">
            <input type="checkbox" class="wbe-enable-all-checkbox" title="Enable all roles for this product">
        </td>
        <?php foreach ($roles as $slug => $role): ?>
            <?php
            $price = '';
            $checked = false;
            foreach ($meta as $role_array) {
                if (isset($role_array['slug']) && $role_array['slug'] === $slug) {
                    if ($is_variation && isset($role_array[$product_id]['wholesaleprice'])) {
                        $price = $role_array[$product_id]['wholesaleprice'];
                        $checked = true;
                        break;
                    } elseif (!$is_variation && isset($role_array['wholesale_price'])) {
                        $price = $role_array['wholesale_price'];
                        $checked = true;
                        break;
                    }
                }
            }
            ?>
            <td>
                <div style="display:flex;align-items:center;justify-content:center;">
                    <input type="checkbox"
                        class="wbe-role-checkbox"
                        data-role="<?php echo esc_attr($slug); ?>"
                        <?php checked($checked); ?>
                    />
                </div>
                <div class="wbe-copy-wrapper">
                    <input type="text"
                        class="wbe-wholesale-input"
                        data-role="<?php echo esc_attr($slug); ?>"
                        value="<?php echo esc_attr($price); ?>"
                        <?php if (!$checked) echo 'disabled'; ?>
                    >
                    <button class="wbe-copy-across" title="Copy this price across">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
            </td>
        <?php endforeach; ?>
        <td>
            <button class="button wbe-save-row" data-product_id="<?php echo esc_attr($product_id); ?>">Save</button>
            <span class="wholesale-feedback"></span>
        </td>
    </tr>
    <?php
}

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
