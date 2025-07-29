<?php
/*
Plugin Name: Wholesale Bulk Editor
Description: Bulk editor for WooCommerce wholesale prices with modular code.
Version: 2.3
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

define('WBE_PATH', plugin_dir_path(__FILE__));
define('WBE_URL', plugin_dir_url(__FILE__));

require_once WBE_PATH . 'includes/helpers.php';
require_once WBE_PATH . 'includes/assets.php';
require_once WBE_PATH . 'includes/class-wholesale-bulk-editor.php';
require_once WBE_PATH . 'includes/class-wholesale-bulk-editor-ajax.php';
