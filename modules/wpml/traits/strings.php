<?php
namespace Aiutoma\Modules\Wpml\traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound



trait Strings {
    public function render_strings_tab() {
        global $sitepress, $wpdb;
        $active_langs = $sitepress->get_active_languages();
        $default_lang = $sitepress->get_default_language();

        $string_domains = [];
        $string_domains_grouped = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'")) {
            $string_domains = $wpdb->get_col("SELECT DISTINCT context FROM {$wpdb->prefix}icl_strings ORDER BY context ASC");
            
            $string_domains_grouped = [
                'Theme' => [],
                'Plugins' => [],
                'Blocks' => [],
                'Settings / Admin' => [],
                'Other' => []
            ];
            
            $theme_slugs = [get_stylesheet(), get_template()];
            $plugin_slugs = [];
            foreach (get_option('active_plugins', []) as $plugin) {
                $plugin_slugs[] = dirname($plugin);
            }
            
            foreach ($string_domains as $domain) {
                if (strpos($domain, 'admin_texts_') === 0) {
                    $string_domains_grouped['Settings / Admin'][] = $domain;
                } elseif (strpos($domain, 'gutenberg_') === 0 || strpos($domain, 'block') !== false) {
                    $string_domains_grouped['Blocks'][] = $domain;
                } elseif (in_array($domain, $theme_slugs)) {
                    $string_domains_grouped['Theme'][] = $domain;
                } elseif (in_array($domain, $plugin_slugs) || strpos($domain, 'plugin-') === 0) {
                    $string_domains_grouped['Plugins'][] = $domain;
                } else {
                    $string_domains_grouped['Other'][] = $domain;
                }
            }
        }

        ?>
        <div class="icl_tm_wrap">
            <div class="wpml-tm-dashboard-message info with-close-button" style="margin-bottom: 24px;">
                <div class="wpml-tm-dashboard-message-content">
                    <?php esc_html_e('Select a domain and target language to find untranslated strings and generate translations automatically via AI.', 'aiutoma'); ?>
                </div>
            </div>
            
            <div class="wpml-global-filter">
                <div class="wpml-flex-space-between">
                    <div class="wpml-flex-sb-container global-filters-wrapper" style="width: 100%; display:flex; gap:16px;">
                        
                        <div class="select-dropdown">
                            <label for="aiutoma_wpml_string_domain" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Domain:', 'aiutoma'); ?></label>
                            <select id="aiutoma_wpml_string_domain">
                                <?php 
                                foreach ($string_domains_grouped as $group_name => $domains) {
                                    if (empty($domains)) continue;
                                    echo '<optgroup label="' . esc_attr($group_name) . '">';
                                    foreach ($domains as $domain) {
                                        echo '<option value="' . esc_attr($domain) . '">' . esc_html($domain) . '</option>';
                                    }
                                    echo '</optgroup>';
                                } 
                                ?>
                            </select>
                        </div>

                        <div class="select-dropdown">
                            <label for="aiutoma_wpml_string_lang" style="font-weight: 500; margin-right: 8px;"><?php esc_html_e('Target Language:', 'aiutoma'); ?></label>
                            <select id="aiutoma_wpml_string_lang">
                                <?php foreach ($active_langs as $lang) {
                                    if ($lang['code'] === $default_lang) continue;
                                    echo '<option value="' . esc_attr($lang['code']) . '">' . esc_html($lang['display_name']) . '</option>';
                                } ?>
                            </select>
                        </div>
                        
                        <button class="wpml-button base-btn wpml-button--outlined filter-button" id="aiutoma_scan_strings">
                            <?php esc_html_e('Filter Strings', 'aiutoma'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="aiutoma_bulk_strings_results" style="display:none; margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <div style="padding: 15px; border-bottom: 2px solid #373737; display:flex; justify-content:space-between; align-items:center; background: #fafafa;">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <label class="wpml-checkbox" style="margin: 0;"><input type="checkbox" id="aiutoma_select_all_strings" checked> <span><?php esc_html_e('Select All', 'aiutoma'); ?> (<span id="aiutoma_missing_strings_count">0</span> items)</span></label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label for="aiutoma_local_search_strings" style="font-weight: 500;"><?php esc_html_e('Filter by:', 'aiutoma'); ?></label>
                            <input type="text" id="aiutoma_local_search_strings" placeholder="<?php esc_attr_e('String name or text...', 'aiutoma'); ?>" style="padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
                        </div>
                    </div>
                    <button class="wpml-button base-btn" id="aiutoma_start_bulk_strings">
                        <?php esc_html_e('Translate Strings via AI', 'aiutoma'); ?>
                    </button>
                </div>
                <div id="aiutoma_missing_strings_list" style="max-height: 500px; overflow-y: auto;"></div>
                <div id="aiutoma_bulk_strings_progress" style="padding:15px; font-weight:500;"></div>
            </div>
        </div>
        <?php
    }

