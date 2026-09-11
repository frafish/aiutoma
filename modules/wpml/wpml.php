<?php

namespace Aiutoma\Modules\Wpml;

if (! defined('ABSPATH')) exit;
class Wpml
{
    use traits\Contents;
    use traits\Strings;
    use traits\Settings;
    use traits\Elementor;
    use traits\Xliff;
    use traits\Google_Translate;

    public function __construct()
    {
        if (!class_exists('SitePress')) return;

        add_action('admin_footer', [$this, 'inject_wpml_ai_buttons']);
        add_action('rest_api_init', [$this, 'register_wpml_translate_route']);
        add_action('admin_menu', [$this, 'add_wpml_ai_menu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_wpml_scripts'], 9999);

        // Hooks for auto-updating translations
        add_action('post_updated', [$this, 'schedule_translation_updates'], 10, 3);
        add_action('saved_term', [$this, 'schedule_term_translation_updates'], 10, 3);
        add_action('admin_footer', [$this, 'inject_auto_translation_checkbox']);
        add_action('save_post', [$this, 'save_auto_translation_meta'], 10, 2);

        // Cron handlers
        add_action('aiutoma_wpml_update_translations', [$this, 'process_translation_updates'], 10, 1);
        add_action('aiutoma_wpml_update_term_translations', [$this, 'process_term_translation_updates'], 10, 2);

        // WPML Native Translation Management Interceptor
        add_action('wpml_added_local_translation_job', [$this, 'intercept_wpml_tm_job'], 10, 1);
        add_action('aiutoma_process_wpml_tm_job', [$this, 'process_wpml_tm_job'], 10, 1);
    }

    public function enqueue_wpml_scripts($hook)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        $is_aiutoma_wpml = ($page === 'aiutoma-wpml' || strpos($hook, 'aiutoma-wpml') !== false);
        $is_native_wpml_jobs = (strpos($page, 'tm/menu/main.php') !== false || strpos($hook, 'tm/menu/main') !== false);

        if (!$is_aiutoma_wpml && !$is_native_wpml_jobs) {
            return;
        }

        wp_enqueue_style('aiutoma-wpml-style', AIUTOMA_URL . 'modules/wpml/assets/css/wpml.css', [], filemtime(AIUTOMA_PATH . 'modules/wpml/assets/css/wpml.css'));
        
        $dependencies = ['jquery'];

        // Enqueue Select2 and XLIFF script only on Aiutoma WPML pages
        if ($is_aiutoma_wpml) {
            $s2_js = AIUTOMA_PATH . 'modules/ai/assets/js/select2.min.js';
            $s2_css = AIUTOMA_PATH . 'modules/ai/assets/css/select2.min.css';
            $s2_js_ver = file_exists($s2_js) ? filemtime($s2_js) : AIUTOMA_VERSION;
            $s2_css_ver = file_exists($s2_css) ? filemtime($s2_css) : AIUTOMA_VERSION;

            wp_enqueue_style('aiutoma-select2', AIUTOMA_URL . 'modules/ai/assets/css/select2.min.css', array(), $s2_css_ver);
            wp_enqueue_script('aiutoma-select2', AIUTOMA_URL . 'modules/ai/assets/js/select2.min.js', array('jquery'), $s2_js_ver, true);
            $dependencies[] = 'aiutoma-select2';

            wp_enqueue_script('aiutoma-wpml-xliff-script', AIUTOMA_URL . 'modules/wpml/assets/js/xliff.js', array('jquery'), filemtime(AIUTOMA_PATH . 'modules/wpml/assets/js/xliff.js'), true);
        }

        // Force inject JS to bypass WPML strict deregistration
        wp_enqueue_script('aiutoma-wpml-script', AIUTOMA_URL . 'modules/wpml/assets/js/wpml.js', $dependencies, filemtime(AIUTOMA_PATH . 'modules/wpml/assets/js/wpml.js'), true);

