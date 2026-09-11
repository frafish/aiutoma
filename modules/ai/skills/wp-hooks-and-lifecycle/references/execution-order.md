# WordPress Core Execution Order & Lifecycle

## Chronological Action Sequence

Understanding the exact sequence in which WordPress fires core actions prevents race conditions and ensures functions are hooked at the correct stage.

```
1. muplugins_loaded
   └─ Must-use plugins have loaded.

2. plugins_loaded
   └─ All active plugins are loaded. Best place to initialize plugin logic, text domains, and check dependencies.

3. setup_theme
   └─ Before the theme is loaded.

4. after_setup_theme
   └─ Active theme functions.php has loaded. Best place to declare add_theme_support() and register_nav_menus().

5. init
   └─ WordPress environment is fully initialized (user, taxonomies, post types, rewrite rules).
   └─ Best place for: register_post_type(), register_taxonomy(), add_rewrite_rule(), add_shortcode().

6. wp_loaded
   └─ WordPress is fully loaded and ready to process requests. Best place for external API webhooks or non-template form processing.

7. parse_request / send_headers
   └─ Request query parameters parsed; HTTP headers sent.

8. wp
   └─ The $wp environment object and main WP_Query are established.

9. template_redirect
   └─ Triggered right before the template file is selected.
   └─ Best place for: custom redirects (wp_safe_redirect()), custom endpoint output (JSON/CSV exports), or access control enforcement.

10. template_include (filter)
    └─ Allows overriding the template file path before rendering.

11. wp_head (via get_header())
    └─ Injects meta tags, analytics, enqueued styles, and head scripts.

12. the_post / loop_start / loop_end
    └─ Executes inside The Loop for each post.

13. wp_footer (via get_footer())
    └─ Injects footer scripts, analytics, and closing tags.

14. shutdown
    └─ Final hook fired right before PHP execution ends. Output buffer flushing, background logging, and transient cleanup.
```

---

## Admin Action Sequence

When accessing `wp-admin`, the sequence branches after `wp_loaded`:

```
1. auth_redirect
2. admin_init
   └─ Settings API registration (register_setting, add_settings_section, add_settings_field), admin form processing.
3. admin_menu / network_admin_menu
   └─ Registering admin pages (add_menu_page, add_submenu_page).
4. admin_enqueue_scripts
   └─ Enqueuing CSS/JS specific to the admin area ($hook_suffix check).
5. admin_notices
   └─ Displaying admin success/error banners.
```

---

## "Which Hook Should I Use?" Decision Guide

| Goal | Recommended Hook | Why |
|---|---|---|
| Initialize plugin classes & load text domain | `plugins_loaded` | All plugins are loaded; safe to check active plugins. |
| Declare theme supports (`add_theme_support`) | `after_setup_theme` | Fired immediately after theme's `functions.php` runs. |
| Register Custom Post Type or Custom Taxonomy | `init` | Rewrite rules and taxonomies must be ready. |
| Register REST API routes | `rest_api_init` | Only fires on REST API requests, saving frontend resources. |
| Register Gutenberg Blocks | `init` | Block types are registered in PHP via `register_block_type()`. |
| Perform page access redirect or download file | `template_redirect` | Headers have not yet sent; main query is already parsed. |
| Enqueue frontend CSS & JS | `wp_enqueue_scripts` | Prevents hardcoded tags in header/footer. |
| Enqueue admin CSS & JS | `admin_enqueue_scripts` | Provides `$hook_suffix` parameter to scope assets. |
| Register admin options & Settings API | `admin_init` | Core settings infrastructure is initialized. |
| Add admin menu items | `admin_menu` | Core admin navigation structure is being built. |
| Save or modify post data on update | `save_post` or `wp_insert_post_data` | Safe access to post ID and submitted content. |