    public function handle_wpml_strings_get_missing(\WP_REST_Request $request) {
        global $wpdb;
        $domain = $request->get_param('domain');
        $target_lang = $request->get_param('target_lang');
        
        if (empty($domain) || empty($target_lang)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing domain or target_lang parameters'], 400);
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT s.id, s.name, s.value 
            FROM {$wpdb->prefix}icl_strings s
            LEFT JOIN {$wpdb->prefix}icl_string_translations st 
                ON s.id = st.string_id AND st.language = %s
            WHERE s.context = %s 
              AND s.language != %s
              AND (st.id IS NULL OR st.status != 10)
            ORDER BY s.id DESC LIMIT 500
        ", $target_lang, $domain, $target_lang));
        
        $items = [];
        foreach ($results as $r) {
            $items[] = [
                'id' => $r->id,
                'name' => esc_html(mb_strimwidth($r->name, 0, 80, '...')),
                'value' => esc_html(mb_strimwidth($r->value, 0, 150, '...'))
            ];
        }
        
        return new \WP_REST_Response(['success' => true, 'items' => $items], 200);
    }

    public function handle_wpml_strings_translate(\WP_REST_Request $request) {
        global $wpdb;
        $string_id = (int)$request->get_param('string_id');
        $target_lang = $request->get_param('target_lang');
        
        if (!$string_id || empty($target_lang) || !function_exists('icl_add_string_translation')) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid request or ST not installed'], 400);
        }
        
        $string_value = $wpdb->get_var($wpdb->prepare("SELECT value FROM {$wpdb->prefix}icl_strings WHERE id = %d", $string_id));
        if (empty($string_value)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'String empty or not found'], 400);
        }
        
        $prompt = "You are an expert translator. Translate the following UI/text string into the language represented by the ISO code '{$target_lang}'.\n";
        $prompt .= "IMPORTANT RULES:\n";
        $prompt .= "1. Respond ONLY with the translated text. Do NOT include any explanations, quotes, or JSON.\n";
        $prompt .= "2. Preserve any placeholders like %s, %d, {name}, HTML tags exactly.\n\n";
        $prompt .= "TEXT TO TRANSLATE:\n" . $string_value;
        
        $model = get_option('aiutoma_wpml_model', '');
        if ($model === 'google_free' && method_exists($this, 'google_translate_free')) {
            $source_lang = $wpdb->get_var($wpdb->prepare("SELECT language FROM {$wpdb->prefix}icl_strings WHERE id = %d", $string_id));
            if (!$source_lang) $source_lang = 'en'; // default fallback
            $result = $this->google_translate_free($source_lang, $target_lang, $string_value);
        } else {
            $result = $this->call_ai_for_text($prompt);
        }
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
        }
        
        if (trim($result) === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'Empty translation received'], 500);
        }
        
        icl_add_string_translation($string_id, $target_lang, trim($result), 10);
        
        return new \WP_REST_Response(['success' => true], 200);
    }

    private function call_ai_for_text($prompt) {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_Error('ai_missing', 'AI Client not found.');
        }
        
        $preferred_model = get_option('aiutoma_wpml_model', '');
        $auto_fallback = get_option('aiutoma_wpml_auto_fallback', 0);
        $fallback_models = get_option('aiutoma_wpml_fallback_models', []);
        
        $models_to_try = [];
        if (!empty($preferred_model)) {
            $models_to_try[] = $preferred_model;
            if ($auto_fallback && !empty($fallback_models)) {
                $models_to_try = array_merge($models_to_try, $fallback_models);
            }
        }
        
        if (empty($models_to_try)) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()], []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $pm) {
                $pid = $pm->getProvider()->getId();
                foreach ($pm->getModels() as $m) {
                    $models_to_try[] = $pid . '|' . $m->getId();
                    break 2; 
                }
            }
        }
        
        $last_exception = null;
        
        foreach ($models_to_try as $model_str) {
            $model_parts = explode('|', $model_str);
            if (count($model_parts) !== 2) continue;
            
            $max_retries = 2;
            for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
                try {
                    $ai_query = \WordPress\AiClient\AiClient::prompt([
                        new \WordPress\AiClient\Messages\DTO\UserMessage([
                            new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                        ])
                    ]);
                    
                    $ai_query->usingModelPreference($model_parts);
                    $res = $ai_query->generateResult();
                    $text = $res->toText();
                    
                    if (!empty($text)) {
                        return $text; 
                    } else {
                        throw new \Exception("Empty text returned from model.");
                    }
                } catch (\Exception $e) {
                    $last_exception = $e;
                    $msg = strtolower($e->getMessage());
                    
                    if (strpos($msg, 'token') !== false || strpos($msg, 'quota') !== false || strpos($msg, 'credit') !== false || strpos($msg, 'rate') !== false || strpos($msg, 'limit') !== false || strpos($msg, '429') !== false || strpos($msg, '408') !== false || strpos($msg, '500') !== false || strpos($msg, '502') !== false || strpos($msg, '503') !== false || strpos($msg, 'timeout') !== false) {
                        if ($attempt < $max_retries) {
                            sleep(2);
                            continue;
                        }
                    }
                    
                    break;
                }
            }
        }
        
        if ($last_exception) {
            return new \WP_Error('ai_exception', 'All AI models failed. Last error: ' . $last_exception->getMessage());
        }
        
        return new \WP_Error('ai_unknown_error', 'Failed to generate text.');
    }

    public function aiutoma_wpml_has_strings() {
        global $wpdb;
        return (bool) $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_strings'");
    }
}
