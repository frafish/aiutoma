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
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
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
            'description' => __('Send an email using the native wp_mail() function. Supports single or multiple recipients, CC/BCC, HTML or plain text, custom headers, and attachments (local filesystem paths or WordPress media URLs).', 'aiutoma'),
            'execute_callback' => function ($input) {
                $parse_single_email = function ($entry) {
                    $entry = trim((string)$entry);
                    if (empty($entry)) {
                        return null;
                    }
                    if (preg_match('/^(.*?)\s*<([^>]+)>$/', $entry, $matches)) {
                        $name = trim($matches[1]);
                        $email = sanitize_email($matches[2]);
                        if (is_email($email)) {
                            return !empty($name) ? sprintf('%s <%s>', sanitize_text_field($name), $email) : $email;
                        }
                    } elseif (is_email($entry)) {
                        return sanitize_email($entry);
                    }
                    return null;
                };

                $parse_email_list = function ($raw) use ($parse_single_email) {
                    $results = [];
                    if (is_array($raw)) {
                        foreach ($raw as $item) {
                            $parsed = $parse_single_email($item);
                            if ($parsed) {
                                $results[] = $parsed;
                            }
                        }
                    } elseif (is_string($raw)) {
                        $parts = explode(',', $raw);
                        foreach ($parts as $part) {
                            $parsed = $parse_single_email($part);
                            if ($parsed) {
                                $results[] = $parsed;
                            }
                        }
                    }
                    return array_values(array_unique($results));
                };

                $to_list = $parse_email_list($input['to'] ?? '');
                if (empty($to_list)) {
                    return new \WP_Error('invalid_email', __('At least one valid recipient email address is required in "to".', 'aiutoma'));
                }

                $subject = sanitize_text_field($input['subject'] ?? '');
                $message = (string)($input['message'] ?? '');

                if (empty($subject) || empty($message)) {
                    return new \WP_Error('missing_content', __('Both subject and message are required.', 'aiutoma'));
                }

                $headers_list = [];

                // HTML content type via header to avoid leaking global wp_mail_content_type filter
                $is_html = !isset($input['is_html']) || (bool)$input['is_html'];
                if ($is_html) {
                    $headers_list[] = 'Content-Type: text/html; charset=UTF-8';
                } else {
                    $headers_list[] = 'Content-Type: text/plain; charset=UTF-8';
                }

                // From headers
                if (!empty($input['from_email']) && is_email($input['from_email'])) {
                    $from_name = !empty($input['from_name']) ? sanitize_text_field($input['from_name']) : '';
                    $from_email = sanitize_email($input['from_email']);
                    $headers_list[] = !empty($from_name) ? sprintf('From: %s <%s>', $from_name, $from_email) : sprintf('From: %s', $from_email);
                }

                // Reply-To
                if (!empty($input['reply_to'])) {
                    $reply_parsed = $parse_single_email($input['reply_to']);
                    if ($reply_parsed) {
                        $headers_list[] = 'Reply-To: ' . $reply_parsed;
                    }
                }

                // CC
                if (!empty($input['cc'])) {
                    $cc_list = $parse_email_list($input['cc']);
                    foreach ($cc_list as $cc) {
                        $headers_list[] = 'Cc: ' . $cc;
                    }
                }

                // BCC
                if (!empty($input['bcc'])) {
                    $bcc_list = $parse_email_list($input['bcc']);
                    foreach ($bcc_list as $bcc) {
                        $headers_list[] = 'Bcc: ' . $bcc;
                    }
                }

                // Custom headers
                if (!empty($input['headers']) && is_array($input['headers'])) {
                    foreach ($input['headers'] as $header_item) {
                        $clean_header = sanitize_text_field($header_item);
                        if (!empty($clean_header)) {
                            $headers_list[] = $clean_header;
                        }
                    }
                }

                // Resolve attachments
                $resolved_attachments = [];
                if (!empty($input['attachments']) && is_array($input['attachments'])) {
                    $upload_dir = wp_upload_dir();
                    $upload_baseurl = $upload_dir['baseurl'];
                    $upload_basedir = wp_normalize_path($upload_dir['basedir']);
                    $site_url = site_url();
                    $abspath_normalized = wp_normalize_path(ABSPATH);
                    $temp_dir_normalized = wp_normalize_path(get_temp_dir());

                    foreach ($input['attachments'] as $att) {
                        $att = trim((string)$att);
                        if (empty($att)) {
                            continue;
                        }

                        $file_path = null;

                        // Check if URL pointing to WordPress uploads
                        if (preg_match('/^https?:\/\//i', $att)) {
                            if (strpos($att, $upload_baseurl) === 0) {
                                $relative_upload = substr($att, strlen($upload_baseurl));
                                $file_path = $upload_basedir . $relative_upload;
                            } elseif (strpos($att, $site_url) === 0) {
                                $relative_site = substr($att, strlen($site_url));
                                $file_path = $abspath_normalized . ltrim($relative_site, '/');
                            }
                        } else {
                            $normalized = wp_normalize_path($att);
                            if (file_exists($normalized)) {
                                $file_path = $normalized;
                            } elseif (file_exists($abspath_normalized . ltrim($normalized, '/'))) {
                                $file_path = $abspath_normalized . ltrim($normalized, '/');
                            }
                        }

                        if ($file_path) {
                            $real = realpath($file_path);
                            if ($real && file_exists($real) && is_readable($real)) {
                                $real_normalized = wp_normalize_path($real);
                                // Security: ensure file resides inside WordPress or system temp, and is not wp-config.php
                                $is_allowed = (strpos($real_normalized, $abspath_normalized) === 0 || strpos($real_normalized, $temp_dir_normalized) === 0);
                                if ($is_allowed && !preg_match('/wp-config\.php$|\.env$/i', $real_normalized)) {
                                    $resolved_attachments[] = $real_normalized;
                                }
                            }
                        }
                    }
                }

                // Capture wp_mail_failed action for exact diagnostic error messages
                $mail_error = null;
                $error_capturer = function (\WP_Error $error) use (&$mail_error) {
                    $mail_error = $error;
                };
                add_action('wp_mail_failed', $error_capturer);

                $recipient_arg = count($to_list) === 1 ? $to_list[0] : $to_list;
                $sent = wp_mail($recipient_arg, $subject, $message, $headers_list, $resolved_attachments);

                remove_action('wp_mail_failed', $error_capturer);

                if ($sent) {
                    return [
                        'success' => true,
                        'message' => sprintf(__('Email successfully dispatched to: %s', 'aiutoma'), implode(', ', $to_list)),
                        'recipients' => $to_list,
                        'attachments_count' => count($resolved_attachments),
                        'attachments' => array_map('basename', $resolved_attachments)
                    ];
                } else {
                    $error_msg = $mail_error instanceof \WP_Error ? $mail_error->get_error_message() : __('Failed to send email. Check your WordPress SMTP or mail server configuration.', 'aiutoma');
                    return new \WP_Error('mail_failed', $error_msg, $mail_error instanceof \WP_Error ? $mail_error->get_error_data() : null);
                }
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'to' => [
                        'description' => 'Recipient email address(es). Accepts a single email string, an array of emails, or a comma-separated list. Formats like "Name <email@example.com>" are supported.',
                        'anyOf' => [
                            ['type' => 'string'],
                            ['type' => 'array', 'items' => ['type' => 'string']]
                        ]
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'Email subject line.'
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => 'Email body content. Supports HTML markup (default) or plain text.'
                    ],
                    'is_html' => [
                        'type' => 'boolean',
                        'description' => 'Whether to send email as HTML format. Defaults to true.'
                    ],
                    'cc' => [
                        'description' => 'Optional CC recipient(s). Accepts email string or array of emails.',
                        'anyOf' => [
                            ['type' => 'string'],
                            ['type' => 'array', 'items' => ['type' => 'string']]
                        ]
                    ],
                    'bcc' => [
                        'description' => 'Optional BCC recipient(s). Accepts email string or array of emails.',
                        'anyOf' => [
                            ['type' => 'string'],
                            ['type' => 'array', 'items' => ['type' => 'string']]
                        ]
                    ],
                    'reply_to' => [
                        'type' => 'string',
                        'description' => 'Optional Reply-To email address.'
                    ],
                    'from_name' => [
                        'type' => 'string',
                        'description' => 'Optional custom sender display name.'
                    ],
                    'from_email' => [
                        'type' => 'string',
                        'description' => 'Optional custom sender email address.'
                    ],
                    'headers' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional raw custom headers (e.g. ["X-Custom-Header: value"]).'
                    ],
                    'attachments' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional array of file paths or WordPress media/upload URLs to attach to the email.'
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
                    /* translators: %s: option name */
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
                        /* translators: %s: option name */
                        'message' => sprintf(__('Option "%s" successfully saved.', 'aiutoma'), $option_name)
                    ];
                } elseif ($action === 'delete') {
                    $deleted = delete_option($option_name);
                    return [
                        'success' => true,
                        'option_name' => $option_name,
                        'deleted' => $deleted,
                        /* translators: %s: option name */
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

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/abilities', [
            'category' => 'aiutoma',
            'label' => __('System Abilities & Tools', 'aiutoma'),
            'description' => __('Discover, inspect, and execute any registered WordPress ability on-demand. Use action "list" to discover available abilities, action "get" to inspect parameter schemas, or action "execute" to run an ability with parameters.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = isset($input['action']) ? sanitize_key($input['action']) : 'list';
                $all_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
                $all_abilities = apply_filters('aiutoma/abilities', $all_abilities);

                // Helper to resolve an ability by name or normalized slug
                $resolve_ability = function ($name) use ($all_abilities) {
                    if (empty($name) || !is_string($name)) return null;
                    if (function_exists('wp_get_ability')) {
                        $ab = wp_get_ability($name);
                        if ($ab) return $ab;
                    }
                    $clean = str_replace('_', '-', $name);
                    foreach ($all_abilities as $ab) {
                        $ab_name = method_exists($ab, 'get_name') ? $ab->get_name() : '';
                        if ($ab_name === $name || str_replace('/', '-', $ab_name) === $clean || str_replace('/', '_', $ab_name) === $name) {
                            return $ab;
                        }
                    }
                    return null;
                };

                // Action: LIST
                if ($action === 'list') {
                    $category_filter = isset($input['category']) ? sanitize_text_field($input['category']) : '';
                    $search_filter = isset($input['search']) ? trim(sanitize_text_field($input['search'])) : '';
                    
                    // If no filter is specified and registry is large, return a compact categorized index to save tokens
                    $is_unfiltered = empty($category_filter) && empty($search_filter);
                    if ($is_unfiltered && count($all_abilities) > 25) {
                        $categories_map = [];
                        foreach ($all_abilities as $ability) {
                            $name = method_exists($ability, 'get_name') ? $ability->get_name() : '';
                            if (empty($name)) continue;
                            $meta = method_exists($ability, 'get_meta') ? $ability->get_meta() : [];
                            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) continue;
                            $parts = explode('/', $name);
                            $ns = count($parts) >= 2 ? $parts[0] : 'other';
                            if (!isset($categories_map[$ns])) {
                                $categories_map[$ns] = [];
                            }
                            $categories_map[$ns][] = $name;
                        }
                        return [
                            'success' => true,
                            'total' => count($all_abilities),
                            'categories' => $categories_map,
                            'usage_hint' => 'To keep token usage minimal, call action "list" with "search" (e.g. "site", "post", "template") or "category" (e.g. "core", "gutenberg", "aiutoma") to inspect specific abilities, or call action "get" / "execute" directly with the ability name.',
                        ];
                    }

                    $list = [];

                    foreach ($all_abilities as $ability) {
                        $name = method_exists($ability, 'get_name') ? $ability->get_name() : '';
                        if (empty($name)) continue;

                        $meta = method_exists($ability, 'get_meta') ? $ability->get_meta() : [];
                        if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                            continue;
                        }

                        $parts = explode('/', $name);
                        $ns = count($parts) >= 2 ? $parts[0] : 'other';

                        if (!empty($category_filter) && strcasecmp($ns, $category_filter) !== 0) {
                            continue;
                        }

                        $label = method_exists($ability, 'get_label') ? $ability->get_label() : $name;
                        $desc = method_exists($ability, 'get_description') ? $ability->get_description() : '';

                        if (!empty($search_filter)) {
                            $haystack = $name . ' ' . $label . ' ' . $desc;
                            if (stripos($haystack, $search_filter) === false) {
                                continue;
                            }
                        }

                        $list[] = [
                            'name' => $name,
                            'label' => $label,
                            'category' => $ns,
                            'description' => (mb_strlen($desc) > 120 ? mb_substr($desc, 0, 117) . '...' : $desc),
                        ];
                    }

                    return [
                        'success' => true,
                        'total' => count($list),
                        'abilities' => $list,
                        'usage_hint' => 'Call action "get" with "ability_name" to inspect parameter requirements, or action "execute" with "ability_name" and "ability_input" to run it.',
                    ];
                }

                // Action: GET (schema)
                if ($action === 'get') {
                    $ability_name = isset($input['ability_name']) ? sanitize_text_field($input['ability_name']) : '';
                    if (empty($ability_name)) {
                        return new \WP_Error('missing_ability_name', __('Parameter "ability_name" is required for action "get".', 'aiutoma'));
                    }

                    $ability = $resolve_ability($ability_name);
                    if (!$ability) {
                        /* translators: %s: ability name */
                        return new \WP_Error('ability_not_found', sprintf(__('Ability "%s" not found. Call action "list" to view all registered abilities.', 'aiutoma'), $ability_name));
                    }

                    $input_schema = method_exists($ability, 'get_input_schema') ? $ability->get_input_schema() : ['type' => 'object', 'properties' => new \stdClass()];
                    
                    // Expose variable support in woocommerce/products-query schema to the AI
                    if ($ability_name === 'woocommerce/products-query' && is_array($input_schema) && isset($input_schema['properties']['product_type_alias'])) {
                        if (isset($input_schema['properties']['product_type_alias']['enum']) && is_array($input_schema['properties']['product_type_alias']['enum'])) {
                            if (!in_array('variable', $input_schema['properties']['product_type_alias']['enum'])) {
                                $input_schema['properties']['product_type_alias']['enum'][] = 'variable';
                            }
                        }
                        if (isset($input_schema['properties']['product_type_alias']['description'])) {
                            $input_schema['properties']['product_type_alias']['description'] .= ' (Also supports "variable" for variable products).';
                        }
                    }

                    return [
                        'success' => true,
                        'name' => $ability->get_name(),
                        'label' => method_exists($ability, 'get_label') ? $ability->get_label() : $ability->get_name(),
                        'description' => method_exists($ability, 'get_description') ? $ability->get_description() : '',
                        'input_schema' => $input_schema,
                    ];
                }

                // Action: EXECUTE
                if ($action === 'execute') {
                    $ability_name = isset($input['ability_name']) ? sanitize_text_field($input['ability_name']) : '';
                    if (empty($ability_name)) {
                        return new \WP_Error('missing_ability_name', __('Parameter "ability_name" is required for action "execute".', 'aiutoma'));
                    }

                    if ($ability_name === 'aiutoma/abilities') {
                        return new \WP_Error('invalid_target', __('Cannot execute aiutoma/abilities within itself.', 'aiutoma'));
                    }

                    $ability = $resolve_ability($ability_name);
                    if (!$ability) {
                        /* translators: %s: ability name */
                        return new \WP_Error('ability_not_found', sprintf(__('Ability "%s" not found.', 'aiutoma'), $ability_name));
                    }

                    $ability_input = isset($input['ability_input']) && is_array($input['ability_input']) ? $input['ability_input'] : [];

                    // Strictly enforce target ability permission callback (fail-closed / deny by default)
                    $permission_verified = false;
                    if (method_exists($ability, 'check_permissions')) {
                        $has_permission = $ability->check_permissions($ability_input);
                        if (is_wp_error($has_permission)) {
                            return $has_permission;
                        }
                        if (true === $has_permission) {
                            $permission_verified = true;
                        }
                    } elseif (method_exists($ability, 'get_permission_callback')) {
                        $perm_cb = $ability->get_permission_callback();
                        if (is_callable($perm_cb)) {
                            $has_permission = call_user_func($perm_cb, $ability_input);
                            if (is_wp_error($has_permission)) {
                                return $has_permission;
                            }
                            if (true === $has_permission) {
                                $permission_verified = true;
                            }
                        }
                    }

                    if (!$permission_verified) {
                        /* translators: %s: ability name */
                        return new \WP_Error('forbidden', sprintf(__('Permission denied for ability "%s".', 'aiutoma'), $ability_name));
                    }

                    // Direct native handling for variable products query (bypasses WooCommerce core enum limitation)
                    if ($ability_name === 'woocommerce/products-query') {
                        $p_type = $ability_input['product_type_alias'] ?? ($ability_input['type'] ?? '');
                        if ($p_type === 'variable' && function_exists('wc_get_products')) {
                            $page = (int)($ability_input['page'] ?? 1);
                            $per_page = (int)($ability_input['per_page'] ?? 10);
                            $wc_args = [
                                'type' => 'variable',
                                'limit' => $per_page,
                                'page' => $page,
                                'paginate' => true,
                                'return' => 'objects',
                            ];
                            if (!empty($ability_input['status'])) $wc_args['status'] = wc_clean($ability_input['status']);
                            if (!empty($ability_input['sku'])) $wc_args['sku'] = wc_clean($ability_input['sku']);
                            if (!empty($ability_input['stock_status'])) $wc_args['stock_status'] = wc_clean($ability_input['stock_status']);
                            if (!empty($ability_input['search'])) $wc_args['s'] = wc_clean($ability_input['search']);

                            $results = wc_get_products($wc_args);
                            $products = is_object($results) && isset($results->products) ? $results->products : [];
                            $pages = is_object($results) && isset($results->max_num_pages) ? (int)$results->max_num_pages : (count($products) > 0 ? 1 : 0);
                            $total = is_object($results) && isset($results->total) ? (int)$results->total : count($products);

                            $formatted = [];
                            foreach ($products as $product) {
                                $stock_quantity = $product->get_stock_quantity();
                                $permalink = $product->get_permalink();
                                $formatted[] = [
                                    'id' => $product->get_id(),
                                    'name' => $product->get_name(),
                                    'slug' => $product->get_slug(),
                                    'permalink' => false === $permalink ? null : $permalink,
                                    'type' => $product->get_type(),
                                    'status' => $product->get_status(),
                                    'sku' => $product->get_sku(),
                                    'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                                    'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : '$',
                                    'price' => $product->get_price(),
                                    'regular_price' => $product->get_regular_price(),
                                    'sale_price' => $product->get_sale_price(),
                                    'stock_status' => $product->get_stock_status(),
                                    'stock_quantity' => null === $stock_quantity ? null : (function_exists('wc_stock_amount') ? wc_stock_amount($stock_quantity) : (int)$stock_quantity),
                                    'manage_stock' => (bool)$product->get_manage_stock(),
                                    'virtual' => (bool)$product->get_virtual(),
                                    'downloadable' => (bool)$product->get_downloadable(),
                                ];
                            }

                            return [
                                'success' => true,
                                'ability_name' => $ability_name,
                                'data' => [
                                    'products' => $formatted,
                                    'total_pages' => $pages,
                                    'page' => $page,
                                    'per_page' => $per_page,
                                    'total_items' => $total,
                                    'total' => $total,
                                ],
                            ];
                        }
                    }

                    $result = $ability->execute($ability_input);
                    if (is_wp_error($result)) {
                        return $result;
                    }

                    // Enrich WooCommerce products-query with total_items if omitted by WooCommerce
                    if ($ability_name === 'woocommerce/products-query' && is_array($result)) {
                        if (!isset($result['total_items']) && !isset($result['total'])) {
                            if (function_exists('wc_get_products')) {
                                $count_args = array_merge($ability_input, [
                                    'return' => 'ids',
                                    'limit' => -1,
                                    'paginate' => false,
                                ]);
                                unset($count_args['page'], $count_args['per_page']);
                                $all_ids = wc_get_products($count_args);
                                $total_count = is_array($all_ids) ? count($all_ids) : (int)$all_ids;
                                $result['total_items'] = $total_count;
                                $result['total'] = $total_count;
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'ability_name' => $ability->get_name(),
                        'data' => $result,
                    ];
                }

                return new \WP_Error('invalid_action', __('Invalid action. Supported actions are "list", "get", and "execute".', 'aiutoma'));
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => [
                'mcp' => ['public' => false],
                'annotations' => [
                    'readonly' => false,
                    'destructive' => false,
                    'idempotent' => false,
                ],
            ],
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['list', 'get', 'execute'],
                        'description' => 'The action to perform: "list" to discover available abilities, "get" to inspect parameter schema, or "execute" to run an ability.',
                    ],
                    'ability_name' => [
                        'type' => 'string',
                        'description' => 'The name of the ability to inspect or execute (e.g. "aiutoma/manage-posts", "gutenberg/manage-templates", "aiutoma/page-snapshot", "aiutoma/skills"). Required for "get" and "execute".',
                    ],
                    'ability_input' => [
                        'type' => 'object',
                        'description' => 'Parameters to pass to the ability when action is "execute". Required schema matches the target ability\'s input_schema.',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Optional filter for action "list" (e.g. "aiutoma", "gutenberg", "woocommerce", "wpml").',
                    ],
                    'search' => [
                        'type' => 'string',
                        'description' => 'Optional keyword search filter for action "list".',
                    ],
                ],
                'required' => ['action'],
            ],
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/skills', [
            'category' => 'aiutoma',
            'label' => __('Knowledge Skills & Guidelines', 'aiutoma'),
            'description' => __('Discover and read specialized WordPress development skills, architecture guides, and coding guidelines on-demand (e.g. WooCommerce, Interactivity API, Block Themes, Performance Tuning, Blueprint, Playground, Hooks & Lifecycle). Use "list" to view available skills and "read" to retrieve the full instructions for a specific skill.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = isset($input['action']) ? sanitize_key($input['action']) : 'list';
                $ai = \Aiutoma\Modules\Ai\Ai::instance();

                if ($action === 'list') {
                    $skills = $ai->get_all_skills_summary();
                    return [
                        'success' => true,
                        'total_skills' => count($skills),
                        'skills' => $skills,
                        'usage_hint' => 'Call action "read" with the skill_id to retrieve detailed instructions and architecture patterns.',
                    ];
                }

                if ($action === 'read') {
                    $skill_id = isset($input['skill_id']) ? sanitize_text_field($input['skill_id']) : '';
                    if (empty($skill_id)) {
                        return new \WP_Error('missing_skill_id', __('Skill ID is required when action is "read".', 'aiutoma'));
                    }

                    $skill = $ai->get_skill_by_id($skill_id);
                    if (!$skill) {
                        /* translators: %s: skill name */
                        return new \WP_Error('skill_not_found', sprintf(__('Skill "%s" not found. Call action "list" to see all available skills.', 'aiutoma'), $skill_id));
                    }

                    return [
                        'success' => true,
                        'skill_id' => $skill['slug'],
                        'name' => $skill['name'],
                        'description' => $skill['description'],
                        'is_builtin' => !empty($skill['is_builtin']),
                        'content' => $skill['content'],
                    ];
                }

                return new \WP_Error('invalid_action', __('Invalid action. Supported actions are "list" and "read".', 'aiutoma'));
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => [
                'annotations' => [
                    'readonly' => true,
                    'destructive' => false,
                    'idempotent' => true,
                ],
            ],
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['list', 'read'],
                        'description' => 'The action to perform: "list" to get available skills and summaries, or "read" to get the full guidelines for a specific skill.',
                    ],
                    'skill_id' => [
                        'type' => 'string',
                        'description' => 'The ID or slug of the skill to read (e.g. "wp-woocommerce-development", "wp-interactivity-api", "wp-block-themes", "wp-hooks-and-lifecycle"). Required when action is "read".',
                    ],
                ],
                'required' => ['action'],
            ],
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/page-snapshot', [
            'category' => 'aiutoma',
            'label' => __('Page & Post Snapshot', 'aiutoma'),
            'description' => __('Inspect a page or post and get an all-in-one structured snapshot: status, permalink, author, template, categories/tags, custom fields, featured image, hierarchical block outline with text previews and paths, and word count.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $post = null;
                if (!empty($input['post_id'])) {
                    $post = get_post(absint($input['post_id']));
                } elseif (!empty($input['url'])) {
                    $post_id = url_to_postid(esc_url_raw($input['url']));
                    if ($post_id) {
                        $post = get_post($post_id);
                    }
                }

                if (!$post || !($post instanceof \WP_Post)) {
                    return new \WP_Error('post_not_found', __('Post or page not found. Provide a valid post_id or url.', 'aiutoma'));
                }

                $post_id = $post->ID;

                // Author
                $author_data = [
                    'id' => (int) $post->post_author,
                    'name' => get_the_author_meta('display_name', $post->post_author) ?: '',
                ];

                // Template
                $template = get_page_template_slug($post_id);
                if (empty($template)) {
                    $template = 'default';
                }

                // Featured Image
                $featured_image = null;
                if (has_post_thumbnail($post_id)) {
                    $thumb_id = get_post_thumbnail_id($post_id);
                    $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
                    $alt_text = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
                    $featured_image = [
                        'id' => $thumb_id,
                        'url' => $thumb_url ?: '',
                        'alt' => $alt_text ?: '',
                    ];
                }

                // Taxonomies
                $taxonomies = [];
                $post_taxonomies = get_object_taxonomies($post->post_type, 'objects');
                if (is_array($post_taxonomies)) {
                    foreach ($post_taxonomies as $tax_name => $tax_obj) {
                        $terms = wp_get_post_terms($post_id, $tax_name, ['fields' => 'names']);
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $taxonomies[$tax_name] = $terms;
                        }
                    }
                }

                // Custom fields (non-internal or public meta)
                $custom_fields = [];
                $all_meta = get_post_meta($post_id);
                if (is_array($all_meta)) {
                    foreach ($all_meta as $key => $values) {
                        if (str_starts_with($key, '_')) {
                            continue; // Skip WordPress internal meta
                        }
                        $val = maybe_unserialize($values[0] ?? '');
                        $custom_fields[$key] = $val;
                    }
                }

                // Block outline builder
                $outline_builder = function ($blocks_array, $parent_path = '') use (&$outline_builder) {
                    $outline = [];
                    foreach ($blocks_array as $index => $block) {
                        if (empty($block['blockName']) && empty(trim($block['innerHTML'] ?? ''))) {
                            continue;
                        }
                        $current_path = $parent_path === '' ? (string) $index : $parent_path . '.' . $index;
                        $inner_text = wp_strip_all_tags($block['innerHTML'] ?? '');
                        $preview_text = wp_trim_words($inner_text, 15, '...');

                        $item = [
                            'path' => $current_path,
                            'name' => $block['blockName'] ?: 'core/freeform',
                            'attrs' => !empty($block['attrs']) ? $block['attrs'] : new \stdClass(),
                            'preview' => $preview_text,
                            'inner_blocks_count' => !empty($block['innerBlocks']) ? count($block['innerBlocks']) : 0,
                        ];

                        if (!empty($block['innerBlocks'])) {
                            $item['inner_blocks'] = $outline_builder($block['innerBlocks'], $current_path);
                        }

                        $outline[] = $item;
                    }
                    return $outline;
                };

                $blocks = parse_blocks($post->post_content);
                $block_outline = $outline_builder($blocks);

                $plain_text = wp_strip_all_tags($post->post_content);

                return [
                    'success' => true,
                    'id' => $post_id,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'post_type' => $post->post_type,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'permalink' => get_permalink($post_id),
                    'author' => $author_data,
                    'template' => $template,
                    'featured_image' => $featured_image,
                    'taxonomies' => $taxonomies,
                    'custom_fields' => $custom_fields,
                    'has_blocks' => has_blocks($post->post_content),
                    'total_top_level_blocks' => count($block_outline),
                    'block_outline' => $block_outline,
                    'stats' => [
                        'word_count' => str_word_count($plain_text),
                        'char_count' => mb_strlen($plain_text),
                    ],
                ];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => [
                'annotations' => [
                    'readonly' => true,
                    'destructive' => false,
                    'idempotent' => true,
                ],
            ],
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the post or page to inspect.',
                    ],
                    'url' => [
                        'type' => 'string',
                        'description' => 'The public URL or permalink of the post/page (used if post_id is not provided).',
                    ],
                ],
            ],
        ]);
    }
}
