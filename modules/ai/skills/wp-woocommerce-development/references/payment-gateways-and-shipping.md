# Custom Payment Gateways & Shipping Methods

## Custom Payment Gateway Architecture

To create a custom payment method, extend `WC_Payment_Gateway`:

```php
// 1. Register gateway class with WooCommerce
add_filter( 'woocommerce_payment_gateways', 'register_my_custom_gateway' );
function register_my_custom_gateway( array $gateways ): array {
    $gateways[] = 'WC_Gateway_Custom_Direct';
    return $gateways;
}

// 2. Define Gateway Class
class WC_Gateway_Custom_Direct extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'custom_direct';
        $this->icon               = ''; // URL to payment method icon
        $this->has_fields         = false;
        $this->method_title       = esc_html__( 'Direct Custom Pay', 'my-extension' );
        $this->method_description = esc_html__( 'Process payments securely via custom API.', 'my-extension' );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );

        // Save settings hook
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled' => [
                'title'   => esc_html__( 'Enable/Disable', 'my-extension' ),
                'type'    => 'checkbox',
                'label'   => esc_html__( 'Enable Custom Direct Payment', 'my-extension' ),
                'default' => 'no',
            ],
            'title' => [
                'title'       => esc_html__( 'Title', 'my-extension' ),
                'type'        => 'text',
                'description' => esc_html__( 'Title displayed during checkout.', 'my-extension' ),
                'default'     => esc_html__( 'Custom Direct Pay', 'my-extension' ),
            ],
            'api_key' => [
                'title' => esc_html__( 'API Key', 'my-extension' ),
                'type'  => 'password',
            ],
        ];
    }

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        // Execute payment with external API
        // ...

        // Mark as on-hold or processing
        $order->payment_complete();

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Empty cart
        WC()->cart->empty_cart();

        // Return success with redirect URL
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }
}
```

---

## Custom Shipping Method Architecture

To create a custom shipping calculation or rule, extend `WC_Shipping_Method`:

```php
// 1. Register shipping method
add_filter( 'woocommerce_shipping_methods', 'register_my_custom_shipping' );
function register_my_custom_shipping( array $methods ): array {
    $methods['custom_courier'] = 'WC_Shipping_Custom_Courier';
    return $methods;
}

// 2. Define Shipping Method Class
class WC_Shipping_Custom_Courier extends WC_Shipping_Method {

    public function __construct( int $instance_id = 0 ) {
        $this->id                 = 'custom_courier';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = esc_html__( 'Custom Express Courier', 'my-extension' );
        $this->method_description = esc_html__( 'Calculates real-time express courier rates.', 'my-extension' );
        $this->supports           = [ 'shipping-zones', 'instance-settings' ];

        $this->init();
    }

    public function init(): void {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title', esc_html__( 'Express Courier', 'my-extension' ) );
        $this->cost  = $this->get_option( 'cost', '10.00' );

        add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function calculate_shipping( $package = [] ): void {
        $rate = [
            'id'       => $this->get_rate_id(),
            'label'    => $this->title,
            'cost'     => (float) $this->cost,
            'package'  => $package,
        ];

        // Register rate with cart
        $this->add_rate( $rate );
    }
}
```

