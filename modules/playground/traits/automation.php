<?php

namespace Aiutoma\Modules\Playground\Traits;

if (! defined('ABSPATH')) exit;
trait Automation
{

    public function register_automation_hooks()
    {
        add_action('init', [$this, 'register_automated_task_cpt']);
        add_action('admin_menu', [$this, 'add_automation_menu']);
        add_action('rest_api_init', [$this, 'register_automation_routes']);

        // CPT Hooks
        add_action('add_meta_boxes', [$this, 'add_aiutoma_task_meta_boxes']);
        add_action('save_post_aiutoma_task', [$this, 'save_aiutoma_task_meta']);
        add_filter('manage_aiutoma_task_posts_columns', [$this, 'aiutoma_task_columns']);
        add_action('manage_aiutoma_task_posts_custom_column', [$this, 'aiutoma_task_custom_column'], 10, 2);

        // Custom Cron schedule
        add_filter('cron_schedules', function ($schedules) {
            if (!isset($schedules['aiutoma_every_minute'])) {
                $schedules['aiutoma_every_minute'] = [
                    'interval' => 60,
                    'display' => __('Every Minute (Aiutoma)', 'aiutoma')
                ];
            }
            return $schedules;
        });

        // Cron hook
        add_action('aiutoma_automated_tasks_cron', [$this, 'run_automated_tasks']);

        // Register cron if not registered
        if (!wp_next_scheduled('aiutoma_automated_tasks_cron')) {
            wp_schedule_event(time(), 'aiutoma_every_minute', 'aiutoma_automated_tasks_cron');
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue_automation_scripts']);
    }

    public function register_automated_task_cpt()
    {
        $labels = [
            'name'                  => _x('Automated Tasks', 'Post type general name', 'aiutoma'),
            'singular_name'         => _x('Automated Task', 'Post type singular name', 'aiutoma'),
            'menu_name'             => _x('Automated Tasks', 'Admin Menu text', 'aiutoma'),
            'name_admin_bar'        => _x('Automated Task', 'Add New on Toolbar', 'aiutoma'),
            'add_new'               => __('Add New', 'aiutoma'),
            'add_new_item'          => __('Add New Task', 'aiutoma'),
            'new_item'              => __('New Task', 'aiutoma'),
            'edit_item'             => __('Edit Task', 'aiutoma'),
            'view_item'             => __('View Task', 'aiutoma'),
            'all_items'             => __('All Tasks', 'aiutoma'),
            'search_items'          => __('Search Tasks', 'aiutoma'),
            'parent_item_colon'     => __('Parent Tasks:', 'aiutoma'),
            'not_found'             => __('No tasks found.', 'aiutoma'),
            'not_found_in_trash'    => __('No tasks found in Trash.', 'aiutoma'),
            'item_published'        => __('Task published.', 'aiutoma'),
            'item_published_privately' => __('Task published privately.', 'aiutoma'),
            'item_reverted_to_draft' => __('Task reverted to draft.', 'aiutoma'),
            'item_scheduled'        => __('Task scheduled.', 'aiutoma'),
            'item_updated'          => __('Task updated.', 'aiutoma'),
        ];

        $args = [
            'label'               => __('Automated Tasks', 'aiutoma'),
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'capability_type'     => 'post',
            'supports'            => ['title', 'editor', 'author'],
        ];
        register_post_type('aiutoma_task', $args);
    }

    public function add_aiutoma_task_meta_boxes()
    {
        add_meta_box('aiutoma_task_settings', __('Task Settings', 'aiutoma'), [$this, 'aiutoma_task_settings_html'], 'aiutoma_task', 'side', 'default');
    }

    public function aiutoma_task_settings_html($post)
    {
        wp_nonce_field('aiutoma_task_save_meta', 'aiutoma_task_meta_nonce');

        $meta = get_post_meta($post->ID, '_aiutoma_task_data', true);
        if (!is_array($meta)) $meta = [];

        $schedule = $meta['schedule'] ?? 'hourly';
        $pause_time = $meta['pause_time'] ?? '0';

        $model = $meta['model'] ?? '';
        $execute_time = $meta['execute_time'] ?? '';

        $sched = $schedule;
        $custom_cron = '';
        if (!in_array($sched, ['hourly', 'twicedaily', 'daily', 'once', 'minute'])) {
            $custom_cron = $sched;
            $sched = 'custom';
        }
?>
        <div class="aiutoma-task-setting-group">
            <label><strong><?php esc_html_e('Schedule', 'aiutoma'); ?></strong></label><br>
            <select name="aiutoma_schedule_preset" id="aiutoma-task-schedule">
                <option value="minute" <?php selected($sched, 'minute'); ?>><?php esc_html_e('Every Minute', 'aiutoma'); ?></option>
                <option value="hourly" <?php selected($sched, 'hourly'); ?>><?php esc_html_e('Hourly', 'aiutoma'); ?></option>
                <option value="twicedaily" <?php selected($sched, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'aiutoma'); ?></option>
                <option value="daily" <?php selected($sched, 'daily'); ?>><?php esc_html_e('Daily', 'aiutoma'); ?></option>
                <option value="custom" <?php selected($sched, 'custom'); ?>><?php esc_html_e('Custom Cron...', 'aiutoma'); ?></option>
                <option value="once" <?php selected($sched, 'once'); ?>><?php esc_html_e('Execute Once', 'aiutoma'); ?></option>
            </select>
            <div id="aiutoma-custom-cron-wrap" class="aiutoma-automation-cron-wrap" style="<?php echo esc_attr($sched === 'custom' ? 'display:block;' : 'display:none;'); ?>">
                <input type="text" name="aiutoma_schedule_custom" id="aiutoma-custom-cron" placeholder="* * * * *" value="<?php echo esc_attr($custom_cron); ?>">
                <p class="description"><?php esc_html_e('Minute, Hour, Day, Month, Day of week.', 'aiutoma'); ?></p>
            </div>
            <div id="aiutoma-once-wrap" class="aiutoma-automation-once-wrap" style="<?php echo esc_attr($sched === 'once' ? 'display:block;' : 'display:none;'); ?>">
                <input type="datetime-local" name="aiutoma_execute_time" id="aiutoma-task-execute-time" value="<?php echo esc_attr($execute_time ? wp_date('Y-m-d\TH:i', $execute_time) : ''); ?>">
                <p class="description"><?php esc_html_e('Select the exact date and time to run this task.', 'aiutoma'); ?></p>
            </div>
        </div>

        <div class="aiutoma-task-setting-group">
            <label><strong><?php esc_html_e('AI Model', 'aiutoma'); ?></strong></label><br>
            <select name="aiutoma_model" id="aiutoma-task-model" data-selected="<?php echo esc_attr($model); ?>" class="aiutoma-task-model-select">
                <option value=""><?php esc_html_e('Automatic (Default)', 'aiutoma'); ?></option>
            </select>
        </div>
        <div class="aiutoma-task-setting-group">
            <label><strong><?php esc_html_e('Rate Limit Pause (between tool calls in seconds)', 'aiutoma'); ?></strong></label><br>
            <select name="aiutoma_pause_time" id="aiutoma-task-pause-time">
                <option value="0" <?php selected($pause_time, '0'); ?>><?php esc_html_e('No pause (Fastest)', 'aiutoma'); ?></option>
                <option value="1" <?php selected($pause_time, '1'); ?>><?php esc_html_e('1 Second', 'aiutoma'); ?></option>
                <option value="2" <?php selected($pause_time, '2'); ?>><?php esc_html_e('2 Seconds (Recommended)', 'aiutoma'); ?></option>
                <option value="5" <?php selected($pause_time, '5'); ?>><?php esc_html_e('5 Seconds', 'aiutoma'); ?></option>
                <option value="random" <?php selected($pause_time, 'random'); ?>><?php esc_html_e('Random (1-5s)', 'aiutoma'); ?></option>
            </select>
        </div>
    <?php
    }

    public function save_aiutoma_task_meta($post_id)
    {
        if (!isset($_POST['aiutoma_task_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiutoma_task_meta_nonce'])), 'aiutoma_task_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $meta = get_post_meta($post_id, '_aiutoma_task_data', true);
        if (!is_array($meta)) $meta = [];

        $preset = sanitize_text_field(wp_unslash($_POST['aiutoma_schedule_preset'] ?? 'hourly'));
        if ($preset === 'custom') {
            $meta['schedule'] = sanitize_text_field(wp_unslash($_POST['aiutoma_schedule_custom'] ?? ''));
        } else {
            $meta['schedule'] = $preset;
        }

        $meta['model'] = sanitize_text_field(wp_unslash($_POST['aiutoma_model'] ?? ''));
        $meta['pause_time'] = sanitize_text_field(wp_unslash($_POST['aiutoma_pause_time'] ?? '2'));


        if (!empty($_POST['aiutoma_execute_time'])) {
            $meta['execute_time'] = strtotime(sanitize_text_field(wp_unslash($_POST['aiutoma_execute_time'])));
        }
        if (!isset($meta['active'])) {
            $meta['active'] = true;
        }

        update_post_meta($post_id, '_aiutoma_task_data', $meta);
    }

    public function aiutoma_task_columns($columns)
    {
        unset($columns['date']);
        $columns['schedule'] = __('Schedule', 'aiutoma');
        $columns['pause'] = __('Pause', 'aiutoma');
        $columns['last_run'] = __('Last Run', 'aiutoma');
        $columns['next_run'] = __('Next Run', 'aiutoma');
        $columns['count'] = __('Count', 'aiutoma');
        $columns['status'] = __('Status', 'aiutoma');
        $columns['actions'] = __('Actions', 'aiutoma');
        return $columns;
    }

    public function aiutoma_task_custom_column($column, $post_id)
    {
        $tasks = $this->_get_all_automated_tasks();
        if (!isset($tasks[$post_id])) return;
        $task = $tasks[$post_id];

        switch ($column) {
            case 'schedule':
                if (($task['schedule'] ?? '') === 'once' && !empty($task['execute_time'])) {
                    echo esc_html__('Once at ', 'aiutoma') . esc_html(wp_date('Y-m-d H:i', $task['execute_time']));
                } else {
                    echo esc_html(ucfirst($task['schedule'] ?? 'Hourly'));
                }
                break;
            case 'pause':
                echo esc_html(($task['pause_time'] ?? '2') . 's');
                break;
            case 'last_run':
                echo !empty($task['last_run']) ? esc_html(wp_date('Y-m-d H:i:s', $task['last_run'])) : esc_html__('Never', 'aiutoma');
                break;
            case 'next_run':
                $next = $this->_get_next_run_time($task);
                echo $next ? esc_html(wp_date('Y-m-d H:i:s', $next)) : '-';
                break;
            case 'count':
                echo esc_html($task['run_count'] ?? '0');
                break;
            case 'status':
                $post = get_post($post_id);
                if ($post->post_status !== 'publish') {
                    echo '<span style="color:gray">' . esc_html(ucfirst($post->post_status)) . '</span>';
                } else {
                    echo empty($task['active']) ? '<span style="color:red">Paused</span>' : '<span style="color:green">Active</span>';
                }
                break;
            case 'actions':
                echo '<button class="button button-small aiutoma-btn-run aiutoma-run-task" data-id="' . esc_attr($post_id) . '">' . esc_html__('Run Now', 'aiutoma') . '</button> ';
                if (!empty($task['last_log'])) {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=aiutoma-automation-log&id=' . $post_id)) . '" class="button button-small" target="_blank">' . esc_html__('View Log', 'aiutoma') . '</a> ';
                }

                $upload_dir = wp_upload_dir();
                $session_id_prefix = 'cron_' . $post_id;
                $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions';
                if (is_dir($log_dir)) {
                    $files = glob($log_dir . '/' . $session_id_prefix . '*.json');
                    if (!empty($files)) {
                        rsort($files);
                        $latest_file = basename($files[0], '.json');
                        $playground_url = admin_url('admin.php?page=aiutoma&session_id=' . urlencode($latest_file));
                        echo '<br><a href="' . esc_url($playground_url) . '" class="button button-small aiutoma-btn-session" style="margin-top:5px;" target="_blank">' . esc_html__('Session', 'aiutoma') . '</a>';
                    }
                }
                break;
        }
    }

    private function _get_all_automated_tasks()
    {
        $tasks = [];
        $query = new \WP_Query([
            'post_type' => 'aiutoma_task',
            'post_status' => 'any',
            'posts_per_page' => -1
        ]);

        foreach ($query->posts as $post) {
            $meta = get_post_meta($post->ID, '_aiutoma_task_data', true);
            if (is_array($meta)) {
                $tasks[$post->ID] = $meta;
                $tasks[$post->ID]['name'] = $post->post_title;
                $tasks[$post->ID]['prompt'] = $post->post_content;
                $tasks[$post->ID]['post_status'] = $post->post_status;
                $tasks[$post->ID]['post_author'] = $post->post_author;
            }
        }
        return $tasks;
    }

    private function _update_task_meta($post_id, $task_data)
    {
        // Don't duplicate title/content/status/author in meta
        $meta_data = $task_data;
        unset($meta_data['name']);
        unset($meta_data['prompt']);
        unset($meta_data['post_status']);
        unset($meta_data['post_author']);
        update_post_meta($post_id, '_aiutoma_task_data', $meta_data);
    }

    public function enqueue_automation_scripts($hook)
    {
        $is_cpt_page = (isset($_GET['post_type']) && $_GET['post_type'] === 'aiutoma_task') ||
            (isset($_GET['post']) && get_post_type($_GET['post']) === 'aiutoma_task');
        $is_valid_hook = in_array($hook, ['edit.php', 'post.php', 'post-new.php']);

        if (strpos($hook, 'aiutoma-automation') !== false || ($is_valid_hook && $is_cpt_page)) {
            wp_enqueue_style('aiutoma-select2');
            wp_enqueue_script('aiutoma-select2');
            wp_enqueue_style('aiutoma-automation-style', AIUTOMA_URL . 'modules/playground/assets/css/automation.css', [], filemtime(AIUTOMA_PATH . 'modules/playground/assets/css/automation.css'));
            wp_enqueue_script('aiutoma-automation-script', AIUTOMA_URL . 'modules/playground/assets/js/automation.js', ['jquery', 'aiutoma-select2'], filemtime(AIUTOMA_PATH . 'modules/playground/assets/js/automation.js'), true);

            wp_localize_script('aiutoma-automation-script', 'aiutomaAutomationData', [
                'restModelsUrl' => esc_url_raw(rest_url('aiutoma/v1/ai-models')),
                'restSaveUrl' => esc_url_raw(rest_url('aiutoma/v1/automation/save')),
                'restDeleteUrl' => esc_url_raw(rest_url('aiutoma/v1/automation/delete')),
                'restRunUrl' => esc_url_raw(rest_url('aiutoma/v1/automation/run-now')),
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        }
    }

    public function add_automation_menu()
    {
        add_submenu_page(
            null,
            __('Task Log', 'aiutoma'),
            __('Task Log', 'aiutoma'),
            'manage_options',
            'aiutoma-automation-log',
            [$this, 'aiutoma_automation_log_page_html']
        );
    }

    public function register_automation_routes()
    {

        register_rest_route('aiutoma/v1', '/automation/run-now', [
            'methods' => 'POST',
            'callback' => [$this, 'run_task_now'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function aiutoma_automation_log_page_html()
    {
        if (!current_user_can('manage_options')) return;
        // Handle log page
        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        $tasks = $this->_get_all_automated_tasks();

        if (empty($id) || !isset($tasks[$id])) {
            echo '<div class="wrap"><h1>' . esc_html__('Task Not Found', 'aiutoma') . '</h1></div>';
            return;
        }

        $task = $tasks[$id];
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Task History: ', 'aiutoma'); ?><?php echo esc_html($task['name']); ?></h1>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=aiutoma_task')); ?>" class="page-title-action">&larr; <?php esc_html_e('Back to Tasks', 'aiutoma'); ?></a>
            <a href="<?php echo esc_url(get_edit_post_link($id)); ?>" class="page-title-action"><?php esc_html_e('Edit Task', 'aiutoma'); ?></a>
            <hr class="wp-header-end">
            <div style="margin-top: 20px;">
                <div class="aiutoma-automation-log-box"><?php
                                                    $raw_log = $task['last_log'] ?? __('No log available yet.', 'aiutoma');

                                                    // Escape HTML first so JSON payloads with HTML tags don't render as actual DOM elements
                                                    $raw_log = esc_html($raw_log);

                                                    // Add a dashed separator and newlines before every timestamp (except the very first one)
                                                    $raw_log = preg_replace('/(?<!^)\s*\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', "\n<hr class=\"aiutoma-log-sep\">[$1]", $raw_log);

                                                    // Style the timestamps with emphasis
                                                    $raw_log = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="aiutoma-log-time">[$1]</span>', $raw_log);

                                                    // Use a custom KSES array to strictly allow our classes
                                                    $allowed_html = array_merge(wp_kses_allowed_html('post'), [
                                                        'hr' => ['class' => true],
                                                        'span' => ['class' => true]
                                                    ]);
                                                    echo wp_kses(trim($raw_log), $allowed_html);
                                                    ?></div>
            </div>
        </div>
<?php
    }



    public function run_task_now(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $this->_execute_task($id);
        return new \WP_REST_Response(['success' => true], 200);
    }

    private function _is_cron_due($expression, $last_run)
    {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($expression)));
        if (count($parts) !== 5) return false;

        $now = time();
        if ($now - $last_run < 50) return false;
        if (empty($last_run)) return true; // First time execution

        $match = function ($val, $current) {
            if ($val === '*') return true;
            if (strpos($val, '*/') === 0) {
                $div = intval(substr($val, 2));
                return $div > 0 && $current % $div === 0;
            }
            if (strpos($val, ',') !== false) {
                $arr = explode(',', $val);
                foreach ($arr as $v) {
                    if (is_numeric($v) && intval($v) === $current) return true;
                }
                return false;
            }
            if (is_numeric($val)) return intval($val) === $current;
            return false;
        };

        // Align last run to the start of the next minute
        $check_time = $last_run - ($last_run % 60) + 60;

        // Cap the check at max 30 days to prevent infinite loops if last_run is very old
        $max_time = min($now, $check_time + (30 * 86400));

        while ($check_time <= $max_time) {
            $c_min = intval(gmdate('i', $check_time));
            $c_hour = intval(gmdate('H', $check_time));
            $c_dom = intval(gmdate('d', $check_time));
            $c_month = intval(gmdate('m', $check_time));
            $c_dow = intval(gmdate('w', $check_time));

            if (
                $match($parts[0], $c_min) &&
                $match($parts[1], $c_hour) &&
                $match($parts[2], $c_dom) &&
                $match($parts[3], $c_month) &&
                $match($parts[4], $c_dow)
            ) {
                return true;
            }
            $check_time += 60;
        }

        return false;
    }

    private function _get_next_run_time($task)
    {
        if (empty($task['active'])) return false;

        $sched = $task['schedule'] ?? '';
        $last_run = $task['last_run'] ?? 0;
        $now = time();

        if ($sched === 'once') {
            $exec_time = $task['execute_time'] ?? 0;
            if ($exec_time > $now) return $exec_time;
            return false;
        }

        if (empty($last_run)) return $now;

        if ($sched === 'hourly') {
            return $last_run + 3600;
        }
        if ($sched === 'twicedaily') {
            return $last_run + 43200;
        }
        if ($sched === 'daily') {
            return $last_run + 86400;
        }

        if (!in_array($sched, ['hourly', 'twicedaily', 'daily', 'once'])) {
            $parts = explode(' ', preg_replace('/\s+/', ' ', trim($sched)));
            if (count($parts) !== 5) return false;

            $match = function ($val, $current) {
                if ($val === '*') return true;
                if (strpos($val, '*/') === 0) {
                    $div = intval(substr($val, 2));
                    return $div > 0 && $current % $div === 0;
                }
                if (strpos($val, ',') !== false) {
                    $arr = explode(',', $val);
                    foreach ($arr as $v) {
                        if (is_numeric($v) && intval($v) === $current) return true;
                    }
                    return false;
                }
                if (is_numeric($val)) return intval($val) === $current;
                return false;
            };

            $start = max($last_run + 60, $now);
            $check_time = $start - ($start % 60);
            $max_time = $check_time + (365 * 86400);

            while ($check_time <= $max_time) {
                $c_min = intval(gmdate('i', $check_time));
                $c_hour = intval(gmdate('H', $check_time));
                $c_dom = intval(gmdate('d', $check_time));
                $c_month = intval(gmdate('m', $check_time));
                $c_dow = intval(gmdate('w', $check_time));

                if (
                    $match($parts[0], $c_min) &&
                    $match($parts[1], $c_hour) &&
                    $match($parts[2], $c_dom) &&
                    $match($parts[3], $c_month) &&
                    $match($parts[4], $c_dow)
                ) {
                    return $check_time;
                }
                $check_time += 60;
            }
        }

        return false;
    }

    public function run_automated_tasks()
    {
        $tasks = $this->_get_all_automated_tasks();
        if (empty($tasks)) return;

        $now = time();

        $intervals = [
            'hourly' => 3600,
            'twicedaily' => 43200,
            'daily' => 86400,
            'weekly' => 604800
        ];

        foreach ($tasks as $id => $task) {
            if (empty($task['active'])) continue;
            if (isset($task['post_status']) && $task['post_status'] !== 'publish') continue;

            $should_run = false;

            // If it's currently running a long task, continue immediately
            if (!empty($task['running_conversation_id'])) {
                $should_run = true;
            } else {
                $sched = $task['schedule'];
                if ($sched === 'once') {
                    if (!empty($task['execute_time']) && $now >= $task['execute_time'] && empty($task['last_run'])) {
                        $should_run = true;
                    }
                } elseif (isset($intervals[$sched])) {
                    if ($now - $task['last_run'] >= $intervals[$sched]) {
                        $should_run = true;
                    }
                } else {
                    $should_run = $this->_is_cron_due($sched, $task['last_run']);
                }
            }

            if ($should_run) {
                $this->_execute_task($id, true);
            }
        }
    }

    public function append_to_task_log($task_id, $message)
    {
        $tasks = $this->_get_all_automated_tasks();
        if (isset($tasks[$task_id])) {
            $task = $tasks[$task_id];

            // Limit individual message length to prevent massive tool payloads from flooding the log
            if (strlen($message) > 1500) {
                $message = substr($message, 0, 1500) . "\n... [Message truncated due to length]";
            }

            $time = current_time('mysql');
            $log_entry = "[$time] $message\n";
            $current_log = $task['last_log'] ?? '';
            // Prevent overall log from growing infinitely (keep last 50000 chars)
            if (strlen($current_log) > 50000) {
                $current_log = substr($current_log, 0, 50000) . "... [Truncated]";
            }
            $task['last_log'] = $log_entry . $current_log;
            $this->_update_task_meta($task_id, $task);
        }
    }

    public function aiutoma_test_recovery($task_id, $log_entry)
    {
        $tasks = $this->_get_all_automated_tasks();
        if (isset($tasks[$task_id])) {
            $task = $tasks[$task_id];

            $task['is_running'] = false;
            $task['running_conversation_id'] = '';
            $task['running_action'] = '';

            $current_log = $task['last_log'] ?? '';
            if (strlen($current_log) > 50000) {
                $current_log = substr($current_log, 0, 50000) . "... [Truncated]";
            }
            $task['last_log'] = $log_entry . $current_log;
            $this->_update_task_meta($task_id, $task);
        }
    }

    private function _execute_task($task_id, $is_cron = false)
    {
        $tasks = $this->_get_all_automated_tasks();
        if (!isset($tasks[$task_id])) return false;

        $task = $tasks[$task_id];

        if (!class_exists('\WordPress\AiClient\AiClient')) return false;

        $author_id = !empty($task['post_author']) ? (int) $task['post_author'] : (int) get_post_field('post_author', $task_id);
        if (!$author_id) {
            $author_id = 1;
        }

        $author = get_userdata($author_id);
        if (!$author || !$author->has_cap('manage_options')) {
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Error: Task execution aborted. The task author is invalid or lacks administrative privileges (manage_options).\n" . ($tasks[$task_id]['last_log'] ?? '');
            $this->_update_task_meta($task_id, $tasks[$task_id]);
            return false;
        }

        // Establish the task author user context via WordPress core's determine_current_user hook
        $determine_user_callback = function ($user_id) use ($author_id) {
            return $author_id;
        };
        add_filter('determine_current_user', $determine_user_callback, 20);

        global $current_user;
        $prev_user = $current_user;
        $current_user = null;
        wp_get_current_user();

        try {

        // Crash detection
        $crashed_last_time = !empty($task['is_running']);

        // Lock the task
        $tasks[$task_id]['is_running'] = true;
        if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
            $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
        }
        $this->_update_task_meta($task_id, $tasks[$task_id]);

        $is_resuming = !empty($task['running_conversation_id']);
        $max_iterations = apply_filters('aiutoma_automation_max_iterations_per_tick', 3);
        $conversation_id = $is_resuming ? $task['running_conversation_id'] : 'cron_' . $task_id;

        $request = new \WP_REST_Request('POST', '/aiutoma/v1/ai-chat');
        $request->set_param('conversation_id', $conversation_id);
        if (!empty($task['model'])) {
            $request->set_param('model', $task['model']);
        }

        if (!$is_resuming) {
            $prompt = "CRON AUTOMATED TASK: " . $task['name'] . "\n\n" . $task['prompt'];
            if ($crashed_last_time) {
                $prompt .= "\n\n[SYSTEM NOTE: The previous execution encountered an error. Please use tools carefully to debug and resolve the issue.]";
            }
            $request->set_param('prompt', $prompt);
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Task Started.\n" . ($tasks[$task_id]['last_log'] ?? '');
            if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
            }
            $this->_update_task_meta($task_id, $tasks[$task_id]);
        } elseif (!empty($task['running_action']) && $task['running_action'] === 'tool_calls') {
            $request->set_param('execute_tools', true);
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Resuming task execution (tool calls)...\n" . ($tasks[$task_id]['last_log'] ?? '');
            if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
            }
            $this->_update_task_meta($task_id, $tasks[$task_id]);
        } elseif (!empty($task['running_action']) && $task['running_action'] === 'broken_site') {
            $request->set_param('prompt', "[CRITICAL ALERT]: Your last actions completed, but caused the entire website to return a 500 Fatal Error! Please review the changes you just made and fix the site immediately.");
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Resuming task execution (Recovery)...\n" . ($tasks[$task_id]['last_log'] ?? '');
            if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
            }
            $this->_update_task_meta($task_id, $tasks[$task_id]);
        }

        $request->set_param('is_cron', true);

        $response = $this->handle_chat_request($request);
        $iterations = 0;

        $is_done = false;
        $last_action = '';

        while ($iterations < $max_iterations && !is_wp_error($response) && $response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            $last_action = $data['action'] ?? '';

            if ($last_action === 'tool_calls') {
                $iterations++;
                if (!empty($data['tools'])) {
                    foreach ($data['tools'] as $tool) {
                        $tool_args = !empty($tool['args']) ? json_encode($tool['args']) : '';
                        $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Executing tool: " . $tool['name'] . (!empty($tool_args) ? " (Args: " . $tool_args . ")" : "") . "\n" . ($tasks[$task_id]['last_log'] ?? '');
                    }
                    if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                        $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
                    }
                    $this->_update_task_meta($task_id, $tasks[$task_id]);
                }

                if ($iterations >= $max_iterations) {
                    $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Yielding execution to next cron cycle to prevent timeouts...\n" . ($tasks[$task_id]['last_log'] ?? '');
                    if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                        $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
                    }
                    $this->_update_task_meta($task_id, $tasks[$task_id]);
                    break;
                }

                $exec_request = new \WP_REST_Request('POST', '/aiutoma/v1/ai-chat');
                $exec_request->set_param('conversation_id', $conversation_id);
                $exec_request->set_param('execute_tools', true);
                $exec_request->set_param('is_cron', true);

                $pause_setting = $task['pause_time'] ?? '2';
                if ($pause_setting === 'random') {
                    sleep(wp_rand(1, 5));
                } elseif (is_numeric($pause_setting) && intval($pause_setting) > 0) {
                    sleep(intval($pause_setting));
                }

                $response = $this->handle_chat_request($exec_request);
            } else {
                $is_done = true;
                break;
            }
        }

        $tasks = $this->_get_all_automated_tasks();

        if (is_wp_error($response)) {
            $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Error: " . $response->get_error_message() . "\n" . ($tasks[$task_id]['last_log'] ?? '');
        } else if ($response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            if (isset($data['success']) && $data['success'] === false) {
                $tasks[$task_id]['is_running'] = false;
                $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Error: " . ($data['message'] ?? 'Unknown API Error') . "\n" . ($tasks[$task_id]['last_log'] ?? '');
                if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                    $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
                }
                $this->_update_task_meta($task_id, $tasks[$task_id]);
            }
        }
        if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
            $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
        }
        $this->_update_task_meta($task_id, $tasks[$task_id]);

        $transient_data = get_transient($conversation_id);
        $has_error = false;
        $error_msg = '';
        if (is_wp_error($response)) {
            $has_error = true;
            $error_msg = $response->get_error_message();
        } else if ($response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            if (isset($data['success']) && $data['success'] === false) {
                $has_error = true;
                $error_msg = $data['message'] ?? 'Unknown API Error';
            }
        }

        if ($transient_data || $has_error) {
            $messages = [];
            if ($transient_data) {
                $json_data = json_decode($transient_data, true);
                if (is_array($json_data)) {
                    foreach ($json_data as $msg_array) {
                        if (is_array($msg_array) && isset($msg_array['role'])) {
                            $messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray($msg_array);
                        }
                    }
                }
            } else {
                if (class_exists('\WordPress\AiClient\Messages\DTO\UserMessage') && class_exists('\WordPress\AiClient\Messages\DTO\MessagePart')) {
                    $messages = [
                        new \WordPress\AiClient\Messages\DTO\UserMessage([
                            new \WordPress\AiClient\Messages\DTO\MessagePart($task['prompt'])
                        ])
                    ];
                }
            }

            if (is_array($messages)) {
                $html = '';
                $first_prompt = $task['prompt'];
                foreach ($messages as $msg) {
                    $role_obj = method_exists($msg, 'getRole') ? $msg->getRole() : '';
                    $role = (is_object($role_obj) && property_exists($role_obj, 'value')) ? $role_obj->value : (string) $role_obj;
                    $content = '';
                    if (method_exists($msg, 'getParts')) {
                        $parts = $msg->getParts();
                        foreach ($parts as $part) {
                            if (method_exists($part, 'getText') && (string)$part->getText() !== '') {
                                $content .= (string) $part->getText() . "\n";
                            } elseif (method_exists($part, 'getFunctionCall') && $part->getFunctionCall()) {
                                $func = $part->getFunctionCall();
                                $name = method_exists($func, 'getName') ? $func->getName() : '';
                                $args = method_exists($func, 'getArgs') ? json_encode($func->getArgs(), JSON_PRETTY_PRINT) : '';
                                $content .= "🛠️ **[Tool Execution]**: `$name`\n```json\n$args\n```\n";
                            } elseif (method_exists($part, 'getFunctionResponse') && $part->getFunctionResponse()) {
                                $resp = $part->getFunctionResponse();
                                $name = method_exists($resp, 'getName') ? $resp->getName() : '';
                                $res = method_exists($resp, 'getResponse') ? json_encode($resp->getResponse(), JSON_PRETTY_PRINT) : '';
                                $content .= "✅ **[Tool Response]**: `$name`\n```json\n$res\n```\n";
                            }
                        }
                    }

                    if ($role === 'user') {
                        $html .= '<div class="aiutoma-msg-user"><strong>You (Cron):</strong><br>' . nl2br(esc_html($content)) . '</div>';
                    } elseif ($role === 'model') {
                        $display_text = $content;
                        if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                            if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                                $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                            } else {
                                $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                            }
                            $display_text = $converter->convert($content)->getContent();
                        } else {
                            $display_text = nl2br(esc_html($content));
                        }
                        $html .= '<div class="aiutoma-msg-ai"><strong>AI:</strong><br>' . $display_text . '</div>';
                    } elseif ($role === 'tool' || $role === 'function') {
                        $html .= '<div class="aiutoma-msg-tool-result aiutoma-success"><strong>Tool Response:</strong><br><pre class="aiutoma-msg-sql-pre"><code>' . esc_html($content) . '</code></pre></div>';
                    }
                }

                if ($has_error) {
                    $html .= '<div class="aiutoma-msg-error"><strong>Error:</strong><br>' . esc_html($error_msg) . '</div>';
                }

                $upload_dir = wp_upload_dir();
                $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions';
                if (!is_dir($log_dir)) wp_mkdir_p($log_dir);
                $file_path = $log_dir . '/' . sanitize_file_name($conversation_id) . '.json';

                $sess_data = [
                    'is_full_state' => true,
                    'id' => sanitize_file_name($conversation_id),
                    'date' => current_time('mysql'),
                    'user' => 'Automated Task: ' . $task['name'],
                    'first_prompt' => $first_prompt,
                    'html' => $html,
                    'conversation_id' => $conversation_id,
                    'session_prompts' => [$first_prompt],
                    'raw_messages' => base64_encode(wp_json_encode($messages))
                ];
                file_put_contents($file_path, json_encode($sess_data));
            }
        }

        if (!$is_done && !is_wp_error($response) && $last_action === 'tool_calls') {
            $tasks[$task_id]['running_conversation_id'] = $conversation_id;
            $tasks[$task_id]['running_action'] = 'tool_calls';
            $tasks[$task_id]['is_running'] = false;
            if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
            }
            $this->_update_task_meta($task_id, $tasks[$task_id]);
        } else {

            $test_url = add_query_arg('aiutoma_test', time(), home_url());
            $test_response = wp_remote_get($test_url, ['timeout' => 5]);
            $is_error = is_wp_error($test_response);
            $code = wp_remote_retrieve_response_code($test_response);

            if ($is_error || $code >= 500) {
                do_action('aiutoma_site_error_detected');
                $tasks[$task_id]['running_conversation_id'] = $conversation_id;
                $tasks[$task_id]['running_action'] = 'broken_site';
                $tasks[$task_id]['is_running'] = false;
                $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] CRITICAL ERROR: The background task finished, but the website returned a 500 Error! Forcing an autonomous recovery iteration." . "\n" . ($tasks[$task_id]['last_log'] ?? '');
                if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                    $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
                }
                $this->_update_task_meta($task_id, $tasks[$task_id]);
            } else {
                do_action('aiutoma_site_healthy');
                unset($tasks[$task_id]['running_conversation_id']);
                unset($tasks[$task_id]['running_action']);
                $tasks[$task_id]['is_running'] = false; // Safely finished

                // Only update last_run if it fully completed cleanly
                $tasks[$task_id]['last_run'] = time();
                $tasks[$task_id]['run_count'] = ($tasks[$task_id]['run_count'] ?? 0) + 1;

                if ($tasks[$task_id]['schedule'] === 'once') {
                    $tasks[$task_id]['active'] = false;
                }

                if (!is_wp_error($response) && $response instanceof \WP_REST_Response) {
                    $data = $response->get_data();
                    if (!empty($data['response'])) {
                        $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Task Completed Successfully.\n" . wp_kses_post(is_string($data['response']) ? $data['response'] : (isset($data['response']['text']) ? $data['response']['text'] : json_encode($data['response']))) . "\n" . ($tasks[$task_id]['last_log'] ?? '');
                    } elseif (!empty($data['message'])) {
                        $tasks[$task_id]['last_log'] = "[" . current_time('mysql') . "] Task Completed.\n" . wp_kses_post($data['message']) . "\n" . ($tasks[$task_id]['last_log'] ?? '');
                    }
                }
                if (isset($tasks[$task_id]['last_log']) && strlen($tasks[$task_id]['last_log']) > 50000) {
                    $tasks[$task_id]['last_log'] = substr($tasks[$task_id]['last_log'], 0, 50000) . "\n[Truncated...]";
                }
                $this->_update_task_meta($task_id, $tasks[$task_id]);
            }
        }
    } finally {
        remove_filter('determine_current_user', $determine_user_callback, 20);
        global $current_user;
        $current_user = $prev_user;
    }
}
}
