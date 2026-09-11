---
name: wp-classic-themes
description: "Use when developing classic PHP-based WordPress themes and child themes: template hierarchy, PHP template tags, functions.php, asset enqueuing, child theme overrides, WooCommerce template integration, and ACF."
compatibility: "Targets WordPress 6.0+ (PHP 7.4+ / PHP 8.1+)."
---

# WP Classic Themes & Child Themes

## When to use

Use this skill for classic WordPress theme and child theme development:
- building or modifying PHP template files according to the WordPress template hierarchy (`index.php`, `single.php`, `archive.php`, `page.php`, `front-page.php`, etc.)
- setting up theme bootstrapping, `style.css` headers, and `functions.php`
- enqueuing stylesheets, scripts, and localized data safely
- creating and customizing Child Themes (overriding parent templates, stylesheet dependencies)
- integrating WooCommerce templates (`woocommerce.php`, template overrides under `yourtheme/woocommerce/`)
- implementing Advanced Custom Fields (ACF) in classic templates

## Reference Guides

Read:
- `references/template-hierarchy.md`
- `references/classic-structure-and-tags.md`
- `references/child-themes-and-integrations.md`

## Key Implementation Patterns

### 1. Theme Asset Enqueuing
Always use `wp_enqueue_scripts` to load CSS and JavaScript:
```php
add_action( 'wp_enqueue_scripts', 'my_theme_assets' );
function my_theme_assets(): void {
    wp_enqueue_style(
        'my-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' )
    );
    wp_enqueue_script(
        'my-theme-script',
        get_template_directory_uri() . '/assets/js/main.js',
        [ 'jquery' ],
        wp_get_theme()->get( 'Version' ),
        true // footer
    );
}
```

### 2. Standard Template Loop
Always structure the main loop cleanly with standard fallbacks:
```php
<?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
    <?php the_posts_pagination(); ?>
<?php else : ?>
    <p><?php esc_html_e( 'No posts found.', 'my-theme' ); ?></p>
<?php endif; ?>
```
