# Database Query Optimization & Tuning

## Eliminating N+1 Query Antipatterns

A common cause of severe server slowdowns in WordPress is running single queries inside loops:

### BAD: Query Inside Loop
```php
// 100 posts = 1 query for posts + 100 separate queries for meta!
foreach ( $post_ids as $post_id ) {
    $price = get_post_meta( $post_id, '_price', true );
}
```

### GOOD Option 1: WordPress Core Meta Cache Priming
WordPress provides built-in functions to preload metadata in bulk:
```php
// Primes the internal meta cache for all IDs in a single SQL query
update_meta_cache( 'post', $post_ids );

// Now subsequent calls hit memory cache, with 0 additional SQL queries
foreach ( $post_ids as $post_id ) {
    $price = get_post_meta( $post_id, '_price', true );
}
```

### GOOD Option 2: Direct Single Batch Query
When querying custom tables or specific keys:
```php
function get_batch_meta( array $post_ids, string $meta_key ): array {
    global $wpdb;

    if ( empty( $post_ids ) ) {
        return [];
    }

    $sanitized_ids = implode( ',', array_map( 'absint', $post_ids ) );

    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, meta_value 
         FROM {$wpdb->postmeta} 
         WHERE post_id IN ({$sanitized_ids}) 
         AND meta_key = %s",
        $meta_key
    ) );

    $map = [];
    foreach ( $results as $row ) {
        $map[ $row->post_id ] = $row->meta_value;
    }
    return $map;
}
```

---

## High-Performance `WP_Query` Configuration

Default `WP_Query` arguments include features that may be unnecessary:

| Parameter | Default | Optimized | Impact |
|---|---|---|---|
| `no_found_rows` | `false` | `true` | Skips `SQL_CALC_FOUND_ROWS`. Set to `true` whenever pagination is not required. Saves 30% to 50% query time. |
| `update_post_meta_cache` | `true` | `false` | Skips querying the `postmeta` table. Set to `false` when displaying only titles/excerpts. |
| `update_post_term_cache` | `true` | `false` | Skips querying taxonomy tables. Set to `false` when categories/tags are not needed. |
| `fields` | `''` (all fields) | `'ids'` | Returns array of post IDs (`int[]`) instead of full `WP_Post` objects, reducing memory usage. |

### Complete Optimized Query Example
```php
$latest_news = new WP_Query( [
    'post_type'              => 'news',
    'posts_per_page'         => 5,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'fields'                 => 'ids',
] );

if ( ! empty( $latest_news->posts ) ) {
    foreach ( $latest_news->posts as $news_id ) {
        echo '<li>' . esc_html( get_the_title( $news_id ) ) . '</li>';
    }
}
```

---

## Custom Database Tables & Indexing

When building custom tables for plugins, index design is critical:

```sql
CREATE TABLE {$wpdb->prefix}my_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(50) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'pending',
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY status_created (status, created_at)
) {$charset_collate};
```

### Indexing Rules
1. **Index columns used in `WHERE`, `JOIN`, and `ORDER BY`**.
2. **Composite indexes**: Order composite index columns from highest selectivity to lowest (e.g. `KEY status_created (status, created_at)`).
3. **Avoid leading wildcards in `LIKE` queries**: `WHERE title LIKE '%word'` cannot use an index and results in a full table scan.
