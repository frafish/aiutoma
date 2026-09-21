<?php
namespace Aiutoma\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;

trait InstantMessaging {

    public function register_im_hooks() {
        add_action('admin_menu', [$this, 'add_im_menu']);
        add_action('rest_api_init', [$this, 'register_im_routes']);
        add_action('admin_init', [$this, 'register_telegram_webhook_action']);
    }

    public function add_im_menu() {
        add_submenu_page(
            'aiutoma-hidden', // Fake parent slug hides it from the menu without PHP TypeError
            __('Instant Messaging', 'aiutoma'),
            __('Instant Messaging', 'aiutoma'),
            'manage_options',
            'aiutoma-im',
            [$this, 'aiutoma_im_page_html']
        );
    }
    
    public function register_telegram_webhook_action() {
        if (isset($_GET['aiutoma_telegram_register_webhook']) && current_user_can('manage_options')) {
            $bot_token = get_option('aiutoma_tg_bot_token');
            $status = 'error';
            $message = 'Bot token is empty.';
            
            if (!empty($bot_token)) {
                $webhook_url = rest_url('aiutoma/v1/telegram-webhook');
                $secret_token = get_option('aiutoma_tg_webhook_secret', '');
                if (empty($secret_token)) {
                    $secret_token = wp_generate_password(32, false, false);
                    update_option('aiutoma_tg_webhook_secret', $secret_token);
                }
                $url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url) . "&secret_token=" . urlencode($secret_token);
                $response = wp_remote_get($url);
                
                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $json = json_decode($body, true);
                    if (isset($json['ok']) && $json['ok'] === true) {
                        $status = 'success';
                        $message = 'Webhook registered successfully.';
                    } else {
                        $message = isset($json['description']) ? $json['description'] : 'Unknown Telegram API error.';
                    }
                }
            }
            wp_redirect(admin_url('admin.php?page=aiutoma-im&webhook_registered=' . $status . '&webhook_msg=' . urlencode($message)));
            exit;
        }
    }

    public function aiutoma_im_page_html() {
        if (!current_user_can('manage_options')) return;

        // WhatsApp Saving
        if (isset($_POST['aiutoma_wa_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_wa_settings_nonce'])), 'aiutoma_wa_settings_action')) {
            update_option('aiutoma_wa_phone_number_id', sanitize_text_field(wp_unslash($_POST['aiutoma_wa_phone_number_id'])));
            update_option('aiutoma_wa_access_token', sanitize_text_field(wp_unslash($_POST['aiutoma_wa_access_token'])));
            update_option('aiutoma_wa_target_number', sanitize_text_field(wp_unslash($_POST['aiutoma_wa_target_number'])));
            update_option('aiutoma_wa_app_secret', sanitize_text_field(wp_unslash($_POST['aiutoma_wa_app_secret'])));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('WhatsApp Settings saved.', 'aiutoma') . '</p></div>';
        }

        // Telegram Saving
        if (isset($_POST['aiutoma_tg_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_tg_settings_nonce'])), 'aiutoma_tg_settings_action')) {
            $new_token = sanitize_text_field(wp_unslash($_POST['aiutoma_tg_bot_token']));
            
            if (!empty($new_token) && !preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/', $new_token)) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error: Invalid Telegram Bot Token format. It must look like: 123456789:ABCdefGHIjkl...', 'aiutoma') . '</p></div>';
            } else {
                $is_valid = true;
                if (!empty($new_token)) {
                    $response = wp_remote_get("https://api.telegram.org/bot{$new_token}/getMe");
                    if (!is_wp_error($response)) {
                        $body = wp_remote_retrieve_body($response);
                        $json = json_decode($body, true);
                        if (isset($json['ok']) && $json['ok'] === true) {
                            update_option('aiutoma_tg_bot_info', [
                                'name' => $json['result']['first_name'],
                                'username' => $json['result']['username']
                            ]);
                        } else {
                            $is_valid = false;
                            delete_option('aiutoma_tg_bot_info');
                            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Telegram API Error: Token format is correct, but Telegram rejected it as invalid.', 'aiutoma') . '</p></div>';
                        }
                    }
                } else {
                    delete_option('aiutoma_tg_bot_info');
                }
                
                if ($is_valid) {
                    update_option('aiutoma_tg_bot_token', $new_token);
                    update_option('aiutoma_tg_allowed_chat_id', sanitize_text_field(wp_unslash($_POST['aiutoma_tg_allowed_chat_id'])));
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Telegram Settings saved.', 'aiutoma') . '</p></div>';
                }
            }
        }
        
        // Global Settings Saving
        if (isset($_POST['aiutoma_im_global_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_im_global_nonce'])), 'aiutoma_im_global_action')) {
            update_option('aiutoma_im_model', sanitize_text_field(wp_unslash($_POST['aiutoma_im_model'])));
            update_option('aiutoma_im_system_prompt', sanitize_textarea_field(wp_unslash($_POST['aiutoma_im_system_prompt'])));
            update_option('aiutoma_im_acting_user', intval(wp_unslash($_POST['aiutoma_im_acting_user'])));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Global Settings saved.', 'aiutoma') . '</p></div>';
        }

        if (isset($_GET['webhook_registered'])) {
            $status = sanitize_text_field($_GET['webhook_registered']);
            $msg = isset($_GET['webhook_msg']) ? sanitize_text_field(wp_unslash($_GET['webhook_msg'])) : '';
            if ($status === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            } else if ($status === 'error') {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('Webhook Error:', 'aiutoma') . '</strong> ' . esc_html($msg) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Telegram Webhook registration requested. Check Bot logs to confirm.', 'aiutoma') . '</p></div>';
            }
        }

        if (isset($_GET['aiutoma_clear_history']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'aiutoma_clear_im_history')) {
            $platform_to_clear = sanitize_text_field($_GET['aiutoma_clear_history']);
            $conv_id = '';
            if ($platform_to_clear === 'whatsapp') {
                $t_num = get_option('aiutoma_wa_target_number');
                if (!empty($t_num)) {
                    $conv_id = 'whatsapp_' . preg_replace('/[^0-9]/', '', $t_num);
                }
            } else if ($platform_to_clear === 'telegram') {
                $c_id = get_option('aiutoma_tg_allowed_chat_id');
                if (!empty($c_id)) {
                    $conv_id = 'telegram_' . $c_id;
                }
            }
            if (!empty($conv_id)) {
                delete_transient($conv_id);
                $upload_dir = wp_upload_dir();
                $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . sanitize_file_name($conv_id) . '.json';
                if (file_exists($file_path)) {
                    wp_delete_file($file_path);
                }
                /* translators: %s: Platform name */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('AI Memory wiped for %s. You can now start fresh!', 'aiutoma'), ucfirst($platform_to_clear))) . '</p></div>';
            }
        }

        // Global Options
        $im_model = get_option('aiutoma_im_model', '');
        $im_system_prompt = get_option('aiutoma_im_system_prompt', '');
        $saved_im_acting_user = (int) get_option('aiutoma_im_acting_user', 0);
        $current_user_id = get_current_user_id();
        $selected_im_user_id = (int) apply_filters('aiutoma_im_selected_user_id', $saved_im_acting_user ?: $current_user_id);

        $selected_user = get_userdata($selected_im_user_id);
        $current_user = wp_get_current_user();
        $selected_user_login = ($selected_user && !empty($selected_user->user_login)) ? $selected_user->user_login : (($current_user && !empty($current_user->user_login)) ? $current_user->user_login : 'admin');

        // WA Options
        $phone_number_id = get_option('aiutoma_wa_phone_number_id', '');
        $access_token = get_option('aiutoma_wa_access_token', '');
        $target_number = get_option('aiutoma_wa_target_number', '');
        $app_secret = get_option('aiutoma_wa_app_secret', '');
        $wa_verify_token = get_option('aiutoma_wa_verify_token', '');
        if (empty($wa_verify_token)) {
            $wa_verify_token = wp_generate_password(32, false, false);
            update_option('aiutoma_wa_verify_token', $wa_verify_token);
        }
        
        // TG Options
        $tg_bot_token = get_option('aiutoma_tg_bot_token', '');
        $tg_allowed_chat_id = get_option('aiutoma_tg_allowed_chat_id', '');
        $tg_bot_info = get_option('aiutoma_tg_bot_info', []);

        $mcp_token = get_option('aiutoma_mcp_token', '');

        // Enqueue Select2 for the models dropdown
        wp_enqueue_style('aiutoma-select2');
        wp_enqueue_script('aiutoma-select2');

        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 20px;"><?php esc_html_e('Instant Messaging Integrations', 'aiutoma'); ?></h1>
            
            <div class="card" style="max-width: 100%; margin-top: 0; margin-bottom: 25px; padding: 25px; box-sizing: border-box;">
                <h2 style="margin-top: 0; padding-top: 0;"><span class="dashicons dashicons-admin-settings" style="vertical-align: middle;"></span> <?php esc_html_e('Global AI Settings', 'aiutoma'); ?></h2>
                <p class="description"><?php esc_html_e('These settings apply to all configured Instant Messaging channels (WhatsApp, Telegram).', 'aiutoma'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('aiutoma_im_global_action', 'aiutoma_im_global_nonce'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" style="padding-left: 0;"><?php esc_html_e('AI Model', 'aiutoma'); ?></th>
                            <td>
                                <select name="aiutoma_im_model" id="aiutoma-im-model" data-selected="<?php echo esc_attr($im_model); ?>" style="width: 100%; max-width: 400px;">
                                    <option value=""><?php esc_html_e('-- Default Model --', 'aiutoma'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" style="padding-left: 0;"><?php esc_html_e('AI Acting User', 'aiutoma'); ?></th>
                            <td>
                                <select name="aiutoma_im_acting_user" id="aiutoma-im-acting-user" style="width: 100%; max-width: 400px;">
                                    <option value="0" <?php selected($saved_im_acting_user, 0); ?>><?php esc_html_e('-- Select an Administrator --', 'aiutoma'); ?></option>
                                    <?php
                                    $users = get_users(['role' => 'administrator']);
                                    foreach ($users as $u) {
                                        ?>
                                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($saved_im_acting_user, $u->ID); ?>>
                                            <?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_login); ?>)
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Select which administrator account the AI executes under when processing messages from WhatsApp or Telegram.', 'aiutoma'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" style="padding-left: 0;"><?php esc_html_e('System Prompt / Context', 'aiutoma'); ?></th>
                            <td>
                                <textarea name="aiutoma_im_system_prompt" rows="3" style="width: 100%;" placeholder="<?php esc_attr_e('E.g. You are a helpful assistant talking to the site administrator on their phone. Give concise answers.', 'aiutoma'); ?>"><?php echo esc_textarea($im_system_prompt); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Global Settings', 'aiutoma'), 'primary', 'submit', false); ?>
                </form>
            </div>
            
            <?php
            wp_enqueue_script('aiutoma-im-script', AIUTOMA_URL . 'modules/playground/assets/js/instant-messaging.js', [], AIUTOMA_VERSION, true);
            wp_localize_script('aiutoma-im-script', 'aiutoma_im_vars', [
                'api_url' => esc_url(rest_url('aiutoma/v1/ai-models')),
                'nonce' => wp_create_nonce('wp_rest')
            ]);
            ?>
            
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                
                <!-- WhatsApp Column -->
                <div style="flex: 1 1 45%; min-width: 350px;">
                    <div class="card" style="max-width: 100%; margin-top: 0; padding: 25px; box-sizing: border-box;">
                        <h2 style="display: flex; align-items: center; gap: 10px; margin-top: 0; padding-top: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="28" height="28" style="fill: #25D366;">
                                <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157.1zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path>
                            </svg>
                            <?php esc_html_e('WhatsApp Cloud API', 'aiutoma'); ?>
                        </h2>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field('aiutoma_wa_settings_action', 'aiutoma_wa_settings_nonce'); ?>
                            <table class="form-table" style="margin-bottom: 20px;">
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Phone Number ID', 'aiutoma'); ?></th>
                                    <td><input type="text" name="aiutoma_wa_phone_number_id" value="<?php echo esc_attr($phone_number_id); ?>" class="regular-text" style="width: 100%;" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Access Token', 'aiutoma'); ?></th>
                                    <td><input type="password" name="aiutoma_wa_access_token" value="<?php echo esc_attr($access_token); ?>" class="regular-text" style="width: 100%;" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Target Phone Number', 'aiutoma'); ?></th>
                                    <td>
                                        <input type="text" name="aiutoma_wa_target_number" value="<?php echo esc_attr($target_number); ?>" placeholder="393331234567" class="regular-text" style="width: 100%;" />
                                        <p class="description"><?php esc_html_e('E.164 format without +, e.g. 393331234567', 'aiutoma'); ?></p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Meta App Secret', 'aiutoma'); ?></th>
                                    <td>
                                        <input type="password" name="aiutoma_wa_app_secret" value="<?php echo esc_attr($app_secret); ?>" class="regular-text" style="width: 100%;" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Verify Token', 'aiutoma'); ?></th>
                                    <td>
                                        <input type="text" value="<?php echo esc_attr($wa_verify_token); ?>" class="regular-text" style="width: 100%;" readonly />
                                        <p class="description"><?php esc_html_e('Copy this token and paste it into the Meta App Developer portal during Webhook Setup.', 'aiutoma'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(__('Save WhatsApp Settings', 'aiutoma'), 'primary', 'submit', false); ?>
                            <?php if (!empty($target_number)): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=aiutoma-im&aiutoma_clear_history=whatsapp&_wpnonce=' . wp_create_nonce('aiutoma_clear_im_history'))); ?>" class="button button-secondary" style="margin-left: 10px;" onclick="return confirm('<?php esc_attr_e('Are you sure you want to completely wipe the AI memory for this WhatsApp conversation?', 'aiutoma'); ?>');"><?php esc_html_e('Clear AI Memory (Reset)', 'aiutoma'); ?></a>
                            <?php endif; ?>
                        </form>
                        
                        <hr style="margin: 25px 0;">
                        
                        <details>
                            <summary style="cursor: pointer; font-weight: 600; font-size: 14px; outline: none; padding: 5px 0;">
                                <?php esc_html_e('Configuration Guide: WhatsApp Cloud API', 'aiutoma'); ?>
                            </summary>
                            <div style="padding-top: 15px;">
                                <ol style="margin-bottom: 0; padding-left: 20px;">
                                    <li>Go to the <a href="https://developers.facebook.com/" target="_blank">Meta for Developers</a> portal, log in, click <strong>"My Apps"</strong>.</li>
                                    <li>Click <strong>"Create App"</strong>, select <strong>"Other"</strong> then <strong>"Business"</strong>. Name it and proceed.</li>
                                    <li>Scroll down to find <strong>"WhatsApp"</strong> and click <strong>"Set Up"</strong>.</li>
                                    <li>Expand <strong>"WhatsApp" &rarr; "API Setup"</strong>. Copy the <strong>Phone Number ID</strong> to the field above.</li>
                                    <li>Go to <a href="https://business.facebook.com/settings/system-users" target="_blank">Meta Business Settings &rarr; System Users</a>. Create a system user, assign your App, click "Generate New Token" with <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>. Paste token above.</li>
                                    <li>Under <strong>"WhatsApp" &rarr; "Configuration"</strong>, edit the Webhook:
                                        <ul style="margin: 5px 0 5px 20px; list-style-type: disc;">
                                            <li><strong>Callback URL:</strong> <code><?php echo esc_url(rest_url('aiutoma/v1/whatsapp-webhook')); ?></code></li>
                                            <li><strong>Verify Token:</strong> Copy your global MCP token from the field above.</li>
                                        </ul>
                                    </li>
                                    <li>Click <strong>"Manage"</strong> under Webhook fields and "Subscribe" to the <strong>"messages"</strong> event.</li>
                                    <li>Send a message from your target number to the Meta number to test!</li>
                                </ol>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Telegram Column -->
                <div style="flex: 1 1 45%; min-width: 350px;">
                    <div class="card" style="max-width: 100%; margin-top: 0; padding: 25px; box-sizing: border-box;">
                        <h2 style="display: flex; align-items: center; gap: 10px; margin-top: 0; padding-top: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" width="28" height="28" style="fill: #229ED9;">
                                <path d="M248 8C111 8 0 119 0 256S111 504 248 504 496 393 496 256 385 8 248 8zM363 176.7c-3.7 39.2-19.9 134.4-28.1 178.3-3.5 18.6-10.3 24.8-16.9 25.4-14.4 1.3-25.3-9.5-39.3-18.7-21.8-14.3-34.2-23.2-55.3-37.2-24.5-16.1-8.6-25 5.3-39.5 3.7-3.8 67.1-61.5 68.3-66.7 .2-.7 .3-3.1-1.2-4.4s-3.6-.8-5.1-.5q-3.3 .7-104.6 69.1-14.8 10.2-26.9 9.9c-8.9-.2-25.9-5-38.6-9.1-15.5-5-27.9-7.7-26.8-16.3q.8-6.7 18.5-13.7 108.4-47.2 144.6-62.3c68.9-28.6 83.2-33.6 92.5-33.8 2.1 0 6.6 .5 9.6 2.9a10.5 10.5 0 0 1 3.5 6.7A43.8 43.8 0 0 1 363 176.7z"/>
                            </svg>
                            <?php esc_html_e('Telegram Bot API Integration', 'aiutoma'); ?>
                        </h2>
                        
                        <?php if (!empty($tg_bot_info) && isset($tg_bot_info['name']) && isset($tg_bot_info['username'])): ?>
                            <div style="background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; padding: 12px 15px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <strong style="color: #2e7d32; display: block; margin-bottom: 4px;"><?php esc_html_e('Connected Bot:', 'aiutoma'); ?> <?php echo esc_html($tg_bot_info['name']); ?></strong>
                                    <code style="background: transparent; padding: 0; color: #388e3c; font-size: 13px;">@<?php echo esc_html($tg_bot_info['username']); ?></code>
                                </div>
                                <a href="https://t.me/<?php echo esc_attr($tg_bot_info['username']); ?>" target="_blank" class="button button-secondary" style="border-color: #2e7d32; color: #2e7d32;">
                                    <?php esc_html_e('Open in Telegram', 'aiutoma'); ?> &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field('aiutoma_tg_settings_action', 'aiutoma_tg_settings_nonce'); ?>
                            <table class="form-table" style="margin-bottom: 20px;">
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Bot Token', 'aiutoma'); ?></th>
                                    <td><input type="password" name="aiutoma_tg_bot_token" value="<?php echo esc_attr($tg_bot_token); ?>" class="regular-text" style="width: 100%;" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Allowed Chat ID', 'aiutoma'); ?></th>
                                    <td>
                                        <input type="text" name="aiutoma_tg_allowed_chat_id" value="<?php echo esc_attr($tg_allowed_chat_id); ?>" placeholder="123456789" class="regular-text" style="width: 100%;" />
                                        <p class="description"><?php esc_html_e('Your personal User ID. The bot will ignore others.', 'aiutoma'); ?></p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" style="padding-left: 0;"><?php esc_html_e('Webhook URL', 'aiutoma'); ?></th>
                                    <td>
                                        <input type="text" value="<?php echo esc_url(rest_url('aiutoma/v1/telegram-webhook')); ?>" class="regular-text" style="width: 100%;" readonly />
                                        <?php if (!empty($tg_bot_token)) : ?>
                                            <p class="description" style="margin-top: 10px;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=aiutoma-im&aiutoma_telegram_register_webhook=1')); ?>" class="button button-secondary"><?php esc_html_e('Register Webhook via API', 'aiutoma'); ?></a>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(__('Save Telegram Settings', 'aiutoma'), 'primary', 'submit', false); ?>
                            <?php if (!empty($tg_allowed_chat_id)): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=aiutoma-im&aiutoma_clear_history=telegram&_wpnonce=' . wp_create_nonce('aiutoma_clear_im_history'))); ?>" class="button button-secondary" style="margin-left: 10px;" onclick="return confirm('<?php esc_attr_e('Are you sure you want to completely wipe the AI memory for this Telegram conversation?', 'aiutoma'); ?>');"><?php esc_html_e('Clear AI Memory (Reset)', 'aiutoma'); ?></a>
                            <?php endif; ?>
                        </form>
                        
                        <hr style="margin: 25px 0;">
                        
                        <details>
                            <summary style="cursor: pointer; font-weight: 600; font-size: 14px; outline: none; padding: 5px 0;">
                                <?php esc_html_e('Configuration Guide: Telegram Bot API', 'aiutoma'); ?>
                            </summary>
                            <div style="padding-top: 15px;">
                                <ol style="margin-bottom: 0; padding-left: 20px;">
                                    <li>Open Telegram and search for <strong>@BotFather</strong>.</li>
                                    <li>Send the command <code>/newbot</code> and follow instructions to choose a name and username.</li>
                                    <li>Copy the <strong>Bot Token</strong> (e.g. <code>123456789:ABCdef...</code>) into the field above.</li>
                                    <li>To find your personal <strong>Allowed Chat ID</strong>, search for <strong>@userinfobot</strong> on Telegram and send a message. It replies with your ID. Copy it to the field above to secure your bot.</li>
                                    <li>Save the settings using the button above.</li>
                                    <li>Click <strong>Register Webhook via API</strong> to automatically tell Telegram to send messages to your site.</li>
                                    <li>Done! You can now chat with your bot directly on Telegram.</li>
                                </ol>
                            </div>
                        </details>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    public function register_im_routes() {
        // WhatsApp Webhook
        register_rest_route('aiutoma/v1', '/whatsapp-webhook', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'whatsapp_webhook_verify'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'whatsapp_webhook_receive'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Telegram Webhook
        register_rest_route('aiutoma/v1', '/telegram-webhook', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'telegram_webhook_receive'],
                'permission_callback' => '__return_true'
            ]
        ]);
    }

    // --- WhatsApp Methods ---
    
    public function whatsapp_webhook_verify(\WP_REST_Request $request) {
        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');
        
        $verify_token = get_option('aiutoma_wa_verify_token', '');
        
        // Fallback for backwards compatibility with existing setups
        if (empty($verify_token)) {
            $verify_token = get_option('aiutoma_mcp_token', '');
        }

        if (empty($token) && isset($_GET['hub_verify_token'])) {
            $token = sanitize_text_field(wp_unslash($_GET['hub_verify_token']));
        }
        if (empty($mode) && isset($_GET['hub_mode'])) {
            $mode = sanitize_text_field(wp_unslash($_GET['hub_mode']));
        }
        if (empty($challenge) && isset($_GET['hub_challenge'])) {
            $challenge = sanitize_text_field(wp_unslash($_GET['hub_challenge']));
        }

        if ($mode === 'subscribe' && $token === $verify_token) {
            echo esc_html($challenge);
            exit;
        }

        return new \WP_REST_Response('Forbidden', 403);
    }

    public function whatsapp_webhook_receive(\WP_REST_Request $request) {
        $raw_body = $request->get_body();
        $signature = $request->get_header('x_hub_signature_256');
        $app_secret = get_option('aiutoma_wa_app_secret', '');

        if ($app_secret === '' || !is_string($signature)) {
            return new \WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $raw_body, $app_secret);

        if (!hash_equals($expected, $signature)) {
            return new \WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $acting_user_id = (int) apply_filters('aiutoma_im_acting_user_id', (int) get_option('aiutoma_im_acting_user', 0));
        $user = get_userdata($acting_user_id);
        if (!$user || !$user->has_cap('manage_options')) {
            return new \WP_REST_Response('Service Unavailable: Missing or demoted acting user.', 503);
        }

        $body = $request->get_json_params();

        if (isset($body['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message_data = $body['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $message_data['from'];
            
            $target_number = get_option('aiutoma_wa_target_number', '');
            
            $clean_from = preg_replace('/[^0-9]/', '', $from);
            $clean_target = preg_replace('/[^0-9]/', '', $target_number);

            if (!empty($clean_target) && $clean_from === $clean_target) {
                
                ignore_user_abort(true);
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }
                
                if (function_exists('fastcgi_finish_request')) {
                    status_header(200);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    fastcgi_finish_request();
                } else {
                    status_header(200);
                    header('Connection: close');
                    header('Content-Type: application/json');
                    ob_start();
                    echo json_encode(['success' => true]);
                    $size = ob_get_length();
                    header("Content-Length: {$size}");
                    ob_end_flush();
                    @ob_flush();
                    flush();
                }

                $text = '';
                if (isset($message_data['text']['body'])) {
                    $text = $message_data['text']['body'];
                } elseif (isset($message_data['type']) && $message_data['type'] === 'image') {
                    $text = "User sent an image."; 
                    if (isset($message_data['image']['caption'])) {
                        $text .= " Caption: " . $message_data['image']['caption'];
                    }
                }
                
                if (!empty($text)) {
                    $this->process_im_message($text, 'whatsapp');
                }
            }
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    // --- Telegram Methods ---

    public function telegram_webhook_receive(\WP_REST_Request $request) {
        $secret_token = get_option('aiutoma_tg_webhook_secret', '');
        $incoming_token = $request->get_header('x_telegram_bot_api_secret_token');
        
        if (empty($secret_token) || !is_string($incoming_token) || !hash_equals($secret_token, $incoming_token)) {
            return new \WP_REST_Response('Forbidden', 403);
        }

        $acting_user_id = (int) apply_filters('aiutoma_im_acting_user_id', (int) get_option('aiutoma_im_acting_user', 0));
        $user = get_userdata($acting_user_id);
        if (!$user || !$user->has_cap('manage_options')) {
            return new \WP_REST_Response('Service Unavailable: Missing or demoted acting user.', 503);
        }

        $body = $request->get_json_params();

        if (isset($body['message'])) {
            $message = $body['message'];
            $chat_id = $message['chat']['id'] ?? '';
            
            $allowed_chat_id = get_option('aiutoma_tg_allowed_chat_id', '');
            
            if (!empty($allowed_chat_id) && (string)$chat_id === (string)$allowed_chat_id) {
                
                ignore_user_abort(true);
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }
                
                if (function_exists('fastcgi_finish_request')) {
                    status_header(200);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    fastcgi_finish_request();
                } else {
                    status_header(200);
                    header('Connection: close');
                    header('Content-Type: application/json');
                    ob_start();
                    echo json_encode(['success' => true]);
                    $size = ob_get_length();
                    header("Content-Length: {$size}");
                    ob_end_flush();
                    @ob_flush();
                    flush();
                }

                $text = '';
                if (isset($message['text'])) {
                    $text = $message['text'];
                } elseif (isset($message['photo'])) {
                    $text = "User sent an image.";
                    if (isset($message['caption'])) {
                        $text .= " Caption: " . $message['caption'];
                    }
                } elseif (isset($message['voice'])) {
                    $text = "User sent a voice message.";
                } elseif (isset($message['document'])) {
                    $text = "User sent a document.";
                }
                
                if (!empty($text)) {
                    $this->process_im_message($text, 'telegram', $chat_id);
                }
            }
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    // --- Shared Processing Methods ---

    private function process_im_message($text, $platform, $telegram_chat_id = null) {
        if (!class_exists('\WordPress\AiClient\AiClient')) return;
        
        if (trim($text) === '/clear') {
            $conversation_id = '';
            if ($platform === 'whatsapp') {
                $target_number = get_option('aiutoma_wa_target_number');
                if (!empty($target_number)) {
                    $conversation_id = 'whatsapp_' . preg_replace('/[^0-9]/', '', $target_number);
                }
            } else if ($platform === 'telegram') {
                $conversation_id = 'telegram_' . $telegram_chat_id;
            }
            if (!empty($conversation_id)) {
                delete_transient($conversation_id);
                $upload_dir = wp_upload_dir();
                $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . sanitize_file_name($conversation_id) . '.json';
                if (file_exists($file_path)) wp_delete_file($file_path);
                if ($platform === 'telegram') {
                    $this->send_telegram_message($telegram_chat_id, "Conversation history cleared. We can start fresh!");
                } else if ($platform === 'whatsapp') {
                    $this->send_whatsapp_message("Conversation history cleared. We can start fresh!");
                }
            }
            return;
        }

        // Authenticate the webhook request as the configured user to allow AI abilities to function
        if (get_current_user_id() === 0) {
            $acting_user_id = (int) apply_filters('aiutoma_im_acting_user_id', (int) get_option('aiutoma_im_acting_user', 0));
            if ($acting_user_id) {
                add_filter('determine_current_user', function() use ($acting_user_id) {
                    return $acting_user_id;
                }, 20);
                global $current_user;
                $current_user = null;
                wp_get_current_user();
            }
        }
        
        $conversation_id = '';
        if ($platform === 'whatsapp') {
            $target_number = get_option('aiutoma_wa_target_number');
            if (empty($target_number)) return;
            $conversation_id = 'whatsapp_' . preg_replace('/[^0-9]/', '', $target_number);
            $prompt_prefix = "User via WhatsApp: ";
        } else if ($platform === 'telegram') {
            $conversation_id = 'telegram_' . $telegram_chat_id;
            $prompt_prefix = "User via Telegram: ";
        } else {
            return;
        }
        
        $chat_request = new \WP_REST_Request('POST', '/aiutoma/v1/ai-chat');
        $chat_request->set_param('conversation_id', $conversation_id);
        $chat_request->set_param('prompt', $prompt_prefix . $text);
        
        $im_model = get_option('aiutoma_im_model', '');
        $im_system_prompt = get_option('aiutoma_im_system_prompt', '');
        
        if (!empty($im_model)) {
            $chat_request->set_param('model', $im_model);
        }
        if (!empty($im_system_prompt)) {
            $chat_request->set_param('session_context', $im_system_prompt);
        }
        
        if (!method_exists($this, 'handle_chat_request')) return;
        
        $response = $this->handle_chat_request($chat_request);
        
        $iterations = 0;
        $max_iterations = 5;
        
        $final_text = '';
        
        while ($iterations < $max_iterations && !is_wp_error($response) && $response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            $last_action = $data['action'] ?? '';
            
            if (isset($data['success']) && $data['success'] === false) {
                $final_text = "Error: " . ($data['message'] ?? 'Unknown error occurred.');
                break;
            }
            
            if ($last_action === 'tool_calls') {
                $iterations++;
                
                $exec_request = new \WP_REST_Request('POST', '/aiutoma/v1/ai-chat');
                $exec_request->set_param('conversation_id', $conversation_id);
                $exec_request->set_param('execute_tools', true);
                if (!empty($im_model)) {
                    $exec_request->set_param('model', $im_model);
                }
                
                // Add a short delay to prevent hitting strict rate limits on the API proxy
                sleep(2);
                
                $response = $this->handle_chat_request($exec_request);
            } else {
                if (isset($data['response']['text'])) {
                    $final_text = $data['response']['text'];
                } elseif (isset($data['response']) && is_string($data['response'])) {
                    $final_text = $data['response'];
                } elseif (isset($data['message'])) {
                    $final_text = $data['message'];
                }
                break;
            }
        }
        
        if (is_wp_error($response)) {
            $final_text = "Error: " . $response->get_error_message();
        }
        
        if (!empty($final_text)) {
            $media_url = '';
            
            if (preg_match('/(https?:\/\/[^\s]+(?:jpg|jpeg|png|webp|pdf|docx|xlsx|mp4))/i', $final_text, $matches)) {
                $media_url = $matches[1];
                $final_text = trim(str_replace($media_url, '', $final_text));
            }
            
            $display_text = $final_text;
            if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                    $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                } else {
                    $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                }
                $display_text = trim($converter->convert($final_text)->getContent());
                
                // Add newlines around block elements so they don't squash together when stripped
                $display_text = preg_replace('/<\/?(p|br|div|h[1-6]|tr|ul|ol)[^>]*>/i', "\n$0\n", $display_text);
                $display_text = preg_replace('/<li[^>]*>/i', "\n- $0", $display_text);
            }
            
            if ($platform === 'whatsapp') {
                $this->send_whatsapp_message($final_text, $media_url); // WhatsApp handles markdown natively better
            } else if ($platform === 'telegram') {
                // Remove unsupported HTML tags for Telegram
                $display_text = strip_tags($display_text, '<b><strong><i><em><u><ins><s><strike><del><a><code><pre>');
                $display_text = trim(preg_replace("/[\r\n]{3,}/", "\n\n", $display_text));
                $this->send_telegram_message($telegram_chat_id, $display_text, $media_url);
            }
            
            // Save Session for Playground History
            $upload_dir = wp_upload_dir();
            $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions';
            if (!is_dir($log_dir)) wp_mkdir_p($log_dir);
            $session_id = sanitize_file_name($conversation_id);
            $file_path = $log_dir . '/' . $session_id . '.json';
            
            $sess_data = [];
            if (file_exists($file_path)) {
                $sess_data = json_decode(file_get_contents($file_path), true);
            }
            if (empty($sess_data)) {
                $sess_data = [
                    'is_full_state' => true,
                    'id' => $session_id,
                    'user' => 'IM User (' . ucfirst($platform) . ')',
                    'first_prompt' => $text,
                    'html' => '',
                    'conversation_id' => $conversation_id,
                    'session_prompts' => []
                ];
            }
            
            $sess_data['date'] = current_time('mysql');
            $sess_data['session_prompts'][] = $text;
            
            $transient_data = get_transient($conversation_id);
            if ($transient_data) {
                $json_data = json_decode($transient_data, true);
                if (is_array($json_data)) {
                    $messages = [];
                    foreach ($json_data as $msg_array) {
                        if (is_array($msg_array) && isset($msg_array['role'])) {
                            $messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray($msg_array);
                        }
                    }
                }
                if (isset($messages) && is_array($messages)) {
                    $html = '';
                    foreach ($messages as $msg) {
                        $role_obj = method_exists($msg, 'getRole') ? $msg->getRole() : '';
                        $role = (is_object($role_obj) && property_exists($role_obj, 'value')) ? $role_obj->value : (string) $role_obj;
                        $content_html = '';
                        $raw_text = '';
                        if (method_exists($msg, 'getParts')) {
                            $parts = $msg->getParts();
                            foreach ($parts as $part) {
                                if (method_exists($part, 'getText') && (string)$part->getText() !== '') {
                                    if ($role === 'model') {
                                        $raw_text .= (string) $part->getText() . "\n";
                                    } else {
                                        $content_html .= nl2br(esc_html((string) $part->getText())) . "<br>";
                                    }
                                } elseif (method_exists($part, 'getFunctionCall') && $part->getFunctionCall()) {
                                    $func = $part->getFunctionCall();
                                    $name = method_exists($func, 'getName') ? $func->getName() : '';
                                    $args = method_exists($func, 'getArgs') ? wp_json_encode($func->getArgs(), JSON_PRETTY_PRINT) : '';
                                    $content_html .= "<strong>🛠️ [Tool Execution]:</strong> <code>" . esc_html($name) . "</code><div style='background:#f0f0f1;padding:10px;margin:5px 0;border-radius:4px;overflow-x:auto;'><pre style='margin:0;'><code>" . esc_html($args) . "</code></pre></div>";
                                } elseif (method_exists($part, 'getFunctionResponse') && $part->getFunctionResponse()) {
                                    $resp = $part->getFunctionResponse();
                                    $name = method_exists($resp, 'getName') ? $resp->getName() : '';
                                    $res = method_exists($resp, 'getResponse') ? wp_json_encode($resp->getResponse(), JSON_PRETTY_PRINT) : '';
                                    $content_html .= "<strong>✅ [Tool Response]:</strong> <code>" . esc_html($name) . "</code><div style='background:#f8f9fa;border-left:3px solid #4caf50;padding:10px;margin:5px 0;overflow-x:auto;'><pre style='margin:0;'><code>" . esc_html($res) . "</code></pre></div>";
                                }
                            }
                        }
                        
                        if ($role === 'model' && !empty($raw_text)) {
                            if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                                if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                                    $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                                } else {
                                    $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
                                }
                                $content_html = wp_kses_post($converter->convert($raw_text)->getContent()) . $content_html;
                            } else {
                                $content_html = nl2br(esc_html($raw_text)) . $content_html;
                            }
                        }
                        
                        if ($role === 'user') {
                            $html .= '<div class="aiutoma-msg-user"><strong>You (' . ucfirst($platform) . '):</strong><br>' . $content_html . '</div>';
                        } elseif ($role === 'model') {
                            $html .= '<div class="aiutoma-msg-ai"><strong>AI:</strong><br>' . $content_html . '</div>';
                        } elseif ($role === 'tool' || $role === 'function') {
                            $html .= '<div class="aiutoma-msg-tool-result aiutoma-success"><strong>Tool Response:</strong><br>' . $content_html . '</div>';
                        }
                    }
                    
                    if (is_wp_error($response)) {
                        $html .= '<div class="aiutoma-msg-error"><strong>Error:</strong><br>' . esc_html($response->get_error_message()) . '</div>';
                    } else if ($response instanceof \WP_REST_Response) {
                        $data = $response->get_data();
                        if (isset($data['success']) && $data['success'] === false) {
                            $html .= '<div class="aiutoma-msg-error"><strong>Error:</strong><br>' . esc_html($data['message'] ?? 'Unknown API Error') . '</div>';
                        }
                    }
                    
                    $sess_data['html'] = $html;
                    $sess_data['raw_messages'] = base64_encode(wp_json_encode($messages));
                }
            }
            
            $sess_data['date'] = current_time('mysql');
            file_put_contents($file_path, json_encode($sess_data));
        }
    }

    public function send_im_admin_notification($message_text, $media_url = '', $buttons = []) {
        $wa_sent = false;
        $tg_sent = false;
        
        $target_number = get_option('aiutoma_wa_target_number');
        if (!empty($target_number)) {
            $this->send_whatsapp_message($message_text, $media_url, $buttons);
            $wa_sent = true;
        }
        
        $tg_chat_id = get_option('aiutoma_tg_allowed_chat_id');
        if (!empty($tg_chat_id)) {
            $this->send_telegram_message($tg_chat_id, $message_text, $media_url, $buttons);
            $tg_sent = true;
        }
        
        return $wa_sent || $tg_sent;
    }

    private function send_whatsapp_message($message_text, $media_url = '', $buttons = []) {
        $phone_number_id = get_option('aiutoma_wa_phone_number_id');
        $access_token = get_option('aiutoma_wa_access_token');
        $target_number = get_option('aiutoma_wa_target_number');

        if (empty($phone_number_id) || empty($access_token) || empty($target_number)) {
            return false;
        }

        if (!empty($buttons)) {
            foreach ($buttons as $btn) {
                if (!empty($btn['url']) && !empty($btn['text'])) {
                    $message_text .= "\n\n" . $btn['text'] . ": " . $btn['url'];
                }
            }
        }

        // Convert HTML to WhatsApp formatting
        $message_text = str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $message_text);
        $message_text = str_ireplace(['<b>', '</b>', '<strong>', '</strong>'], '*', $message_text);
        $message_text = preg_replace('/<a\s+[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/i', '$2: $1', $message_text);
        $message_text = wp_strip_all_tags($message_text);
        $message_text = html_entity_decode($message_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $clean_target = preg_replace('/[^0-9]/', '', $target_number);

        $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";
        
        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $clean_target,
        ];
        
        if (!empty($media_url)) {
            $ext = strtolower(pathinfo(wp_parse_url($media_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
            
            if ($is_image) {
                $body['type'] = 'image';
                $body['image'] = [
                    'link' => $media_url
                ];
                if (!empty($message_text)) {
                    $body['image']['caption'] = mb_strimwidth($message_text, 0, 1024, '...');
                }
            } else {
                $body['type'] = 'document';
                $body['document'] = [
                    'link' => $media_url
                ];
                if (!empty($message_text)) {
                    $body['document']['caption'] = mb_strimwidth($message_text, 0, 1024, '...');
                }
            }
        } else {
            $body['type'] = 'text';
            $body['text'] = [
                'body' => $message_text
            ];
        }

        $args = [
            'body'        => wp_json_encode($body),
            'headers'     => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout'     => 15,
            'data_format' => 'body',
        ];

        return wp_remote_post($url, $args);
    }
    
    private function send_telegram_message($chat_id, $message_text, $media_url = '', $buttons = []) {
        $bot_token = get_option('aiutoma_tg_bot_token');
        if (empty($bot_token) || empty($chat_id)) return false;

        $api_url = "https://api.telegram.org/bot{$bot_token}/";
        
        $reply_markup = '';
        if (!empty($buttons)) {
            $inline_keyboard = [];
            foreach ($buttons as $btn) {
                if (!empty($btn['url']) && !empty($btn['text'])) {
                    $parsed_host = wp_parse_url($btn['url'], PHP_URL_HOST);
                    if (in_array($parsed_host, ['localhost', '127.0.0.1'])) {
                        // Telegram blocks localhost in buttons. Append to text instead.
                        $message_text .= "\n\n" . $btn['text'] . ": " . $btn['url'];
                    } else {
                        $inline_keyboard[] = [['text' => $btn['text'], 'url' => $btn['url']]];
                    }
                }
            }
            if (!empty($inline_keyboard)) {
                $reply_markup = json_encode(['inline_keyboard' => $inline_keyboard]);
            }
        }
        
        if (!empty($media_url)) {
            $ext = strtolower(pathinfo(wp_parse_url($media_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
            
            $endpoint = $is_image ? 'sendPhoto' : 'sendDocument';
            $media_field = $is_image ? 'photo' : 'document';
            
            $body = [
                'chat_id' => $chat_id,
                $media_field => $media_url,
                'caption' => mb_strimwidth($message_text, 0, 1024, '...'),
                'parse_mode' => 'HTML'
            ];
        } else {
            $endpoint = 'sendMessage';
            $body = [
                'chat_id' => $chat_id,
                'text' => $message_text,
                'parse_mode' => 'HTML'
            ];
        }
        
        if ($reply_markup) {
            $body['reply_markup'] = $reply_markup;
        }
        
        $args = [
            'body' => $body,
            'timeout' => 15
        ];
        
        $response = wp_remote_post($api_url . $endpoint, $args);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            error_log('Telegram API Error: ' . print_r($response, true));
            error_log('Telegram Payload: ' . print_r($body, true));
        }
        
        return $response;
    }
}
