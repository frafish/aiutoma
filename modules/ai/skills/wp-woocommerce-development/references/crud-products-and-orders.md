# WooCommerce CRUD: Products & Orders

## Working with `WC_Product`

WooCommerce provides strict object-oriented CRUD wrappers around products:

### 1. Reading & Updating Existing Products
```php
$product = wc_get_product( $product_id );

if ( ! $product ) {
    return;
}

// Getters
$name          = $product->get_name();
$regular_price = $product->get_regular_price();
$sale_price    = $product->get_sale_price();
$stock_status  = $product->get_stock_status(); // 'instock', 'outofstock'
$sku           = $product->get_sku();

// Setters
$product->set_regular_price( '49.99' );
$product->set_sale_price( '39.99' );
$product->set_stock_status( 'instock' );

// Custom metadata
$product->update_meta_data( '_custom_supplier_id', 'SUP-1234' );

// Save to persist changes
$product->save();
```

### 2. Creating a Product Programmatically
```php
$new_product = new WC_Product_Simple();
$new_product->set_name( 'Premium Canvas Bag' );
$new_product->set_status( 'publish' );
$new_product->set_catalog_visibility( 'visible' );
$new_product->set_regular_price( '29.00' );
$new_product->set_description( 'Eco-friendly durable canvas shoulder bag.' );
$new_product->set_short_description( 'Eco-friendly canvas bag.' );
$new_product->set_sku( 'BAG-001' );
$new_product->set_manage_stock( true );
$new_product->set_stock_quantity( 50 );

// Returns created product ID
$product_id = $new_product->save();
```

### 3. Querying Products
Use `wc_get_products()` instead of `WP_Query`:
```php
$products = wc_get_products( [
    'status'   => 'publish',
    'limit'    => 20,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'category' => [ 'bags', 'accessories' ],
] );

foreach ( $products as $product ) {
    // $product is already a WC_Product instance
    echo esc_html( $product->get_name() . ': ' . $product->get_price() );
}
```

---

## Working with `WC_Order`

### 1. Reading Order Data
```php
$order = wc_get_order( $order_id );

if ( ! $order ) {
    return;
}

$order_number   = $order->get_order_number();
$total          = $order->get_total();
$status         = $order->get_status(); // e.g. 'processing', 'completed'
$currency       = $order->get_currency();
$customer_id    = $order->get_customer_id();
$billing_email  = $order->get_billing_email();
$shipping_city  = $order->get_shipping_city();

// Iterating over line items:
foreach ( $order->get_items() as $item_id => $item ) {
    $product_name = $item->get_name();
    $quantity     = $item->get_quantity();
    $subtotal     = $item->get_subtotal();
    $product      = $item->get_product(); // WC_Product or null
}
```

### 2. Creating an Order Programmatically
```php
$order = wc_create_order();

// Add line item with product ID and quantity
$order->add_product( wc_get_product( $product_id ), 2 );

// Set address data
$order->set_address( [
    'first_name' => 'Mario',
    'last_name'  => 'Rossi',
    'email'      => 'mario.rossi@example.com',
    'phone'      => '+39012345678',
    'address_1'  => 'Via Roma 10',
    'city'       => 'Milano',
    'postcode'   => '20100',
    'country'    => 'IT',
], 'billing' );

// Calculate totals and set status
$order->calculate_totals();
$order->set_status( 'processing', 'Order generated via automated API' );
$order->save();
```

### 3. Querying Orders with HPOS Compatibility
```php
$pending_orders = wc_get_orders( [
    'status'   => 'pending',
    'limit'    => 10,
    'paginate' => true,
] );

foreach ( $pending_orders->orders as $order ) {
    echo 'Order #' . esc_html( $order->get_id() );
}
```

