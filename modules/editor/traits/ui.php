<?php
namespace Aiutoma\Modules\Editor\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.ValidatedSanitizedInput



trait Ui {
    public function enqueue_agent_scripts_elementor() {
        $this->enqueue_agent_scripts('', false, true);
    }

    public function render_agent_chatbot_elementor() {
        $this->render_agent_chatbot(false, true);
    }

    public function add_agent_menu() {
        add_submenu_page(
            'aiutoma',
            __('Editor Agent Assistant Settings', 'aiutoma'),
            __('Editor Assistant', 'aiutoma'),
            'manage_options',
            'aiutoma-agent',
            [$this, 'aiutoma_agent_page_html']
        );
    }

    public function enqueue_media_integration_assets() {
        wp_enqueue_script(
            'aiutoma-media-integration',
            AIUTOMA_URL . 'modules/editor/assets/js/media-integration.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-hooks', 'wp-compose'],
            file_exists(AIUTOMA_PATH . 'modules/editor/assets/js/media-integration.js') ? filemtime(AIUTOMA_PATH . 'modules/editor/assets/js/media-integration.js') : '1.0',
            true
        );
    }


    private function should_render_agent($is_elementor = false) {


        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen && !$is_elementor) return false;

        $user = wp_get_current_user();
        if (empty($user)) return false;

        $selected_roles = get_option('aiutoma_agent_roles', ['administrator']);
        $has_role = false;
        if (!empty($user->roles)) {
            foreach ($user->roles as $role) {
                if (in_array($role, $selected_roles)) {
                    $has_role = true;
                    break;
                }
            }
        }
        if (!$has_role) return false;

        $selected_post_types = get_option('aiutoma_agent_post_types', ['post', 'page']);
        $selected_taxonomies = get_option('aiutoma_agent_taxonomies', ['category', 'post_tag']);
        $enable_users = get_option('aiutoma_agent_enable_users', 1);
        $enable_theme_editor = get_option('aiutoma_agent_enable_theme_editor', 0);
        $enable_plugin_editor = get_option('aiutoma_agent_enable_plugin_editor', 0);

        if ($is_elementor) {
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            if ($post_id) {
                $post_type = get_post_type($post_id);
                return in_array($post_type, $selected_post_types);
            }
            return false;
        }

        if ($screen->base === 'post') {
            return in_array($screen->post_type, $selected_post_types);
        } elseif ($screen->base === 'term' || $screen->base === 'edit-tags') {
            return in_array($screen->taxonomy, $selected_taxonomies);
        } elseif (in_array($screen->base, ['user', 'profile', 'user-edit'])) {
            return $enable_users ? true : false;
        } elseif ($screen->base === 'theme-editor') {
            return $enable_theme_editor ? true : false;
        } elseif ($screen->base === 'plugin-editor') {
            return $enable_plugin_editor ? true : false;
        }

        return false;
    }

    public function enqueue_agent_scripts($hook = '', $force = false, $is_elementor = false) {
        if (!$force && !$this->should_render_agent($is_elementor)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_base = $screen ? $screen->base : 'elementor';

        wp_enqueue_style('aiutoma-agent-style', AIUTOMA_URL . 'modules/editor/assets/css/agent.css', [], filemtime(AIUTOMA_PATH . 'modules/editor/assets/css/agent.css'));
        wp_enqueue_style('aiutoma-select2');
        wp_enqueue_script('aiutoma-select2');
        wp_enqueue_script('aiutoma-agent-script', AIUTOMA_URL . 'modules/editor/assets/js/agent.js', ['jquery', 'aiutoma-select2'], filemtime(AIUTOMA_PATH . 'modules/editor/assets/js/agent.js'), true);
        
        $ui_mode = get_option('aiutoma_agent_ui_mode', 'floating');

        wp_localize_script('aiutoma-agent-script', 'aiutomaAgentData', [
            'rest_url' => esc_url_raw(rest_url('aiutoma/v1/ai-chat')),
            'nonce' => wp_create_nonce('wp_rest'),
            'screen' => $screen_base,
            'preferredModel' => get_user_meta(get_current_user_id(), '_aiutoma_preferred_model', true),
            'debugMode' => (defined('WP_DEBUG') && WP_DEBUG),
            'uiMode' => $ui_mode
        ]);
    }

    public function render_agent_chatbot($force = false, $is_elementor = false) {
        if (!$force && !$this->should_render_agent($is_elementor)) {
            return;
        }
        $ui_mode = get_option('aiutoma_agent_ui_mode', 'floating');
        ?>
        <div id="aiutoma-agent-chatbot" class="aiutoma-agent-closed" data-ui-mode="<?php echo esc_attr($ui_mode); ?>">
            <div id="aiutoma-agent-header">
                <span class="dashicons dashicons-superhero"></span> <span class="aiutoma-agent-title-text">Aiutoma Agent</span>
                <button id="aiutoma-agent-settings-toggle" title="Settings" style="margin-left: auto; margin-right: 5px; background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-admin-generic"></span></button>
                <button id="aiutoma-agent-toggle" style="background: none; border: none; color: #fff; cursor: pointer;"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            </div>
            <div id="aiutoma-agent-model-area" style="display: none; padding: 10px; background: #fff; border-bottom: 1px solid #ccd0d4;">
                <select id="aiutoma-agent-model-select" style="width: 100%;">
                    <option value="">Loading models...</option>
                </select>
                <label style="display: block; margin-top: 10px; font-size: 12px; color: #555;">
                    <input type="checkbox" id="aiutoma-agent-pass-theme" value="1" checked> Pass Theme Styles (Colors, Fonts)
                </label>
            </div>
            <div id="aiutoma-agent-body" style="display: none;">
                <div id="aiutoma-agent-messages">
                    <div class="aiutoma-agent-msg aiutoma-agent-sys">Hello! I can help you manage this content. Just tell me what you need.</div>
                </div>
                <div id="aiutoma-agent-input-area">
                    <textarea id="aiutoma-agent-prompt" placeholder="<?php esc_attr_e('Ask me to manage this content (title, content, meta)...', 'aiutoma'); ?>"></textarea>
                    <button id="aiutoma-agent-send" class="button button-primary"><span class="dashicons dashicons-controls-play"></span></button>
                </div>
            </div>
        </div>
        <?php
    }

}
