# Wholesale Bulk Editor

A powerful and user-friendly bulk editor for managing WooCommerce wholesale prices per user role.  
Designed to work with the "Wholesale For WooCommerce" plugin and variable products.

## Features

- Fast tabular editing of wholesale prices for all products, including variations.
- Support for unlimited wholesale roles (pulled dynamically from your WooCommerce setup).
- Per-product and per-variation price editing.
- "Enable all" checkbox per product/variation for quickly toggling all wholesale roles.
- "Copy across" button lets you instantly copy a price to all enabled roles for a row.
- Filter by search, product category, and brand (uses WooCommerce’s native Brands).
- Pagination for fast browsing and editing of large product catalogs.
- Sticky table header for easy context when scrolling.

## Requirements

- WordPress 6.0+  
- WooCommerce 8.3+  
- [Wholesale For WooCommerce](https://woocommerce.com/products/wholesale-for-woocommerce/) plugin

## Installation

1. Clone or download this repository.
2. Place the plugin folder (or PHP file) in your site's `/wp-content/plugins/` directory.
3. Activate **Wholesale Bulk Editor** from the WordPress admin.
4. Navigate to **WooCommerce > Wholesale Bulk Editor** to start editing.

## Usage

- Use the filters to search, select category, or brand.
- For each product/variation row, enable the roles you want, enter prices, and use the "Save" button.
- Use the "Copy across" icon to copy a price to all enabled roles in the row.
- Use the "Enable all" checkbox to quickly enable or disable all wholesale roles for a product/variation.

## Notes

- The plugin reads and writes prices using the `wholesale_multi_user_pricing` meta key.
- Compatible with both simple and variable products.
- The brand filter uses the native WooCommerce Brands taxonomy (`product_brand`).

## Development

- Fork, branch, and submit pull requests for improvements!
- [Issues](https://github.com/pavlosb/Wholesale-Bulk-Editor/issues) are welcome for bugs, feature requests, or questions.

---

**Enjoy streamlined wholesale price editing!**
