<?php
if (!defined('ABSPATH')) exit;

// Enqueue scripts and styles only on the plugin page
add_action('admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'wholesale-bulk-editor') {
        wp_enqueue_script('wbe-admin-js', WBE_URL . 'assets/admin.js', ['jquery'], '1.0', true);
        wp_enqueue_style('wbe-admin-css', WBE_URL . 'assets/admin.css', [], '1.0');
        wp_localize_script('wbe-admin-js', 'WBE_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wbe_nonce'),
        ]);
    }
});
