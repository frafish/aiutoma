<?php

namespace Aiutoma\Modules\Ai\Traits;

if (! defined('ABSPATH')) exit;
trait Rag
{

    public function register_rag_hooks()
    {
        // Include RAG embeddings cron class
        require_once AIUTOMA_PATH . 'modules/ai/rag.php';

        add_action('aiutoma_rag_sync_event', [$this, 'run_rag_sync']);
        add_action('admin_init', [$this, 'rag_settings_init']);

        add_action('update_option_aiutoma_rag_cron_enabled', [$this, 'rag_reschedule_cron'], 10, 0);
        add_action('update_option_aiutoma_rag_cron_frequency', [$this, 'rag_reschedule_cron'], 10, 0);
        add_action('update_option_aiutoma_rag_embedding_provider', [$this, 'rag_provider_changed'], 10, 2);

        add_action('init', [$this, 'schedule_rag_sync']);
        register_deactivation_hook(AIUTOMA_FILE, [$this, 'deactivate_rag_sync']);

        add_action('admin_post_aiutoma_manual_rag_sync', [$this, 'handle_manual_rag_sync']);
        add_action('admin_notices', [$this, 'rag_sync_notice']);
        add_action('admin_menu', [$this, 'rag_settings_menu'], 11);
    }

    public function run_rag_sync()
    {
        if (class_exists('Aiutoma_RAG_Embeddings_Cron')) {
            $cron = new \Aiutoma_RAG_Embeddings_Cron();
            $cron->run();
        }
    }

    public function rag_settings_init()
    {
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_cron_enabled', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_cron_frequency', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_embedding_provider', ['sanitize_callback' => 'sanitize_text_field']);

        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_contents', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_products', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_terms', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_plugins', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_settings', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_comments', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('aiutoma_rag_settings_group', 'aiutoma_rag_sync_media', ['sanitize_callback' => 'sanitize_text_field']);
    }

    public function rag_provider_changed($old_value, $new_value)
    {
        if ($old_value !== $new_value) {
            $upload_dir = wp_upload_dir();
            $db_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/rag.sqlite';
            if (file_exists($db_path)) {
                try {
                    // phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
                    $db = new \PDO('sqlite:' . $db_path);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                    $db->exec("DELETE FROM document_embeddings");
                    // phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }
    }

    public function rag_reschedule_cron()
    {
        wp_clear_scheduled_hook('aiutoma_rag_sync_event');
        $this->schedule_rag_sync();
    }

    public function schedule_rag_sync()
    {
        $enabled = get_option('aiutoma_rag_cron_enabled', 'no');
        if ($enabled === 'no') {
            wp_clear_scheduled_hook('aiutoma_rag_sync_event');
            return;
        }

        $frequency = get_option('aiutoma_rag_cron_frequency', 'hourly');

        $timestamp = wp_next_scheduled('aiutoma_rag_sync_event');
        if ($timestamp) {
            $current_schedule = wp_get_schedule('aiutoma_rag_sync_event');
            if ($current_schedule !== $frequency) {
                wp_clear_scheduled_hook('aiutoma_rag_sync_event');
                wp_schedule_event(time(), $frequency, 'aiutoma_rag_sync_event');
            }
        } else {
            wp_schedule_event(time(), $frequency, 'aiutoma_rag_sync_event');
        }
    }

    public function deactivate_rag_sync()
    {
        wp_clear_scheduled_hook('aiutoma_rag_sync_event');
    }

    public function handle_manual_rag_sync()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'aiutoma'));
        }
        check_admin_referer('aiutoma_rag_sync_nonce');

        // Run the sync
        $this->run_rag_sync();

