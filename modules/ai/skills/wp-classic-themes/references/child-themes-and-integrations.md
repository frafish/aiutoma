# Child Themes & Framework Integrations

## Child Theme Architecture

Child themes allow customizing or overriding an existing theme without losing changes on parent theme updates.

### Child Theme `style.css`
A child theme MUST declare the parent theme slug in the `Template:` header:

```css
/*
Theme Name: My Child Theme
Theme URI: https://example.com/child-theme
Template: parent-theme-slug
Author: My Name
Version: 1.0.0
Text Domain: my-child-theme
*/
```

### Correct Stylesheet Enqueueing in Child Theme
Never use `@import` in CSS. Enqueue the parent and child stylesheets in `functions.php`:

```php
add_action( 'wp_enqueue_scripts', 'my_child_theme_enqueue_styles' );
function my_child_theme_enqueue_styles(): void {
    // Parent style
    wp_enqueue_style(
        'parent-theme-style',
        get_template_directory_uri() . '/style.css'
    );

    // Child style (with parent as dependency)
    wp_enqueue_style(
        'child-theme-style',
        get_stylesheet_uri(),
        [ 'parent-theme-style' ],
        wp_get_theme()->get( 'Version' )
    );
}
```

> **Important**: `get_template_directory()` always points to the parent theme; `get_stylesheet_directory()` points to the active (child) theme.

---

## WooCommerce Classic Theme Integration

> **Tip**: For complete WooCommerce plugin extension, HPOS, orders/products CRUD, and custom gateways, use the dedicated `wp-woocommerce-development` skill.

### 1. Declare Theme Support
In `functions.php`:

```php
add_action( 'after_setup_theme', 'my_theme_woocommerce_support' );
function my_theme_woocommerce_support(): void {
    add_theme_support( 'woocommerce', [
        'thumbnail_image_width' => 300,
        'single_image_width'    => 600,
        'product_grid'          => [
            'default_rows'    => 3,
            'min_rows'        => 1,
            'max_rows'        => 6,
            'default_columns' => 4,
            'min_columns'     => 1,
            'max_columns'     => 6,
        ],
    ] );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
}
```

### 2. Overriding WooCommerce Templates
To customize WooCommerce layouts, copy the template file from `plugins/woocommerce/templates/` to `your-theme/woocommerce/`:
- `woocommerce/single-product.php`
- `woocommerce/archive-product.php`
- `woocommerce/cart/cart.php`

Or use the generic catch-all `woocommerce.php` in the theme root:

```php
<?php
get_header();
?>
<main id="main" class="site-main">
    <div class="woocommerce-container">
        <?php woocommerce_content(); ?>
    </div>
</main>
<?php
get_footer();
```

---

## Advanced Custom Fields (ACF) Integration

Always check if functions exist before executing ACF calls to avoid fatal errors if ACF is deactivated:

### Basic Fields
```php
<?php if ( function_exists( 'get_field' ) ) : ?>
    <?php 
    $subtitle = get_field( 'hero_subtitle' );
    $cta_link = get_field( 'hero_cta_link' );
    ?>
    <?php if ( ! empty( $subtitle ) ) : ?>
        <p class="hero-subtitle"><?php echo esc_html( $subtitle ); ?></p>
    <?php endif; ?>

    <?php if ( ! empty( $cta_link ) ) : ?>
        <a href="<?php echo esc_url( $cta_link['url'] ); ?>" target="<?php echo esc_attr( $cta_link['target'] ?? '_self' ); ?>" class="btn">
            <?php echo esc_html( $cta_link['title'] ); ?>
        </a>
    <?php endif; ?>
<?php endif; ?>
```

### Repeater Fields
```php
<?php if ( function_exists( 'have_rows' ) && have_rows( 'feature_list' ) ) : ?>
    <ul class="features-list">
        <?php while ( have_rows( 'feature_list' ) ) : the_row(); ?>
            <li>
                <h3><?php echo esc_html( get_sub_field( 'title' ) ); ?></h3>
                <p><?php echo esc_html( get_sub_field( 'description' ) ); ?></p>
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>
```
