<?php

namespace Aiutoma\Modules\Ai;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite
// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped


class Ai
{



    use Traits\Skills;
    use Traits\AbilitiesUi;
    use Traits\ModelsUi;
    use Traits\SettingsUi;
    use Traits\CacheBypass;
    use Traits\AiOutputValidator;
    use Traits\AuditLogger;
    use Traits\AiTokensLog;

    public $cm_settings = null;
    public $cm_sql_settings = null;
    use Traits\Rag;
    use Traits\DocumentParser;

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        if (self::$instance !== null) return;
        self::$instance = $this;

        $this->register_cache_bypass_hooks();
        $this->register_rag_hooks();
        $this->register_ai_tokens_log_hooks();

        add_action('rest_api_init', [$this, 'register_ai_routes']);


        add_action('admin_menu', [$this, 'add_ai_settings_menu']);
        add_action('admin_menu', [$this, 'add_ai_skills_abilities_menu'], 99);

        new Abilities();



        $ai_enabled = class_exists('\WordPress\AiClient\AiClient');
        if ($ai_enabled) {

            $this->register_skills_hooks();
        }

        add_action('aiutoma_update_models_cron', [$this, 'run_update_models_cron']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 5);
        add_action('admin_notices', [$this, 'display_low_token_alert']);
        add_action('wp_ajax_aiutoma_dismiss_low_token_alert', [$this, 'ajax_dismiss_low_token_alert']);
        add_action('updated_option', [$this, 'on_option_updated'], 10, 1);
    }


    private static $storage_dir = null;
    
    public static function get_storage_dir()
    {
        if (self::$storage_dir !== null) {
            return self::$storage_dir;
        }

        $upload_dir = wp_upload_dir();
        $selected_dir = $upload_dir['basedir'] . '/aiutoma';
        wp_mkdir_p($selected_dir);
        
        self::secure_directory($selected_dir);
        self::$storage_dir = $selected_dir;
        
        return $selected_dir;
    }

    public static function get_safe_mode_flag_path()
    {
        return self::get_storage_dir() . '/.aiutoma_safe';
    }

    public static function get_cron_flag_path()
    {
        return self::get_storage_dir() . '/.aiutoma_cron_running';
    }

    private static function secure_directory($dir)
    {
        if (empty($dir) || !is_dir($dir)) return;
        
        $htaccess_path = $dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccess_path, $htaccess_content);
        }

        $index_path = $dir . '/index.php';
        if (!file_exists($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }
    }

    public function enqueue_admin_assets($hook)
    {
        $s2_js = AIUTOMA_PATH . 'modules/ai/assets/js/select2.min.js';
        $s2_css = AIUTOMA_PATH . 'modules/ai/assets/css/select2.min.css';
        $s2_js_ver = file_exists($s2_js) ? filemtime($s2_js) : AIUTOMA_VERSION;
        $s2_css_ver = file_exists($s2_css) ? filemtime($s2_css) : AIUTOMA_VERSION;

        wp_register_style('aiutoma-select2', AIUTOMA_URL . 'modules/ai/assets/css/select2.min.css', array(), $s2_css_ver);
        wp_register_script('aiutoma-select2', AIUTOMA_URL . 'modules/ai/assets/js/select2.min.js', array('jquery'), $s2_js_ver, true);

        if (strpos($hook, 'aiutoma') !== false) {
            wp_enqueue_style('aiutoma-select2');
            wp_enqueue_script('aiutoma-select2');
        }

        if ($hook === 'aiutoma_page_aiutoma-models') {
            wp_enqueue_style('aiutoma-models-ui-css', AIUTOMA_URL . 'modules/ai/assets/css/models-ui.css', [], filemtime(AIUTOMA_PATH . 'modules/ai/assets/css/models-ui.css'));
            wp_enqueue_script('aiutoma-models-ui-js', AIUTOMA_URL . 'modules/ai/assets/js/models-ui.js', [], filemtime(AIUTOMA_PATH . 'modules/ai/assets/js/models-ui.js'), true);
        }

        if ($hook === 'aiutoma_page_aiutoma-skills') {
            wp_enqueue_script('aiutoma-skills-ui-js', AIUTOMA_URL . 'modules/ai/assets/js/skills-ui.js', [], filemtime(AIUTOMA_PATH . 'modules/ai/assets/js/skills-ui.js'), true);
            wp_localize_script('aiutoma-skills-ui-js', 'aiutomaSkillsData', [
                'nonce' => wp_create_nonce('wp_rest'),
                'apiUrl' => rest_url('aiutoma/v1/skills'),
                'loading' => __('Loading...', 'aiutoma'),
                'noSkills' => __('No skills found. Create one!', 'aiutoma'),
                'saving' => __('Saving...', 'aiutoma'),
                'saveSkill' => __('Save Skill', 'aiutoma'),
                'saveSuccess' => __('Skill saved successfully!', 'aiutoma'),
                'confirmDelete' => __('Are you sure you want to delete this skill?', 'aiutoma')
            ]);
        }

        if ($hook === 'aiutoma_page_aiutoma-abilities') {
            wp_enqueue_style('aiutoma-abilities-ui-css', AIUTOMA_URL . 'modules/ai/assets/css/abilities-ui.css', [], filemtime(AIUTOMA_PATH . 'modules/ai/assets/css/abilities-ui.css'));
            wp_enqueue_script('aiutoma-abilities-ui-js', AIUTOMA_URL . 'modules/ai/assets/js/abilities-ui.js', ['jquery'], filemtime(AIUTOMA_PATH . 'modules/ai/assets/js/abilities-ui.js'), true);
            wp_localize_script('aiutoma-abilities-ui-js', 'aiutomaAbilitiesData', [
                'nonce' => wp_create_nonce("aiutoma_ability_explorer_invoke")
            ]);
        }
    }

    public static function _description()
    {
        return __('Get real-time AI assistance directly during block editing to generate code snippets, troubleshoot logic, or brainstorm block structures instantly', 'aiutoma');
    }

    public function add_ai_settings_menu()
    {
        add_menu_page(
            __('Aiutoma', 'aiutoma'),
            __('Aiutoma', 'aiutoma'),
            'manage_options',
            'aiutoma',
            [\Aiutoma\Modules\Playground\Playground::instance(), 'aiutoma_page_html'],
            'dashicons-superhero',
            30
        );
        add_submenu_page(
            'aiutoma',
            __('Playground', 'aiutoma'),
            __('Playground', 'aiutoma'),
            'manage_options',
            'aiutoma',
            [\Aiutoma\Modules\Playground\Playground::instance(), 'aiutoma_page_html']
        );
        add_submenu_page(
            'aiutoma',
            __('Settings', 'aiutoma'),
            __('Settings', 'aiutoma'),
            'manage_options',
            'aiutoma-settings',
            [$this, 'aiutoma_settings_page_html']
        );
    }

    public function add_ai_skills_abilities_menu()
    {
        add_submenu_page(
            'aiutoma',
            __('AI Abilities', 'aiutoma'),
            __('AI Abilities', 'aiutoma'),
            'manage_options',
            'aiutoma-abilities',
            [$this, 'aiutoma_abilities_page_html']
        );
        add_submenu_page(
            'aiutoma',
            __('AI Skills', 'aiutoma'),
            __('AI Skills', 'aiutoma'),
            'manage_options',
            'aiutoma-skills',
            [$this, 'aiutoma_skills_page_html']
        );
        add_submenu_page(
            'aiutoma',
            __('AI Models', 'aiutoma'),
            __('AI Models', 'aiutoma'),
            'manage_options',
            'aiutoma-models',
            [$this, 'aiutoma_models_page_html']
        );
    }



    public function register_ai_routes()
    {

        register_rest_route('aiutoma/v1', '/ai-models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ai_models'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/ai-models/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'save_ai_models_settings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/rollback-ai-action', [
            'methods' => 'POST',
            'callback' => [$this, 'rollback_ai_action'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/delete-ai-backups', [
            'methods' => 'POST',
            'callback' => [$this, 'delete_ai_backups'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/get-rag-data', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_rag_data'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        register_rest_route('aiutoma/v1', '/download-ai-backup', [
            'methods' => 'GET',
            'callback' => [$this, 'download_ai_backup'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function chat_permission_check()
    {
        return current_user_can('manage_options');
    }

    public function api_get_rag_data(\WP_REST_Request $request)
    {
        $rag_file = self::get_storage_dir() . '/rag.json';
        if (file_exists($rag_file)) {
            $content = file_get_contents($rag_file);
            return new \WP_REST_Response(json_decode($content, true), 200);
        }
        return new \WP_REST_Response([], 200);
    }



    public function rollback_ai_action(\WP_REST_Request $request)
    {
        $backup_id = sanitize_text_field($request->get_param('backup_id'));
        if (!$backup_id) return new \WP_Error('missing_param', 'backup_id is required');

        $upload_dir = wp_upload_dir();
        $backup_file = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/backup/' . basename($backup_id);

        if (!file_exists($backup_file)) return new \WP_Error('not_found', 'Backup file not found');

        $data = json_decode(file_get_contents($backup_file), true);
        if (!$data) return new \WP_Error('invalid_backup', 'Invalid backup format');

        if ($data['action'] === 'db-query') {
            global $wpdb;
            $table = $data['table'];
            if ($data['type'] === 'UPDATE') {
                foreach ($data['rows'] as $row) {
                    $common_pks = ['ID', 'id', 'post_id', 'meta_id', 'umeta_id', 'term_id', 'option_id', 'comment_ID'];
                    $pk = array_key_first($row);
                    foreach ($common_pks as $p) {
                        if (isset($row[$p])) {
                            $pk = $p;
                            break;
                        }
                    }
                    if ($pk) {
                        $wpdb->update($table, $row, [$pk => $row[$pk]]);
                    }
                }
            } elseif ($data['type'] === 'DELETE') {
                foreach ($data['rows'] as $row) {
                    $wpdb->insert($table, $row);
                }
            }
        } elseif ($data['action'] === 'update-options') {
            if (isset($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $opt => $val) {
                    if ($val === false) {
                        delete_option($opt);
                    } else {
                        update_option($opt, $val);
                    }
                }
            }
        } elseif ($data['action'] === 'execute-php-rollback' || $data['action'] === 'global-rollback' || $data['action'] === 'cron-rollback') {
            if (!empty($data['options'])) {
                foreach ($data['options'] as $opt => $val) {
                    if ($val === false) delete_option($opt);
                    else update_option($opt, $val);
                }
            }
            if (!empty($data['posts'])) {
                foreach ($data['posts'] as $post_id => $post_data) {
                    if (is_array($post_data)) {
                        wp_update_post($post_data);
                    } elseif (is_object($post_data)) {
                        wp_update_post(get_object_vars($post_data));
                    }
                }
            }
            if (!empty($data['db_changes'])) {
                global $wpdb;
                foreach ($data['db_changes'] as $change) {
                    $table = $change['table'];
                    $type = $change['type'];
                    if ($type === 'UPDATE') {
                        foreach ($change['rows'] as $row) {
                            $common_pks = ['ID', 'id', 'post_id', 'meta_id', 'umeta_id', 'term_id', 'option_id', 'comment_ID'];
                            $pk = array_key_first($row);
                            foreach ($common_pks as $p) {
                                if (isset($row[$p])) {
                                    $pk = $p;
                                    break;
                                }
                            }
                            if ($pk) {
                                $wpdb->update($table, $row, [$pk => $row[$pk]]);
                            }
                        }
                    } elseif ($type === 'DELETE') {
                        foreach ($change['rows'] as $row) {
                            $wpdb->insert($table, $row);
                        }
                    }
                }
            }
        }

        wp_delete_file($backup_file);
        return new \WP_REST_Response(['success' => true, 'message' => 'Rollback completed successfully.'], 200);
    }

    public function delete_ai_backups(\WP_REST_Request $request)
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/backup';
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) wp_delete_file($file);
            }
        }
        return new \WP_REST_Response(['success' => true, 'message' => 'Temporary backups cleared.'], 200);
    }

    public function toggle_safe_mode(\WP_REST_Request $request)
    {
        $default_response = new \WP_REST_Response([
            'success' => false,
            'message' => __('Safe Mode is handled by the Aiutoma Developer Extension companion plugin.', 'aiutoma'),
        ], 400);

        return apply_filters('aiutoma_toggle_safe_mode_response', $default_response, $request);
    }

    public function on_option_updated($option)
    {
        if (strpos($option, 'api_key') !== false || strpos($option, 'connector') !== false || strpos($option, 'aiutoma_enabled_models') !== false) {
            delete_transient('aiutoma_client_models');
            delete_transient('aiutoma_client_models_vision');
        }
    }

    public function save_ai_models_settings(\WP_REST_Request $request)
    {
        $enabled_models = $request->get_param('enabled_models');
        if (is_array($enabled_models)) {
            update_option('aiutoma_enabled_models', $enabled_models);
            delete_transient('aiutoma_client_models');
            delete_transient('aiutoma_client_models_vision');
        }

        $budget_cap = $request->get_param('budget_cap');
        if (isset($budget_cap)) {
            update_option('aiutoma_token_budget_cap', intval($budget_cap));
        }

        $ollama_url = $request->get_param('ollama_base_url');
        if (isset($ollama_url)) {
            update_option('aiutoma_ollama_base_url', sanitize_text_field($ollama_url));
        }

        $cron_enabled = $request->get_param('cron_enabled');
        if ($cron_enabled) {
            if (!wp_next_scheduled('aiutoma_update_models_cron')) {
                wp_schedule_event(time(), 'daily', 'aiutoma_update_models_cron');
            }
        } else {
            $timestamp = wp_next_scheduled('aiutoma_update_models_cron');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'aiutoma_update_models_cron');
            }
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function check_budget_cap()
    {
        $budget_cap = (int) get_option('aiutoma_token_budget_cap', 0);
        if ($budget_cap <= 0) {
            return true;
        }

        if (class_exists('\Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager')) {
            $manager = new \Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager();
            $manager->init();
            $summary = $manager->get_summary('month');
            $total_tokens = isset($summary['total_tokens']) ? (int) $summary['total_tokens'] : 0;

            if ($total_tokens >= $budget_cap) {
                return false;
            }
        }

        return true;
    }

    public function display_low_token_alert()
    {
        $budget_cap = (int) get_option('aiutoma_token_budget_cap', 0);
        if ($budget_cap <= 0) return;

        $user_id = get_current_user_id();
        if (get_user_meta($user_id, 'aiutoma_dismissed_low_token_alert_month_' . gmdate('Y_m'), true)) {
            return;
        }

        if (class_exists('\Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager')) {
            $manager = new \Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager();
            $manager->init();
            $summary = $manager->get_summary('month');
            $total_tokens = isset($summary['total_tokens']) ? (int) $summary['total_tokens'] : 0;

            $percentage = ($budget_cap > 0) ? ($total_tokens / $budget_cap) * 100 : 0;

            if ($percentage >= 80) {
                $class = $percentage >= 100 ? 'notice-error' : 'notice-warning';
                /* translators: 1: percentage, 2: used tokens, 3: total budget tokens */
                $message = sprintf(
                    __('<strong>Aiutoma Alert:</strong> You have reached %1$.1f%% of your monthly AI token budget (%2$s / %3$s tokens).', 'aiutoma'),
                    $percentage,
                    number_format_i18n($total_tokens),
                    number_format_i18n($budget_cap)
                );

                $nonce = wp_create_nonce('aiutoma_dismiss_token_alert');

                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible" id="aiutoma-low-token-alert" data-nonce="' . esc_attr($nonce) . '">';
                echo '<p>' . wp_kses_post($message) . ' <a href="' . esc_url(admin_url('admin.php?page=ai-tokens-log')) . '">' . esc_html__('View Logs & Adjust Cap', 'aiutoma') . '</a></p>';
                echo '</div>';

                wp_register_script('aiutoma-token-alert', '', ['jquery'], AIUTOMA_VERSION, true);
                wp_enqueue_script('aiutoma-token-alert');
                wp_add_inline_script(
                    'aiutoma-token-alert',
                    'jQuery(document).ready(function($) {' .
                    '  $("#aiutoma-low-token-alert").on("click", ".notice-dismiss", function() {' .
                    '    $.post(ajaxurl, {' .
                    '      action: "aiutoma_dismiss_low_token_alert",' .
                    '      nonce: $("#aiutoma-low-token-alert").data("nonce")' .
                    '    });' .
                    '  });' .
                    '});'
                );
            }
        }
    }

    public function ajax_dismiss_low_token_alert()
    {
        check_ajax_referer('aiutoma_dismiss_token_alert', 'nonce');
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'aiutoma_dismissed_low_token_alert_month_' . gmdate('Y_m'), 1);
        wp_send_json_success();
    }

    public function run_update_models_cron()
    {
        if (!class_exists('\WordPress\AiClient\AiClient')) return;
        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        foreach ($registry->getRegisteredProviderIds() as $providerId) {
            try {
                $className = $registry->getProviderClassName($providerId);
                $directory = $className::modelMetadataDirectory();
                if ($directory instanceof \WordPress\AiClient\Common\Contracts\CachesDataInterface) {
                    $directory->invalidateCaches();
                }
            } catch (\Exception $e) {
            }
        }

        $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements([], []);
        $registry->findModelsMetadataForSupport($requirements);

        delete_transient('aiutoma_client_models');
        delete_transient('aiutoma_client_models_vision');
    }

    public function has_ai_models()
    {
        $cached_models = get_transient('aiutoma_client_models');
        if ($cached_models !== false && is_array($cached_models) && !empty($cached_models)) {
            return true;
        }

        $response = $this->get_ai_models();
        if ($response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            return !empty($data['models']);
        }
        return false;
    }

    public function get_ai_models(?\WP_REST_Request $request = null)
    {
        if (class_exists('\WordPress\AiClient\AiClient')) {
            // Providers are automatically loaded via register_subplugins_providers on init

            $registry = \WordPress\AiClient\AiClient::defaultRegistry();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $forceRefresh = isset($_GET['refresh']) && sanitize_text_field(wp_unslash($_GET['refresh'])) === '1';
            $is_vision = $request ? ($request->get_param('vision') === '1') : false;
            $transient_key = $is_vision ? 'aiutoma_client_models_vision' : 'aiutoma_client_models';

            if (!$forceRefresh) {
                $cached_models = get_transient($transient_key);
                if ($cached_models !== false) {
                    return new \WP_REST_Response(['success' => true, 'models' => $cached_models], 200);
                }
            }

            foreach ($registry->getRegisteredProviderIds() as $providerId) {
                if ($forceRefresh || in_array($providerId, ['local', 'ollama'])) {
                    try {
                        $className = $registry->getProviderClassName($providerId);
                        $directory = $className::modelMetadataDirectory();
                        if ($directory instanceof \WordPress\AiClient\Common\Contracts\CachesDataInterface) {
                            $directory->invalidateCaches();
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }

            if ($is_vision) {
                $capabilities = [
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()
                ];
                $options = [
                    new \WordPress\AiClient\Providers\Models\DTO\RequiredOption(
                        \WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
                        [
                            \WordPress\AiClient\Messages\Enums\ModalityEnum::text(),
                            \WordPress\AiClient\Messages\Enums\ModalityEnum::image()
                        ]
                    )
                ];
            } else {
                $capabilities = [
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
                    \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory()
                ];
                $options = [];
            }

            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                $capabilities,
                $options
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);

            $enabled_models = get_option('aiutoma_enabled_models', []);
            $models = [];
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $name = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();
                    $uid = $providerId . '|' . $id;

                    if (!empty($enabled_models) && !in_array($uid, $enabled_models)) {
                        continue;
                    }

                    if (!isset($models[$providerName])) {
                        $models[$providerName] = [];
                    }
                    $models[$providerName][$uid] = '[' . $providerName . '] ' . $name;
                }
            }

            if (!empty($models)) {
                $models = apply_filters('aiutoma/models', $models);
                set_transient($transient_key, $models, 0);
            }
            return new \WP_REST_Response(['success' => true, 'models' => $models], 200);
        }

        return new \WP_REST_Response(['success' => true, 'models' => []], 200);
    }

    public function download_ai_backup($request)
    {
        $backup_id = $request->get_param('id');
        $type = $request->get_param('type'); // 'file' or 'sql'
        $index = (int)$request->get_param('index');

        if (!$backup_id || !$type) {
            return new \WP_Error('invalid_params', 'Missing required parameters.', ['status' => 400]);
        }

        $upload_dir = wp_upload_dir();
        $backup_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/backup';
        $json_file = $backup_dir . '/' . basename($backup_id);

        if (!file_exists($json_file)) {
            return new \WP_Error('not_found', 'Backup not found.', ['status' => 404]);
        }

        $data = json_decode(file_get_contents($json_file), true);
        if (!$data) {
            return new \WP_Error('invalid_backup', 'Invalid backup file.', ['status' => 500]);
        }

        if ($type === 'file') {
            if (!isset($data['files'][$index])) {
                return new \WP_Error('not_found', 'File backup not found.', ['status' => 404]);
            }
            $file_info = $data['files'][$index];
            if ($file_info['is_new']) {
                return new \WP_Error('not_found', 'This file was created by AI, no previous version exists.', ['status' => 404]);
            }
            $physical = $backup_dir . '/' . basename($file_info['physical_backup']);
            if (!file_exists($physical)) {
                return new \WP_Error('not_found', 'Physical backup file not found.', ['status' => 404]);
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_info['path']) . '"');
            header('Content-Length: ' . filesize($physical));
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            readfile($physical);
            exit;
        } elseif ($type === 'sql') {
            if (!isset($data['db_changes'])) {
                return new \WP_Error('not_found', 'No DB changes in this backup.', ['status' => 404]);
            }

            $sql_dump = "-- AI Action Rollback SQL Dump\n";
            $sql_dump .= "-- Original Backup ID: " . $backup_id . "\n\n";

            foreach ($data['db_changes'] as $change) {
                if ($change['type'] === 'UPDATE' || $change['type'] === 'DELETE' || $change['type'] === 'INSERT') {
                    $table = $change['table'];
                    $sql_dump .= "-- Restore original rows for table: {$table}\n";
                    if (!empty($change['rows'])) {
                        foreach ($change['rows'] as $row) {
                            $cols = array_keys($row);
                            $vals = array_map(function ($v) {
                                if ($v === null) return 'NULL';
                                return "'" . esc_sql($v) . "'";
                            }, array_values($row));
                            $sql_dump .= "REPLACE INTO `{$table}` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                        }
                    }
                    $sql_dump .= "\n";
                }
            }

            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="rollback_' . $backup_id . '.sql"');
            echo $sql_dump;
            exit;
        }

        return new \WP_Error('invalid_type', 'Invalid download type.', ['status' => 400]);
    }
}
