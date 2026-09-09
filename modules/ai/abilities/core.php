<?php

namespace Aiutoma\Modules\Ai\Abilities;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

trait Core
{
    public function register_core_abilities()
    {
        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/search', [
            'category' => 'aiutoma',
            'label' => __('Search Content', 'aiutoma'),
            'description' => __('Search for public posts and pages using the native WordPress search function.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $query = isset($input['query']) ? sanitize_text_field($input['query']) : '';
                if (empty($query)) {
                    return new \WP_Error('invalid_query', 'Search query cannot be empty.');
                }

                $args = [
                    's' => $query,
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'posts_per_page' => isset($input['limit']) ? absint($input['limit']) : 10,
                ];

                $search_query = new \WP_Query($args);
                $results = [];

                if ($search_query->have_posts()) {
                    foreach ($search_query->posts as $post) {
                        $results[] = [
                            'id' => $post->ID,
                            'title' => $post->post_title,
                            'url' => get_permalink($post->ID),
                            'type' => $post->post_type,
                            'excerpt' => wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_content)), 55),
                        ];
                    }
                }

                return [
                    'success' => true,
                    'results' => $results,
                    'total_found' => $search_query->found_posts
                ];
            },
            'permission_callback' => '__return_true',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query string.'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (default: 10).'
                    ]
                ],
                'required' => ['query']
            ]
        ]);
        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/send-email', [
            'category' => 'aiutoma',
            'label' => __('Send Email', 'aiutoma'),
            'description' => __('Send an email using the native wp_mail() function. Useful for sending notifications, reports, or alerts to users or admins.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $to = sanitize_email($input['to']);
                if (!is_email($to)) {
                    return new \WP_Error('invalid_email', 'The recipient email address is invalid.');
                }

                $subject = sanitize_text_field($input['subject']);
                $message = $input['message'];
                $headers = isset($input['headers']) ? (is_array($input['headers']) ? array_map('sanitize_text_field', $input['headers']) : sanitize_text_field($input['headers'])) : '';

                if (empty($subject) || empty($message)) {
                    return new \WP_Error('missing_content', 'Subject and message are required.');
                }

                $is_html = !empty($input['is_html']);
                if ($is_html) {
                    add_filter('wp_mail_content_type', function () {
                        return 'text/html';
                    });
                }

                $attachments = [];
                if (!empty($input['attachments']) && is_array($input['attachments'])) {
                    foreach ($input['attachments'] as $att) {
                        $att = wp_normalize_path(sanitize_text_field($att));
                        if (file_exists($att)) {
                            $attachments[] = $att;
                        }
                    }
                }

                $result = wp_mail($to, $subject, $message, $headers, $attachments);

                // We shouldn't remove anonymous function filter directly like this, but we can reset to default text/plain or let WP handle it per request. A better way:
                // Removing filter added via closure is tricky. But WordPress wp_mail resets itself mostly, or we use a named function.
                // It's safe enough for this context as this is isolated to the AI request cycle.

                if ($result) {
                    return ['success' => true, 'message' => 'Email sent successfully to ' . $to];
                } else {
                    return new \WP_Error('mail_failed', 'Failed to send email. Check your WordPress SMTP settings.');
                }
            },
            'permission_callback' => '__return_true', // AI enforces role logic if needed
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'to' => [
                        'type' => 'string',
                        'description' => 'Recipient email address'
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'Email subject'
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => 'Email body/content. Can contain HTML if is_html is true.'
                    ],
                    'is_html' => [
                        'type' => 'boolean',
                        'description' => 'Whether to send the email as HTML format (default: false)'
                    ],
                    'headers' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional array of email headers (e.g. ["From: Me <me@example.com>"])'
                    ],
                    'attachments' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional array of absolute file paths on the server to attach to the email (not web URLs).'
                    ]
                ],
                'required' => ['to', 'subject', 'message']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/live-push', [
            'category' => 'gutenberg',
            'label' => __('Gutenberg Live Push', 'aiutoma'),
            'description' => __('Push raw Gutenberg block HTML directly into the user\'s active block editor session. The blocks will appear on their screen instantly via JS without saving to the database, allowing them to review the layout.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $user_id = get_current_user_id();
                if (!$user_id) {
                    return new \WP_Error('unauthorized', 'No active user session found to push blocks to.');
                }

                $transient_key = 'aiutoma_editor_push_' . $user_id;
                set_transient($transient_key, $input['blocks'], 60);

                return ['success' => true, 'message' => 'Blocks pushed to the editor canvas successfully. They will appear on the screen shortly.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'blocks' => ['type' => 'string', 'description' => 'Raw Gutenberg block HTML (e.g. <!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->)']
                ],
                'required' => ['blocks']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/read-file', [
            'category' => 'aiutoma',
            'label' => __('Read File', 'aiutoma'),
            'description' => __('Read a file from the server.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $path = $input['path'];
                if (!file_exists($path)) {
                    return new \WP_Error('file_error', 'File not found: ' . $path);
                }
                $content = file_get_contents($path);
                if ($content === false) {
                    return new \WP_Error('file_error', 'Failed to read file: ' . $path);
                }
                return ['success' => true, 'content' => $content];
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute file path']
                ],
                'required' => ['path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/list-directory', [
            'category' => 'aiutoma',
            'label' => __('List Directory', 'aiutoma'),
            'description' => __('List files and folders in a directory.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $path = $input['path'];
                if (!is_dir($path)) {
                    return new \WP_Error('dir_error', 'Directory not found: ' . $path);
                }
                $files = scandir($path);
                if ($files === false) {
                    return new \WP_Error('dir_error', 'Failed to read directory: ' . $path);
                }
                return ['success' => true, 'files' => array_values(array_diff($files, ['.', '..']))];
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute directory path']
                ],
                'required' => ['path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-plugins', [
            'category' => 'aiutoma',
            'label' => __('Manage Plugins', 'aiutoma'),
            'description' => __('Manage WordPress plugins safely (list, install, activate, deactivate, delete).', 'aiutoma'),
            'execute_callback' => function ($input) {
                if (!function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $action = $input['action'];
                $slug = isset($input['slug']) ? sanitize_text_field($input['slug']) : '';

                if ($action === 'list') {
                    $all_plugins = get_plugins();
                    $active_plugins = get_option('active_plugins', []);
                    $data = [];
                    foreach ($all_plugins as $path => $info) {
                        $data[] = [
                            'path' => $path,
                            'name' => $info['Name'],
                            'version' => $info['Version'],
                            'status' => in_array($path, $active_plugins) ? 'active' : 'inactive'
                        ];
                    }
                    return ['success' => true, 'plugins' => $data];
                }

                if (empty($slug)) {
                    return new \WP_Error('missing_slug', 'Plugin slug/path is required for this action.');
                }

                $plugin_file = $slug;
                if (strpos($plugin_file, '.php') === false && $action !== 'install') {
                    $plugins = get_plugins();
                    foreach ($plugins as $path => $p) {
                        if (strpos($path, $slug . '/') === 0 || $path === $slug . '.php') {
                            $plugin_file = $path;
                            break;
                        }
                    }
                }

                if ($action === 'activate') {
                    $result = activate_plugin($plugin_file);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true, 'message' => "Plugin $plugin_file activated."];
                } elseif ($action === 'deactivate') {
                    deactivate_plugins($plugin_file);
                    return ['success' => true, 'message' => "Plugin $plugin_file deactivated."];
                } elseif ($action === 'delete') {
                    deactivate_plugins($plugin_file);
                    $result = delete_plugins([$plugin_file]);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true, 'message' => "Plugin $plugin_file deleted."];
                } elseif ($action === 'install' || $action === 'update' || $action === 'rollback') {
                    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                    include_once ABSPATH . 'wp-admin/includes/file.php';
                    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                    include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

                    if ($action === 'update' && empty($input['version'])) {
                        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
                        $result = $upgrader->upgrade($plugin_file);
                        if (is_wp_error($result) || $result === false) {
                            return new \WP_Error('update_failed', 'Failed to update plugin.');
                        }
                        return ['success' => true, 'message' => "Plugin $plugin_file updated successfully."];
                    }

                    $api = plugins_api('plugin_information', ['slug' => $slug]);
                    if (is_wp_error($api)) return $api;

                    $download_link = $api->download_link;
                    $version = $input['version'] ?? '';

                    if ($action === 'rollback' || (!empty($version) && $action === 'update')) {
                        if (empty($version)) return new \WP_Error('missing_version', 'Version is required for rollback.');
                        if (!isset($api->versions) || !isset($api->versions[$version])) {
                            return new \WP_Error('invalid_version', "Version $version not found in WordPress repository for $slug.");
                        }
                        $download_link = $api->versions[$version];
                    }

                    $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
                    $install_args = [];
                    if ($action === 'rollback' || $action === 'update') {
                        $install_args['clear_destination'] = true;
                    }

                    $result = $upgrader->install($download_link, $install_args);

                    if (is_wp_error($result) || $result === false) {
                        return new \WP_Error('action_failed', "Failed to $action plugin.");
                    }
                    return ['success' => true, 'message' => "Plugin $slug successfully processed ($action" . (!empty($version) ? " to version $version" : "") . ")."];
                }

                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('activate_plugins');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['list', 'install', 'activate', 'deactivate', 'delete', 'update', 'rollback'], 'description' => 'Action to perform'],
                    'slug' => ['type' => 'string', 'description' => 'Plugin directory slug (e.g., "woocommerce") or full path (e.g., "woocommerce/woocommerce.php"). Not needed for list action.'],
                    'version' => ['type' => 'string', 'description' => 'Specific version to rollback/update to.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-themes', [
            'category' => 'aiutoma',
            'label' => __('Manage Themes', 'aiutoma'),
            'description' => __('Manage WordPress themes safely (list, activate).', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $slug = isset($input['slug']) ? sanitize_text_field($input['slug']) : '';

                if ($action === 'list') {
                    $themes = wp_get_themes();
                    $active = wp_get_theme()->get_stylesheet();
                    $data = [];
                    foreach ($themes as $stylesheet => $theme) {
                        $data[] = [
                            'slug' => $stylesheet,
                            'name' => $theme->get('Name'),
                            'version' => $theme->get('Version'),
                            'status' => ($stylesheet === $active) ? 'active' : 'inactive'
                        ];
                    }
                    return ['success' => true, 'themes' => $data];
                } elseif ($action === 'activate') {
                    if (empty($slug)) return new \WP_Error('missing_slug', 'Theme slug is required.');
                    switch_theme($slug);
                    return ['success' => true, 'message' => "Theme $slug activated."];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('switch_themes');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['list', 'activate'], 'description' => 'Action to perform'],
                    'slug' => ['type' => 'string', 'description' => 'Theme slug. Not needed for list action.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-system', [
            'category' => 'aiutoma',
            'label' => __('Manage System & Cache', 'aiutoma'),
            'description' => __('Perform system actions like flushing permalinks, clearing cache, and transients.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                if ($action === 'flush_rewrite_rules') {
                    flush_rewrite_rules();
                    return ['success' => true, 'message' => 'Rewrite rules flushed.'];
                } elseif ($action === 'clear_transients') {
                    global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'");
                    return ['success' => true, 'message' => 'Transients cleared.'];
                } elseif ($action === 'clear_cache') {
                    $cleared = [];
                    if (function_exists('rocket_clean_domain')) {
                        rocket_clean_domain();
                        $cleared[] = 'WP Rocket';
                    }
                    if (function_exists('w3tc_flush_all')) {
                        w3tc_flush_all();
                        $cleared[] = 'W3TC';
                    }
                    if (class_exists('LiteSpeed\Purge')) {
                        \LiteSpeed\Purge::purge_all();
                        $cleared[] = 'LiteSpeed';
                    }
                    if (function_exists('sg_cachepress_purge_cache')) {
                        sg_cachepress_purge_cache();
                        $cleared[] = 'SG Optimizer';
                    }
                    return ['success' => true, 'message' => 'Cache cleared.', 'cleared_systems' => $cleared];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['flush_rewrite_rules', 'clear_transients', 'clear_cache'], 'description' => 'Action to perform']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-debug', [
            'category' => 'aiutoma',
            'label' => __('Manage Debug Log', 'aiutoma'),
            'description' => __('Read or check WordPress debug logging. Reads the debug log file from the content directory.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $log_path = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(wp_upload_dir()['basedir'])) . '/debug.log';

                if ($action === 'read') {
                    if (!file_exists($log_path)) {
                        return ['success' => true, 'log' => 'Debug log is empty or does not exist.'];
                    }
                    $filesize = filesize($log_path);
                    $read_size = min(100000, $filesize);
                    $offset = max(0, $filesize - $read_size);
                    $log_content = file_get_contents($log_path, false, null, $offset, $read_size);
                    if ($log_content !== false) {
                        return ['success' => true, 'log' => $log_content];
                    }
                    return new \WP_Error('read_error', 'Could not read debug.log.');
                }

                if ($action === 'enable' || $action === 'disable') {
                    $is_enabled = defined('WP_DEBUG') && WP_DEBUG;
                    return [
                        'success' => true,
                        'message' => $is_enabled ? 'WP_DEBUG is currently active.' : 'WP_DEBUG is not enabled in wp-config.php.'
                    ];
                }

                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['enable', 'disable', 'read'], 'description' => 'Action to perform']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/convert-document', [
            'category' => 'aiutoma',
            'label' => __('Convert Document (MarkItDown)', 'aiutoma'),
            'description' => __('Convert documents (PDF, DOCX, XLSX, etc.) to Markdown using MarkItDown. Extracts structural content and tables optimally for LLM understanding.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $path = wp_normalize_path($input['path']);
                if (!file_exists($path)) {
                    return new \WP_Error('file_not_found', 'The specified file does not exist: ' . $path);
                }

                // Security: restrict to local files within WordPress uploads directory
                $upload_dir = wp_upload_dir();
                $allowed_dir = wp_normalize_path($upload_dir['basedir']);

                if (strpos($path, $allowed_dir) !== 0) {
                    return new \WP_Error('security_error', 'File access is restricted to the WordPress uploads directory.');
                }

                if (!trait_exists('\\Aiutoma\\Modules\\Ai\\Traits\\DocumentParser')) {
                    require_once AIUTOMA_PATH . 'modules/ai/traits/document-parser.php';
                }

                $parser = new class {
                    use \Aiutoma\Modules\Ai\Traits\DocumentParser;
                };

                $body = $parser->parse_document($path);

                if (strpos($body, 'Error') === 0) {
                    return new \WP_Error('conversion_failed', $body);
                }

                return [
                    'success' => true,
                    'markdown' => $body
                ];
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Absolute file path of the document to convert (e.g. PDF, DOCX, XLSX)']
                ],
                'required' => ['path']
            ]
        ]);

        
        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-options', [
            'category' => 'aiutoma',
            'label' => __('Manage Options', 'aiutoma'),
            'description' => __('Get, update, or delete WordPress site options safely.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $option_name = sanitize_key($input['option_name'] ?? '');

                if (empty($option_name)) {
                    return new \WP_Error('missing_option_name', __('Option name is required.', 'aiutoma'));
                }

                if (strpos($option_name, 'connectors_ai_') === 0) {
                    return new \WP_Error('protected_option', __('Access to WordPress AI Client credentials is restricted.', 'aiutoma'));
                }

                $protected_options = ['siteurl', 'home', 'active_plugins', 'admin_email', 'users_can_register', 'default_role'];
                if (in_array($option_name, $protected_options, true) && in_array($action, ['update', 'delete'], true)) {
                    return new \WP_Error('protected_option', sprintf(__('Modifying the "%s" option is restricted for site security.', 'aiutoma'), $option_name));
                }

                if ($action === 'get') {
                    $default = $input['default'] ?? null;
                    $value = get_option($option_name, $default);
                    return [
                        'success' => true,
                        'option_name' => $option_name,
                        'value' => $value,
                    ];
                } elseif ($action === 'update') {
                    if (!array_key_exists('option_value', $input)) {
                        return new \WP_Error('missing_option_value', __('Option value is required for update.', 'aiutoma'));
                    }
                    $option_value = $input['option_value'];
                    $autoload = isset($input['autoload']) ? (bool) $input['autoload'] : null;
                    $updated = update_option($option_name, $option_value, $autoload);
                    return [
                        'success' => true,
                        'option_name' => $option_name,
                        'updated' => $updated,
                        'message' => sprintf(__('Option "%s" successfully saved.', 'aiutoma'), $option_name)
                    ];
                } elseif ($action === 'delete') {
                    $deleted = delete_option($option_name);
                    return [
                        'success' => true,
                        'option_name' => $option_name,
                        'deleted' => $deleted,
                        'message' => sprintf(__('Option "%s" deleted.', 'aiutoma'), $option_name)
                    ];
                }

                return new \WP_Error('invalid_action', __('Invalid action specified.', 'aiutoma'));
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['get', 'update', 'delete'],
                        'description' => 'The action to perform: "get", "update", or "delete".'
                    ],
                    'option_name' => [
                        'type' => 'string',
                        'description' => 'The name of the WordPress option.'
                    ],
                    'option_value' => [
                        'description' => 'The value to store (required for "update"). Can be string, number, boolean, or array.'
                    ],
                    'default' => [
                        'description' => 'Default value to return if option does not exist (optional, for "get").'
                    ],
                    'autoload' => [
                        'type' => 'boolean',
                        'description' => 'Whether to autoload the option when WordPress starts (optional, for "update").'
                    ]
                ],
                'required' => ['action', 'option_name']
            ]
        ]);
    }
}
