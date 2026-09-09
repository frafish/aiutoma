<?php

namespace Aiutoma\Modules\Chatbot\Traits;

if (! defined('ABSPATH')) exit;
trait Settings
{
    public function aiutoma_chatbot_page_html()
    {
        if (isset($_POST['aiutoma_chatbot_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_settings_nonce'])), 'aiutoma_chatbot_settings')) {

            update_option('aiutoma_chatbot_icon', sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_icon'] ?? '')));
            update_option('aiutoma_chatbot_color', sanitize_hex_color(wp_unslash($_POST['aiutoma_chatbot_color'] ?? '')));
            update_option('aiutoma_chatbot_position', sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_position'] ?? '')));

            $name = sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_name'] ?? ''));
            update_option('aiutoma_chatbot_name', $name);
            do_action('wpml_register_single_string', 'aiutoma', 'chatbot_name', $name);

            $greeting = sanitize_textarea_field(wp_unslash($_POST['aiutoma_chatbot_greeting'] ?? ''));
            update_option('aiutoma_chatbot_greeting', $greeting);
            do_action('wpml_register_single_string', 'aiutoma', 'chatbot_greeting', $greeting);

            $contact_msg = sanitize_textarea_field(wp_unslash($_POST['aiutoma_chatbot_contact_msg'] ?? ''));
            update_option('aiutoma_chatbot_contact_msg', $contact_msg);
            do_action('wpml_register_single_string', 'aiutoma', 'chatbot_contact_msg', $contact_msg);

            $custom_context = sanitize_textarea_field(wp_unslash($_POST['aiutoma_chatbot_custom_context'] ?? ''));
            update_option('aiutoma_chatbot_custom_context', $custom_context);

            update_option('aiutoma_chatbot_model', sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_model'] ?? '')));

            update_option('aiutoma_chatbot_auto_fallback', isset($_POST['aiutoma_chatbot_auto_fallback']) ? 1 : 0);
            update_option('aiutoma_chatbot_use_rag', isset($_POST['aiutoma_chatbot_use_rag']) ? 1 : 0);
            update_option('aiutoma_chatbot_full_rag_on_first', isset($_POST['aiutoma_chatbot_full_rag_on_first']) ? 1 : 0);
            update_option('aiutoma_chatbot_rag_first_limit', absint(wp_unslash($_POST['aiutoma_chatbot_rag_first_limit'] ?? 0)));
            update_option('aiutoma_chatbot_rag_search_limit', absint(wp_unslash($_POST['aiutoma_chatbot_rag_search_limit'] ?? 0)));
            update_option('aiutoma_chatbot_history_limit', absint(wp_unslash($_POST['aiutoma_chatbot_history_limit'] ?? 0)));
            update_option('aiutoma_chatbot_woocommerce', isset($_POST['aiutoma_chatbot_woocommerce']) ? 1 : 0);
            $fallback_models = isset($_POST['aiutoma_chatbot_fallback_models']) && is_array($_POST['aiutoma_chatbot_fallback_models'])
                ? array_map('sanitize_text_field', wp_unslash($_POST['aiutoma_chatbot_fallback_models'])) : [];
            update_option('aiutoma_chatbot_fallback_models', $fallback_models);

            $track = isset($_POST['aiutoma_chatbot_track_sessions']) ? 1 : 0;
            update_option('aiutoma_chatbot_track_sessions', $track);

            if (isset($_POST['aiutoma_chatbot_settings_nonce'])) {
                $enabled_abs = isset($_POST['aiutoma_chatbot_enabled_abilities']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['aiutoma_chatbot_enabled_abilities'])) : [];
                update_option('aiutoma_chatbot_enabled_abilities', $enabled_abs);
            }

            $notify = isset($_POST['aiutoma_chatbot_notify_new_session']) ? 1 : 0;
            update_option('aiutoma_chatbot_notify_new_session', $notify);

            $notify_im = isset($_POST['aiutoma_chatbot_notify_new_session_im']) ? 1 : 0;
            update_option('aiutoma_chatbot_notify_new_session_im', $notify_im);

            $notify_email = sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_notify_email'] ?? ''));
            update_option('aiutoma_chatbot_notify_email', $notify_email);

            $notify_template = wp_kses_post(wp_unslash($_POST['aiutoma_chatbot_notify_template'] ?? ''));
            update_option('aiutoma_chatbot_notify_template', $notify_template);

            $gdpr_text = sanitize_textarea_field(wp_unslash($_POST['aiutoma_chatbot_gdpr_text'] ?? ''));
            update_option('aiutoma_chatbot_gdpr_text', $gdpr_text);
            do_action('wpml_register_single_string', 'aiutoma', 'chatbot_gdpr_text', $gdpr_text);

            $gdpr_required = isset($_POST['aiutoma_chatbot_gdpr_required']) ? 1 : 0;
            update_option('aiutoma_chatbot_gdpr_required', $gdpr_required);

            $turnstile_sitekey = sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_turnstile_sitekey'] ?? ''));
            update_option('aiutoma_chatbot_turnstile_sitekey', $turnstile_sitekey);

            $turnstile_secret = sanitize_text_field(wp_unslash($_POST['aiutoma_chatbot_turnstile_secret'] ?? ''));
            update_option('aiutoma_chatbot_turnstile_secret', $turnstile_secret);
            
            $akismet_enabled = isset($_POST['aiutoma_chatbot_akismet_enabled']) ? 1 : 0;
            update_option('aiutoma_chatbot_akismet_enabled', $akismet_enabled);

            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'aiutoma') . '</p></div>';
        }

        $icon = get_option('aiutoma_chatbot_icon', 'dashicons-format-chat');
        $color = get_option('aiutoma_chatbot_color', '#2271b1');
        $position = get_option('aiutoma_chatbot_position', 'bottom-right');

        $name = get_option('aiutoma_chatbot_name', '');
        $greeting = get_option('aiutoma_chatbot_greeting', 'Hello! How can I help you today?');
        $contact_msg = get_option('aiutoma_chatbot_contact_msg', '');
        $custom_context = get_option('aiutoma_chatbot_custom_context', '');
        $selected_model = get_option('aiutoma_chatbot_model', '');
        $auto_fallback = get_option('aiutoma_chatbot_auto_fallback', 0);
        $saved_fallback_models = get_option('aiutoma_chatbot_fallback_models', []);

        $notify_new_session = get_option('aiutoma_chatbot_notify_new_session', 0);
        $notify_email = get_option('aiutoma_chatbot_notify_email', get_option('admin_email'));

        $enabled_abilities = get_option('aiutoma_chatbot_enabled_abilities', false);
        if ($enabled_abilities === false) {
            $enabled_abilities = ['wc_search_products', 'wc_add_to_cart', 'wc_remove_from_cart', 'wc_apply_coupon', 'wc_get_user_orders', 'search_site_content', 'wpml_get_translated_url', 'wpab__ai__fill_form_field', 'request_human_contact'];
        }

        $upload_dir = wp_upload_dir();
        $rag_db_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/rag.sqlite';
        $rag_db_exists = file_exists($rag_db_path);

        // Fetch models for dropdown
        $models = [];
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(), \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory()],
                []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $modelName = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    $models[$providerId . '|' . $id] = '[' . $providerName . '] ' . $modelName;
                }
            }
        }

        $export_data = [
            'model' => $selected_model,
            'fallback_models' => $saved_fallback_models,
        ];
        $export_json = wp_json_encode($export_data);
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Frontend Chatbot Settings', 'aiutoma'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aiutoma-chatbot-logs')); ?>" class="page-title-action"><?php esc_html_e('View Chat Logs', 'aiutoma'); ?></a>
            <hr class="wp-header-end">
            <form method="post" action="" id="aiutoma_chatbot_settings_form">
                <?php wp_nonce_field('aiutoma_chatbot_settings', 'aiutoma_chatbot_settings_nonce'); ?>


                <h2 class="nav-tab-wrapper" id="aiutoma-chatbot-tabs">
                    <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'aiutoma'); ?></a>
                    <a href="#appearance" class="nav-tab"><?php esc_html_e('Appearance', 'aiutoma'); ?></a>
                    <a href="#content" class="nav-tab"><?php esc_html_e('Content', 'aiutoma'); ?></a>
                    <?php if ($rag_db_exists): ?>
                        <a href="#rag" class="nav-tab"><?php esc_html_e('Knowledge', 'aiutoma'); ?></a>
                    <?php endif; ?>
                    <a href="#abilities" class="nav-tab"><?php esc_html_e('Abilities', 'aiutoma'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php esc_html_e('Advanced', 'aiutoma'); ?></a>
                    <a href="#antispam" class="nav-tab"><?php esc_html_e('Anti-Spam', 'aiutoma'); ?></a>
                    <a href="#notification" class="nav-tab"><?php esc_html_e('Notification', 'aiutoma'); ?></a>
                </h2>
                <div id="tab-general" class="aiutoma-chatbot-tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('AI Model', 'aiutoma'); ?></th>
                            <td>
                                <select name="aiutoma_chatbot_model" class="aiutoma-chatbot-select-model">
                                    <option value=""><?php esc_html_e('Default (Auto-select)', 'aiutoma'); ?></option>
                                    <?php foreach ($models as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto-Fallback', 'aiutoma'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aiutoma_chatbot_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                    <?php esc_html_e('Automatically switch to another model if the selected model fails', 'aiutoma'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'aiutoma'); ?></th>
                            <td>
                                <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks. If none are selected, all available models will be used.', 'aiutoma'); ?></p>
                                <div class="aiutoma-chatbot-flex-actions">
                                    <select name="aiutoma_chatbot_fallback_models[]" multiple class="aiutoma-chatbot-select-fallback">
                                        <?php foreach ($models as $val => $label): ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected(in_array($val, (array) $saved_fallback_models, true), true); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="aiutoma-chatbot-flex-btns">
                                        <button type="button" class="button aiutoma-chatbot-icon-btn" id="aiutoma_export_btn" data-export="<?php echo esc_attr($export_json); ?>" title="<?php esc_attr_e('Export Models Configuration', 'aiutoma'); ?>">
                                            <span class="dashicons dashicons-download"></span>
                                        </button>
                                        <button type="button" class="button aiutoma-chatbot-icon-btn" id="aiutoma_import_btn" title="<?php esc_attr_e('Import Models Configuration', 'aiutoma'); ?>">
                                            <span class="dashicons dashicons-upload"></span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Custom Context (Optional)', 'aiutoma'); ?></th>
                            <td>
                                <textarea name="aiutoma_chatbot_custom_context" rows="4" class="large-text" placeholder="<?php esc_attr_e('Provide custom instructions or context for the chatbot about your site...', 'aiutoma'); ?>"><?php echo esc_textarea($custom_context); ?></textarea>
                                <p class="description"><?php esc_html_e('These instructions will be appended to the AI\'s system prompt. Useful for setting specific rules, brand tone, or custom context.', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php if ($rag_db_exists): ?>
                    <div id="tab-rag" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Use RAG', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <?php $default_rag = get_option('aiutoma_rag_cron_enabled', 'no') === 'yes' ? 1 : 1; ?>
                                        <input type="checkbox" name="aiutoma_chatbot_use_rag" id="aiutoma_chatbot_use_rag" value="1" <?php checked(get_option('aiutoma_chatbot_use_rag', $default_rag), 1); ?>>
                                        <?php esc_html_e('Pass website Knowledge Base context to the Chatbot to improve answers', 'aiutoma'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr class="aiutoma-rag-dependent">
                                <th scope="row"><?php esc_html_e('Full RAG on First Message', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aiutoma_chatbot_full_rag_on_first" value="1" <?php checked(get_option('aiutoma_chatbot_full_rag_on_first', 0), 1); ?>>
                                        <?php esc_html_e('Inject the entire RAG knowledge base into the very first chat message so the AI "learns" all the site content upfront. (Warning: uses more tokens on the first request)', 'aiutoma'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr class="aiutoma-rag-dependent">
                                <th scope="row"><?php esc_html_e('Token Optimization: RAG Limits', 'aiutoma'); ?></th>
                                <td>
                                    <div style="margin-bottom: 5px;">
                                        <input type="number" name="aiutoma_chatbot_rag_first_limit" value="<?php echo esc_attr(get_option('aiutoma_chatbot_rag_first_limit', 30)); ?>" style="width: 80px;" min="1" max="500">
                                        <label><?php esc_html_e('Max RAG records to inject on First Message (if enabled)', 'aiutoma'); ?></label>
                                    </div>
                                    <div>
                                        <input type="number" name="aiutoma_chatbot_rag_search_limit" value="<?php echo esc_attr(get_option('aiutoma_chatbot_rag_search_limit', 5)); ?>" style="width: 80px;" min="1" max="50">
                                        <label><?php esc_html_e('Max RAG records to fetch on subsequent Search Queries', 'aiutoma'); ?></label>
                                    </div>
                                    <p class="description"><?php esc_html_e('Lower values drastically reduce Token usage, but the AI will have less context.', 'aiutoma'); ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </table>
                    </div>

                    <div id="tab-abilities" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <p class="description" style="margin-bottom: 20px;"><?php esc_html_e('Select which abilities the chatbot is allowed to use. Each active ability increases the token cost of every prompt.', 'aiutoma'); ?></p>
                        <div class="notice notice-warning inline">
                            <p><strong><?php esc_html_e('Security Warning:', 'aiutoma'); ?></strong> <?php esc_html_e('Enabling arbitrary abilities (like executing code, modifying files, etc.) for a frontend chatbot allows ANY public visitor to execute those actions on your server. ONLY enable abilities that are safe for public use.', 'aiutoma'); ?></p>
                        </div>

                        <?php
                        $suggested = [
                            'search_site_content' => ['title' => 'Search Site Content', 'desc' => 'Search the website for pages, posts, or products matching a query.'],
                            'wpab__ai__fill_form_field' => ['title' => 'Fill Form Field', 'desc' => 'Fill a frontend HTML form field with a specific value. Useful when assisting a user to complete a form.'],
                            'request_human_contact' => ['title' => 'Request Human Contact', 'desc' => 'Allow the bot to offer the user a contact form if it cannot help them.']
                        ];

                        if (class_exists('WooCommerce')) {
                            $suggested['wc_search_products'] = ['title' => 'Search Products', 'desc' => 'Search for WooCommerce products by name or keyword.'];
                            $suggested['wc_add_to_cart'] = ['title' => 'Add to Cart', 'desc' => 'Add a specific product or variation to the shopping cart.'];
                            $suggested['wc_remove_from_cart'] = ['title' => 'Remove from Cart', 'desc' => 'Remove a product from the shopping cart.'];
                            $suggested['wc_apply_coupon'] = ['title' => 'Apply Coupon', 'desc' => 'Apply a discount coupon code to the shopping cart.'];
                            $suggested['wc_get_user_orders'] = ['title' => 'Get User Orders', 'desc' => 'Get the current logged-in user\'s past orders and their status.'];
                        }

                        if (class_exists('SitePress')) {
                            $suggested['wpml_get_translated_url'] = ['title' => 'Get Translated URL', 'desc' => 'Get the translated URL of a page or product in a specific language.'];
                        }

                        $chatbot_abilities = [
                            'Suggested (Frontend Safe)' => $suggested
                        ];

                        $all_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
                        $all_abilities = apply_filters('aiutoma/abilities', $all_abilities);
                        $all_abilities = apply_filters('wizard_blocks_ai_abilities', $all_abilities);

                        $grouped_other = [];
                        foreach ($all_abilities as $ab) {
                            $ab_name = method_exists($ab, 'get_name') ? $ab->get_name() : '';
                            if (empty($ab_name)) continue;
                            $ns = explode('/', $ab_name)[0];
                            if (strpos($ns, '__') !== false) {
                                $parts = explode('__', $ns);
                                $ns = $parts[0] . ' (Pro)';
                            }
                            if (!isset($grouped_other[$ns])) $grouped_other[$ns] = [];
                            $grouped_other[$ns][$ab_name] = [
                                'title' => method_exists($ab, 'get_label') && !empty($ab->get_label()) ? $ab->get_label() : ucwords(str_replace(['_', '-'], ' ', str_replace($ns . '__', '', $ab_name))),
                                'desc' => method_exists($ab, 'get_description') ? $ab->get_description() : ''
                            ];
                        }
                        $chatbot_abilities = array_merge($chatbot_abilities, $grouped_other);

                        foreach ($chatbot_abilities as $group => $abs) {
                            $is_suggested = $group === 'Suggested (Frontend Safe)';
                            $card_border = $is_suggested ? 'border: 2px solid #2271b1; box-shadow: 0 2px 10px rgba(34,113,177,0.1);' : 'border: 1px solid #ccd0d4;';
                            $title_style = $is_suggested ? 'color: #2271b1; font-size: 18px; font-weight: bold;' : 'color: #1d2327; font-size: 15px; font-weight: 600;';

                            echo '<div class="postbox" style="padding: 0 20px 20px; background: #fff; margin-top: 15px; border-radius: 6px; ' . esc_attr($card_border) . '">';
                            echo '<h3 style="margin: 20px 0 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; ' . esc_attr($title_style) . '">' . esc_html(ucfirst($group)) . '</h3>';
                            echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">';
                            foreach ($abs as $id => $info) {
                                if ($id === 'wpab__ai__fill_form_field' && $group !== 'Suggested (Frontend Safe)') continue;
                                $is_checked = in_array($id, $enabled_abilities);

                                $border_color = $is_checked ? '#2271b1' : '#c3c4c7';
                                $bg_color = $is_checked ? '#f0f6fc' : '#f6f7f7';

                                echo '<div style="background: ' . esc_attr($bg_color) . '; border: 1px solid ' . esc_attr($border_color) . '; padding: 12px; border-radius: 4px; transition: all 0.2s;">';
                                echo '<label style="display:block; font-weight: bold; margin-bottom: 6px; color: #1d2327; cursor: pointer;">';
                                echo '<input type="checkbox" name="aiutoma_chatbot_enabled_abilities[]" value="' . esc_attr($id) . '" class="aiutoma-chatbot-ability-cb" ' . checked($is_checked, true, false) . '>';
                                echo ' ' . esc_html($info['title']);
                                echo '</label>';
                                echo '<p style="margin: 0 0 8px 0; font-size: 12px; color: #50575e; line-height: 1.4;">' . esc_html($info['desc']) . '</p>';
                                echo '<div style="font-size: 11px; color: #8c8f94;">⚡ Est. Tokens: <strong>~110</strong></div>';
                                echo '</div>';
                            }
                            echo '</div>';
                            echo '</div>'; // close postbox
                        }
                        ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #72aee6; font-size: 14px;">
                            <strong>Total Estimated Token Cost for Abilities: </strong>
                            <span id="aiutoma-chatbot-total-tokens-estimate" style="font-weight: bold; color: #2271b1; font-size: 16px;">0</span> tokens per prompt
                        </div>
                    </div>

                    <div id="tab-advanced" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Token Optimization: Chat History Limit', 'aiutoma'); ?></th>
                                <td>
                                    <input type="number" name="aiutoma_chatbot_history_limit" value="<?php echo esc_attr(get_option('aiutoma_chatbot_history_limit', 10)); ?>" style="width: 80px;" min="0">
                                    <label><?php esc_html_e('Max previous messages to keep in memory', 'aiutoma'); ?></label>
                                    <p class="description"><?php esc_html_e('Trims the chat history to the last N messages to avoid the payload growing infinitely. Set to 0 to disable trimming. (Note: The first message is always kept if RAG is injected into it).', 'aiutoma'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Track Sessions', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aiutoma_chatbot_track_sessions" value="1" <?php checked(get_option('aiutoma_chatbot_track_sessions', 0), 1); ?>>
                                        <?php esc_html_e('Log conversations in the database so you can analyze them in the Chatbot Logs page', 'aiutoma'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Require GDPR Consent', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aiutoma_chatbot_gdpr_required" value="1" <?php checked(get_option('aiutoma_chatbot_gdpr_required', 1), 1); ?>>
                                        <?php esc_html_e('Require users to check a privacy consent box before sending a message', 'aiutoma'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('GDPR Notice Text', 'aiutoma'); ?></th>
                                <td>
                                    <textarea name="aiutoma_chatbot_gdpr_text" rows="3" class="aiutoma-chatbot-gdpr-textarea"><?php echo esc_textarea(get_option('aiutoma_chatbot_gdpr_text', 'By chatting, you agree to our processing of conversation logs to assist with your request. See our Privacy for your data rights.')); ?></textarea>
                                    <p class="description"><?php esc_html_e('Enter the text to display in the GDPR consent notice.', 'aiutoma'); ?></p>
                                </td>
                            </tr>
                            <?php if (class_exists('WooCommerce')): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('WooCommerce Integration', 'aiutoma'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="aiutoma_chatbot_woocommerce" value="1" <?php checked(get_option('aiutoma_chatbot_woocommerce', 1), 1); ?>>
                                            <?php esc_html_e('Enable WooCommerce tools (Add to cart, apply coupon, check orders) for the Chatbot', 'aiutoma'); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div id="tab-antispam" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Cloudflare Turnstile', 'aiutoma'); ?></th>
                                <td>
                                    <p class="description"><?php esc_html_e('Protect your chatbot from spam and budget exhaustion by requiring a Cloudflare Turnstile challenge.', 'aiutoma'); ?></p>
                                    <br>
                                    <input type="text" name="aiutoma_chatbot_turnstile_sitekey" value="<?php echo esc_attr(get_option('aiutoma_chatbot_turnstile_sitekey', '')); ?>" class="regular-text" placeholder="Site Key">
                                    <p class="description"><?php esc_html_e('Turnstile Site Key', 'aiutoma'); ?></p>
                                    <br>
                                    <input type="password" name="aiutoma_chatbot_turnstile_secret" value="<?php echo esc_attr(get_option('aiutoma_chatbot_turnstile_secret', '')); ?>" class="regular-text" placeholder="Secret Key">
                                    <p class="description"><?php esc_html_e('Turnstile Secret Key', 'aiutoma'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Akismet Anti-Spam', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aiutoma_chatbot_akismet_enabled" value="1" <?php checked(get_option('aiutoma_chatbot_akismet_enabled', 0), 1); ?>>
                                        <?php esc_html_e('Enable Akismet spam filtering for chatbot messages (requires Akismet plugin to be active)', 'aiutoma'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div id="tab-appearance" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Chatbot Icon', 'aiutoma'); ?></th>
                                <td>
                                    <?php
                                    $dashicons = [
                                        'dashicons-format-chat',
                                        'dashicons-smiley',
                                        'dashicons-admin-users',
                                        'dashicons-businessman',
                                        'dashicons-testimonial',
                                        'dashicons-megaphone',
                                        'dashicons-microphone',
                                        'dashicons-info',
                                        'dashicons-editor-help',
                                        'dashicons-lightbulb',
                                        'dashicons-thumbs-up',
                                        'dashicons-heart',
                                        'dashicons-star-filled',
                                        'dashicons-yes',
                                        'dashicons-warning',
                                        'dashicons-sos',
                                        'dashicons-lifesaver',
                                        'dashicons-visibility',
                                        'dashicons-welcome-learn-more',
                                        'dashicons-admin-site',
                                        'dashicons-admin-generic',
                                        'dashicons-admin-customizer',
                                        'dashicons-admin-comments',
                                        'dashicons-admin-network',
                                        'dashicons-welcome-widgets-menus',
                                        'dashicons-welcome-comments',
                                        'dashicons-groups',
                                        'dashicons-store',
                                        'dashicons-format-status',
                                        'dashicons-format-quote',
                                        'dashicons-carrot',
                                        'dashicons-art',
                                        'dashicons-buddicons-buddypress-logo',
                                        'dashicons-buddicons-groups',
                                        'dashicons-buddicons-topics',
                                        'dashicons-buddicons-pm',
                                        'dashicons-email-alt',
                                        'dashicons-email-alt2',
                                        'dashicons-whatsapp',
                                        'dashicons-facebook',
                                        'dashicons-twitter'
                                    ];
                                    ?>
                                    <div class="aiutoma-chatbot-icon-selector">
                                        <?php foreach ($dashicons as $d): ?>
                                            <label class="aiutoma-chatbot-icon-label <?php echo esc_attr($icon === $d ? 'aiutoma-chatbot-icon-label-active' : ''); ?>" onclick="document.querySelectorAll('.aiutoma-chatbot-icon-selector label').forEach(l => {l.classList.remove('aiutoma-chatbot-icon-label-active');}); this.classList.add('aiutoma-chatbot-icon-label-active'); document.getElementById('aiutoma_chatbot_icon_input').value='<?php echo esc_attr($d); ?>';">
                                                <span class="dashicons <?php echo esc_attr($d); ?>"></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="aiutoma_chatbot_icon" id="aiutoma_chatbot_icon_input" value="<?php echo esc_attr($icon); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Main Color', 'aiutoma'); ?></th>
                                <td>
                                    <input type="color" name="aiutoma_chatbot_color" value="<?php echo esc_attr($color); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Position', 'aiutoma'); ?></th>
                                <td>
                                    <select name="aiutoma_chatbot_position">
                                        <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'aiutoma'); ?></option>
                                        <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'aiutoma'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div id="tab-content" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Chatbot Name', 'aiutoma'); ?></th>
                                <td>
                                    <input type="text" name="aiutoma_chatbot_name" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="AI Bot">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Initial Greeting', 'aiutoma'); ?></th>
                                <td>
                                    <textarea name="aiutoma_chatbot_greeting" rows="4" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Contact Prompt (Optional)', 'aiutoma'); ?></th>
                                <td>
                                    <textarea name="aiutoma_chatbot_contact_msg" rows="2" class="large-text" placeholder="<?php esc_attr_e('e.g. Do you want to be contacted by us? Leave your email.', 'aiutoma'); ?>"><?php echo esc_textarea($contact_msg); ?></textarea>
                                    <p class="description"><?php esc_html_e('This message will be appended to the AI\'s responses to encourage visitors to leave their email address. It will automatically hide once an email is provided or if the user is logged in.', 'aiutoma'); ?></p>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <div id="tab-notification" class="aiutoma-chatbot-tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('New Session Notification', 'aiutoma'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aiutoma_chatbot_notify_new_session" value="1" <?php checked($notify_new_session, 1); ?>>
                                        <?php esc_html_e('Send an email notification when a new chat session starts', 'aiutoma'); ?>
                                    </label>
                                    <br><br>
                                    <input type="email" name="aiutoma_chatbot_notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Email address to receive the notification. Defaults to admin email.', 'aiutoma'); ?></p>
                                    <br><br>
                                    <label>
                                        <?php $notify_new_session_im = get_option('aiutoma_chatbot_notify_new_session_im', 0); ?>
                                        <input type="checkbox" name="aiutoma_chatbot_notify_new_session_im" value="1" <?php checked($notify_new_session_im, 1); ?>>
                                        <?php esc_html_e('Send an IM notification when a new chat session starts', 'aiutoma'); ?>
                                    </label>
                                    <p class="description" style="margin-top: 5px;">
                                        <?php /* translators: %s: URL to IM page */ echo wp_kses_post(sprintf(__('Configure your <a href="%s">Instant Messaging Settings</a> to receive notifications via WhatsApp or Telegram.', 'aiutoma'), admin_url('admin.php?page=aiutoma-im'))); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Custom Email Template', 'aiutoma'); ?></th>
                                <td>
                                    <?php
                                    $notify_template = get_option('aiutoma_chatbot_notify_template', '');
                                    wp_editor($notify_template, 'aiutoma_chatbot_notify_template', ['textarea_name' => 'aiutoma_chatbot_notify_template', 'media_buttons' => false, 'textarea_rows' => 10]);
                                    ?>
                                    <p class="description">
                                        <?php esc_html_e('Leave empty to use native WordPress comment notification email.', 'aiutoma'); ?><br>
                                        <?php esc_html_e('Available placeholders: [site_name], [session_id], [log_url], [user_message], [user_name], [user_email]', 'aiutoma'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit">
                        <?php submit_button('', 'primary', 'submit', false); ?>
                    </p>

                    <input type="file" id="aiutoma_import_file" style="display:none" accept=".json">
            </form>
        </div>
<?php
    }
}
