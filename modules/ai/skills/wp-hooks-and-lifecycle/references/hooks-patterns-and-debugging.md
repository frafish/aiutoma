# Hooks Patterns, Removing Callbacks & Debugging

## Actions vs Filters

| Aspect | Action (`add_action`) | Filter (`add_filter`) |
|---|---|---|
| Purpose | Execute side effects (log, save to DB, send email, render markup) | Transform and return a piece of data |
| Return value | Ignored | **MANDATORY**: Must always return the modified (or untouched) variable |
| Invocation | `do_action( 'hook_name', ...$args )` | `$val = apply_filters( 'hook_name', $val, ...$args )` |

```php
// FILTER EXAMPLE: Always return the value
add_filter( 'the_title', 'my_prefix_title', 10, 2 );
function my_prefix_title( string $title, int $id ): string {
    if ( in_category( 'special', $id ) ) {
        return '★ ' . $title;
    }
    return $title; // Essential! Never omit return
}
```

---

## Removing Hooks Registered by Classes

To remove a hook registered with a class instance (`[ $instance, 'method' ]`), you must pass the exact same object reference and priority:

### 1. Removing from a Singleton
```php
// If the class exposes an instance or singleton:
add_action( 'init', function() {
    if ( class_exists( 'Third_Party_Plugin' ) ) {
        $instance = Third_Party_Plugin::get_instance();
        remove_action( 'wp_head', [ $instance, 'print_tracking_code' ], 10 );
    }
}, 5 );
```

### 2. Removing from Global or Anonymous Instances
When the original instance is not stored in a variable:
```php
function remove_anonymous_object_filter( string $hook, string $class_name, string $method, int $priority = 10 ): bool {
    global $wp_filter;

    if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
        return false;
    }

    foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $idx => $callback_data ) {
        $cb = $callback_data['function'];
        if ( is_array( $cb ) && is_object( $cb[0] ) && get_class( $cb[0] ) === $class_name && $cb[1] === $method ) {
            unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $idx ] );
            return true;
        }
    }
    return false;
}
```

---

## Creating Custom Extensible Hooks

When writing plugins or themes, provide hooks so third-party developers can extend your code without modifying files:

### Custom Actions
```php
function process_custom_order( int $order_id, array $order_data ): void {
    // Action before processing
    do_action( 'my_plugin_before_order_process', $order_id, $order_data );

    // Core business logic
    update_post_meta( $order_id, '_order_status', 'completed' );

    // Action after processing
    do_action( 'my_plugin_after_order_process', $order_id );
}
```

### Custom Filters
```php
function get_discount_multiplier( int $user_id, float $default_rate ): float {
    /**
     * Filters the discount multiplier for a specific customer.
     *
     * @param float $default_rate The base discount rate (e.g. 1.0).
     * @param int   $user_id      Customer user ID.
     */
    return (float) apply_filters( 'my_plugin_discount_multiplier', $default_rate, $user_id );
}
```

---

## Hook Inspection & Debugging Tools

```php
// Check if a specific function is hooked
if ( has_action( 'wp_footer', 'my_tracking_footer' ) ) {
    // Already registered
}

// Inspect current hook name
$current = current_action(); // Returns e.g. "init"

// Check if currently executing a specific hook
if ( doing_action( 'save_post' ) ) {
    // Prevent recursion
}
```
