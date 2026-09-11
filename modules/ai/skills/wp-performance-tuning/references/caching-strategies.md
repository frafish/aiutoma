# Caching Strategies & Asset Optimization

## Transients API Pattern

The Transients API stores cached data with an expiration time. If a persistent object cache is installed (e.g. Redis, Memcached), transients are stored in memory; otherwise they fall back to the `wp_options` table.

### Robust Transient Fetch Pattern
```php
function get_expensive_api_data(): array {
    $cache_key = 'my_plugin_api_data';
    $data      = get_transient( $cache_key );

    if ( false === $data ) {
        // Cache miss: fetch or calculate fresh data
        $response = wp_remote_get( 'https://api.example.com/data', [ 'timeout' => 15 ] );
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return []; // Do not cache failed responses
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        // Cache for 1 hour (3600 seconds)
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
    }

    return is_array( $data ) ? $data : [];
}
```

### Invalidating Transients on Content Change
Never wait for expiration if data changes earlier:
```php
add_action( 'save_post_product', 'clear_product_transients' );
function clear_product_transients( int $post_id ): void {
    delete_transient( 'my_plugin_featured_products' );
}
```

---

## Persistent Object Cache (`wp_cache_*`)

For high-concurrency applications, the Object Cache API provides fine-grained, in-memory caching:

```php
function get_user_activity_summary( int $user_id ): array {
    $cache_group = 'my_plugin_activity';
    $cache_key   = 'user_' . $user_id;

    // Check memory cache first
    $found   = false;
    $summary = wp_cache_get( $cache_key, $cache_group, false, $found );

    if ( ! $found ) {
        // Calculate expensive metrics from DB
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}my_activity WHERE user_id = %d",
            $user_id
        ) );

        $summary = [ 'total_actions' => $count, 'calculated_at' => time() ];

        // Store in cache (e.g. for 15 minutes)
        wp_cache_set( $cache_key, $summary, $cache_group, 15 * MINUTE_IN_SECONDS );
    }

    return $summary;
}

// Invalidation:
function invalidate_user_activity( int $user_id ): void {
    wp_cache_delete( 'user_' . $user_id, 'my_plugin_activity' );
}
```

---

## Frontend Asset Optimization

Avoid loading CSS and JS globally when they are only needed on specific templates:

```php
add_action( 'wp_enqueue_scripts', 'conditionally_enqueue_checkout_assets', 20 );
function conditionally_enqueue_checkout_assets(): void {
    // Only load payment script on checkout page
    if ( ! is_page( 'checkout' ) && ! is_checkout() ) {
        wp_dequeue_script( 'my-gateway-stripe-js' );
        wp_dequeue_style( 'my-gateway-styles' );
    }
}
```

### Loading Scripts Asynchronously or Deferred
```php
add_filter( 'script_loader_tag', 'add_defer_attribute', 10, 3 );
function add_defer_attribute( string $tag, string $handle, string $src ): string {
    // Defer non-critical analytics or widget scripts
    if ( in_array( $handle, [ 'my-analytics', 'my-floating-widget' ], true ) ) {
        return str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}
```
