<?php

namespace Aiutoma\Modules\Wpml\traits;

if (! defined('ABSPATH')) exit;
trait Settings
{
    public function handle_settings_post()
    {
        if (isset($_POST['aiutoma_wpml_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_wpml_settings_nonce'])), 'aiutoma_wpml_settings')) {
            if (isset($_POST['aiutoma_clear_logs'])) {
                $upload_dir = wp_upload_dir();
                $log_file = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/wpml.log';
                if (file_exists($log_file)) {
                    wp_delete_file($log_file);
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs cleared.', 'aiutoma') . '</p></div>';
            } elseif (isset($_POST['aiutoma_wpml_context_submit'])) {
                $context = isset($_POST['aiutoma_wpml_context']) ? sanitize_textarea_field(wp_unslash($_POST['aiutoma_wpml_context'])) : '';
                update_option('aiutoma_wpml_context', $context);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Context & Glossary saved.', 'aiutoma') . '</p></div>';
            } else {
                $model = isset($_POST['aiutoma_wpml_model']) ? sanitize_text_field(wp_unslash($_POST['aiutoma_wpml_model'])) : '';
                update_option('aiutoma_wpml_model', $model);
                update_option('aiutoma_wpml_auto_fallback', isset($_POST['aiutoma_wpml_auto_fallback']) ? 1 : 0);

                $fallback_models = isset($_POST['aiutoma_wpml_fallback_models']) && is_array($_POST['aiutoma_wpml_fallback_models'])
                    ? array_map('sanitize_text_field', wp_unslash($_POST['aiutoma_wpml_fallback_models'])) : [];
                update_option('aiutoma_wpml_fallback_models', $fallback_models);

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'aiutoma') . '</p></div>';
            }
        }
    }

    public function render_context_tab()
    {
        $saved_context = get_option('aiutoma_wpml_context', '');
?>
        <div class="icl_tm_wrap">
            <form method="post" action="">
                <?php wp_nonce_field('aiutoma_wpml_settings', 'aiutoma_wpml_settings_nonce'); ?>
                <input type="hidden" name="aiutoma_wpml_context_submit" value="1">

                <h3 style="margin-top: 20px;"><?php esc_html_e('Translation Context & Glossary', 'aiutoma'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Enter global context and glossary rules for AI translations. This text is injected directly into the AI prompt.', 'aiutoma'); ?><br>
                </p>

                <table class="form-table">
                    <tr>
                        <td style="padding-left: 0;">
                            <textarea name="aiutoma_wpml_context" rows="10" style="width: 100%; max-width: 800px;"><?php echo esc_textarea($saved_context); ?></textarea>
                            <br><small><strong><?php esc_html_e('Example:', 'aiutoma'); ?></strong> <em>"We are a nautical safety company. Never translate the company name as it is a trademark. Keep the tone professional but welcoming."</em></small>

                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Context', 'aiutoma'); ?></button>
                </p>
            </form>
        </div>
    <?php
    }

    public function render_settings_tab()
    {
        $models = [];
        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()],
                []
            );
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $modelName = $modelMeta->getName() ?: $id;
                    $providerId = $providerMetadata->getProvider()->getId();

                    if (stripos($id, 'dall-e') !== false || stripos($id, 'midjourney') !== false) continue;

                    $models[$providerId . '|' . $id] = '[' . $providerName . '] ' . $modelName;
                }
            }
        }
        $selected_model = get_option('aiutoma_wpml_model', '');
        $auto_fallback = get_option('aiutoma_wpml_auto_fallback', 0);
        $saved_fallback_models = get_option('aiutoma_wpml_fallback_models', []);
    ?>
        <div class="icl_tm_wrap">
            <form method="post" action="">
                <?php wp_nonce_field('aiutoma_wpml_settings', 'aiutoma_wpml_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aiutoma_wpml_model"><?php esc_html_e('Preferred Translation Model', 'aiutoma'); ?></label></th>
                        <td>
                            <select name="aiutoma_wpml_model" id="aiutoma_wpml_model" style="min-width: 300px;">
                                <option value=""><?php esc_html_e('&mdash; Auto Detect Best Model &mdash;', 'aiutoma'); ?></option>
                                <option value="google_free" <?php selected($selected_model, 'google_free'); ?>><?php esc_html_e('[Google] Google Translator FREE', 'aiutoma'); ?></option>
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_model, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the primary AI model to use for translations. These models support Text Generation. If empty, your globally preferred model will be used.', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Fallback', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aiutoma_wpml_auto_fallback" value="1" <?php checked($auto_fallback, 1); ?>>
                                <?php esc_html_e('Automatically switch to another model if the selected model fails (e.g. rate limit reached, out of tokens)', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Trusted Fallback Models', 'aiutoma'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Select which models are allowed to be used as fallbacks during batch translation to prevent failures. If none are selected, all available text models will be used.', 'aiutoma'); ?></p>
                            <select id="aiutoma_wpml_fallback_models" name="aiutoma_wpml_fallback_models[]" multiple style="min-width: 300px; height: 150px;">
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected(in_array($val, (array) $saved_fallback_models, true), true); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'aiutoma'); ?></button>
                </p>
            </form>

        </div>

<?php
    }

    public function render_logs_tab()
    {
        ?>
        <div class="icl_tm_wrap">
            <h3 style="margin-top: 20px;"><?php esc_html_e('Recent Error Logs', 'aiutoma'); ?></h3>
            <?php
            $upload_dir = wp_upload_dir();
            $log_file = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/wpml.log';
            $logs = [];
            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $logs = array_filter(explode(PHP_EOL, $log_content));
                $logs = array_slice(array_reverse($logs), 0, 100);
            }
            ?>
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; max-height: 500px; overflow-y: auto;">
                <?php if (empty($logs)): ?>
                    <p><?php esc_html_e('No errors logged.', 'aiutoma'); ?></p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; margin: 0; font-family: monospace;">
                        <?php foreach ($logs as $log): ?>
                            <li style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; color: #d63638;">
                                <?php echo esc_html($log); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if (!empty($logs)): ?>
                <form method="post" action="" style="margin-top: 10px;">
                    <?php wp_nonce_field('aiutoma_wpml_settings', 'aiutoma_wpml_settings_nonce'); ?>
                    <input type="hidden" name="aiutoma_clear_logs" value="1">
                    <button type="submit" class="button"><?php esc_html_e('Clear Logs', 'aiutoma'); ?></button>
                </form>
            <?php endif; ?>
        </div>
<?php
    }

    public function log_error($message)
    {
        $upload_dir = wp_upload_dir();
        $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.");
        }
        $log_file = $log_dir . '/wpml.log';
        $time = current_time('mysql');
        $log_entry = "[{$time}] " . $message . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
