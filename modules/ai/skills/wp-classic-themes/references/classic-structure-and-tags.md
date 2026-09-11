# Classic Theme Structure & Template Tags

## Theme Headers (`style.css`)

Every classic theme requires a `style.css` in its root:

```css
/*
Theme Name: My Custom Theme
Theme URI: https://example.com/theme
Author: My Name
Author URI: https://example.com
Description: Custom high-performance classic WordPress theme.
Version: 1.0.0
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: my-custom-theme
Tags: custom-header, custom-menu, featured-images, threaded-comments
*/
```

## Theme Setup in `functions.php`

Use `after_setup_theme` for declaring theme capabilities and support:

```php
add_action( 'after_setup_theme', 'my_theme_setup' );
function my_theme_setup(): void {
    // Internationalization
    load_theme_textdomain( 'my-custom-theme', get_template_directory() . '/languages' );

    // Core supports
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', [ 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ] );
    add_theme_support( 'custom-logo', [
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // Register navigation menus
    register_nav_menus( [
        'primary' => esc_html__( 'Primary Menu', 'my-custom-theme' ),
        'footer'  => esc_html__( 'Footer Menu', 'my-custom-theme' ),
    ] );
}
```

## Modular Template Parts

Never duplicate structural layout. Use template parts:

```php
// Load header.php
get_header();

// Load footer.php
get_footer();

// Load sidebar.php
get_sidebar();

// Load partials/content-post.php with arguments (WP 5.5+)
get_template_part( 'partials/content', get_post_type(), [
    'show_author' => true,
    'custom_badge' => 'Featured',
] );

// Inside partials/content.php retrieve args:
$show_author  = $args['show_author'] ?? false;
$custom_badge = $args['custom_badge'] ?? '';
```

## Essential Template Tags Reference

| Tag | Purpose | Example |
|-----|---------|---------|
| `the_title()` | Output post title | `the_title( '<h1>', '</h1>' );` |
| `the_title_attribute()` | Sanitized title for HTML attribute | `<a title="<?php the_title_attribute(); ?>">` |
| `the_permalink()` | Post permalink URL | `<a href="<?php the_permalink(); ?>">` |
| `the_content()` | Full post content with filters applied | `the_content();` |
| `the_excerpt()` | Post excerpt or auto-trimmed content | `the_excerpt();` |
| `the_post_thumbnail()` | Featured image tag | `the_post_thumbnail( 'large', [ 'class' => 'img-fluid' ] );` |
| `wp_nav_menu()` | Render navigation menu | `wp_nav_menu( [ 'theme_location' => 'primary', 'container' => 'nav' ] );` |
| `the_posts_pagination()` | Paginated post links | `the_posts_pagination( [ 'mid_size' => 2 ] );` |

## Enqueuing Theme Assets

```php
add_action( 'wp_enqueue_scripts', 'my_theme_register_assets' );
function my_theme_register_assets(): void {
    $version = wp_get_theme()->get( 'Version' );

    // Main stylesheet
    wp_enqueue_style( 'my-theme-style', get_stylesheet_uri(), [], $version );

    // Custom CSS
    wp_enqueue_style( 'my-theme-main', get_template_directory_uri() . '/assets/css/main.css', [], $version );

    // Main script
    wp_enqueue_script( 'my-theme-js', get_template_directory_uri() . '/assets/js/main.js', [], $version, true );

    // Localized settings for JS
    wp_localize_script( 'my-theme-js', 'themeData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'restUrl' => esc_url_raw( rest_url( 'my-theme/v1' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
    ] );
}
```
