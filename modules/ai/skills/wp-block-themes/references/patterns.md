# Block Patterns (Filesystem, Programmatic, & Design Tokens)

Use this file when creating, registering, or designing block patterns for block themes.

---

## 1. Auto-Registration via `patterns/` Directory

WordPress 6.0+ automatically registers patterns from `.php` files in the theme's `patterns/` directory.

### File Header Structure

```php
<?php
/**
 * Title: Hero with Call to Action
 * Slug: my-theme/hero-cta
 * Categories: banner, call-to-action
 * Description: High-converting full-width hero with primary and secondary CTA buttons.
 * Viewport Width: 1400
 * Inserter: yes
 * Keywords: hero, header, banner, cta
 * Block Types: core/post-content
 * Post Types: page, wp_template
 * Template Types: home, front-page
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
    <!-- wp:heading {"textAlign":"center","level":1,"fontSize":"xx-large"} -->
    <h1 class="wp-block-heading has-text-align-center has-xx-large-font-size"><?php esc_html_e( 'Build Faster with Autonomous AI', 'my-theme' ); ?></h1>
    <!-- /wp:heading -->
</div>
<!-- /wp:group -->
```

### Complete Pattern Header Reference

| Field | Required | Description | Example |
|---|---|---|---|
| `Title` | Yes | Display name in inserter | `Hero with Call to Action` |
| `Slug` | Yes | Unique identifier (`theme-slug/pattern-name`) | `my-theme/hero-cta` |
| `Categories` | Yes | Comma-separated category slugs | `banner, featured, call-to-action` |
| `Description` | No | Tooltip/help text in editor inserter | `A full-width hero section` |
| `Viewport Width` | No | Preview width in inserter modal (px) | `1400` |
| `Inserter` | No | Show in inserter (`yes` / `no`) | `no` (for internal or template-part patterns) |
| `Keywords` | No | Search keywords in editor inserter | `hero, header, banner` |
| `Block Types` | No | Blocks this pattern can transform/replace | `core/post-content`, `core/query` |
| `Post Types` | No | Restrict pattern to specific post types | `page, post, product` |
| `Template Types` | No | Contextual suggestion for template hierarchy | `home, single, 404` |

---

## 2. Programmatic Registration via PHP

When patterns are provided by a plugin or require dynamic logic:

```php
add_action( 'init', 'my_theme_register_patterns' );
function my_theme_register_patterns(): void {
    // 1. Register custom pattern category
    register_block_pattern_category( 'marketing', [
        'label' => esc_html__( 'Marketing & Conversion', 'my-theme' ),
    ] );

    // 2. Register block pattern
    register_block_pattern( 'my-theme/lead-capture', [
        'title'         => esc_html__( 'Lead Capture Banner', 'my-theme' ),
        'categories'    => [ 'marketing' ],
        'viewportWidth' => 1200,
        'content'       => '<!-- wp:group {"backgroundColor":"primary"} --><div class="wp-block-group has-primary-background-color">...</div><!-- /wp:group -->',
    ] );
}
```

---

## 3. Designing with Theme.json Tokens

Never hardcode raw pixel values or hex colors inside block pattern attributes. Always bind to `theme.json` presets:

| Intent | Anti-Pattern (Hardcoded) | Best Practice (Token Binding) |
|---|---|---|
| Spacing | `"padding":{"top":"50px"}` | `"padding":{"top":"var:preset|spacing|50"}` |
| Color | `"backgroundColor":"#1a1a1a"` | `"backgroundColor":"contrast"` |
| Font Size | `"style":{"typography":{"fontSize":"36px"}}}` | `"fontSize":"x-large"` |
| Font Family | `"fontFamily":"Helvetica, Arial"` | `"fontFamily":"heading"` |

### Pattern Overrides in Synced Patterns (WP 6.5+)
Allow users to change text/media in synced patterns without breaking global layout:

```html
<!-- wp:heading {"metadata":{"name":"Hero Title","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
<h2 class="wp-block-heading">Editable Title Across Instances</h2>
<!-- /wp:heading -->
```

---

## 4. Visual Composition Guardrails & Anti-Patterns

- **Avoid Redundant Nested Groups**: Do not wrap a group in another group unless alternating background bands or complex alignments require it.
- **Avoid Fixed Widths**: Never use fixed inline pixel widths on containers (`style="width: 800px"`). Use `layout: {"type": "constrained", "contentSize": "800px"}`.
- **Ensure Translatable Strings**: Any text inside `patterns/*.php` must be wrapped in `<?php esc_html_e( '...', 'text-domain' ); ?>`.
