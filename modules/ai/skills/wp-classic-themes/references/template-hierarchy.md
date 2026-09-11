# WordPress Template Hierarchy

## Template Hierarchy Principles

WordPress uses a specific hierarchy to determine which template file renders content. When rendering a URL, WordPress traverses down the hierarchy until it finds an existing template file.

### Complete Template Map

```
Homepage:
  front-page.php → home.php → index.php

Single Post:
  single-{post-type}-{slug}.php → single-{post-type}.php → single.php → singular.php → index.php

Static Page:
  {custom-template}.php → page-{slug}.php → page-{id}.php → page.php → singular.php → index.php

Category Archive:
  category-{slug}.php → category-{id}.php → category.php → archive.php → index.php

Tag Archive:
  tag-{slug}.php → tag-{id}.php → tag.php → archive.php → index.php

Custom Taxonomy Archive:
  taxonomy-{taxonomy}-{term}.php → taxonomy-{taxonomy}.php → taxonomy.php → archive.php → index.php

Custom Post Type Archive:
  archive-{post-type}.php → archive.php → index.php

Author Archive:
  author-{nicename}.php → author-{id}.php → author.php → archive.php → index.php

Date Archive:
  date.php → archive.php → index.php

Search Results:
  search.php → index.php

404 (Not Found):
  404.php → index.php

Attachment:
  {mime-type}.php → attachment.php → single-attachment-{slug}.php → single.php → singular.php → index.php
```

## Conditional Tags for Template Logic

Use conditional tags in template files or `template_include` filter:

```php
if ( is_front_page() && is_home() ) {
    // Default homepage (latest posts)
} elseif ( is_front_page() ) {
    // Static front page
} elseif ( is_home() ) {
    // Blog posts index
} elseif ( is_single() ) {
    // Any single post (except attachment or page)
    if ( is_singular( 'product' ) ) {
        // Single product
    }
} elseif ( is_page( 'about-us' ) || is_page( [ 42, 'contact' ] ) ) {
    // Specific page by slug or ID
} elseif ( is_category( 'news' ) ) {
    // Category archive
} elseif ( is_tax( 'brand', 'nike' ) ) {
    // Custom taxonomy archive
} elseif ( is_post_type_archive( 'portfolio' ) ) {
    // Custom post type archive
} elseif ( is_search() ) {
    // Search results page
} elseif ( is_404() ) {
    // 404 page
}
```

## Template Filtering Hook

To dynamically route or swap templates via code:

```php
add_filter( 'template_include', 'my_custom_template_router' );
function my_custom_template_router( string $template ): string {
    if ( is_singular( 'event' ) ) {
        $custom = locate_template( 'single-event-custom.php' );
        if ( ! empty( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
```