        // Redirect back
        wp_safe_redirect(add_query_arg('rag_sync_run', '1', wp_get_referer()));
        exit;
    }

    public function rag_sync_notice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['rag_sync_run']) && $_GET['rag_sync_run'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Aiutoma RAG Embeddings sync completed.', 'aiutoma') . '</p></div>';
        }
    }

    public function rag_settings_menu()
    {
        $parent_slug = 'aiutoma';
        add_submenu_page(
            $parent_slug,
            __('RAG Embeddings', 'aiutoma'),
            __('AI RAG', 'aiutoma'),
            'manage_options',
            'aiutoma-rag',
            [$this, 'rag_settings_page']
        );
    }

    public function rag_settings_page()
    {
        $sync_url = wp_nonce_url(admin_url('admin-post.php?action=aiutoma_manual_rag_sync'), 'aiutoma_rag_sync_nonce');
        $enabled = get_option('aiutoma_rag_cron_enabled', 'no');
        $frequency = get_option('aiutoma_rag_cron_frequency', 'hourly');
?>
        <div class="wrap">
            <h1><?php esc_html_e('Aiutoma - RAG Vector Embeddings', 'aiutoma'); ?></h1>
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php esc_html_e('Synchronize Knowledge Base', 'aiutoma'); ?></h2>
                <p><?php esc_html_e('Synchronize your site\'s content into the local vector database. This allows the AI to reference your published pages and posts as context.', 'aiutoma'); ?></p>

                <form method="post" action="options.php" style="margin-top: 20px; background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
                    <?php settings_fields('aiutoma_rag_settings_group'); ?>
                    <h3><?php esc_html_e('Automated Sync Settings', 'aiutoma'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Background Sync', 'aiutoma'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="aiutoma_rag_cron_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                                    <?php esc_html_e('Yes, run automatically', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="aiutoma_rag_cron_enabled" value="no" <?php checked($enabled, 'no'); ?>>
                                    <?php esc_html_e('No, manual sync only', 'aiutoma'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Sync Frequency', 'aiutoma'); ?></th>
                            <td>
                                <select name="aiutoma_rag_cron_frequency">
                                    <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php esc_html_e('Once Hourly (0 * * * *)', 'aiutoma'); ?></option>
                                    <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php esc_html_e('Twice Daily (0 0,12 * * *)', 'aiutoma'); ?></option>
                                    <option value="daily" <?php selected($frequency, 'daily'); ?>><?php esc_html_e('Once Daily (0 0 * * *)', 'aiutoma'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('How often should the site sync content to the AI database?', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('What to Sync', 'aiutoma'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_contents" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_contents" value="1" <?php checked(get_option('aiutoma_rag_sync_contents', 1), 1); ?>>
                                    <?php esc_html_e('WordPress Contents (Posts/Pages/Blocks)', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_products" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_products" value="1" <?php checked(get_option('aiutoma_rag_sync_products', 1), 1); ?>>
                                    <?php esc_html_e('WooCommerce Products', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_terms" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_terms" value="1" <?php checked(get_option('aiutoma_rag_sync_terms', 1), 1); ?>>
                                    <?php esc_html_e('Taxonomies & Categories', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_plugins" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_plugins" value="1" <?php checked(get_option('aiutoma_rag_sync_plugins', 0), 1); ?>>
                                    <?php esc_html_e('Active Plugins API (Hooks, Classes, Functions)', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_settings" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_settings" value="1" <?php checked(get_option('aiutoma_rag_sync_settings', 1), 1); ?>>
                                    <?php esc_html_e('Global Settings (Site Name, Description, List of active plugins)', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_comments" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_comments" value="1" <?php checked(get_option('aiutoma_rag_sync_comments', 0), 1); ?>>
                                    <?php esc_html_e('Comments', 'aiutoma'); ?>
                                </label><br>
                                <label>
                                    <input type="hidden" name="aiutoma_rag_sync_media" value="0">
                                    <input type="checkbox" name="aiutoma_rag_sync_media" value="1" <?php checked(get_option('aiutoma_rag_sync_media', 0), 1); ?>>
                                    <abbr title="Requires python 'markitdown' installed on the server."><?php esc_html_e('Media Documents (PDF, Office, etc.)', 'aiutoma'); ?></abbr>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Embedding Provider', 'aiutoma'); ?></th>
                            <td>
                                <?php
                                $provider = get_option('aiutoma_rag_embedding_provider', '');
                                $has_gemini = (class_exists('\WordPress\AiClient\AiClient') && \WordPress\AiClient\AiClient::defaultRegistry()->isProviderConfigured('google')) || defined('GEMINI_API_KEY');
                                $has_openai = (class_exists('\WordPress\AiClient\AiClient') && \WordPress\AiClient\AiClient::defaultRegistry()->isProviderConfigured('openai')) || defined('OPENAI_API_KEY');
                                $has_hf     = (class_exists('\WordPress\AiClient\AiClient') && \WordPress\AiClient\AiClient::defaultRegistry()->isProviderConfigured('huggingface')) || defined('HUGGINGFACE_API_KEY');
                                ?>
                                <select name="aiutoma_rag_embedding_provider" id="aiutoma_rag_embedding_provider" required>
                                    <option value="" disabled <?php selected($provider, ''); ?>><?php esc_html_e('-- Select a Provider --', 'aiutoma'); ?></option>
                                    <option value="gemini" <?php selected($provider, 'gemini'); ?> <?php disabled(!$has_gemini); ?>>Google Gemini <?php if (!$has_gemini) : ?>(<?php esc_html_e('Not Configured', 'aiutoma'); ?>)<?php endif; ?></option>
                                    <option value="openai" <?php selected($provider, 'openai'); ?> <?php disabled(!$has_openai); ?>>OpenAI <?php if (!$has_openai) : ?>(<?php esc_html_e('Not Configured', 'aiutoma'); ?>)<?php endif; ?></option>
                                    <option value="huggingface" <?php selected($provider, 'huggingface'); ?> <?php disabled(!$has_hf); ?>>HuggingFace <?php if (!$has_hf) : ?>(<?php esc_html_e('Not Configured', 'aiutoma'); ?>)<?php endif; ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Select the AI provider to use for vectorization. You must first configure its API key in the WordPress Connectors page.', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Sync Settings', 'aiutoma'), 'primary', 'submit', false); ?>
                </form>

                <hr style="margin: 30px 0;">

                <h3><?php esc_html_e('Manual Trigger', 'aiutoma'); ?></h3>
                <p><?php esc_html_e('You can trigger the synchronization process manually at any time.', 'aiutoma'); ?></p>
                <p><a href="<?php echo esc_url($sync_url); ?>" class="button button-secondary"><?php esc_html_e('Sync RAG DB Now', 'aiutoma'); ?></a></p>
            </div>

            <?php
            // Generate Database Status Report
            $upload_dir = wp_upload_dir();
            $db_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/rag.sqlite';
            $total_chunks = 0;
            $total_posts = 0;
            $last_update = __('Never', 'aiutoma');
            $db_size = '0 KB';

            if (file_exists($db_path)) {
                $db_size = size_format(filesize($db_path));
                try {
                    // phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
                    $db = new \PDO('sqlite:' . $db_path);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

                    $result = $db->query("SELECT COUNT(*) as c FROM document_embeddings");
                    if ($result) {
                        $row = $result->fetch(\PDO::FETCH_ASSOC);
                        $total_chunks = $row['c'] ?? 0;
                    }

                    $result = $db->query("SELECT COUNT(DISTINCT post_id) as c FROM document_embeddings");
                    if ($result) {
                        $row = $result->fetch(\PDO::FETCH_ASSOC);
                        $total_posts = $row['c'] ?? 0;
                    }

                    $result = $db->query("SELECT MAX(updated_at) as m FROM document_embeddings");
                    if ($result) {
                        $row = $result->fetch(\PDO::FETCH_ASSOC);
                        if (!empty($row['m'])) {
                            $last_update = get_date_from_gmt($row['m'], get_option('date_format') . ' ' . get_option('time_format'));
                        }
                    }
                    // phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
                } catch (\Exception $e) {
                    $last_update = __('Error reading database', 'aiutoma');
                }
            }
            ?>
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php esc_html_e('Database Status Report', 'aiutoma'); ?></h2>
                <table class="widefat striped" style="margin-top: 15px;">
                    <tbody>
                        <tr>
                            <th style="width: 30%;"><?php esc_html_e('Status', 'aiutoma'); ?></th>
                            <td>
                                <?php if ($total_chunks > 0): ?>
                                    <span style="color: #46b450; font-weight: bold;">&#10003; <?php esc_html_e('Active & Populated', 'aiutoma'); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">&#10007; <?php esc_html_e('Empty', 'aiutoma'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Indexed Posts', 'aiutoma'); ?></th>
                            <td><strong><?php echo esc_html(number_format_i18n($total_posts)); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Total Vectors (Chunks)', 'aiutoma'); ?></th>
                            <td><strong><?php echo esc_html(number_format_i18n($total_chunks)); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Last Sync Time', 'aiutoma'); ?></th>
                            <td><?php echo esc_html($last_update); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Database Size', 'aiutoma'); ?></th>
                            <td><?php echo esc_html($db_size); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
<?php
    }
}
