<?php
namespace Aiutoma\Modules\Seo\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Init {
    public function register_seo_hooks() {
        add_action('admin_menu', [$this, 'add_seo_menu']);
        // Register the old render block logic for fallback alt text
        add_filter('render_block', [$this, 'custom_gutenberg_image_alt'], 10, 2);

        // Add button to Media Library attachment edit fields
        add_filter('attachment_fields_to_edit', [$this, 'add_seo_media_button'], 10, 2);

        // Register REST API endpoints
        add_action('rest_api_init', function () {
            register_rest_route('aiutoma/v1', '/optimize-media-seo', [
                'methods' => 'POST',
                'callback' => [$this, 'api_optimize_media_seo'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/save-seo-settings', [
                'methods' => 'POST',
                'callback' => [$this, 'api_save_seo_settings'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/content-seo-list', [
                'methods' => 'GET',
                'callback' => [$this, 'api_content_seo_list'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/optimize-content-seo', [
                'methods' => 'POST',
                'callback' => [$this, 'api_optimize_content_seo'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
        });

        // Cron hooks
        add_action('aiutoma_cron_optimize_media', [$this, 'cron_optimize_media']);
        
        // Auto schedule on attachment addition if enabled
        add_action('add_attachment', [$this, 'on_add_attachment']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_seo_scripts']);
    }

    public function enqueue_seo_scripts($hook) {
        $is_seo_page = ($hook === 'aiutoma_page_aiutoma-seo');
        $is_media_page = in_array($hook, ['upload.php', 'post.php', 'post-new.php']); // For media library button

        if ($is_seo_page || $is_media_page) {
            wp_enqueue_style('aiutoma-select2');
            wp_enqueue_style('aiutoma-seo-style', AIUTOMA_URL . 'modules/seo/assets/css/seo.css', [], filemtime(AIUTOMA_PATH . 'modules/seo/assets/css/seo.css'));
            wp_enqueue_script('aiutoma-seo-script', AIUTOMA_URL . 'modules/seo/assets/js/seo.js', ['jquery', 'aiutoma-select2'], filemtime(AIUTOMA_PATH . 'modules/seo/assets/js/seo.js'), true);
            
            wp_localize_script('aiutoma-seo-script', 'aiutomaSeoData', [
                'isSettingsPage' => $is_seo_page,
                'nonce' => wp_create_nonce('wp_rest'),
                'restModelsVisionUrl' => esc_url_raw(rest_url('aiutoma/v1/ai-models?vision=1')),
                'restModelsUrl' => esc_url_raw(rest_url('aiutoma/v1/ai-models')),
                'restSaveUrl' => esc_url_raw(rest_url('aiutoma/v1/save-seo-settings')),
                'restMediaUrl' => esc_url_raw(rest_url('wp/v2/media?media_type=image&per_page=100')),
                'restOptimizeMediaUrl' => esc_url_raw(rest_url('aiutoma/v1/optimize-media-seo')),
                'restContentListUrl' => esc_url_raw(rest_url('aiutoma/v1/content-seo-list')),
                'restOptimizeContentUrl' => esc_url_raw(rest_url('aiutoma/v1/optimize-content-seo')),
                'adminPostEditUrl' => esc_url_raw(admin_url('post.php?action=edit&post=')),
                'preferredModel' => get_option('aiutoma_seo_preferred_model', ''),
                'textModel' => get_option('aiutoma_seo_text_model', ''),
                
                // Translations
                'textSaving' => __('Saving...', 'aiutoma'),
                'textSaveSettings' => __('Save Settings', 'aiutoma'),
                'textScanning' => __('Scanning...', 'aiutoma'),
                'textScanComplete' => __('Scan Complete: Found', 'aiutoma'),
                'textProcessing' => __('Processing...', 'aiutoma'),
                'textProcessingImg' => __('Processing image ID', 'aiutoma'),
                'textDoneAlt' => __('Done! Alt:', 'aiutoma'),
                'textFailed' => __('Failed.', 'aiutoma'),
                'textError' => __('Error', 'aiutoma'),
                'textFinished' => __('Finished!', 'aiutoma'),
                'textLoading' => __('Loading...', 'aiutoma'),
                'textLoadContent' => __('Load Content', 'aiutoma'),
                'textOptimize' => __('Optimize', 'aiutoma'),
                'textNoContent' => __('No content found.', 'aiutoma'),
                'textWorking' => __('Working...', 'aiutoma'),
                'textOptimized' => __('Optimized', 'aiutoma'),
                'textDone' => __('Done', 'aiutoma'),
                'textRetry' => __('Retry', 'aiutoma'),
                'textSelectOne' => __('Please select at least one item.', 'aiutoma'),
                // translators: %d: number of items
                'textProcessingItems' => __('Processing %d items...', 'aiutoma'),
                'textOptimizeSelected' => __('Optimize Selected', 'aiutoma'),
                'textOptimizedWithExclamation' => __('Optimized!', 'aiutoma'),
                'textGenerateMeta' => __('Generate SEO Meta (AI)', 'aiutoma')
            ]);
        }
    }
    
    public function add_seo_menu() {
        add_submenu_page(
            'aiutoma',
            __('SEO/GEO/AEO', 'aiutoma'),
            __('SEO/GEO/AEO', 'aiutoma'),
            'manage_options',
            'aiutoma-seo',
            [$this, 'aiutoma_seo_page_html']
        );
    }
}
