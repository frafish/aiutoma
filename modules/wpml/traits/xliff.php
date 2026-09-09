<?php

namespace Aiutoma\Modules\Wpml\Traits;

if (! defined('ABSPATH')) {
    exit;
}

trait Xliff
{

    public function register_xliff_routes()
    {
        register_rest_route('aiutoma/v1', '/wpml-xliff-jobs', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wpml_get_xliff_jobs'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aiutoma/v1', '/wpml-xliff-translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wpml_xliff_translate'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function render_xliff_tab()
    {
?>
        <div class="aiutoma-wpml-xliff-wrapper" style="margin-top: 20px;">
            <div class="wpml-tm-dashboard-message info with-close-button" style="margin-bottom: 24px;">
                <div class="wpml-tm-dashboard-message-content">
                    <?php esc_html_e('These are the pending Translation Jobs from WPML Translation Management. Select the jobs and process them via AI XLIFF in-memory.', 'aiutoma'); ?>
                </div>
            </div>



            <table class="wp-list-table widefat fixed striped wpml-translation-management-jobs">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-xliff" />
                        </th>
                        <th class="manage-column"><?php esc_html_e('Title', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Type', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Languages', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Translated by', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Deadline', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Status', 'aiutoma'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Actions', 'aiutoma'); ?></th>
                    </tr>
                </thead>
                <tbody id="aiutoma-wpml-xliff-list">
                    <tr>
                        <td colspan="8"><?php esc_html_e('Loading jobs...', 'aiutoma'); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="tablenav bottom" style="display:flex; justify-content:space-between; align-items:center; margin-top: 10px;">
                <div class="alignleft actions bulkactions">
                    <button class="button button-primary" id="aiutoma-start-xliff-translation" style="background:#2271b1;">
                        <?php esc_html_e('Translate Selected via AI', 'aiutoma'); ?>
                    </button>
                    <span id="aiutoma-xliff-progress" style="font-weight: 500; margin-left:10px;"></span>
                </div>
            </div>
        </div>

<?php
    }

    public function handle_wpml_get_xliff_jobs(\WP_REST_Request $request)
    {
        global $wpdb;

        if (!class_exists('SitePress')) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML not active.'], 500);
        }

        $cache_key = 'aiutoma_wpml_xliff_jobs';
        $jobs = wp_cache_get($cache_key, 'aiutoma');

        if (false === $jobs) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $jobs = $wpdb->get_results(
                "SELECT j.job_id, j.title, s.status, t.source_language_code, t.language_code, s.needs_update, 
                        t.element_type, j.deadline_date, j.translator_id, t.element_id 
                 FROM {$wpdb->prefix}icl_translate_job j
                 JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
                 JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
                 WHERE (s.status != 10 OR s.needs_update = 1) AND (j.revision IS NULL OR j.revision = '')
                 ORDER BY j.job_id DESC
                 LIMIT 100"
            );
            wp_cache_set($cache_key, $jobs, 'aiutoma', 60);
        }

        $formatted_jobs = [];
        if ($jobs) {
            global $sitepress;
            foreach ($jobs as $job) {
                $status_html = '';
                if ($job->needs_update == 1) {
                    $status_html = '<span class="otgs-badge" style="background:#f0b849; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px;">' . esc_html__('Needs Update', 'aiutoma') . '</span>';
                } elseif ($job->status == 2) {
                    $status_html = '<span class="otgs-badge" style="background:#2271b1; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px;">' . esc_html__('In Progress', 'aiutoma') . '</span>';
                } else {
                    $status_html = '<span class="otgs-badge" style="background:#8c8f94; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px;">' . esc_html__('Pending', 'aiutoma') . '</span>';
                }

                $type = str_replace('post_', '', $job->element_type);
                $type = str_replace('tax_', 'Tax: ', $type);
                $type = ucfirst($type);

                $src_code = (string) $job->source_language_code;
                $tgt_code = (string) $job->language_code;
                
                $source_flag = $sitepress ? $sitepress->get_flag_url($src_code) : '';
                $target_flag = $sitepress ? $sitepress->get_flag_url($tgt_code) : '';
                $source_name = $sitepress ? $sitepress->get_display_language_name($src_code, 'en') : $src_code;
                $target_name = $sitepress ? $sitepress->get_display_language_name($tgt_code, 'en') : $tgt_code;

                $languages_html = '';
                if ($source_flag) {
                    $languages_html .= '<img src="' . esc_url($source_flag) . '" alt="' . esc_attr($source_name) . '" style="vertical-align:middle; width:18px; height:12px; margin-right:4px;"> ';
                }
                $languages_html .= esc_html($source_name) . ' &raquo; ';
                if ($target_flag) {
                    $languages_html .= '<img src="' . esc_url($target_flag) . '" alt="' . esc_attr($target_name) . '" style="vertical-align:middle; width:18px; height:12px; margin-right:4px;"> ';
                }
                $languages_html .= esc_html($target_name);

                $translator = 'Aiutoma';
                if ($job->translator_id > 0) {
                    $user = get_userdata($job->translator_id);
                    $translator = $user ? $user->user_login : $job->translator_id;
                }

                $deadline = !empty($job->deadline_date) ? date_i18n(get_option('date_format'), strtotime($job->deadline_date)) : '-';

                $edit_url = '';
                if (!empty($job->element_id)) {
                    if (strpos($job->element_type, 'post_') === 0) {
                        if (get_post_type($job->element_id)) {
                            $edit_url = get_edit_post_link((int) $job->element_id, 'raw');
                        }
                    } elseif (strpos($job->element_type, 'tax_') === 0) {
                        $taxonomy = str_replace('tax_', '', $job->element_type);
                        if (term_exists((int) $job->element_id, $taxonomy)) {
                            $edit_url = get_edit_term_link((int) $job->element_id, $taxonomy);
                        }
                    }
                }

                $formatted_jobs[] = [
                    'job_id' => $job->job_id,
                    'title' => esc_html($job->title),
                    'edit_url' => $edit_url ? esc_url_raw($edit_url) : '',
                    'type' => $type,
                    'languages' => $languages_html,
                    'translator' => $translator,
                    'deadline' => $deadline,
                    'status' => $status_html
                ];
            }
        }

        return new \WP_REST_Response(['success' => true, 'jobs' => $formatted_jobs], 200);
    }

