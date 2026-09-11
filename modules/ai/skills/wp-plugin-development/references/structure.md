# Plugin structure and loading

Use this file when introducing or refactoring a plugin architecture.

## Core concepts

- Main plugin file contains the plugin header and bootstraps the plugin.
- Prefer predictable init:
  - minimal boot file
  - a loader/class that registers hooks
  - admin-only code behind admin hooks

Upstream reference:

- https://developer.wordpress.org/plugins/plugin-basics/

---

## Appendix: Advanced Plugin Architecture & Custom Tables

### Custom Database Tables with `dbDelta()`
When plugins need dedicated database tables (e.g. logs, caching, custom entities), use `dbDelta()` on plugin activation.

> **CRITICAL `dbDelta()` Syntax Rules**:
> 1. You must put each field on its own line in your SQL statement.
> 2. You must have **two spaces** between the words `PRIMARY KEY` and the definition of your primary key: `PRIMARY KEY  (id)`.
> 3. You must use the keyword `KEY`, not `INDEX`.
> 4. You must specify the field length for all `VARCHAR` types.

```php
function my_plugin_create_tables(): void {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'my_custom_records';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        record_type varchar(50) NOT NULL DEFAULT '',
        payload longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY record_type (record_type)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Store schema version in options for future migrations
    update_option( 'my_plugin_db_version', '1.0.0' );
}
register_activation_hook( __FILE__, 'my_plugin_create_tables' );
```

### Composer Autoloader Isolation
When bundling third-party Composer packages in a WordPress plugin:
1. Always prefix vendor packages or scope namespaces (using tools like `php-scoper` or Mozart) to prevent collisions when another active plugin loads an incompatible version of the same library (e.g. Guzzle, Symfony).
2. Wrap `require_once __DIR__ . '/vendor/autoload.php'` in a `file_exists()` check.

