---
name: wp-hooks-and-lifecycle
description: "Mastery of the WordPress hook system: core action execution order from bootstrap to shutdown, filter data manipulation, hook priorities, removing hooks from class methods, and creating custom extensible hooks."
compatibility: "Targets WordPress 6.0+ (PHP 7.4+ / PHP 8.1+)."
---

# WP Hooks & Core Lifecycle

## When to use

Use this skill when:
- deciding which hook to attach functionality to during WordPress execution
- understanding the exact bootstrap sequence (`muplugins_loaded` to `shutdown`)
- registering, prioritizing, and debugging action and filter callbacks
- removing action or filter callbacks attached by other plugins/themes (including class methods and anonymous functions)
- designing custom actions (`do_action`, `do_action_ref_array`) and filters (`apply_filters`, `apply_filters_ref_array`) to make code extensible

## Reference Guides

Read:
- `references/execution-order.md`
- `references/hooks-patterns-and-debugging.md`

## Key Implementation Patterns

### 1. Registering with Explicit Priority and Argument Count
```php
// Hook with priority 20 and 2 accepted arguments
add_action( 'save_post', 'my_custom_save_handler', 20, 2 );
function my_custom_save_handler( int $post_id, WP_Post $post ): void {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Process post save
}
```

### 2. Class-Based Hook Registration
```php
class My_Service {
    public function init(): void {
        add_action( 'init', [ $this, 'register_post_types' ] );
        add_filter( 'the_content', [ $this, 'filter_content' ] );
    }

    public function register_post_types(): void {
        // CPT registration
    }

    public function filter_content( string $content ): string {
        return $content . '<p>Appended by My_Service</p>';
    }
}
```
