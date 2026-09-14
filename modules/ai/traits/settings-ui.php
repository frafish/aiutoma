<?php
namespace Aiutoma\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait SettingsUi {
    public function aiutoma_settings_page_html() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiutoma'));
        }

        if (isset($_POST['aiutoma_save_settings']) && check_admin_referer('aiutoma_settings_action', 'aiutoma_settings_nonce')) {
            $active_modules = [
                'providers' => isset($_POST['module_providers']) ? 1 : 0,
                'editor'  => isset($_POST['module_editor']) ? 1 : 0,
                'chatbot' => isset($_POST['module_chatbot']) ? 1 : 0,
                'seo'     => isset($_POST['module_seo']) ? 1 : 0,
                'mcp'     => isset($_POST['module_mcp']) ? 1 : 0,
                'wpml'    => isset($_POST['module_wpml']) ? 1 : 0,
            ];
            update_option('aiutoma_active_modules', $active_modules);
            
            if (isset($_POST['aiutoma_mcp_token'])) {
                update_option('aiutoma_mcp_token', sanitize_text_field(wp_unslash($_POST['aiutoma_mcp_token'])));
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully. Please refresh the page to see changes in the menu.', 'aiutoma') . '</p></div>';
        }

        $active_modules = get_option('aiutoma_active_modules', [
            'providers' => 1,
            'editor'  => 1,
            'chatbot' => 0,
            'seo'     => 0,
            'mcp'     => 0,
            'wpml'    => 0,
        ]);
        $token = get_option('aiutoma_mcp_token', wp_generate_password(24, false));
        if (empty(get_option('aiutoma_mcp_token'))) {
            update_option('aiutoma_mcp_token', $token);
        }

        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Aiutoma Settings', 'aiutoma'); ?></h1>
            <p><?php esc_html_e('Manage core plugin features and modules. Disable features you don\'t use to keep the admin area clean and improve performance.', 'aiutoma'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('aiutoma_settings_action', 'aiutoma_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Active Modules (Experimental & Core)', 'aiutoma'); ?></th>
                        <td>
                            <?php if (!\Aiutoma\Modules\Ai\Ai::instance()->has_ai_models()): ?>
                                <div class="notice notice-warning inline" style="margin: 0 0 15px 0; padding: 10px 15px; border-left: 4px solid #dba617; background: #fff;">
                                    <p style="margin: 0; font-size: 13px;">
                                        <strong><?php esc_html_e('Nota sui Moduli AI:', 'aiutoma'); ?></strong> <?php esc_html_e('Nessun modello AI risulta configurato o abilitato. I moduli Editor Agent, Frontend Chatbot, SEO e WPML rimangono disattivati finché non viene configurato almeno un modello in Connettori o Modelli AI.', 'aiutoma'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <fieldset>
                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_providers" value="1" <?php checked(!empty($active_modules['providers'])); ?>>
                                    <strong><?php esc_html_e('Custom Providers', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables custom AI Providers beyond the default ones.', 'aiutoma'); ?></span>
                                </label>

                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_editor" value="1" <?php checked(!empty($active_modules['editor'])); ?>>
                                    <strong><?php esc_html_e('Editor Agent', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables the AI Agent directly inside the Gutenberg Block Editor.', 'aiutoma'); ?></span>
                                </label>
                                
                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_chatbot" value="1" <?php checked(!empty($active_modules['chatbot'])); ?>>
                                    <strong><?php esc_html_e('Frontend Chatbot', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables the customizable AI Chatbot for your website visitors.', 'aiutoma'); ?></span>
                                </label>

                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_seo" value="1" <?php checked(!empty($active_modules['seo'])); ?>>
                                    <strong><?php esc_html_e('SEO (Beta)', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables AI-powered SEO tools, such as automatic slug generation and metadata suggestions.', 'aiutoma'); ?></span>
                                </label>

                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_mcp" value="1" <?php checked(!empty($active_modules['mcp'])); ?>>
                                    <strong><?php esc_html_e('MCP & External Integrations', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables the Model Context Protocol (MCP) integrations for connecting Aiutoma with external GPT apps.', 'aiutoma'); ?></span>
                                </label>

                                <label style="display:block; margin-bottom: 10px;">
                                    <input type="checkbox" name="module_wpml" value="1" <?php checked(!empty($active_modules['wpml'])); ?>>
                                    <strong><?php esc_html_e('WPML Content Translation (Beta)', 'aiutoma'); ?></strong><br>
                                    <span class="description"><?php esc_html_e('Enables AI-driven bulk translation tools for WPML via XLIFF.', 'aiutoma'); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Webhook & Custom Actions Token', 'aiutoma'); ?></th>
                        <td>
                            <div style="display: flex; gap: 10px; max-width: 400px;">
                                <input type="text" id="aiutoma_mcp_main_token" name="aiutoma_mcp_token" value="<?php echo esc_attr($token); ?>" class="regular-text" style="width: 100%;">
                                <button type="button" class="button aiutoma-copy-btn" onclick="let copyText = document.getElementById('aiutoma_mcp_main_token'); copyText.select(); copyText.setSelectionRange(0, 99999); navigator.clipboard.writeText(copyText.value);"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Legacy API token. Use this as X-MCP-API-Key header for simple GPT Custom Actions and Webhooks.', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Developer Extension', 'aiutoma'); ?></th>
                        <td>
                            <?php if (defined('AIUTOMA_DEV_VERSION')): ?>
                                <p style="color: #00a32a; font-weight: 600;">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php
                                    /* translators: %s: version number */
                                    printf(esc_html__('Developer Extension is ACTIVE (v%s).', 'aiutoma'), esc_html(AIUTOMA_DEV_VERSION));
                                    ?>
                                </p>
                                <p class="description"><?php esc_html_e('Developer abilities (Execute PHP Code, Modify Files, Run WP-CLI) are unlocked with interactive review and rollbacks.', 'aiutoma'); ?></p>
                            <?php else: ?>
                                <p style="color: #646970; font-weight: 600;">
                                    <span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e('Developer Extension is NOT ACTIVE.', 'aiutoma'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('In compliance with WordPress.org repository guidelines, arbitrary PHP code execution, file modifications, and WP-CLI commands are managed via the companion "Aiutoma Developer Extension". Activate the extension to unlock these abilities.', 'aiutoma'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'aiutoma'), 'primary', 'aiutoma_save_settings'); ?>
            </form>
        </div>
        <?php
    }
}
