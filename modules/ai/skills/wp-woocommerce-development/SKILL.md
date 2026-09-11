---
name: wp-woocommerce-development
description: "Develop and customize WooCommerce stores: CRUD operations with WC_Product and WC_Order, custom payment gateways, custom shipping methods, cart and checkout hooks, template overrides, REST API, and HPOS (High-Performance Order Storage) compatibility."
compatibility: "Targets WooCommerce 8.0+ and WordPress 6.2+ (PHP 7.4+ / PHP 8.1+)."
---

# WooCommerce Development & Customization

## When to use

Use this skill when:
- building or modifying WooCommerce extensions, plugins, or custom store features
- declaring HPOS (High-Performance Order Storage) compatibility in custom plugins
- reading, creating, or modifying products (`WC_Product`), orders (`WC_Order`), and customers using official CRUD APIs
- intercepting the cart, checkout, or order creation workflows with hooks
- implementing custom payment gateways (`WC_Payment_Gateway`) or shipping methods (`WC_Shipping_Method`)
- safely overriding WooCommerce templates without breaking future plugin updates

## Reference Guides

Read:
- `references/architecture-and-hpos.md`
- `references/crud-products-and-orders.md`
- `references/hooks-and-templates.md`
- `references/payment-gateways-and-shipping.md`

## Key Implementation Patterns

### 1. Declaring HPOS Compatibility
Always declare HPOS compatibility in extension plugins to ensure seamless support for modern WooCommerce:
```php
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );
```

### 2. Modern CRUD Over Raw Postmeta
Never use `get_post_meta()` or `update_post_meta()` for order or product data. Always use the CRUD object methods:
```php
// Reading order data safely across both legacy postmeta and HPOS:
$order = wc_get_order( $order_id );
if ( $order ) {
    $total    = $order->get_total();
    $status   = $order->get_status();
    $items    = $order->get_items();
    $custom   = $order->get_meta( '_my_custom_meta' );
}
```

