# WooCommerce Architecture & HPOS Compatibility

## Extension Bootstrap & Dependency Verification

WooCommerce extensions should guard against fatal errors when WooCommerce is inactive:

```php
// Check on plugins_loaded
add_action( 'plugins_loaded', 'my_wc_extension_init' );
function my_wc_extension_init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . 
                 esc_html__( 'My Extension requires WooCommerce to be installed and active.', 'my-extension' ) . 
                 '</p></div>';
        } );
        return;
    }

    // Initialize extension services
    My_WC_Extension::instance()->init();
}
```

---

## High-Performance Order Storage (HPOS) Compatibility

Since WooCommerce 8.2, HPOS (storing orders in dedicated tables rather than `wp_posts`/`wp_postmeta`) is the default. Every custom extension must declare compatibility:

```php
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        // Declare compatibility with HPOS custom order tables
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true // set false if explicitly incompatible
        );

        // Declare compatibility with Cart and Checkout Blocks (optional)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
} );
```

### HPOS Critical Rules
1. **Never use `$wpdb->posts` or `$wpdb->postmeta` for order data**: Querying post tables for order lookups fails when HPOS is authoritative.
2. **Never use `get_post_meta( $order_id )`**: Use `$order->get_meta( $key )`.
3. **Never use `update_post_meta( $order_id )`**: Use `$order->update_meta_data( $key, $value )` followed by `$order->save()`.
4. **Custom queries**: Use `wc_get_orders( [ 'meta_key' => '...', ... ] )` instead of `new WP_Query( [ 'post_type' => 'shop_order' ] )`.

