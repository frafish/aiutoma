<?php

namespace Aiutoma\Modules\Playground\Traits;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

trait Ui
{
    public static function get_allowed_chat_html()
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['button'] = array_merge($allowed['button'] ?? [], [
            'type' => true,
            'class' => true,
            'id' => true,
            'title' => true,
            'style' => true,
            'disabled' => true,
            'data-backup-id' => true,
            'data-text' => true,
        ]);
        $allowed['details'] = array_merge($allowed['details'] ?? [], [
            'class' => true,
            'open' => true,
            'style' => true,
        ]);
        $allowed['summary'] = array_merge($allowed['summary'] ?? [], [
            'class' => true,
            'style' => true,
        ]);
        $allowed['textarea'] = array_merge($allowed['textarea'] ?? [], [
            'class' => true,
            'id' => true,
            'style' => true,
            'readonly' => true,
        ]);
        $allowed['span'] = array_merge($allowed['span'] ?? [], [
            'class' => true,
            'id' => true,
            'style' => true,
            'title' => true,
        ]);
        $allowed['div'] = array_merge($allowed['div'] ?? [], [
            'class' => true,
            'id' => true,
            'style' => true,
            'title' => true,
        ]);
        return $allowed;
    }

    public function aiutoma_page_html()
    {
        $is_ai_configured = false;
        $models_response = \Aiutoma\Modules\Ai\Ai::instance()->get_ai_models(new \WP_REST_Request());
        $models_data = $models_response->get_data();
        $models = $models_data['models'] ?? [];
        if (count($models) > 0) {
            $is_ai_configured = true;
        }

        $channel = get_option('aiutoma_gemini_api_channel', 'v1beta');
?>
        <div class="wrap">
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                <h1 class="wp-heading-inline" style="display: inline-flex; align-items: center; gap: 10px; margin: 0;">
                    <img src="<?php echo esc_url(AIUTOMA_URL . 'modules/ai/assets/svg/aiutoma.svg'); ?>" alt="Aiutoma" style="width: 40px; height: 40px;">
                    <span><?php esc_html_e('AIutoma Playground', 'aiutoma'); ?></span>
                </h1>
                <?php if ($is_ai_configured): ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=aiutoma_task')); ?>" class="page-title-action" style="top: 0; margin: 0;"><span class="dashicons dashicons-clock" style="vertical-align: middle;"></span> <span class="aiutoma-hide-on-mobile"><?php esc_html_e('Automated Tasks', 'aiutoma'); ?></span></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiutoma-im')); ?>" class="page-title-action" style="top: 0; margin: 0;"><span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span> <span class="aiutoma-hide-on-mobile"><?php esc_html_e('Instant Messaging', 'aiutoma'); ?></span></a>
                <?php endif; ?>
            </div>
            <hr class="wp-header-end" style="clear: both; margin: 0;">

            <?php if ($is_ai_configured): ?>

                <div class="card aiutoma-playground-card">
                    <div id="aiutoma-playground-chat-wrapper" class="<?php echo isset($_GET['session_id']) ? 'has-content' : ''; ?>">
                        <?php
                        $chat_html = '';
                        $data = [];
                        if (isset($_GET['session_id'])) {
                            $session_id = sanitize_file_name(wp_unslash($_GET['session_id']));
                            $upload_dir = wp_upload_dir();
                            $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . $session_id . '.json';
                            if (file_exists($file_path)) {
                                $data = json_decode(file_get_contents($file_path), true);
                                if (is_array($data)) {
                                    $chat_html = $data['html'] ?? '';
                                    if (empty($chat_html) && !empty($data['messages'])) {
                                        foreach ($data['messages'] as $msg) {
                                            $text = $msg['parts'][0]['text'] ?? '';
                                            if ($msg['role'] === 'user') {
                                                $chat_html .= '<div class="aiutoma-msg-user"><strong>You:</strong><br>' . nl2br(esc_html($text)) . '</div>';
                                            } else {
                                                $chat_html .= '<div class="aiutoma-msg-ai"><div style="display:flex; justify-content:space-between; align-items:center;"><strong>AI:</strong></div>' . wp_kses_post($text) . '</div>';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        ?>
                        <div id="aiutoma-playground-chat"><?php echo wp_kses($chat_html, static::get_allowed_chat_html()); ?></div>
                        <button type="button" class="toggle-distraction-free" title="<?php esc_attr_e('Toggle full screen', 'aiutoma'); ?>">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                    <div class="aiutoma-playground-prompt-wrapper">
                        <textarea id="aiutoma-playground-prompt" placeholder="<?php esc_attr_e('Ask the AI to do something (e.g. make a report, find contents)...', 'aiutoma'); ?>"></textarea>
                        <div id="aiutoma-playground-attachment-preview"></div>
                        <button type="button" id="aiutoma-attach-media" title="<?php esc_attr_e('Attach Media', 'aiutoma'); ?>"><span class="dashicons dashicons-plus"></span></button>
                        <button type="button" id="aiutoma-speech-to-text" title="<?php esc_attr_e('Speech to text', 'aiutoma'); ?>"><span class="dashicons dashicons-microphone"></span></button>
                        <div class="aiutoma-prompt-hint"><?php esc_html_e('Ctrl + Enter to send', 'aiutoma'); ?></div>
                    </div>
                    <div class="aiutoma-toolbar">
                        <div class="aiutoma-toolbar-left">
                            <select id="aiutoma-playground-model">
                                <option value=""><?php esc_html_e('Automatic (Default)', 'aiutoma'); ?></option>
                            </select>

                            <label class="aiutoma-fallback-models-label" id="aiutoma-fallback-models-container">
                                <input type="checkbox" id="aiutoma-fallback-models"> <?php esc_html_e('Auto-retry with other models on failure', 'aiutoma'); ?>
                            </label>
                            <div class="aiutoma-toolbar-separator"></div>

                            <button type="button" id="aiutoma-export-session" class="aiutoma-session-btn" title="<?php esc_attr_e('Export session prompts to a JSON file', 'aiutoma'); ?>" style="display: none;">
                                <span class="dashicons dashicons-download"></span>
                            </button>
                            <button type="button" id="aiutoma-import-session" class="aiutoma-session-btn" title="<?php esc_attr_e('Import prompts from JSON file and execute', 'aiutoma'); ?>">
                                <span class="dashicons dashicons-upload"></span>
                            </button>
                            <input type="file" id="aiutoma-import-file" accept=".json" class="aiutoma-hidden">
                        </div>
                        <div class="aiutoma-toolbar-right" style="display:flex; gap:10px; align-items:center;">
                            <!-- aiutoma-force-md-container removed -->
                            <label class="aiutoma-auto-approve-label">
                                <input type="checkbox" id="aiutoma-global-auto-approve"> <abbr title="<?php esc_attr_e('Skip approvation and Auto-approve tasks for this session', 'aiutoma'); ?>"><?php esc_html_e('Auto-approve', 'aiutoma'); ?></abbr>
                            </label>
                            <?php do_action('aiutoma_playground_toolbar_actions'); ?>
                            <button type="button" id="aiutoma-playground-send" class="button button-primary button-large" title="<?php esc_attr_e('Send', 'aiutoma'); ?>">
                                <span class="dashicons dashicons-controls-play"></span>
                            </button>
                        </div>
                    </div>
                    <div class="aiutoma-media-contexts-container" id="aiutoma-media-contexts-container" style="display:none; padding: 10px; background: #f0f6fc; border-top: 1px solid #c3c4c7; flex-direction: column; gap: 10px;">
                        <!-- Context textareas will be appended here -->
                    </div>
                </div>

                <div class="card aiutoma-context-card">
                    <details>
                        <summary class="aiutoma-card-summary-wrap">
                            <h2>
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php esc_html_e('Context', 'aiutoma'); ?>
                            </h2>
                        </summary>
                        <div class="aiutoma-context-columns">
                            <div class="aiutoma-context-col">
                                <label for="aiutoma-system-info-context">
                                    <strong><?php esc_html_e('System Info', 'aiutoma'); ?></strong>
                                    <input type="checkbox" id="aiutoma-include-system-info" value="1" style="margin-left:5px; margin-top:-2px;">
                                    <span style="font-size:11px; font-weight:normal;"><?php esc_html_e('Pass with prompt', 'aiutoma'); ?></span>
                                </label>
                                <textarea id="aiutoma-system-info-context" class="aiutoma-context-textarea" readonly><?php echo esc_textarea($this->get_environment_details()); ?></textarea>
                            </div>

                            <div class="aiutoma-context-col">
                                <label for="aiutoma-session-context"><strong><?php esc_html_e('Session', 'aiutoma'); ?></strong></label>
                                <textarea id="aiutoma-session-context" class="aiutoma-context-textarea"></textarea>
                            </div>
                            <div class="aiutoma-context-col">
                                <label for="aiutoma-permanent-context"><strong><?php esc_html_e('Permanent', 'aiutoma'); ?></strong></label>
                                <textarea id="aiutoma-permanent-context" class="aiutoma-context-textarea" placeholder="<?php esc_attr_e('Add your site-specific context here. E.g. \'This site uses TailwindCSS\'', 'aiutoma'); ?>"><?php echo esc_textarea(get_option('aiutoma_permanent_context', '')); ?></textarea>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="card aiutoma-abilities-card">
                    <details>
                        <summary class="aiutoma-card-summary-wrap">
                            <h2>
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e('Abilities (Tools)', 'aiutoma'); ?>
                            </h2>
                        </summary>
                        <div style="padding: 15px;">
                            <label for="aiutoma-enable-abilities-toggle" style="display:flex; align-items:center; font-weight:bold; margin-bottom: 10px; cursor: pointer;">
                                <input type="checkbox" id="aiutoma-enable-abilities-toggle" value="1" checked style="margin-right:8px;">
                                <?php esc_html_e('Send Abilities to the Model (Allows the AI to perform actions, but uses more tokens)', 'aiutoma'); ?>
                            </label>
                            <div id="aiutoma-abilities-list-wrap" style="background: #f0f0f1; border: 1px solid #ccc; padding: 10px; margin-bottom: 5px; border-radius: 4px;">
                                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                                    <strong><?php esc_html_e('Select which abilities to provide:', 'aiutoma'); ?></strong>
                                    <div>
                                        <button type="button" class="button button-small" id="aiutoma-abilities-select-all"><?php esc_html_e('Select All', 'aiutoma'); ?></button>
                                        <button type="button" class="button button-small" id="aiutoma-abilities-deselect-all"><?php esc_html_e('Deselect All', 'aiutoma'); ?></button>
                                    </div>
                                </div>
                                <div id="aiutoma-abilities-list-container">
                                    <div style="padding: 20px; text-align: center; color: #666;">
                                        <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> <?php esc_html_e('Loading abilities...', 'aiutoma'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="card aiutoma-skills-card" style="max-width: 100%;">
                    <details>
                        <summary class="aiutoma-card-summary-wrap">
                            <h2>
                                <span class="dashicons dashicons-superhero"></span>
                                <?php esc_html_e('Skills', 'aiutoma'); ?>
                            </h2>
                        </summary>
                        <div style="padding: 15px;">
                            <label for="aiutoma-enable-skills-toggle" style="display:flex; align-items:center; font-weight:bold; margin-bottom: 10px; cursor: pointer;">
                                <input type="checkbox" id="aiutoma-enable-skills-toggle" value="1" autocomplete="off" style="margin-right:8px;">
                                <?php esc_html_e('Inject Skills into System Prompt (Provides context/personas, but uses more tokens)', 'aiutoma'); ?>
                            </label>
                            <div id="aiutoma-skills-list-wrap" style="background: #f0f0f1; border: 1px solid #ccc; padding: 10px; margin-bottom: 5px; border-radius: 4px; opacity: 0.5; pointer-events: none;">
                                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                                    <strong><?php esc_html_e('Select which skills to provide:', 'aiutoma'); ?></strong>
                                    <div>
                                        <button type="button" class="button button-small" id="aiutoma-skills-select-all"><?php esc_html_e('Select All', 'aiutoma'); ?></button>
                                        <button type="button" class="button button-small" id="aiutoma-skills-deselect-all"><?php esc_html_e('Deselect All', 'aiutoma'); ?></button>
                                    </div>
                                </div>
                                <div id="aiutoma-skills-list-container" style="max-height: 250px; overflow-y: auto;">
                                    <div style="padding: 20px; text-align: center; color: #666;">
                                        <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> <?php esc_html_e('Loading skills...', 'aiutoma'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>

                <?php
                $upload_dir = wp_upload_dir();
                $db_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/rag.sqlite';
                $has_rag_data = false;
                if (file_exists($db_path)) {
                    try {
                        // phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
                        $db = new \PDO('sqlite:' . $db_path);
                        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                        $result = $db->query("SELECT COUNT(*) as c FROM document_embeddings");
                        if ($result) {
                            $row = $result->fetch(\PDO::FETCH_ASSOC);
                            if (!empty($row['c']) && $row['c'] > 0) {
                                $has_rag_data = true;
                            }
                        }
                        // phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO
                    } catch (\Exception $e) {
                    }
                }
                if ($has_rag_data):
                ?>
                    <div class="card aiutoma-rag-card">
                        <details>
                            <summary class="aiutoma-card-summary-wrap">
                                <h2>
                                    <span class="dashicons dashicons-database"></span>
                                    <?php esc_html_e('RAG Data', 'aiutoma'); ?>
                                </h2>
                            </summary>
                            <div class="aiutoma-context-columns">
                                <?php
                                $rag_json = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/rag.json';
                                if (file_exists($rag_json)): ?>
                                    <div class="aiutoma-context-col" id="aiutoma-rag-container">
                                        <button type="button" class="button" id="aiutoma-load-rag-data"><?php esc_html_e('Load RAG Data', 'aiutoma'); ?></button>
                                        <p class="description" style="margin-top:5px; font-size:11px;"><?php esc_html_e('RAG data might be very large, load it only if you want to inspect or pass it to the prompt.', 'aiutoma'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="aiutoma-context-col">
                                        <p><?php esc_html_e('No RAG data found.', 'aiutoma'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 0 15px 15px 15px;">
                                <p style="font-size: 12px; margin-top: 0; color: #666;"><em>Note: RAG Data checkboxes are automatically unchecked after the prompt is sent to save tokens on subsequent requests.</em></p>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>

                <div class="aiutoma-cards-row">
                    <div class="card aiutoma-prompts-card aiutoma-column-card">
                        <?php $recent_sessions = $this->get_recent_sessions(10); ?>
                        <?php if (empty($recent_sessions)): ?>
                            <details>
                                <summary class="aiutoma-card-summary-wrap">
                                    <h2>
                                        <span class="dashicons dashicons-lightbulb"></span>
                                        <?php esc_html_e('Try these examples:', 'aiutoma'); ?>
                                    </h2>
                                </summary>
                                <div class="aiutoma-examples-wrapper">
                                    <div class="aiutoma-examples-list">
                                        <?php
                                        $examples = [
                                            "Create a new Gutenberg block that displays a testimonial slider",
                                            "Register a new custom block category called 'Wizard UI'",
                                            "Edit my existing 'Hero' block to add a background video option",
                                            "Enable the core/quote block in my block editor settings",
                                            "Publish my latest blog news",
                                            "Create a new page talking about my new product",
                                            "Create the WPML translation in FR of my page",
                                            "Add a banner with promo text before the woo checkout",
                                            "Fix the compatibility of plugin xyz with PHP 8.5",
                                            "Hide the adminbar for all non admin users"
                                        ];
                                        foreach ($examples as $example) {
                                            echo '<button type="button" class="button button-secondary aiutoma-example-btn" onclick="document.getElementById(\'aiutoma-playground-prompt\').value = this.innerText; document.getElementById(\'aiutoma-playground-prompt\').focus();">' . esc_html($example) . '</button>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </details>
                        <?php else: ?>
                            <details>
                                <summary class="aiutoma-card-summary-wrap">
                                    <h2>
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php esc_html_e('Recent', 'aiutoma'); ?>
                                    </h2>
                                </summary>
                                <div class="aiutoma-recent-prompts-wrapper">
                                    <div style="margin-bottom: 15px;">
                                        <button type="button" id="aiutoma-restore-last-session" class="button button-primary" style="width:100%; display:none;">
                                            <?php esc_html_e('Restore Last Session', 'aiutoma'); ?>
                                        </button>
                                    </div>
                                    <div class="aiutoma-recent-prompts-list">
                                        <?php
                                        foreach ($recent_sessions as $session) {
                                            $prompt_text = $session['first_prompt'];
                                            $date_text = is_numeric($session['date']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $session['date']) : $session['date'];
                                            $user_text = $session['user'];
                                            /* translators: 1: Date, 2: User */
                                            $display_text = sprintf(__('On %1$s by %2$s', 'aiutoma'), $date_text, $user_text);
                                            echo '<div style="display: flex; gap: 5px; margin-bottom: 5px; width: 100%;">';
                                            echo '<button type="button" class="button button-secondary aiutoma-recent-prompt-btn" style="flex-grow: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" onclick="aiutomaRestoreSession(\'' . esc_attr($session['id']) . '\')" title="' . esc_attr($display_text) . '">' . esc_html(mb_strimwidth($prompt_text, 0, 80, '...')) . '</button>';
                                            echo '<button type="button" class="button button-secondary" title="' . esc_attr__('Export Session', 'aiutoma') . '" onclick="aiutomaExportSession(\'' . esc_attr($session['id']) . '\')"><span class="dashicons dashicons-download" style="margin-top:4px;"></span></button>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    $upload_dir = wp_upload_dir();
                                    $log_url = $upload_dir['baseurl'] . '/aiutoma/logs/sessions/';
                                    ?>
                                    <div class="aiutoma-recent-prompts-log-link">
                                        <a href="<?php echo esc_url($log_url); ?>" target="_blank" class="button button-small"><?php esc_html_e('View Sessions', 'aiutoma'); ?></a>
                                    </div>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>

                    <?php
                    $upload_dir = wp_upload_dir();
                    $backup_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/backup';
                    $backup_actions = [];
                    if (is_dir($backup_dir)) {
                        $files = glob($backup_dir . '/*.json');
                        if ($files) {
                            foreach ($files as $file) {
                                $data = json_decode(file_get_contents($file), true);
                                if ($data) {
                                    $filename = basename($file);
                                    $date = filemtime($file);
                                    $desc = '';
                                    $extra_html = '';
                                    $custom_item = apply_filters('aiutoma_backup_item_display', null, $data, $filename);
                                    if (is_array($custom_item)) {
                                        $desc = $custom_item['desc'] ?? '';
                                        $extra_html = $custom_item['extra_html'] ?? '';
                                    } elseif ($data['action'] === 'update-options' || $data['action'] === 'global-rollback' || $data['action'] === 'cron-rollback') {
                                        $details = [];
                                        if (!empty($data['options'])) {
                                            $opts = array_keys($data['options']);
                                            $details[] = count($opts) . ' ' . esc_html__('options', 'aiutoma');
                                            $extra_html .= '<li><strong>' . esc_html__('Options:', 'aiutoma') . '</strong> ' . esc_html(implode(', ', $opts)) . '</li>';
                                        }
                                        if (!empty($data['posts'])) {
                                            $details[] = count($data['posts']) . ' ' . esc_html__('posts', 'aiutoma');
                                            $extra_html .= '<li><strong>' . esc_html__('Posts:', 'aiutoma') . '</strong> ' . esc_html(implode(', ', array_keys($data['posts']))) . '</li>';
                                        }
                                        if ($data['action'] === 'cron-rollback') {
                                            $desc = esc_html__('Automated Task Rollback', 'aiutoma');
                                        } else {
                                            $desc = esc_html__('AI Action Rollback', 'aiutoma') . (!empty($details) ? ' (' . implode(', ', $details) . ')' : '');
                                        }
                                    }

                                    if ($extra_html) {
                                        $extra_html = '<ul style="margin: 4px 0 0 10px; font-size: 11px; list-style-type: disc; opacity: 0.85;">' . $extra_html . '</ul>';
                                    }

                                    if ($desc) {
                                        $backup_actions[] = [
                                            'id' => $filename,
                                            'date' => $date,
                                            'desc' => $desc,
                                            'extra' => $extra_html
                                        ];
                                    }
                                }
                            }
                            usort($backup_actions, function ($a, $b) {
                                return $b['date'] - $a['date'];
                            });
                        }
                    }
                    if (!empty($backup_actions)):
                    ?>
                        <div class="card aiutoma-backups-card aiutoma-column-card">
                            <details>
                                <summary class="aiutoma-card-summary-wrap">
                                    <h2>
                                        <span class="dashicons dashicons-update-alt"></span>
                                        <?php esc_html_e('Backups', 'aiutoma'); ?>
                                    </h2>
                                </summary>
                                <div id="aiutoma-backups-container" class="aiutoma-backups-container">
                                    <p class="aiutoma-backups-summary"><?php
                                                                    /* translators: %d: Number of backups */
                                                                    echo sprintf(esc_html__('There are %d available backups', 'aiutoma'), count($backup_actions));
                                                                    ?></p>
                                    <ul class="aiutoma-backups-list">
                                        <?php foreach ($backup_actions as $action): ?>
                                            <li class="aiutoma-backup-row" style="flex-direction: column; align-items: flex-start; padding: 8px;">
                                                <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
                                                    <span class="aiutoma-backup-desc" title="<?php echo esc_attr($action['desc']); ?>" style="line-height: 1.4; max-width: 80%;">
                                                        <strong>[<?php echo esc_html(date_i18n('Y-m-d H:i:s', $action['date'])); ?>]</strong><br>
                                                        <?php echo esc_html($action['desc']); ?>
                                                    </span>
                                                    <button type="button" class="button button-small aiutoma-rollback-btn" data-backup-id="<?php echo esc_attr($action['id']); ?>">↩️ Restore</button>
                                                </div>
                                                <?php if (!empty($action['extra'])) echo wp_kses_post($action['extra']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="aiutoma-backups-actions">
                                        <button type="button" id="aiutoma-clear-backups" class="button button-secondary aiutoma-btn-danger">
                                            <?php esc_html_e('🗑️ Clear All Rollback Backups', 'aiutoma'); ?>
                                        </button>
                                    </div>
                                </div>
                            </details>
                        </div>
                    <?php else: ?>
                        <div class="aiutoma-empty-column"></div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="notice notice-info inline aiutoma-api-config-notice">
                    <p>
                        <?php esc_html_e('The AI API configuration is managed directly by WordPress Core.', 'aiutoma'); ?>
                        <a href="<?php echo esc_url(admin_url('options-connectors.php')); ?>"><?php esc_html_e('Manage Core AI Settings', 'aiutoma'); ?> &rarr;</a>
                    </p>
                </div>
            <?php endif; ?>

            <?php
            /**
             * Fires at the bottom of the Playground sidebar panel.
             */
            do_action('aiutoma_playground_sidebar_bottom');
            ?>
        </div>
<?php
    }

    public function enqueue_playground_scripts($hook)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (strpos($hook, 'aiutoma') !== false || (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'aiutoma')) {
            $user = wp_get_current_user();
            $prev = $user->syntax_highlighting;
            $user->syntax_highlighting = 'true';

            if (apply_filters('aiutoma_enable_php_codemirror', false)) {
                \Aiutoma\Modules\Ai\Ai::instance()->cm_settings = wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            } else {
                \Aiutoma\Modules\Ai\Ai::instance()->cm_settings = null;
            }
            \Aiutoma\Modules\Ai\Ai::instance()->cm_sql_settings = wp_enqueue_code_editor(array('type' => 'text/x-sql'));

            $user->syntax_highlighting = $prev;

            // Force manual enqueue to guarantee loading
            wp_enqueue_script('wp-codemirror');
            wp_enqueue_style('wp-codemirror');
            wp_enqueue_script('code-editor');
            wp_enqueue_style('code-editor');

            wp_enqueue_script('jquery-ui-resizable');

            wp_enqueue_style('aiutoma-playground-style', AIUTOMA_URL . 'modules/playground/assets/css/playground.css', array(), filemtime(AIUTOMA_PATH . 'modules/playground/assets/css/playground.css'));

            // The JS file was probably enqueued elsewhere previously or was missing. Let's make sure it's enqueued here.
            wp_enqueue_script('aiutoma-playground-script', AIUTOMA_URL . 'modules/playground/assets/js/playground.js', array('jquery', 'aiutoma-select2'), filemtime(AIUTOMA_PATH . 'modules/playground/assets/js/playground.js'), true);

            $aiutoma_settings = [
                'nonceTest' => wp_create_nonce("aiutoma_test_nonce"),
                'nonceRest' => wp_create_nonce("wp_rest"),
                'restUrl' => esc_url_raw(rest_url('aiutoma/v1/ai-chat')),
                'homeUrl' => esc_url_raw(home_url('/')),
                'textTesting' => __('Testing...', 'aiutoma'),
                'textAiThinking' => __('AI is thinking...', 'aiutoma'),
                'preferredModel' => get_user_meta(get_current_user_id(), '_aiutoma_preferred_model', true),
                'objectType' => 'toplevel_page_aiutoma',
                'cmSettings' => \Aiutoma\Modules\Ai\Ai::instance()->cm_settings,
                'cmSqlSettings' => \Aiutoma\Modules\Ai\Ai::instance()->cm_sql_settings ?: ['codemirror' => ['mode' => 'sql', 'lineNumbers' => true]],
                'debugMode' => (defined('WP_DEBUG') && WP_DEBUG) ? true : false,
                'ragUrl' => esc_url_raw(rest_url('aiutoma/v1/get-rag-data'))
            ];
            $aiutoma_settings = apply_filters('aiutoma_playground_settings', $aiutoma_settings);
            wp_add_inline_script('aiutoma-playground-script', 'window.aiutomaSettings = ' . wp_json_encode($aiutoma_settings) . ';', 'before');

            $session_conv_id = null;
            $session_prompts = [];
            $session_messages = [];
            $session_context = [];
            if (isset($_GET['session_id'])) {
                $session_id = sanitize_file_name(wp_unslash($_GET['session_id']));
                $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . $session_id . '.json';
                if (file_exists($file_path)) {
                    $session_data = json_decode(file_get_contents($file_path), true);
                    if (is_array($session_data)) {
                        $session_conv_id = !empty($session_data['id']) ? $session_data['id'] : (!empty($session_data['conversation_id']) ? $session_data['conversation_id'] : null);
                        $session_prompts = empty($session_data['session_prompts']) ? [] : $session_data['session_prompts'];
                        $session_messages = empty($session_data['messages']) ? [] : $session_data['messages'];
                        $session_context = empty($session_data['context']) ? [] : $session_data['context'];
                    }
                }
            }
            $session_script = 'window.aiutomaCurrentConversationId = ' . wp_json_encode($session_conv_id) . ';' .
                'window.aiutomaSessionPrompts = ' . wp_json_encode($session_prompts) . ';' .
                'window.aiutomaSessionMessages = ' . wp_json_encode($session_messages) . ';' .
                'window.aiutomaSessionContext = ' . wp_json_encode($session_context) . ';';
            wp_add_inline_script('aiutoma-playground-script', $session_script, 'before');

            // Enqueue native WordPress media uploader
            wp_enqueue_media();
        }
    }
}