    public function handle_wpml_xliff_translate(\WP_REST_Request $request)
    {
        // XLIFF translation of large posts can take a while, especially with rate limits
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        
        $raw_job_id = $request->get_param('job_id');
        $translator_id = get_current_user_id();

        if (empty($raw_job_id) || empty($translator_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing required parameters.'], 400);
        }

        $this->log_error("Aiutoma XLIFF Translating requested raw_job_id: $raw_job_id");

        global $wpdb;

        $actual_job_id = false;
        
        // Parse the explicit type if provided by the new JS format (e.g. 'rid_12', 'job_12', 'post_500')
        $type = 'unknown';
        $numeric_id = 0;
        
        if (preg_match('/^(rid|job|post|raw)_(\d+)$/', $raw_job_id, $matches)) {
            $type = $matches[1];
            $numeric_id = (int)$matches[2];
        } else {
            $numeric_id = (int)$raw_job_id;
        }

        if ($type === 'job') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $actual_job_id = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $numeric_id));
        } elseif ($type === 'rid' || $type === 'raw') {
            $this->log_error("Aiutoma: Searching for RID $numeric_id...");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $actual_job_id = $wpdb->get_var($wpdb->prepare("
                SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d ORDER BY job_id DESC LIMIT 1
            ", $numeric_id));
        } elseif ($type === 'post') {
            $this->log_error("Aiutoma: ID $numeric_id is a Post ID. Finding associated translation jobs...");
            $post_like = $wpdb->esc_like('post_') . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $job_ids = $wpdb->get_col($wpdb->prepare("
                SELECT j.job_id 
                FROM {$wpdb->prefix}icl_translate_job j
                JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
                JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
                WHERE t.element_id = %d AND t.element_type LIKE %s
                ORDER BY j.job_id DESC
            ", $numeric_id, $post_like));
            if (!empty($job_ids)) {
                $actual_job_id = $job_ids[0];
                $this->log_error("Aiutoma: Resolved Post ID to Job ID: $actual_job_id");
            } else {
                $this->log_error("Aiutoma: No translation jobs found for Post ID $numeric_id");
                return new \WP_REST_Response(['success' => false, 'message' => 'No translation jobs found for this post.'], 404);
            }
        } else {
            // Fallback for old numeric values: try Job ID first, then RID, then Post ID
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $actual_job_id = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $numeric_id));
            if (!$actual_job_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $actual_job_id = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d ORDER BY job_id DESC LIMIT 1", $numeric_id));
            }
        }

        if (!$actual_job_id) {
            $this->log_error("Aiutoma: Failed to resolve requested ID $raw_job_id to a valid translation job.");
            return new \WP_REST_Response(['success' => false, 'message' => 'Could not find a translation job for this ID.'], 404);
        }

        $job_id = (int)$actual_job_id;
        $this->log_error("Aiutoma: Proceeding with final Job ID: $job_id");

        if (!function_exists('wpml_tm_xliff_factory')) {
            $this->log_error("Aiutoma: wpml_tm_xliff_factory function not found.");
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML XLIFF functions not found.'], 500);
        }

        // 1. Export XLIFF
        try {
            $xliff_writer = wpml_tm_xliff_factory()->create_writer('12');
            if (!$xliff_writer) return new \WP_REST_Response(['success' => false, 'message' => 'Failed to initialize WPML XLIFF writer.'], 500);

            $xliff_string = $xliff_writer->generate_job_xliff($job_id);
            if (!$xliff_string) {
                $this->log_error("Aiutoma XLIFF Error: Failed to generate XLIFF for job_id: $job_id");
                return new \WP_REST_Response(['success' => false, 'message' => 'Failed to generate XLIFF.'], 500);
            }
            $this->log_error("Aiutoma XLIFF: Generated XLIFF of length " . strlen($xliff_string) . " for job_id: $job_id");
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML Exception: ' . $e->getMessage()], 500);
        }

        // 2. Translate with AI
        $prompt = "You are an expert translator. The following is an XLIFF standard translation file.\n";
        $prompt .= "Your task is to translate all the text inside the <source> tags and put the translation in the corresponding <target> tags.\n";
        $prompt .= "IMPORTANT RULES:\n";
        $prompt .= "- Return ONLY the raw valid XML code. Do not wrap it in markdown code blocks like ```xml.\n";
        $prompt .= "- Preserve ALL XML structure and other tags (like <mrk>, <g>).\n";
        $prompt .= "- Ensure the returned string starts with <?xml.\n\n";
        $prompt .= "XLIFF CONTENT:\n" . $xliff_string;

        if (!method_exists($this, 'call_ai_for_text')) {
            return new \WP_REST_Response(['success' => false, 'message' => 'AI Client method not available.'], 500);
        }

        $model = get_option('aiutoma_wpml_model', '');
        if ($model === 'google_free' && method_exists($this, 'google_translate_free')) {
            $translated_xliff = $xliff_string;
            // Get langs from DB since XML parsing might be tricky with namespaces
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $job_row = $wpdb->get_row($wpdb->prepare(
                "
                SELECT t.source_language_code, t.language_code 
                FROM {$wpdb->prefix}icl_translate_job j
                JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
                JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
                WHERE j.job_id = %d",
                $job_id
            ));

            if ($job_row) {
                $source_lang = $job_row->source_language_code;
                $target_lang = $job_row->language_code;

                // Strip any existing target tags to prevent duplicate XML elements
                $translated_xliff = preg_replace('/<target[^>]*>.*?<\/target>/s', '', $translated_xliff);
                $translated_xliff = preg_replace('/<target[^>]*\/>/s', '', $translated_xliff);

                // Simple regex to extract and replace sources
                $translated_xliff = preg_replace_callback('/<source><!\[CDATA\[(.*?)\]\]><\/source>/s', function ($matches) use ($source_lang, $target_lang) {
                    $source_text = $matches[1];
                    usleep(300000); // 300ms delay to prevent Google Translate rate limits
                    $t = $this->google_translate_free($source_lang, $target_lang, $source_text);
                    if (is_wp_error($t)) {
                        $this->log_error("Aiutoma Google Translate Free Error (CDATA): " . $t->get_error_message());
                        return $matches[0] . '<target><![CDATA[' . $source_text . ']]></target>';
                    }
                    return $matches[0] . '<target><![CDATA[' . $t . ']]></target>';
                }, $translated_xliff);

                // Sometimes it's without CDATA
                $translated_xliff = preg_replace_callback('/<source>([^<]+)<\/source>/s', function ($matches) use ($source_lang, $target_lang) {
                    $source_text = html_entity_decode($matches[1]);
                    if (trim($source_text) === '') {
                        return $matches[0] . '<target>' . $matches[1] . '</target>';
                    }
                    usleep(300000); // 300ms delay
                    $t = $this->google_translate_free($source_lang, $target_lang, $source_text);
                    if (is_wp_error($t)) {
                        $this->log_error("Aiutoma Google Translate Free Error (Text): " . $t->get_error_message());
                        return $matches[0] . '<target>' . $matches[1] . '</target>';
                    }
                    return $matches[0] . '<target>' . htmlspecialchars($t) . '</target>';
                }, $translated_xliff);
            }
        } else {
            $translated_xliff = $this->call_ai_for_text($prompt);
            if (is_wp_error($translated_xliff)) {
                return new \WP_REST_Response(['success' => false, 'message' => $translated_xliff->get_error_message()], 500);
            }

            // Clean markdown if present
            $translated_xliff = preg_replace('/^```xml\s*/i', '', trim($translated_xliff));
            $translated_xliff = preg_replace('/```$/', '', $translated_xliff);
            $translated_xliff = trim($translated_xliff);
        }

        if (strpos($translated_xliff, '<?xml') !== 0) {
            $this->log_error("Aiutoma XLIFF Error: AI did not return valid XML. Returned text: " . substr($translated_xliff, 0, 200));
            return new \WP_REST_Response(['success' => false, 'message' => 'AI did not return valid XML.'], 500);
        }

        // 3. Import XLIFF
        if (!class_exists('\WPML_TM_Xliff_Reader_Factory')) {
            $this->log_error("Aiutoma XLIFF Error: WPML XLIFF Reader Factory not found.");
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML XLIFF Reader Factory not found.'], 500);
        }

        try {
            $job_factory = function_exists('wpml_tm_load_job_factory') ? wpml_tm_load_job_factory() : null;
            $xliff_reader_factory = new \WPML_TM_Xliff_Reader_Factory($job_factory);
            $xliff_reader = $xliff_reader_factory->general_xliff_reader();

            $job_data = $xliff_reader->get_data($translated_xliff);
            if (is_wp_error($job_data)) {
                $this->log_error("Aiutoma XLIFF Parsing error: " . $job_data->get_error_message());
                return new \WP_REST_Response(['success' => false, 'message' => 'Failed to parse AI translated XML: ' . $job_data->get_error_message()], 500);
            }

            if (!function_exists('wpml_tm_save_data')) {
                $wpml_dir = defined('WPML_PLUGIN_PATH') ? WPML_PLUGIN_PATH : (defined('ICL_PLUGIN_PATH') ? ICL_PLUGIN_PATH : '');
                if ($wpml_dir && file_exists(trailingslashit($wpml_dir) . 'inc/wpml-private-actions-tm.php')) {
                    require_once trailingslashit($wpml_dir) . 'inc/wpml-private-actions-tm.php';
                }
            }

            // Force all fields in job_data to be finished
            if (isset($job_data['job_id'])) {
                $job_data['complete'] = 1;
            }

            if (function_exists('wpml_tm_save_data')) {
                kses_remove_filters();
                
                // Print job_data structure to log for debugging
                $this->log_error("Aiutoma Job Data before save: " . print_r($job_data, true));

                $result = wpml_tm_save_data($job_data, false);
                $this->log_error("Aiutoma Job Save Result: " . var_export($result, true));
                kses_init();
                if (!$result) {
                    return new \WP_REST_Response(['success' => false, 'message' => 'WPML failed to save translation data.'], 500);
                }
                wp_cache_delete('aiutoma_wpml_xliff_jobs', 'aiutoma');
                return new \WP_REST_Response(['success' => true, 'message' => 'Translation imported successfully.'], 200);
            } else {
                $this->log_error("Aiutoma XLIFF Error: WPML wpml_tm_save_data function not found and could not be loaded.");
                return new \WP_REST_Response(['success' => false, 'message' => 'WPML wpml_tm_save_data function not found.'], 500);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WPML Import Exception: ' . $e->getMessage()], 500);
        }
    }
}
