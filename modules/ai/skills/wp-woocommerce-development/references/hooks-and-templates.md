# WooCommerce Hooks & Template Customization

## Key Cart & Checkout Hooks

### 1. Adding Custom Fees or Surcharges
```php
add_action( 'woocommerce_cart_calculate_fees', 'my_custom_surcharge' );
function my_custom_surcharge( WC_Cart $cart ): void {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    // Example: Add 5.00 surcharge for express packaging
    if ( WC()->session->get( 'express_packaging' ) ) {
        $cart->add_fee( esc_html__( 'Express Packaging', 'my-extension' ), 5.00, true, 'standard' );
    }
}
```

### 2. Dynamically Modifying Item Price in Cart
```php
add_action( 'woocommerce_before_calculate_totals', 'my_custom_discount_pricing', 10, 1 );
function my_custom_discount_pricing( WC_Cart $cart ): void {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        // Example: Apply 10% discount for VIP users
        if ( current_user_can( 'vip_customer' ) ) {
            $original_price = $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price( $original_price * 0.9 );
        }
    }
}
```

### 3. Adding Custom Fields to Checkout & Saving to Order Item
```php
// 1. Output custom field on checkout
add_action( 'woocommerce_after_order_notes', 'my_custom_checkout_field' );
function my_custom_checkout_field( $checkout ): void {
    woocommerce_form_field( 'delivery_instructions', [
        'type'        => 'textarea',
        'class'       => [ 'form-row-wide' ],
        'label'       => esc_html__( 'Delivery Instructions', 'my-extension' ),
        'placeholder' => esc_html__( 'Notes for the courier...', 'my-extension' ),
        'required'    => false,
    ], $checkout->get_value( 'delivery_instructions' ) );
}

// 2. Save field to order meta on order creation
add_action( 'woocommerce_checkout_create_order', 'my_save_custom_checkout_field', 10, 2 );
function my_save_custom_checkout_field( WC_Order $order, array $data ): void {
    if ( ! empty( $_POST['delivery_instructions'] ) ) {
        $order->update_meta_data( 
            '_delivery_instructions', 
            sanitize_textarea_field( wp_unslash( $_POST['delivery_instructions'] ) ) 
        );
    }
}
```

### 4. Responding to Order Status Changes
```php
add_action( 'woocommerce_order_status_changed', 'my_on_order_status_change', 10, 4 );
function my_on_order_status_change( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
    if ( 'completed' === $new_status ) {
        // Trigger automated fulfillment, webhook, or external notification
    }
}
```

---

## Template Overrides vs Hook Injection

> **Best Practice**: Whenever possible, modify layouts using action and filter hooks rather than copying template files. Hook overrides do not break when WooCommerce releases major updates.

### Hook Example: Adding Content under Add-to-Cart
```php
add_action( 'woocommerce_after_add_to_cart_button', function() {
    echo '<p class="shipping-guarantee">' . 
         esc_html__( 'Free shipping on all orders above 50€.', 'my-extension' ) . 
         '</p>';
} );
```

### File Override Structure
When a visual redesign requires full template markup replacement, copy the template from:
`plugins/woocommerce/templates/[path]/[file].php`
to:
`[your-theme]/woocommerce/[path]/[file].php`

Always preserve template hook points (`do_action(...)`) within your customized files to avoid breaking plugins and extensions.

