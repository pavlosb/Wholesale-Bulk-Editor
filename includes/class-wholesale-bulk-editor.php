<?php
if (!defined('ABSPATH')) exit;

class Wholesale_Bulk_Editor_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Wholesale Bulk Editor',
            'Wholesale Bulk Editor',
            'manage_woocommerce',
            'wholesale-bulk-editor',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        $meta_key = 'wholesale_multi_user_pricing';
        $roles = wbe_get_wholesale_roles();
        if (empty($roles)) {
            echo '<div class="notice notice-warning"><p>No wholesale roles found. Please check Wholesale for WooCommerce plugin settings.</p></div>';
            return;
        }
        // Handle filters
        $per_page = isset($_GET['wbe_per_page']) ? max(1, intval($_GET['wbe_per_page'])) : 20;
        $current_page = max(1, intval($_GET['wbe_paged'] ?? 1));
        $search = $_GET['wbe_search'] ?? '';
        $category = $_GET['wbe_category'] ?? '';
        $brand = $_GET['wbe_brand'] ?? '';
        $brands = wbe_get_brands();
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
                <select name="wbe_per_page" onchange="this.form.submit();">
                    <?php foreach ([10, 20, 30, 50, 100] as $num): ?>
                        <option value="<?php echo $num; ?>" <?php selected($per_page, $num); ?>>Show <?php echo $num; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Filter</button>
            </form>
            <div class="wbe-sticky-container">
            <table class="wp-list-table widefat fixed striped products wbe-sticky-header" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>Product/Variation</th>
                        <th>Status</th>
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
                    'posts_per_page' => $per_page,
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
                        'taxonomy' => 'product_brand',
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
                            $this->output_editor_row($product, $roles, $meta_key);
                        } elseif ($product->is_type('variable')) {
                            // Get product post status
                            $product_post = get_post($product->get_id());
                            $status = $product_post ? $product_post->post_status : '';
                            list($status_label, $status_class) = $this->get_status_badge($status, false);
                            ?>
                            <tr class="wbe-row-parent" style="background:#efefef;font-weight:bold;">
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>" target="_blank">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </a>
                                    <span style="color:#999; margin-left:10px;">(Parent Variable Product)</span>
                                </td>
                                <td><span class="wbe-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
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
                                    $this->output_editor_row($variation, $roles, $meta_key, $product);
                                }
                            }
                        }
                    endwhile;
                    wp_reset_postdata();
                else:
                    echo '<tr><td colspan="'.(6+count($roles)).'">No products found.</td></tr>';
                endif;
                ?>
                </tbody>
            </table>
            </div>
            <?php
            // Pagination controls
            $total_products = $products->found_posts ?? 0;
            $total_pages = max(1, ceil($total_products / $per_page));
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
        <?php
    }

    public function output_editor_row($product, $roles, $meta_key, $parent = null) {
        $product_id = $product->get_id();
        $is_variation = $product->is_type('variation');
        $meta = get_post_meta($product_id, $meta_key, true);
        if (!is_array($meta)) $meta = [];

        // Get status for product or variation
        $product_post = get_post($product_id);
        $status = $product_post ? $product_post->post_status : '';
        list($status_label, $status_class) = $this->get_status_badge($status, $is_variation);

        if ($is_variation && $parent) {
            $display_name = esc_html($product->get_name());
        } else {
            $edit_link = get_edit_post_link($product_id, '');
            $display_name = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($product->get_name()) . '</a>';
        }
        ?>
        <tr class="<?php echo $is_variation ? 'wbe-row-variation' : 'wbe-row-parent'; ?>">
            <td><?php echo $display_name; ?></td>
            <td><span class="wbe-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
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

    private function get_status_badge($status, $is_variation = false) {
        if ($is_variation) {
            // Variations: Enabled (publish), Disabled (others)
            if ($status === 'publish') {
                return ['E', 'wbe-badge-enabled'];
            } else {
                return ['D', 'wbe-badge-disabled'];
            }
        } else {
            // Products: Published, Draft, Private, Other
            switch ($status) {
                case 'publish':
                    return ['P', 'wbe-badge-published'];
                case 'draft':
                    return ['Dr', 'wbe-badge-draft'];
                case 'private':
                    return ['Pr', 'wbe-badge-private'];
                default:
                    return [ucfirst($status), 'wbe-badge-other'];
            }
        }
    }
}

new Wholesale_Bulk_Editor_Admin();