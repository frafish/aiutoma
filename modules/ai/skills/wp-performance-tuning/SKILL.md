---
name: wp-performance-tuning
description: "WordPress performance optimization: database query tuning (WP_Query flags, N+1 elimination), persistent Object Caching (wp_cache_*), Transients API caching with race-condition prevention, asset conditional loading, and custom table indexing."
compatibility: "Targets WordPress 6.0+ (PHP 7.4+ / PHP 8.1+)."
---

# WP Performance Tuning & Optimization

## When to use

Use this skill when:
- diagnosing or fixing slow page load times and sluggish admin screens
- optimizing database queries and eliminating N+1 query patterns in loops
- configuring `WP_Query` performance flags (`no_found_rows`, `update_post_meta_cache => false`)
- implementing robust caching via the Transients API or persistent Object Caching (`wp_cache_get`, `wp_cache_set`)
- designing high-traffic custom database tables with proper indexing
- conditionally enqueuing assets to improve Google PageSpeed / Core Web Vitals (CWV)

## Reference Guides

Read:
- `references/database-and-queries.md`
- `references/caching-strategies.md`

## Key Implementation Patterns

### 1. High-Performance WP_Query
```php
$fast_query = new WP_Query( [
    'post_type'              => 'post',
    'posts_per_page'         => 10,
    'no_found_rows'          => true,  // Skips SQL_CALC_FOUND_ROWS (saves ~40% query time if pagination isn't needed)
    'update_post_meta_cache' => false, // Disables postmeta caching if meta fields are not used
    'update_post_term_cache' => false, // Disables taxonomy term caching if terms are not displayed
    'fields'                 => 'ids',  // Returns array of IDs only when post objects are not needed
] );
```

### 2. Eliminating N+1 Meta Queries via Batch Fetching
```php
// BAD: get_post_meta inside loop causes N separate SQL queries
// GOOD: Fetch all metadata in a single query or use update_meta_cache()
update_meta_cache( 'post', $post_ids );
```
