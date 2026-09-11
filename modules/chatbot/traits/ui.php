<?php
namespace Aiutoma\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.ValidatedSanitizedInput



trait Ui {
    public function enqueue_chatbot_scripts() {

        if (is_admin()) return;
        if (wp_is_json_request()) return;
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') return;
        if (isset($_REQUEST['elementor-preview']) && $_REQUEST['elementor-preview']) return;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor') return;

        if (!get_option('aiutoma_chatbot_enabled', 1)) return;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('aiutoma-chatbot-style', AIUTOMA_URL . 'modules/chatbot/assets/css/chatbot.css', [], '1.0.3');
        
        $color = esc_attr(get_option('aiutoma_chatbot_color', '#2271b1'));
        $custom_css = "
            #aiutoma-chatbot-header, #aiutoma-chatbot-send, #aiutoma-chatbot-email-submit { background-color: {$color} !important; }
            .aiutoma-privacy-link { color: {$color} !important; }
        ";
        wp_add_inline_style('aiutoma-chatbot-style', $custom_css);

        wp_enqueue_script('aiutoma-chatbot-script', AIUTOMA_URL . 'modules/chatbot/assets/js/chatbot.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], '1.0.2', true);
        
        $post_id = get_the_ID();
        if (!$post_id) {
            $post_id = get_option('page_on_front');
        }
        if (!$post_id) {
            $fallback = get_posts(['post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 1]);
            $post_id = !empty($fallback) ? $fallback[0]->ID : 0;
        }

        $chatbot_name = get_option('aiutoma_chatbot_name', 'AI Bot');
        if (empty($chatbot_name)) {
            $chatbot_name = 'AI Bot';
        }
        $chatbot_name = apply_filters('wpml_translate_single_string', $chatbot_name, 'aiutoma', 'chatbot_name');
        
        $current_user = wp_get_current_user();
        $user_name = $current_user->exists() ? $current_user->display_name : '';

        wp_localize_script('aiutoma-chatbot-script', 'aiutomaChatbotData', [
            'rest_url' => esc_url_raw(rest_url('aiutoma/v1/chatbot')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG),
            'resetConfirm' => __('Are you sure you want to start a new chat?', 'aiutoma'),
            'post_id' => $post_id,
            'chatbotName' => esc_html($chatbot_name),
            'userName' => esc_html($user_name)
        ]);
    }

    public function render_chatbot() {

        if (is_admin()) return;
        if (wp_is_json_request()) return;
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') return;
        if (isset($_REQUEST['elementor-preview']) && $_REQUEST['elementor-preview']) return;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor') return;

        if (!get_option('aiutoma_chatbot_enabled', 1)) return;
        
        $icon = esc_attr(get_option('aiutoma_chatbot_icon', 'dashicons-format-chat'));
        $color = esc_attr(get_option('aiutoma_chatbot_color', '#2271b1'));
        $position = get_option('aiutoma_chatbot_position', 'bottom-right');
        
        $chatbot_name = get_option('aiutoma_chatbot_name', '');
        $chatbot_name = apply_filters('wpml_translate_single_string', $chatbot_name, 'aiutoma', 'chatbot_name');
        $chatbot_name = esc_html($chatbot_name);

        $show_ai_badge = get_option('aiutoma_chatbot_show_ai_badge', 1);

        $default_greeting = __('Hello! I am an AI assistant for this website. How can I help you today?', 'aiutoma');
        $greeting = get_option('aiutoma_chatbot_greeting', $default_greeting);
        $greeting = apply_filters('wpml_translate_single_string', $greeting, 'aiutoma', 'chatbot_greeting');
        $greeting = esc_html($greeting);

        $default_disclaimer = __('AI assistant • Responses may contain inaccuracies.', 'aiutoma');
        $disclaimer = get_option('aiutoma_chatbot_disclaimer_text', $default_disclaimer);
        $disclaimer = apply_filters('wpml_translate_single_string', $disclaimer, 'aiutoma', 'chatbot_disclaimer_text');

        $show_privacy_link = get_option('aiutoma_chatbot_show_privacy_link', 1);
        $privacy_url = ($show_privacy_link && function_exists('get_privacy_policy_url')) ? get_privacy_policy_url() : '';
        
        $pos_class = $position === 'bottom-left' ? 'aiutoma-pos-bottom-left' : 'aiutoma-pos-bottom-right';
        ?>
        <div id="aiutoma-chatbot" class="aiutoma-chatbot-closed <?php echo esc_attr($pos_class); ?>" data-live-mode="1">
            <div id="aiutoma-chatbot-header">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php if (!empty($chatbot_name)): ?>
                    <span class="aiutoma-chatbot-title-text"><?php echo esc_html($chatbot_name); ?></span>
                <?php endif; ?>
                <?php if ($show_ai_badge): ?>
                    <span class="aiutoma-chatbot-ai-badge" title="<?php esc_attr_e('Artificial Intelligence System', 'aiutoma'); ?>"><?php esc_html_e('AI', 'aiutoma'); ?></span>
                <?php endif; ?>
                <button id="aiutoma-chatbot-reset" title="<?php esc_attr_e('Reset Chat', 'aiutoma'); ?>"><span class="dashicons dashicons-update-alt"></span></button>
                <button id="aiutoma-chatbot-toggle"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="aiutoma-chatbot-body">
                <div id="aiutoma-chatbot-messages">
                    <div class="aiutoma-chatbot-msg aiutoma-chatbot-sys"><?php echo wp_kses_post($greeting); ?></div>
                </div>
                <?php 
                $contact_msg = get_option('aiutoma_chatbot_contact_msg', '');
                if (!is_user_logged_in() && !empty($contact_msg)): ?>
                    <div id="aiutoma-chatbot-email-hint">
                        <div class="aiutoma-chatbot-contact-msg"><?php echo esc_html($contact_msg); ?></div>
                        <div class="aiutoma-chatbot-email-form">
                            <input type="text" id="aiutoma-chatbot-name-input" class="aiutoma-chatbot-email-input" placeholder="<?php esc_attr_e('Your Name', 'aiutoma'); ?>">
                            <input type="email" id="aiutoma-chatbot-email-input" class="aiutoma-chatbot-email-input" placeholder="<?php esc_attr_e('Your Email', 'aiutoma'); ?>">
                            <button id="aiutoma-chatbot-email-submit" class="button"><?php esc_html_e('Send', 'aiutoma'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="aiutoma-chatbot-input-area">
                    <input type="text" id="aiutoma-chatbot-hp" value="" tabindex="-1" autocomplete="off">
                    <div style="position: relative; flex: 1; display: flex;">
                        <textarea id="aiutoma-chatbot-prompt" placeholder="<?php esc_attr_e('Ask a question...', 'aiutoma'); ?>"></textarea>
                        <button id="aiutoma-chatbot-mic" type="button" title="<?php esc_attr_e('Speech to Text', 'aiutoma'); ?>">
                            <span class="dashicons dashicons-microphone"></span>
                        </button>
                    </div>
                    <button id="aiutoma-chatbot-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
                <?php if (!is_user_logged_in() && get_option('aiutoma_chatbot_gdpr_required', 1)): ?>
                <div id="aiutoma-chatbot-gdpr-notice">
                    <?php 
                    $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
                    $privacy_link_html = !empty($privacy_url) 
                        ? '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener noreferrer" class="aiutoma-privacy-link">' . esc_html__('Privacy Policy', 'aiutoma') . '</a>'
                        : esc_html__('Privacy Policy', 'aiutoma');

                    $default_gdpr_text = sprintf(
                        /* translators: %s: Privacy Policy link */
                        __('I agree to the processing of conversation data in accordance with the %s.', 'aiutoma'),
                        $privacy_link_html
                    );

                    $custom_gdpr_text = get_option('aiutoma_chatbot_gdpr_text', '');
                    if (empty($custom_gdpr_text) || $custom_gdpr_text === 'By chatting, you agree to our processing of conversation logs to assist with your request.' || $custom_gdpr_text === 'By chatting, you agree to our processing of conversation logs to assist with your request. See our Privacy for your data rights.') {
                        $gdpr_text = $default_gdpr_text;
                    } else {
                        $gdpr_text = apply_filters('wpml_translate_single_string', $custom_gdpr_text, 'aiutoma', 'chatbot_gdpr_text');
                        if (!empty($privacy_url) && strpos($gdpr_text, '<a') === false) {
                            if (stripos($gdpr_text, 'privacy') !== false) {
                                $gdpr_text = preg_replace('/privacy(\s+policy)?/i', $privacy_link_html, $gdpr_text, 1);
                            } else {
                                $gdpr_text .= ' (' . $privacy_link_html . ')';
                            }
                        }
                    }
                    ?>
                    <label class="aiutoma-chatbot-gdpr-label">
                        <input type="checkbox" id="aiutoma-chatbot-gdpr-consent">
                        <span><?php echo wp_kses_post($gdpr_text); ?></span>
                    </label>
                </div>
                <?php endif; ?>
                <?php if (!empty($disclaimer)): ?>
                <div id="aiutoma-chatbot-disclaimer" class="aiutoma-chatbot-disclaimer">
                    <span><?php echo esc_html($disclaimer); ?><?php if (!empty($privacy_url)): ?> • <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" class="aiutoma-privacy-link"><?php esc_html_e('Privacy', 'aiutoma'); ?></a><?php endif; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

}
