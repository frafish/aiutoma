<?php
namespace Aiutoma\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Init {
    public function register_chatbot_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_chatbot_scripts']);
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('rest_api_init', [$this, 'register_chatbot_routes']);
        add_action('admin_menu', [$this, 'add_chatbot_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_chatbot_admin_scripts']);
        add_action('admin_init', [$this, 'handle_chatbot_logs_actions']);
    }

    public function enqueue_chatbot_admin_scripts($hook) {
        $is_settings_page = ($hook === 'aiutoma_page_aiutoma-chatbot');
        $is_logs_page = ($hook === 'admin_page_aiutoma-chatbot-logs'); // Note: submenus without parent often have 'admin_page_' prefix

        if ($is_settings_page || $is_logs_page) {
            wp_enqueue_style('aiutoma-select2');
            wp_enqueue_style('aiutoma-chatbot-admin-style', AIUTOMA_URL . 'modules/chatbot/assets/css/chatbot-admin.css', [], filemtime(AIUTOMA_PATH . 'modules/chatbot/assets/css/chatbot-admin.css'));
            wp_enqueue_script('aiutoma-chatbot-admin-script', AIUTOMA_URL . 'modules/chatbot/assets/js/chatbot-admin.js', ['jquery', 'aiutoma-select2'], filemtime(AIUTOMA_PATH . 'modules/chatbot/assets/js/chatbot-admin.js'), true);
            
            wp_localize_script('aiutoma-chatbot-admin-script', 'aiutomaChatbotData', [
                'isSettingsPage' => $is_settings_page,
                'isLogsPage' => $is_logs_page,
                'nonce' => wp_create_nonce('wp_rest'),
                'restSummarizeUrl' => esc_url_raw(rest_url('aiutoma/v1/chatbot/summarize-session')),
                'restToggleManualUrl' => esc_url_raw(rest_url('aiutoma/v1/chatbot/toggle-manual')),
                'restOperatorSendUrl' => esc_url_raw(rest_url('aiutoma/v1/chatbot/operator-send')),
                'restPollUrl' => esc_url_raw(rest_url('aiutoma/v1/chatbot/poll')),
                'restCheckNewActivityUrl' => esc_url_raw(rest_url('aiutoma/v1/chatbot/check-new-activity')),
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                'sessionId' => isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '',
                'lastCheckTime' => gmdate('Y-m-d H:i:s'),
                
                // Translations
                'textGenerating' => __('Generating...', 'aiutoma'),
                'textSummarize' => __('Summarize Session', 'aiutoma'),
                'textSessionDigest' => __('Session Digest:', 'aiutoma'),
                'textSending' => __('Sending...', 'aiutoma'),
                'textSendMessage' => __('Send Message', 'aiutoma'),
                'textCommError' => __('Failed to communicate with server.', 'aiutoma'),
                'textImportSuccess' => __('Models imported successfully! Please click Save Changes to apply them.', 'aiutoma'),
                'textImportError' => __('Invalid JSON configuration file.', 'aiutoma')
            ]);
        }
    }

    public function register_chatbot_routes() {
        register_rest_route('aiutoma/v1', '/chatbot', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_request'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('aiutoma/v1', '/chatbot/summarize-session', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_summarize_session_request'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aiutoma/v1', '/chatbot/poll', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_poll'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('aiutoma/v1', '/chatbot/toggle-manual', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_toggle_manual'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aiutoma/v1', '/chatbot/operator-send', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_operator_send'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('aiutoma/v1', '/chatbot/check-new-activity', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_check_new_activity'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function add_chatbot_menu() {
        add_submenu_page(
            'aiutoma',
            __('Frontend Chatbot', 'aiutoma'),
            __('Frontend Chatbot', 'aiutoma'),
            'manage_options',
            'aiutoma-chatbot',
            [$this, 'aiutoma_chatbot_page_html']
        );
        add_submenu_page(
            null,
            __('Chatbot Logs', 'aiutoma'),
            __('Chatbot Logs', 'aiutoma'),
            'manage_options',
            'aiutoma-chatbot-logs',
            [$this, 'aiutoma_chatbot_logs_page_html']
        );
    }


}