        $aiutomaWpmlData = [
            'isWpmlPage' => $is_aiutoma_wpml,
            'isNativeWpmlJobs' => $is_native_wpml_jobs,
            'injectButtons' => false,
            'nonce' => wp_create_nonce('wp_rest'),
            'restGetMissingUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-get-missing')),
            'restTranslateUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-translate')),
            'restStringsGetMissingUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-strings-get-missing')),
            'restStringsTranslateUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-strings-translate')),
            'restXliffGetJobsUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-xliff-jobs')),
            'restXliffTranslateUrl' => esc_url_raw(rest_url('aiutoma/v1/wpml-xliff-translate')),
            'textScanning' => __('Scanning...', 'aiutoma'),
            'textTranslate' => __('Translate via AI', 'aiutoma'),
            'textTranslateAll' => __('Translate all missing fields via AI', 'aiutoma'),
            'textError' => __('Translation failed', 'aiutoma'),
            /* translators: %d: number of missing languages */
            'textConfirm' => __('Translate %d missing languages?', 'aiutoma'),
            'textAutoTranslate' => __('Update translations via AI automatically on save', 'aiutoma'),
            'autoTranslateChecked' => ''
        ];

        wp_localize_script('aiutoma-wpml-script', 'aiutomaWpmlData', $aiutomaWpmlData);
    }

    public function register_wpml_translate_route()
    {
        register_rest_route('aiutoma/v1', '/wpml-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_translate'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/wpml-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_get_missing'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/wpml-strings-get-missing', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_strings_get_missing'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/wpml-strings-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_strings_translate'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        if (method_exists($this, 'register_xliff_routes')) {
            $this->register_xliff_routes();
        }
    }

    public function add_wpml_ai_menu()
    {
        // Register using WPML's native custom menu API
        do_action('wpml_admin_menu_register_item', [
            'order' => 800,
            'page_title' => __('AI Translator', 'aiutoma'),
            'menu_title' => __('AI Translator', 'aiutoma'),
            'capability' => 'manage_options',
            'menu_slug' => 'aiutoma-wpml',
            'function' => [$this, 'render_wpml_bulk_page']
        ]);
    }

    public function render_wpml_bulk_page()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['aiutoma_wpml_settings_nonce'])) {
            $this->handle_settings_post();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'translate';
        $has_strings = method_exists($this, 'aiutoma_wpml_has_strings') && $this->aiutoma_wpml_has_strings();

        if (defined('ICL_PLUGIN_URL')) {
            wp_enqueue_style('wpml-dashboard', ICL_PLUGIN_URL . '/vendor/wpml/wpml/public/css/dashboard.css', [], '1.0.0');
        } elseif (defined('WPML_PLUGIN_URL')) {
            wp_enqueue_style('wpml-dashboard', WPML_PLUGIN_URL . '/vendor/wpml/wpml/public/css/dashboard.css', [], '1.0.0');
        }
        ?>
        <div class="wrap" id="wpml-dashboard">
            <h1 style="margin-bottom: 16px;"><?php esc_html_e('Aiutoma Translation Dashboard', 'aiutoma'); ?></h1>

            <h2 class="nav-tab-wrapper icl-translation-management-menu">
                <a href="?page=aiutoma-wpml&tab=translate" class="nav-tab <?php echo esc_attr($active_tab === 'translate' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Content Translation', 'aiutoma'); ?>
                </a>
                <?php if ($has_strings): ?>
                    <a href="?page=aiutoma-wpml&tab=strings" class="nav-tab <?php echo esc_attr($active_tab === 'strings' ? 'nav-tab-active' : ''); ?>">
                        <?php esc_html_e('String Translation', 'aiutoma'); ?>
                    </a>
                <?php endif; ?>
                <a href="?page=aiutoma-wpml&tab=xliff" class="nav-tab <?php echo esc_attr($active_tab === 'xliff' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Xliff Translation', 'aiutoma'); ?>
                </a>
                <a href="?page=aiutoma-wpml&tab=context" class="nav-tab <?php echo esc_attr($active_tab === 'context' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Context & Glossary', 'aiutoma'); ?>
                </a>
                <a href="?page=aiutoma-wpml&tab=settings" class="nav-tab <?php echo esc_attr($active_tab === 'settings' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('AI Models & Settings', 'aiutoma'); ?>
                </a>
                <a href="?page=aiutoma-wpml&tab=logs" class="nav-tab <?php echo esc_attr($active_tab === 'logs' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Error Logs', 'aiutoma'); ?>
                </a>
            </h2>

            <?php
            if ($active_tab === 'translate' && method_exists($this, 'render_content_tab')) {
                $this->render_content_tab();
            } elseif ($active_tab === 'strings' && method_exists($this, 'render_strings_tab')) {
                $this->render_strings_tab();
            } elseif ($active_tab === 'xliff' && method_exists($this, 'render_xliff_tab')) {
                $this->render_xliff_tab();
            } elseif ($active_tab === 'context' && method_exists($this, 'render_context_tab')) {
                $this->render_context_tab();
            } elseif ($active_tab === 'settings' && method_exists($this, 'render_settings_tab')) {
                $this->render_settings_tab();
            } elseif ($active_tab === 'logs' && method_exists($this, 'render_logs_tab')) {
                $this->render_logs_tab();
            }
            ?>
        </div>
<?php
    }

    public function inject_auto_translation_checkbox()
    {
        $screen = get_current_screen();
        if ($screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }

        global $sitepress;
        if (!$sitepress) return;

        $post = get_post();
        if (!$post) return;

        $el_type = 'post_' . $post->post_type;
        $default_lang = $sitepress->get_default_language();
        $post_lang = $sitepress->get_language_for_element($post->ID, $el_type);

        if ($post_lang === $default_lang) {
            $trid = $sitepress->get_element_trid($post->ID, $el_type);
            if ($trid) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $has_translations = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code != %s",
                    $trid,
                    $default_lang
                ));

                // Only show if the post has existing translations
                if ($has_translations > 0) {
                    wp_nonce_field('aiutoma_wpml_auto_translate', 'aiutoma_wpml_auto_translate_nonce');
                }
            }
        }
    }

    public function save_auto_translation_meta($post_id, $post)
    {
        if (!isset($_POST['aiutoma_wpml_auto_translate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_wpml_auto_translate_nonce'])), 'aiutoma_wpml_auto_translate')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['aiutoma_wpml_auto_retranslate'])) {
            // We set a temporary transient or post meta so the post_updated hook (schedule_translation_updates) can read it
            update_post_meta($post_id, '_aiutoma_wpml_force_retranslate', '1');
        } else {
            delete_post_meta($post_id, '_aiutoma_wpml_force_retranslate');
        }
    }
}
