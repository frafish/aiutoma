<?php

namespace Aiutoma\Modules\Mcp;

if (! defined('ABSPATH')) exit;
class Mcp
{
    use Traits\McpOauth;

    public function __construct()
    {
        if (method_exists($this, 'register_mcp_hooks')) {
            $this->register_mcp_hooks();
        } elseif (method_exists($this, 'register_mcp_routes')) {
            $this->register_mcp_hooks();
        }

        if (method_exists($this, 'init_mcp_oauth')) {
            $this->init_mcp_oauth();
        }
    }

    public function register_mcp_hooks()
    {
        add_action('admin_menu', [$this, 'add_mcp_menu']);
        add_action('rest_api_init', [$this, 'init_mcp_routes']);
        add_filter('determine_current_user', [$this, 'determine_current_user'], 20);
        add_action('admin_post_aiutoma_download_mcpb', [$this, 'download_mcpb']);
        add_action('admin_post_aiutoma_download_log', [$this, 'download_log_file']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_mcp_scripts']);

        // Support for official WordPress MCP Adapter via mcp_adapter_init hook
        add_action('mcp_adapter_init', [$this, 'register_mcp_adapter_server'], 50);

        if (class_exists('\WP\MCP\Core\McpAdapter')) {
            \WP\MCP\Core\McpAdapter::instance();
        } else {
            add_action('plugins_loaded', function () {
                if (class_exists('\WP\MCP\Core\McpAdapter')) {
                    \WP\MCP\Core\McpAdapter::instance();
                }
            }, 30);
        }
    }

    public function enqueue_mcp_scripts($hook)
    {
        if ($hook === 'aiutoma_page_aiutoma-mcp') {
            wp_enqueue_style('aiutoma-mcp-style', AIUTOMA_URL . 'modules/mcp/assets/css/mcp.css', [], filemtime(AIUTOMA_PATH . 'modules/mcp/assets/css/mcp.css'));
            wp_enqueue_script('aiutoma-mcp-script', AIUTOMA_URL . 'modules/mcp/assets/js/mcp.js', [], filemtime(AIUTOMA_PATH . 'modules/mcp/assets/js/mcp.js'), true);
        }
    }

    public function add_mcp_menu()
    {
        add_submenu_page(
            'aiutoma',
            __('MCP & GPT Integrations', 'aiutoma'),
            __('MCP & GPT', 'aiutoma'),
            'manage_options',
            'aiutoma-mcp',
            [$this, 'aiutoma_mcp_page_html']
        );
    }


    public function init_mcp_routes()
    {
        // MCP JSON-RPC Endpoint (Stateless)
        register_rest_route('aiutoma/v1', '/mcp', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_mcp_request'],
            'permission_callback' => [$this, 'mcp_permission_check']
        ]);

        // OpenAPI Schema Endpoint (for GPT Custom Actions)
        register_rest_route('aiutoma/v1', '/openapi.json', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_openapi_schema'],
            'permission_callback' => '__return_true'
        ]);

        // OpenAPI Tool Execution Endpoint
        register_rest_route('aiutoma/v1', '/mcp/tool/(?P<tool_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_openapi_tool_call'],
            'permission_callback' => [$this, 'mcp_permission_check']
        ]);

        // Webhook Receiver Endpoint for AI Logs
        register_rest_route('aiutoma/v1', '/mcp/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook_request'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Determine the current user for REST API requests.
     * Integrates custom bearer tokens (OAuth 2.1) and static API keys into WordPress core's
     * determine_current_user filter pipeline so user context is established by WordPress core
     * without calling wp_set_current_user() directly.
     *
     * @param int|false $user_id User ID if already determined, or false/0.
     * @return int|false Determined user ID or original value.
     */
    public function determine_current_user($user_id)
    {
        if (!empty($user_id)) {
            return $user_id;
        }

        // 1. Check Bearer Token (OAuth 2.1 for MCP clients like Claude Desktop)
        $auth_header = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers['Authorization'])) {
                $auth_header = sanitize_text_field($headers['Authorization']);
            } elseif (!empty($headers['authorization'])) {
                $auth_header = sanitize_text_field($headers['authorization']);
            }
        }

        if (!empty($auth_header) && preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
            if (method_exists($this, 'validate_token')) {
                $oauth_token = $this->validate_token($token);
                if (!empty($oauth_token) && !empty($oauth_token['user_id'])) {
                    return (int) $oauth_token['user_id'];
                }
            }
        }

        // 2. Check Static MCP API Key (if provided and configured)
        $api_key = '';
        if (!empty($_SERVER['HTTP_X_MCP_API_KEY'])) {
            $api_key = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_MCP_API_KEY']));
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers['X-MCP-API-Key'])) {
                $api_key = sanitize_text_field($headers['X-MCP-API-Key']);
            } elseif (!empty($headers['x-mcp-api-key'])) {
                $api_key = sanitize_text_field($headers['x-mcp-api-key']);
            }
        }

        $saved_token = get_option('aiutoma_mcp_token', '');
        if (!empty($saved_token) && is_string($api_key) && !empty($api_key) && hash_equals($saved_token, $api_key)) {
            $acting_user_id = (int) apply_filters('aiutoma_mcp_acting_user_id', (int) get_option('aiutoma_mcp_acting_user', 0));
            if ($acting_user_id > 0) {
                $user = get_userdata($acting_user_id);
                if ($user && $user->has_cap('manage_options')) {
                    return $acting_user_id;
                }
            }
        }

        return $user_id;
    }

    public function mcp_permission_check(\WP_REST_Request $request)
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new \WP_Error(
            'rest_forbidden',
            __('Sorry, you are not allowed to access this resource. Please authenticate with an administrator account, WordPress Application Password, or valid OAuth token.', 'aiutoma'),
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * Register Aiutoma server on the official WordPress MCP Adapter if available.
     *
     * @param \WP\MCP\Core\McpAdapter $adapter MCP Adapter instance.
     */
    public function register_mcp_adapter_server($adapter)
    {
        if (!is_object($adapter) || !method_exists($adapter, 'create_server')) {
            return;
        }

        if (method_exists($adapter, 'get_server') && $adapter->get_server('aiutoma')) {
            return;
        }

        $aiutoma_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
        $aiutoma_abilities = apply_filters('aiutoma/abilities', $aiutoma_abilities);

        $tools = [];
        foreach ($aiutoma_abilities as $ability) {
            $meta = $ability->get_meta();
            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                continue;
            }
            $tools[] = $ability->get_name();
        }

        if (empty($tools)) {
            return;
        }

        $transports = [ \WP\MCP\Transport\HttpTransport::class ];
        $error_handler = class_exists('\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler')
            ? \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class
            : null;
        $observability = class_exists('\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler')
            ? \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class
            : null;

        $server_id = 'aiutoma';
        $server_namespace = 'aiutoma/v1';
        $server_route = 'mcp-adapter';
        $server_name = 'Aiutoma MCP Server';
        $server_desc = 'Aiutoma abilities and tools via official WordPress MCP Adapter';
        $server_version = defined('AIUTOMA_VERSION') ? AIUTOMA_VERSION : '1.0.0';

        try {
            $adapter->create_server(
                $server_id,
                $server_namespace,
                $server_route,
                $server_name,
                $server_desc,
                $server_version,
                $transports,
                $error_handler,
                $observability,
                $tools,
                [], // resources
                [], // prompts
                [$this, 'mcp_adapter_permission_check']
            );
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Aiutoma MCP] Failed to register server with MCP Adapter: ' . $e->getMessage());
            }
        }
    }

    /**
     * Permission callback for the official MCP Adapter transport.
     *
     * @param mixed $request REST request object.
     * @return bool
     */
    public function mcp_adapter_permission_check($request = null)
    {
        return current_user_can('manage_options');
    }

    /**
     * Format formal MCP annotations (readonly, destructive, idempotent) matching MCP 2025-11-25 specifications.
     *
     * @param \WP_Ability|object $ability Ability instance.
     * @return array
     */
    public static function format_mcp_annotations($ability)
    {
        $meta = (is_object($ability) && method_exists($ability, 'get_meta')) ? $ability->get_meta() : [];
        $raw_annotations = (isset($meta['annotations']) && is_array($meta['annotations'])) ? $meta['annotations'] : [];

        $name = (is_object($ability) && method_exists($ability, 'get_name')) ? $ability->get_name() : '';
        $action = basename($name);

        // Determine readonly
        if (isset($raw_annotations['readonly'])) {
            $readonly = (bool) $raw_annotations['readonly'];
        } elseif (isset($raw_annotations['readOnlyHint'])) {
            $readonly = (bool) $raw_annotations['readOnlyHint'];
        } else {
            $readonly = (bool) preg_match('/^(get|list|read|search|view|check|inspect|discover)-?/', $action);
        }

        // Determine destructive
        if (isset($raw_annotations['destructive'])) {
            $destructive = (bool) $raw_annotations['destructive'];
        } elseif (isset($raw_annotations['destructiveHint'])) {
            $destructive = (bool) $raw_annotations['destructiveHint'];
        } else {
            $destructive = (bool) preg_match('/^(delete|remove|trash|purge|destroy|drop)-?/', $action);
        }

        // Determine idempotent
        if (isset($raw_annotations['idempotent'])) {
            $idempotent = (bool) $raw_annotations['idempotent'];
        } elseif (isset($raw_annotations['idempotentHint'])) {
            $idempotent = (bool) $raw_annotations['idempotentHint'];
        } else {
            $idempotent = $readonly || (bool) preg_match('/^(update|set|delete|remove|trash|purge)-?/', $action);
        }

        $annotations = [
            'readonly'        => $readonly,
            'destructive'     => $destructive,
            'idempotent'      => $idempotent,
            // MCP 2025-11-25 ToolAnnotations spec fields (*Hint)
            'readOnlyHint'    => $readonly,
            'destructiveHint' => $destructive,
            'idempotentHint'  => $idempotent,
        ];

        if (isset($raw_annotations['openWorldHint'])) {
            $annotations['openWorldHint'] = (bool) $raw_annotations['openWorldHint'];
        }

        if (!empty($raw_annotations['title'])) {
            $annotations['title'] = (string) $raw_annotations['title'];
        } else {
            $label = (is_object($ability) && method_exists($ability, 'get_label')) ? $ability->get_label() : '';
            if (!empty($label)) {
                $annotations['title'] = $label;
            }
        }

        return $annotations;
    }

    public function handle_mcp_request(\WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (empty($body)) {
            $body = json_decode($request->get_body(), true);
        }

        $is_batch = is_array($body) && isset($body[0]);
        $requests = $is_batch ? $body : [$body];
        $responses = [];

        $aiutoma_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
        $aiutoma_abilities = apply_filters('aiutoma/abilities', $aiutoma_abilities);

        foreach ($requests as $req) {
            $id = isset($req['id']) ? $req['id'] : null;
            $method = isset($req['method']) ? $req['method'] : '';
            $params = isset($req['params']) ? $req['params'] : [];

            $response = ['jsonrpc' => '2.0'];
            if ($id !== null) {
                $response['id'] = $id;
            }

            try {
                if ($method === 'initialize') {
                    $response['result'] = [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => (object)[
                                'listChanged' => false
                            ],
                            'resources' => (object)[
                                'subscribe' => false,
                                'listChanged' => false
                            ],
                            'prompts' => (object)[
                                'listChanged' => false
                            ]
                        ],
                        'serverInfo' => [
                            'name' => 'aiutoma',
                            'version' => defined('AIUTOMA_VERSION') ? AIUTOMA_VERSION : '1.0.0'
                        ]
                    ];
                } elseif ($method === 'notifications/initialized') {
                    $response['result'] = (object)[];
                } elseif ($method === 'ping') {
                    $response['result'] = (object)[];
                } elseif ($method === 'tools/list') {
                    $tools = [
                        [
                            'name' => 'aiutoma_discover_abilities',
                            'description' => 'List all available WordPress abilities (tools) that can be executed. Use this first to find what you can do.',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => (object)[]
                            ],
                            'annotations' => [
                                'readonly' => true,
                                'destructive' => false,
                                'idempotent' => true,
                                'readOnlyHint' => true,
                                'destructiveHint' => false,
                                'idempotentHint' => true,
                                'title' => 'Discover Abilities'
                            ]
                        ],
                        [
                            'name' => 'aiutoma_get_ability_schema',
                            'description' => 'Get the JSON schema (required arguments) for a specific ability before executing it.',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'ability_name' => [
                                        'type' => 'string',
                                        'description' => 'The name of the ability to get the schema for (e.g. aiutoma/create-post).'
                                    ]
                                ],
                                'required' => ['ability_name']
                            ],
                            'annotations' => [
                                'readonly' => true,
                                'destructive' => false,
                                'idempotent' => true,
                                'readOnlyHint' => true,
                                'destructiveHint' => false,
                                'idempotentHint' => true,
                                'title' => 'Get Ability Schema'
                            ]
                        ],
                        [
                            'name' => 'aiutoma_execute_ability',
                            'description' => 'Execute a specific WordPress ability with the required parameters.',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'ability_name' => [
                                        'type' => 'string',
                                        'description' => 'The name of the ability to execute.'
                                    ],
                                    'parameters' => [
                                        'type' => 'object',
                                        'description' => 'The parameters required by the ability schema.'
                                    ]
                                ],
                                'required' => ['ability_name', 'parameters']
                            ],
                            'annotations' => [
                                'readonly' => false,
                                'destructive' => false,
                                'idempotent' => false,
                                'readOnlyHint' => false,
                                'destructiveHint' => false,
                                'idempotentHint' => false,
                                'openWorldHint' => true,
                                'title' => 'Execute Ability'
                            ]
                        ]
                    ];

                    // Expose direct tools matching MCP naming convention with annotations
                    foreach ($aiutoma_abilities as $ability) {
                        $meta = $ability->get_meta();
                        if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                            continue;
                        }
                        $sanitized_name = str_replace('/', '-', $ability->get_name());
                        $tools[] = [
                            'name' => $sanitized_name,
                            'title' => $ability->get_label() ?: $sanitized_name,
                            'description' => $ability->get_description() ?: '',
                            'inputSchema' => $ability->get_input_schema() ?: [
                                'type' => 'object',
                                'properties' => (object)[]
                            ],
                            'annotations' => self::format_mcp_annotations($ability)
                        ];
                    }

                    $response['result'] = ['tools' => $tools];
                } elseif ($method === 'tools/call') {
                    $name = isset($params['name']) ? $params['name'] : '';
                    $args = isset($params['arguments']) ? $params['arguments'] : [];
                    $agent_name = $request->get_header('authorization') ? 'OAuth Client (MCP)' : 'Static Client (MCP)';

                    $resolve_ability = function($tool_input_name) use ($aiutoma_abilities) {
                        if (empty($tool_input_name) || !is_string($tool_input_name)) {
                            return [null, ''];
                        }
                        $ability = function_exists('wp_get_ability') ? wp_get_ability($tool_input_name) : null;
                        if ($ability) {
                            return [$ability, $tool_input_name];
                        }
                        $clean_input_hyphen = str_replace('_', '-', $tool_input_name);
                        $clean_input_under = str_replace('-', '_', $tool_input_name);
                        foreach ($aiutoma_abilities as $ab) {
                            $ab_name = $ab->get_name();
                            $ab_hyphen = str_replace('/', '-', $ab_name);
                            $ab_under = str_replace(['/', '-'], '_', $ab_name);
                            if ($ab_name === $tool_input_name 
                                || $ab_hyphen === $tool_input_name 
                                || $ab_hyphen === $clean_input_hyphen
                                || $ab_under === $clean_input_under) {
                                return [$ab, $ab_name];
                            }
                        }
                        $candidate = str_replace('_', '/', $tool_input_name);
                        $ability = function_exists('wp_get_ability') ? wp_get_ability($candidate) : null;
                        if ($ability) {
                            return [$ability, $candidate];
                        }
                        return [null, $tool_input_name];
                    };

                    if ($name === 'aiutoma_discover_abilities') {
                        $list = [];
                        foreach ($aiutoma_abilities as $ability) {
                            $meta = $ability->get_meta();
                            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                                continue;
                            }
                            $list[] = [
                                'name' => $ability->get_name(),
                                'label' => $ability->get_label() ?: $ability->get_name(),
                                'description' => $ability->get_description() ?: '',
                                'annotations' => self::format_mcp_annotations($ability)
                            ];
                        }
                        $result_data = [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => wp_json_encode(['abilities' => $list])
                                ]
                            ]
                        ];
                        $this->write_file_log($request, $agent_name, 'Discover Abilities', $result_data, $args);
                        $response['result'] = $result_data;
                    } elseif ($name === 'aiutoma_get_ability_schema') {
                        $input_ability_name = isset($args['ability_name']) ? (string)$args['ability_name'] : '';
                        list($ability, $ability_name) = $resolve_ability($input_ability_name);
                        
                        if ($ability) {
                            $meta = $ability->get_meta();
                            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                                throw new \Exception("Ability not permitted in this context: {$ability_name}");
                            }
                            $schema_data = [
                                'name' => $ability->get_name(),
                                'label' => $ability->get_label() ?: $ability->get_name(),
                                'description' => $ability->get_description() ?: '',
                                'inputSchema' => $ability->get_input_schema() ?: ['type' => 'object', 'properties' => (object)[]],
                                'outputSchema' => $ability->get_output_schema() ?: (object)[],
                                'annotations' => self::format_mcp_annotations($ability)
                            ];
                            $result_data = [
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => wp_json_encode($schema_data)
                                    ]
                                ]
                            ];
                            $this->write_file_log($request, $agent_name, 'Get Schema: ' . $ability_name, $result_data, $args);
                            $response['result'] = $result_data;
                        } else {
                            throw new \Exception("Ability not found: {$input_ability_name}");
                        }
                    } elseif ($name === 'aiutoma_execute_ability') {
                        $input_ability_name = isset($args['ability_name']) ? (string)$args['ability_name'] : '';
                        list($ability, $ability_name) = $resolve_ability($input_ability_name);
                        $ability_params = isset($args['parameters']) ? $args['parameters'] : [];
                        
                        if ($ability) {
                            $meta = $ability->get_meta();
                            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                                throw new \Exception("Ability not permitted in this context: {$ability_name}");
                            }
                            $result = $ability->execute($ability_params);
                            if (is_wp_error($result)) {
                                $this->write_file_log($request, $agent_name, 'Tool Error: ' . $ability_name, ['error' => $result->get_error_message()], $ability_params);
                                throw new \Exception($result->get_error_message());
                            }

                            $result_data = [
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => is_string($result) ? $result : wp_json_encode($result)
                                    ]
                                ]
                            ];

                            $this->write_file_log($request, $agent_name, 'Tool Executed: ' . $ability_name, $result_data, $ability_params);
                            
                            $response['result'] = $result_data;
                        } else {
                            throw new \Exception("Ability not found: {$input_ability_name}");
                        }
                    } else {
                        // Direct tool calls (supports hyphenated, underscored, and slash names)
                        list($ability, $real_name) = $resolve_ability($name);

                        if ($ability) {
                            $meta = $ability->get_meta();
                            if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
                                throw new \Exception("Ability not permitted in this context: {$real_name}");
                            }

                            $result = $ability->execute($args);
                            if (is_wp_error($result)) {
                                $this->write_file_log($request, $agent_name, 'Tool Error: ' . $real_name, ['error' => $result->get_error_message()], $args);
                                throw new \Exception($result->get_error_message());
                            }

                            $result_data = [
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => is_string($result) ? $result : wp_json_encode($result)
                                    ]
                                ]
                            ];

                            $this->write_file_log($request, $agent_name, 'Tool Executed: ' . $real_name, $result_data, $args);
                            
                            $response['result'] = $result_data;
                        } else {
                            throw new \Exception("Tool not found: {$name}");
                        }
                    }
                } elseif ($method === 'resources/list') {
                    $all_skills = \Aiutoma\Modules\Ai\Ai::instance()->get_all_skills();
                    
                    $resources = [];
                    foreach ($all_skills as $skill) {
                        $resources[] = [
                            'uri' => 'aiutoma://skills/' . ($skill['slug'] ?? $skill['id']),
                            'name' => 'Skill: ' . ($skill['name'] ?? $skill['id']),
                            'description' => !empty($skill['description']) ? $skill['description'] : ('AI Skill instructions for ' . ($skill['slug'] ?? $skill['id'])),
                            'mimeType' => 'text/markdown'
                        ];
                    }
                    $response['result'] = ['resources' => $resources];
                } elseif ($method === 'resources/read') {
                    $uri = isset($params['uri']) ? $params['uri'] : '';
                    if (strpos($uri, 'aiutoma://skills/') === 0) {
                        $skill_id = str_replace('aiutoma://skills/', '', $uri);
                        $skill = \Aiutoma\Modules\Ai\Ai::instance()->get_skill_by_id($skill_id);
                        
                        if ($skill !== null && !empty($skill['content'])) {
                            $response['result'] = [
                                'contents' => [
                                    [
                                        'uri' => $uri,
                                        'mimeType' => 'text/markdown',
                                        'text' => $skill['content']
                                    ]
                                ]
                            ];
                        } else {
                            throw new \Exception("Resource not found: {$uri}");
                        }
                    } else {
                        throw new \Exception("Unsupported resource URI: {$uri}");
                    }
                } elseif ($method === 'prompts/list') {
                    $response['result'] = ['prompts' => []];
                } else {
                    throw new \Exception("Method not found", -32601);
                }
            } catch (\Exception $e) {
                $response['error'] = [
                    'code' => $e->getCode() ?: -32000,
                    'message' => $e->getMessage()
                ];
            }

            $responses[] = $response;
        }

        return rest_ensure_response($is_batch ? $responses : $responses[0]);
    }

    public function generate_openapi_schema(\WP_REST_Request $request)
    {
        $aiutoma_abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
        $aiutoma_abilities = apply_filters('aiutoma/abilities', $aiutoma_abilities);

        $site_url = get_site_url();
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => get_bloginfo('name') . ' API',
                'description' => 'API for interacting with ' . get_bloginfo('name'),
                'version' => '1.0.0'
            ],
            'servers' => [
                ['url' => $site_url . '/wp-json/aiutoma/v1']
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-MCP-API-Key'
                    ]
                ]
            ],
            'security' => [['ApiKeyAuth' => []]]
        ];

        foreach ($aiutoma_abilities as $ability) {
            $path_name = str_replace('/', '_', $ability->get_name());
            $schema['paths']['/mcp/tool/' . $path_name] = [
                'post' => [
                    'operationId' => $path_name,
                    'summary' => $ability->get_label() ?: $path_name,
                    'description' => $ability->get_description() ?: '',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => $ability->get_input_schema() ?: ['type' => 'object']
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => ['application/json' => ['schema' => ['type' => 'object']]]
                        ]
                    ]
                ]
            ];
        }

        return rest_ensure_response($schema);
    }

    public function handle_openapi_tool_call(\WP_REST_Request $request)
    {
        $tool_name = $request->get_param('tool_name');
        $name = str_replace('_', '/', $tool_name);
        $args = $request->get_json_params() ?: json_decode($request->get_body(), true);

        $ability = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
        if (!$ability) {
            return new \WP_Error('tool_not_found', 'Tool not found', ['status' => 404]);
        }

        $agent_name = $request->get_header('authorization') ? 'OAuth Client (REST)' : 'Static Client (REST)';
        
        $result = $ability->execute($args);
        
        if (is_wp_error($result)) {
            $this->write_file_log($request, $agent_name, 'Tool Error: ' . $name, ['error' => $result->get_error_message()], $args);
            return $result;
        }

        $this->write_file_log($request, $agent_name, 'Tool Executed: ' . $name, $result, $args);

        return rest_ensure_response(['success' => true, 'result' => $result]);
    }

    public function download_mcpb()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to download this file.', 'aiutoma'));
        }
        check_admin_referer('aiutoma_download_mcpb');

        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('ZipArchive extension is required.', 'aiutoma'));
        }

        $token = get_option('aiutoma_mcp_token');
        if (empty($token)) {
            wp_die(esc_html__('No MCP token found. Please save the settings first.', 'aiutoma'));
        }

        $site_name = trim(get_bloginfo('name'));
        $mcp_name = sanitize_file_name($site_name ?: 'aiutoma');
        $display_name = $site_name !== '' ? 'Aiutoma - ' . $site_name : 'Aiutoma';

        $rest_url = rest_url('aiutoma/v1/mcp');

        $manifest = [
            'manifest_version' => '0.3',
            'name' => 'aiutoma-' . strtolower($mcp_name),
            'display_name' => $display_name,
            'version' => '1.0.0',
            'description' => __('Aiutoma MCP Server (Static Token Bridge).', 'aiutoma'),
            'author' => ['name' => 'Aiutoma'],
            'server' => [
                'type' => 'node',
                'entry_point' => 'server/index.js',
                'mcp_config' => [
                    'command' => 'npx',
                    'args' => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                    'env' => [
                        'WP_API_URL' => $rest_url,
                        'CUSTOM_HEADERS' => wp_json_encode(['X-MCP-API-Key' => $token])
                    ]
                ],
            ],
        ];

        $manifest_json = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stub = "// Aiutoma MCP Server Stub\n// This file is required by the .mcpb spec but the real server is launched via mcp_config.\n";

        $tmp = wp_tempnam('aiutoma-mcpb');
        $zip = new \ZipArchive();
        if ($tmp === '' || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('Could not create bundle archive.', 'aiutoma'));
        }

        $zip->addFromString('manifest.json', $manifest_json);
        $zip->addFromString('server/index.js', $stub);
        $zip->close();

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $filename = 'aiutoma-' . sanitize_file_name($host ?: 'site') . '.mcpb';

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($tmp);
        wp_delete_file($tmp);
        exit;
    }

    private function get_log_dir()
    {
        if (class_exists('\Aiutoma\Modules\Ai\Ai') && method_exists('\Aiutoma\Modules\Ai\Ai', 'get_storage_dir')) {
            $base = \Aiutoma\Modules\Ai\Ai::get_storage_dir();
        } else {
            $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => '/tmp'];
            $base = $upload_dir['basedir'] . '/aiutoma';
        }
        $log_dir = $base . '/logs/mcp';
        if (@is_dir($log_dir) || (function_exists('wp_mkdir_p') && wp_mkdir_p($log_dir))) {
            return $log_dir;
        }
        return false;
    }

    public function download_log_file()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to download this file.', 'aiutoma'));
        }

        $file = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
        if (empty($file) || !preg_match('/^mcp-[a-zA-Z0-9_-]+\.log$/', $file)) {
            wp_die(esc_html__('Invalid log file.', 'aiutoma'));
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'aiutoma_download_log_' . $file)) {
            wp_die(esc_html__('Security check failed.', 'aiutoma'));
        }

        $filepath = $this->get_log_dir() . '/' . $file;

        if (!file_exists($filepath)) {
            wp_die(esc_html__('Log file not found.', 'aiutoma'));
        }

        nocache_headers();
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($filepath);
        exit;
    }

    public function handle_webhook_request(\WP_REST_Request $request)
    {
        $body_raw = $request->get_body();
        if (strlen($body_raw) > 524288) { // 512KB max
            return new \WP_Error('payload_too_large', 'Request body exceeds size limit', ['status' => 413]);
        }

        $token = $request->get_header('X-MCP-API-Key') ?: $request->get_param('token');
        $saved_token = get_option('aiutoma_mcp_webhook_token', '');

        if (empty($saved_token)) {
            return new \WP_Error('unconfigured', 'Webhook token not configured. Please visit settings.', ['status' => 503]);
        }

        if (!is_string($token) || !hash_equals($saved_token, $token)) {
            return new \WP_Error('unauthorized', 'Invalid webhook token', ['status' => 401]);
        }

        $body = $request->get_json_params();
        if (empty($body)) {
            $body = json_decode($request->get_body(), true) ?: $request->get_params();
        }

        $title = isset($body['title']) ? sanitize_text_field($body['title']) : 'AI Webhook';
        $author = isset($body['agent']) ? sanitize_text_field($body['agent']) : (isset($body['tool']) ? sanitize_text_field($body['tool']) : 'Aiutoma');
        $content_data = isset($body['content']) ? $body['content'] : $body;
        
        $this->write_file_log($request, $author, $title, ['success' => true], $content_data);

        return rest_ensure_response(['success' => true]);
    }

    private function redact_sensitive_keys(array &$data)
    {
        $sensitive = ['token', 'secret', 'authorization', 'api_key', 'password', 'cookie'];
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->redact_sensitive_keys($value);
            } elseif (in_array(strtolower((string)$key), $sensitive, true)) {
                $value = '[REDACTED]';
            }
        }
    }

    private function write_file_log(\WP_REST_Request $request, $agent, $title, $response_data, $request_body = null)
    {
        $log_dir = $this->get_log_dir();
        if (!$log_dir) {
            return;
        }

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/.htaccess', "Order deny,allow\nDeny from all");
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }

        $session_id = uniqid('req_');
        $date = gmdate('Y-m-d');
        
        // Group by Client/Token and by day
        $safe_agent = sanitize_title_with_dashes($agent);
        if (empty($safe_agent)) {
            $safe_agent = 'unknown';
        }
        $log_file = $log_dir . '/mcp-' . $safe_agent . '-' . $date . '.log';

        $headers = $request->get_headers();
        if (isset($headers['authorization'])) $headers['authorization'] = ['[REDACTED]'];
        if (isset($headers['x-mcp-api-key'])) $headers['x-mcp-api-key'] = ['[REDACTED]'];
        if (isset($headers['cookie'])) $headers['cookie'] = ['[REDACTED]'];

        $req_data = $request_body !== null ? $request_body : $request->get_params();
        if (is_array($req_data)) {
            $this->redact_sensitive_keys($req_data);
        }

        $log_entry = [
            'session_id' => $session_id,
            'timestamp'  => gmdate('Y-m-d\TH:i:s\Z'),
            'who'        => $agent,
            'what'       => $title,
            'where'      => $request->get_route(),
            'how'        => $request->get_method(),
            'headers'    => $headers,
            'request'    => $req_data,
            'response'   => $response_data,
        ];

        $log_line = wp_json_encode($log_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    public function aiutoma_mcp_page_html()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aiutoma_mcp_acting_user']) && check_admin_referer('aiutoma_mcp_save_settings')) {
            update_option('aiutoma_mcp_acting_user', intval($_POST['aiutoma_mcp_acting_user']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'aiutoma') . '</p></div>';
        }

        $token = get_option('aiutoma_mcp_token', wp_generate_password(24, false));
        if (empty(get_option('aiutoma_mcp_token'))) {
            update_option('aiutoma_mcp_token', $token);
        }
        $webhook_token = get_option('aiutoma_mcp_webhook_token', wp_generate_password(32, false));
        if (empty(get_option('aiutoma_mcp_webhook_token'))) {
            update_option('aiutoma_mcp_webhook_token', $webhook_token);
        }
        $acting_user = get_option('aiutoma_mcp_acting_user', '');
        $current_user_id = get_current_user_id();
        $selected_prompt_user_id = (int) apply_filters('aiutoma_mcp_selected_user_id', $current_user_id);
        $current_user = wp_get_current_user();
        $current_user_login = ($current_user && !empty($current_user->user_login)) ? $current_user->user_login : 'user';
        $selected_user = get_userdata($selected_prompt_user_id);
        $selected_user_login = ($selected_user && !empty($selected_user->user_login)) ? $selected_user->user_login : $current_user_login;
        $has_dev_extension = (bool) apply_filters('aiutoma_mcp_dev_extension_active', false);
        $has_application_password = false;
        if (class_exists('\WP_Application_Passwords') && $current_user_id) {
            $user_passwords = \WP_Application_Passwords::get_user_application_passwords($current_user_id);
            $has_application_password = !empty($user_passwords);
        }
        $user_edit_url = get_edit_user_link($current_user_id);
        if (!$user_edit_url) {
            $user_edit_url = admin_url('profile.php');
        }
        $app_pass_url = $user_edit_url . '#application-passwords-section';
        $prompt_users = get_users(['capability' => 'manage_options']);
        if (empty($prompt_users)) {
            $prompt_users = get_users(['role' => 'administrator']);
        }
        $found_current = false;
        foreach ($prompt_users as $u) {
            if ($u->ID === $current_user_id) {
                $found_current = true;
                break;
            }
        }
        if (!$found_current && $current_user && $current_user->exists()) {
            array_unshift($prompt_users, $current_user);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MCP & GPT Integrations', 'aiutoma'); ?></h1>
            <p><?php esc_html_e('Use this server to expose your WordPress site tools to Claude via MCP, or GPT via Custom Actions.', 'aiutoma'); ?></p>

            <?php if ($has_dev_extension) : ?>
                <div class="notice notice-info is-dismissible" style="margin: 15px 0 10px;">
                    <p style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span>
                            <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-top: -2px; color: #2271b1;"></span>
                            <strong><?php esc_html_e('Developer Extension Active:', 'aiutoma'); ?></strong>
                            <?php
                            /* translators: %s: user login */
                            printf(esc_html__('MCP REST requests using your API Key automatically run as site superuser (%s), bypassing the need for Application Passwords.', 'aiutoma'), '<code>' . esc_html($selected_user_login) . '</code>');
                            ?>
                        </span>
                    </p>
                </div>
            <?php elseif (!$has_application_password) : ?>
                <div class="notice notice-warning is-dismissible" style="margin: 15px 0 10px;">
                    <p style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span>
                            <strong><?php esc_html_e('Application Password Notice:', 'aiutoma'); ?></strong>
                            <?php esc_html_e('You do not have a WordPress Application Password set on your account. Creating one allows secure authenticated access to WordPress APIs.', 'aiutoma'); ?>
                        </span>
                        <a href="<?php echo esc_url($app_pass_url); ?>" class="button button-primary" target="_blank">
                            <span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-top: -2px;"></span>
                            <?php esc_html_e('Set Application Password in Profile', 'aiutoma'); ?> &rarr;
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <h2 class="nav-tab-wrapper" id="aiutoma-mcp-tabs">
                    <a href="#" class="nav-tab nav-tab-active" data-target="tab-gemini">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M11.9961 24C12.3023 17.5117 17.5039 12.3102 24 12C17.5039 11.6898 12.3023 6.48834 11.9961 0C11.6898 6.48834 6.48834 11.6898 0 12C6.48834 12.3102 11.6898 17.5117 11.9961 24Z" fill="#1A73E8" />
                        </svg>
                        <?php esc_html_e('Gemini', 'aiutoma'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="tab-claude">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="20" height="20">
                            <path d="m19.6 66.5 19.7-11 .3-1-.3-.5h-1l-3.3-.2-11.2-.3L14 53l-9.5-.5-2.4-.5L0 49l.2-1.5 2-1.3 2.9.2 6.3.5 9.5.6 6.9.4L38 49.1h1.6l.2-.7-.5-.4-.4-.4L29 41l-10.6-7-5.6-4.1-3-2-1.5-2-.6-4.2 2.7-3 3.7.3.9.2 3.7 2.9 8 6.1L37 36l1.5 1.2.6-.4.1-.3-.7-1.1L33 25l-6-10.4-2.7-4.3-.7-2.6c-.3-1-.4-2-.4-3l3-4.2L28 0l4.2.6L33.8 2l2.6 6 4.1 9.3L47 29.9l2 3.8 1 3.4.3 1h.7v-.5l.5-7.2 1-8.7 1-11.2.3-3.2 1.6-3.8 3-2L61 2.6l2 2.9-.3 1.8-1.1 7.7L59 27.1l-1.5 8.2h.9l1-1.1 4.1-5.4 6.9-8.6 3-3.5L77 13l2.3-1.8h4.3l3.1 4.7-1.4 4.9-4.4 5.6-3.7 4.7-5.3 7.1-3.2 5.7.3.4h.7l12-2.6 6.4-1.1 7.6-1.3 3.5 1.6.4 1.6-1.4 3.4-8.2 2-9.6 2-14.3 3.3-.2.1.2.3 6.4.6 2.8.2h6.8l12.6 1 3.3 2 1.9 2.7-.3 2-5.1 2.6-6.8-1.6-16-3.8-5.4-1.3h-.8v.4l4.6 4.5 8.3 7.5L89 80.1l.5 2.4-1.3 2-1.4-.2-9.2-7-3.6-3-8-6.8h-.5v.7l1.8 2.7 9.8 14.7.5 4.5-.7 1.4-2.6 1-2.7-.6-5.8-8-6-9-4.7-8.2-.5.4-2.9 30.2-1.3 1.5-3 1.2-2.5-2-1.4-3 1.4-6.2 1.6-8 1.3-6.4 1.2-7.9.7-2.6v-.2H49L43 72l-9 12.3-7.2 7.6-1.7.7-3-1.5.3-2.8L24 86l10-12.8 6-7.9 4-4.6-.1-.5h-.3L17.2 77.4l-4.7.6-2-2 .2-3 1-1 8-5.5Z" fill="#D97757"></path>
                        </svg>
                        <?php esc_html_e('Claude', 'aiutoma'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="tab-chatgpt">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 2406 2406" width="20" height="20">
                            <path d="M1 578.4C1 259.5 259.5 1 578.4 1h1249.1c319 0 577.5 258.5 577.5 577.4V2406H578.4C259.5 2406 1 2147.5 1 1828.6V578.4z" fill="black" />
                            <path id="aiutoma-chatgpt-a" d="M1107.3 299.1c-197.999 0-373.9 127.3-435.2 315.3L650 743.5v427.9c0 21.4 11 40.4 29.4 51.4l344.5 198.515V833.3h.1v-27.9L1372.7 604c33.715-19.52 70.44-32.857 108.47-39.828L1447.6 450.3C1361 353.5 1237.1 298.5 1107.3 299.1zm0 117.5-.6.6c79.699 0 156.3 27.5 217.6 78.4-2.5 1.2-7.4 4.3-11 6.1L952.8 709.3c-18.4 10.4-29.4 30-29.4 51.4V1248l-155.1-89.4V755.8c-.1-187.099 151.601-338.9 339-339.2z" fill="#fff" />
                            <use xlink:href="#aiutoma-chatgpt-a" transform="rotate(60 1203 1203)" />
                            <use xlink:href="#aiutoma-chatgpt-a" transform="rotate(120 1203 1203)" />
                            <use xlink:href="#aiutoma-chatgpt-a" transform="rotate(180 1203 1203)" />
                            <use xlink:href="#aiutoma-chatgpt-a" transform="rotate(240 1203 1203)" />
                            <use xlink:href="#aiutoma-chatgpt-a" transform="rotate(300 1203 1203)" />
                        </svg>
                        <?php esc_html_e('ChatGPT', 'aiutoma'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="tab-webhook">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Webhook', 'aiutoma'); ?>
                    </a>
                </h2>

                <div class="aiutoma-mcp-tab-content postbox" id="tab-gemini">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('Antigravity / Cursor Configuration', 'aiutoma'); ?></h2>
                    <p><?php esc_html_e('Configure advanced AI coding assistants like Antigravity to use this MCP Server directly via the filesystem using CLI.', 'aiutoma'); ?></p>

                    <div class="notice notice-info inline" style="margin: 15px 0; padding: 12px 15px;">
                        <p><strong><?php esc_html_e('Recommended WordPress Authentication:', 'aiutoma'); ?></strong><br>
                        <?php esc_html_e('In accordance with WordPress security standards, you can authenticate using a standard WordPress Application Password (Users > Profile > Application Passwords). Use HTTP Basic Auth with your username and application password (`curl -u username:password`), or use the static token header below.', 'aiutoma'); ?></p>
                        <?php if (!$has_application_password) : ?>
                            <p style="margin-top: 10px;">
                                <a href="<?php echo esc_url($app_pass_url); ?>" class="button button-secondary" target="_blank">
                                    <span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-top: -2px;"></span>
                                    <?php esc_html_e('Set Application Password on User Edit Page', 'aiutoma'); ?> &rarr;
                                </a>
                            </p>
                        <?php else : ?>
                            <p style="margin-top: 8px; color: #1e7e34;">
                                <span class="dashicons dashicons-yes-alt" style="vertical-align: middle; color: #46b450;"></span>
                                <?php esc_html_e('Application Password is configured for your user.', 'aiutoma'); ?>
                                <a href="<?php echo esc_url($app_pass_url); ?>" style="margin-left: 8px;" target="_blank"><?php esc_html_e('Manage Passwords', 'aiutoma'); ?> &rarr;</a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Prompt Credential Customizer -->
                    <div class="aiutoma-prompt-customizer" data-dev-active="<?php echo $has_dev_extension ? '1' : '0'; ?>" data-token="<?php echo esc_attr($token); ?>">
                        <h4>
                            <span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-right: 4px; color: #2271b1;"></span>
                            <?php esc_html_e('Customize Prompt Credentials', 'aiutoma'); ?>
                        </h4>
                        <p class="description" style="margin: 0 0 10px 0;">
                            <?php if ($has_dev_extension) : ?>
                                <?php
                                /* translators: %s: user login */
                                printf(esc_html__('Developer Extension is active: the prompt below uses your API Key and automatically acts as superuser (%s), bypassing application passwords. You can optionally select another user and enter their Application Password to use Basic Auth instead.', 'aiutoma'), '<code>' . esc_html($selected_user_login) . '</code>');
                                ?>
                            <?php else : ?>
                                <?php esc_html_e('Select a user and type or paste their Application Password. The system prompt below will update in real time with the credentials, ready to copy and use in Antigravity or Cursor.', 'aiutoma'); ?>
                            <?php endif; ?>
                        </p>
                        <div class="aiutoma-customizer-grid">
                            <div class="aiutoma-customizer-col-user">
                                <label for="aiutoma_prompt_user_select" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                    <?php esc_html_e('Select User:', 'aiutoma'); ?>
                                </label>
                                <select id="aiutoma_prompt_user_select" style="width: 100%; max-width: 100%;">
                                    <?php
                                    foreach ($prompt_users as $p_user) {
                                        $u_edit_url = get_edit_user_link($p_user->ID) ?: admin_url('user-edit.php?user_id=' . $p_user->ID);
                                        $u_app_url = $u_edit_url . '#application-passwords-section';
                                        ?>
                                        <option value="<?php echo esc_attr($p_user->user_login); ?>" data-user-id="<?php echo esc_attr($p_user->ID); ?>" data-app-url="<?php echo esc_url($u_app_url); ?>" <?php selected($p_user->ID, $selected_prompt_user_id); ?>>
                                            <?php echo esc_html($p_user->display_name . ' (' . $p_user->user_login . ')'); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="aiutoma-customizer-col-pass">
                                <label for="aiutoma_prompt_app_password" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                    <?php esc_html_e('Application Password:', 'aiutoma'); ?>
                                </label>
                                <div class="aiutoma-pass-input-wrap">
                                    <input type="password" id="aiutoma_prompt_app_password" placeholder="<?php esc_attr_e('Enter Application Password (e.g. abcd efgh ijkl mnop)', 'aiutoma'); ?>" class="regular-text" style="width: 100%; font-family: monospace;" autocomplete="off" spellcheck="false">
                                    <button type="button" class="button" id="aiutoma_prompt_toggle_pass" title="<?php esc_attr_e('Show / Hide Password', 'aiutoma'); ?>">
                                        <span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-top: -2px;"></span>
                                    </button>
                                    <button type="button" class="button" id="aiutoma_prompt_clear_pass" title="<?php esc_attr_e('Clear Password', 'aiutoma'); ?>" style="display: none;">&times;</button>
                                </div>
                            </div>
                            <div class="aiutoma-customizer-col-btn">
                                <a id="aiutoma_prompt_user_app_url" href="<?php echo esc_url($app_pass_url); ?>" class="button button-secondary" target="_blank">
                                    <span class="dashicons dashicons-external" style="vertical-align: middle; margin-top: -2px;"></span>
                                    <?php esc_html_e('Manage Passwords for Selected User', 'aiutoma'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                        <div id="aiutoma_prompt_status_msg" style="margin-top: 10px; font-size: 13px; color: #666;">
                            <?php if ($has_dev_extension) : ?>
                                <span class="dashicons dashicons-admin-generic" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-top: -2px; color: #2271b1;"></span>
                                <strong><?php esc_html_e('Developer Extension Active:', 'aiutoma'); ?></strong>
                                <?php
                                /* translators: %s: user login */
                                printf(esc_html__('Requests with API Key run as superuser (%s), bypassing Application Passwords. Enter an Application Password above only if you wish to use Basic Auth.', 'aiutoma'), '<code>' . esc_html($selected_user_login) . '</code>');
                                ?>
                            <?php else : ?>
                                <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-top: -2px;"></span>
                                <?php esc_html_e('Type or paste an Application Password to generate ready-to-use Basic Auth headers in the prompt below.', 'aiutoma'); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p style="margin-top: 15px;"><strong><?php esc_html_e('Initial System Prompt', 'aiutoma'); ?></strong><br>
                        <?php esc_html_e('Copy and paste this single instruction block when starting a new chat. It contains everything the AI needs to discover and execute tools.', 'aiutoma'); ?></p>
                    <div class="aiutoma-mcp-code-block aiutoma-mcp-code-block-gemini">
                        <button type="button" class="button button-small aiutoma-copy-btn" data-copy-target="aiutoma_gemini_prompt_content"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                        <div id="aiutoma_gemini_prompt_content">
                            You are managing a WordPress site that exposes an MCP Server over REST API. You must communicate with it by using your terminal/command tools to execute `curl` requests.<br><br>
                            Use the JSON-RPC method `tools/list` to discover capabilities, and `tools/call` to execute them.<br><br>
                            <strong>Endpoint:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/mcp'); ?><br>
                            <?php if (class_exists('\WP\MCP\Core\McpAdapter')) : ?>
                            <strong>MCP Adapter Endpoint:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/mcp-adapter'); ?><br>
                            <?php endif; ?>
                            <span id="aiutoma_prompt_auth_title"><strong><?php echo $has_dev_extension ? esc_html__('Authentication Header:', 'aiutoma') : esc_html__('Authentication Options:', 'aiutoma'); ?></strong><?php echo $has_dev_extension ? ' ' : '<br>'; ?></span>
                            <span id="aiutoma_prompt_auth_line" style="<?php echo $has_dev_extension ? 'display: none;' : ''; ?>">- <em>WordPress Application Password (Standard):</em> <code id="aiutoma_prompt_auth_basic">Authorization: Basic &lt;base64(<?php echo esc_html($selected_user_login); ?>:app_password)&gt;</code> (or <code id="aiutoma_prompt_auth_curl">curl -u "<?php echo esc_html($selected_user_login); ?>:your_application_password"</code>)<br></span>
                            <span id="aiutoma_prompt_api_key_line"><?php echo $has_dev_extension ? '' : '- <em>API Key:</em> '; ?><code>X-MCP-API-Key: <?php echo esc_html($token); ?></code></span><br><br>
                            <span id="aiutoma_prompt_safe_mode_hint"><?php if ($has_dev_extension) : ?><?php esc_html_e('If the REST API returns a 500 error, append `?aiutoma_enforce_safe_mode=1` to the endpoint URL to bypass broken plugins and fix the fatal error safely.', 'aiutoma'); ?><br><br><?php endif; ?></span>
                            <strong>NEVER</strong> modify core WordPress files or theme `functions.php`. Always use structured abilities to interact with WordPress data and settings safely.
                        </div>
                    </div>
                </div>

                <div class="aiutoma-mcp-tab-content postbox" id="tab-claude" style="display: none;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('Claude Desktop (via OAuth MCP)', 'aiutoma'); ?></h2>
                    <p><?php esc_html_e('Aiutoma now supports native OAuth 2.1 authentication for Claude Desktop and Cursor. You can also connect via a static token if preferred.', 'aiutoma'); ?></p>

                    <h3 style="margin-bottom: 5px;"><?php esc_html_e('Method 1: 1-Click .mcpb Bundle (Recommended)', 'aiutoma'); ?></h3>
                    <p style="margin-top: 5px;"><?php esc_html_e('The easiest way to connect Claude Desktop. Download the generated configuration bundle and load it in your client.', 'aiutoma'); ?></p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aiutoma_download_mcpb'), 'aiutoma_download_mcpb')); ?>" class="button button-primary" style="margin-bottom: 15px;"><?php esc_html_e('Download .mcpb bundle', 'aiutoma'); ?></a>

                    <h3 style="margin-bottom: 5px; margin-top: 20px;"><?php esc_html_e('Method 2: OAuth 2.1 (Dynamic)', 'aiutoma'); ?></h3>
                    <ol style="margin-left: 1.5em; margin-top: 5px;">
                        <li><strong><?php esc_html_e('No API Keys needed!', 'aiutoma'); ?></strong> <?php esc_html_e('Modern MCP clients can now connect dynamically using OAuth.', 'aiutoma'); ?></li>
                        <li><strong><?php esc_html_e('Authorization Server:', 'aiutoma'); ?></strong> <?php esc_html_e('Point your client to the OAuth Server Metadata URL below:', 'aiutoma'); ?></li>
                    </ol>
                    <div class="aiutoma-mcp-code-block aiutoma-mcp-code-block-claude-issuer">
                        <button type="button" class="button button-small aiutoma-copy-btn"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                        <div>
                            <strong>Issuer URL:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1'); ?><br><br>
                            <strong>Metadata URL:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/.well-known/oauth-authorization-server'); ?>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 5px; margin-top: 20px;"><?php esc_html_e('Method 3: Static Token (Legacy)', 'aiutoma'); ?></h3>
                    <form method="post" action="" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <?php wp_nonce_field('aiutoma_mcp_save_settings'); ?>
                        <p><strong><?php esc_html_e('Acting User Context', 'aiutoma'); ?>:</strong> 
                            <select name="aiutoma_mcp_acting_user">
                                <option value=""><?php esc_html_e('-- Select an Administrator --', 'aiutoma'); ?></option>
                                <?php
                                $admins = get_users(['role' => 'administrator']);
                                foreach ($admins as $admin) {
                                    ?>
                                    <option value="<?php echo esc_attr($admin->ID); ?>" <?php selected($acting_user, $admin->ID); ?>><?php echo esc_html($admin->display_name); ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                            <?php submit_button(__('Save Context', 'aiutoma'), 'secondary', 'submit', false, ['style' => 'margin-left: 10px;']); ?>
                        </p>
                        <p class="description"><?php esc_html_e('The legacy static token executes tools under this user account. It must be explicitly configured.', 'aiutoma'); ?></p>
                    </form>
                    <ol style="margin-left: 1.5em; margin-top: 5px;">
                        <li><strong><?php esc_html_e('Set Up Bridge:', 'aiutoma'); ?></strong> <?php esc_html_e('Since Claude Desktop natively uses stdio, use an HTTP-to-stdio bridge script to connect to this endpoint.', 'aiutoma'); ?></li>
                        <li><strong><?php esc_html_e('Configure Connection:', 'aiutoma'); ?></strong> <?php esc_html_e('Provide your client or bridge with the following connection details:', 'aiutoma'); ?></li>
                    </ol>
                    <div class="aiutoma-mcp-code-block aiutoma-mcp-code-block-claude-legacy">
                        <button type="button" class="button button-small aiutoma-copy-btn"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                        <div>
                            <strong>Endpoint URL:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/mcp'); ?><br><br>
                            <strong>Header Name:</strong> X-MCP-API-Key<br>
                            <strong>Header Value:</strong> <?php echo esc_html($token); ?>
                        </div>
                    </div>
                    <p><em><?php esc_html_e('Once connected, Claude will automatically read the available capabilities and can execute them when prompted.', 'aiutoma'); ?></em></p>
                </div>

                <div class="aiutoma-mcp-tab-content postbox" id="tab-chatgpt" style="display: none;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('ChatGPT Configuration (Custom Actions)', 'aiutoma'); ?></h2>
                    <p><?php esc_html_e('Empower a Custom GPT to interact directly with your site using Custom Actions.', 'aiutoma'); ?></p>
                    <ol style="margin-left: 1.5em;">
                        <li><strong><?php esc_html_e('Create Action:', 'aiutoma'); ?></strong> <?php esc_html_e('In ChatGPT, edit your Custom GPT, go to the Actions section, and click "Create new action".', 'aiutoma'); ?></li>
                        <li><strong><?php esc_html_e('Import Schema:', 'aiutoma'); ?></strong> <?php esc_html_e('Click "Import from URL", paste the link below, and click "Import":', 'aiutoma'); ?>
                            <div class="aiutoma-mcp-flex-row" style="margin-top: 5px;">
                                <input type="text" id="aiutoma_gpt_schema_url" readonly value="<?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/openapi.json'); ?>" class="large-text code" style="width: 100%;" onclick="this.select();">
                                <button type="button" class="button aiutoma-copy-btn" data-copy-target="aiutoma_gpt_schema_url"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                            </div>
                        </li>
                        <li><strong><?php esc_html_e('Setup Authentication:', 'aiutoma'); ?></strong> <?php esc_html_e('Click the gear icon in the Authentication section and choose either standard Basic Auth or Custom API Key:', 'aiutoma'); ?>
                            <div style="margin-top: 10px; margin-bottom: 10px;">
                                <p><strong><?php esc_html_e('Option A: Standard WordPress Application Password (Recommended)', 'aiutoma'); ?></strong><br>
                                <?php esc_html_e('Authentication Type: Basic. Enter your WordPress username and an Application Password generated in Users > Profile.', 'aiutoma'); ?></p>
                                <?php if (!$has_application_password) : ?>
                                    <p style="margin-top: 5px; margin-bottom: 12px;">
                                        <a href="<?php echo esc_url($app_pass_url); ?>" class="button button-secondary button-small" target="_blank">
                                            <span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-top: -2px;"></span>
                                            <?php esc_html_e('Set Application Password on User Edit Page', 'aiutoma'); ?> &rarr;
                                        </a>
                                    </p>
                                <?php endif; ?>
                                <p><strong><?php esc_html_e('Option B: Custom API Key Header', 'aiutoma'); ?></strong></p>
                            </div>
                            <ul style="list-style-type: disc; margin-left: 20px; margin-top: 5px;">
                                <li><strong><?php esc_html_e('Authentication Type:', 'aiutoma'); ?></strong> <?php esc_html_e('API Key', 'aiutoma'); ?></li>
                                <li><strong><?php esc_html_e('Auth Type:', 'aiutoma'); ?></strong> <?php esc_html_e('Custom', 'aiutoma'); ?></li>
                                <li class="aiutoma-mcp-flex-row-small">
                                    <strong><?php esc_html_e('Custom Header Name:', 'aiutoma'); ?></strong>
                                    <code>X-MCP-API-Key</code>
                                    <button type="button" class="button button-small aiutoma-copy-btn" data-copy-text="X-MCP-API-Key"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                                </li>
                                <li class="aiutoma-mcp-flex-row-small">
                                    <strong><?php esc_html_e('API Key:', 'aiutoma'); ?></strong>
                                    <code><?php echo esc_html($token); ?></code>
                                    <button type="button" class="button button-small aiutoma-copy-btn" data-copy-text="<?php echo esc_attr($token); ?>"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                                </li>
                            </ul>
                        </li>
                        <li><strong><?php esc_html_e('Save:', 'aiutoma'); ?></strong> <?php esc_html_e('Save your GPT. It can now access your site tools securely!', 'aiutoma'); ?></li>
                    </ol>
                </div>

                <div class="aiutoma-mcp-tab-content postbox" id="tab-webhook" style="display: none;">
                    <h2 style="margin-top: 0; padding: 0; border-bottom: none;"><?php esc_html_e('AI Webhook Receiver', 'aiutoma'); ?></h2>
                    <p><?php esc_html_e('Use this endpoint to let external AIs and custom integrations send activity logs directly to your WordPress backend.', 'aiutoma'); ?></p>
                    <div class="aiutoma-mcp-code-block aiutoma-mcp-code-block-webhook">
                        <button type="button" class="button button-small aiutoma-copy-btn"><?php esc_html_e('Copy', 'aiutoma'); ?></button>
                        <div>
                            <strong>POST Endpoint:</strong> <?php echo esc_url(get_site_url() . '/wp-json/aiutoma/v1/mcp/webhook'); ?><br><br>
                            <strong>Auth:</strong> Include "token": "<?php echo esc_html($webhook_token); ?>" in JSON body, OR header X-MCP-API-Key.<br><br>
                            <strong>Payload Example:</strong><br>
                            {<br>
                            &nbsp;&nbsp;"token": "<?php echo esc_html($webhook_token); ?>",<br>
                            &nbsp;&nbsp;"post_id": 123, <em>(optional)</em><br>
                            &nbsp;&nbsp;"agent": "Claude 3.5 Sonnet", <em>(optional)</em><br>
                            &nbsp;&nbsp;"title": "Analysis Completed",<br>
                            &nbsp;&nbsp;"content": "Successfully parsed 10 articles."<br>
                            }
                        </div>
                    </div>
                    <p><em><?php esc_html_e('Logs sent here will appear as Comments (type: aiutoma_log) in WordPress. If you provide a post_id, the comment will be attached to that specific post.', 'aiutoma'); ?></em></p>
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
                    <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Common Use Cases:', 'aiutoma'); ?></h4>
                    <ul style="list-style-type: disc; margin-left: 20px; font-size: 13px; color: #555;">
                        <li><strong><?php esc_html_e('Make.com / Zapier:', 'aiutoma'); ?></strong> <?php esc_html_e('Log the successful completion of an external content generation scenario.', 'aiutoma'); ?></li>
                        <li><strong><?php esc_html_e('External AI Agents:', 'aiutoma'); ?></strong> <?php esc_html_e('A remote Python script (e.g., CrewAI) logs its data processing progress.', 'aiutoma'); ?></li>
                        <li><strong><?php esc_html_e('Chatbots:', 'aiutoma'); ?></strong> <?php esc_html_e('Voiceflow or custom widgets notify admins when a goal (like capturing a lead) is achieved.', 'aiutoma'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Connected OAuth Applications -->
            <div style="margin-top: 30px;">
                <h2><?php esc_html_e('Connected OAuth Applications', 'aiutoma'); ?></h2>
                <?php
                global $wpdb;
                $clients_table = $wpdb->prefix . 'aiutoma_oauth_clients';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $clients_table)) === $clients_table) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $apps = $wpdb->get_results("SELECT * FROM `{$clients_table}` ORDER BY created DESC");
                    if (empty($apps)) {
                        echo '<p>' . esc_html__('No applications have connected via OAuth yet.', 'aiutoma') . '</p>';
                    } else {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead><tr>';
                        echo '<th>' . esc_html__('Application Name', 'aiutoma') . '</th>';
                        echo '<th>' . esc_html__('Client ID', 'aiutoma') . '</th>';
                        echo '<th>' . esc_html__('Connected On', 'aiutoma') . '</th>';
                        echo '</tr></thead><tbody>';
                        foreach ($apps as $app) {
                            echo '<tr>';
                            echo '<td><strong>' . esc_html($app->client_name) . '</strong></td>';
                            echo '<td><code>' . esc_html($app->client_id) . '</code></td>';
                            echo '<td>' . esc_html($app->created) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    }
                }
                ?>
            </div>

            <!-- AI Logs Display -->
            <div style="margin-top: 30px;">
                <h2><?php esc_html_e('AI Audit Logs', 'aiutoma'); ?></h2>
                <p><?php esc_html_e('All MCP, REST, and Webhook interactions are securely logged to daily files (in JSON Lines format). Download them below for auditing.', 'aiutoma'); ?></p>
                <?php
                $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/mcp';
                $log_files = file_exists($log_dir) ? glob($log_dir . '/mcp-*.log') : [];

                if (empty($log_files)) {
                    echo '<p>' . esc_html__('No AI logs recorded yet.', 'aiutoma') . '</p>';
                } else {
                    rsort($log_files);
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>' . esc_html__('Log File', 'aiutoma') . '</th>';
                    echo '<th style="width: 20%;">' . esc_html__('Size', 'aiutoma') . '</th>';
                    echo '<th style="width: 15%;">' . esc_html__('Action', 'aiutoma') . '</th>';
                    echo '</tr></thead><tbody>';
                    
                    foreach (array_slice($log_files, 0, 30) as $filepath) {
                        $filename = basename($filepath);
                        $size = size_format(filesize($filepath));
                        $download_url = wp_nonce_url(admin_url('admin-post.php?action=aiutoma_download_log&file=' . $filename), 'aiutoma_download_log_' . $filename);
                        
                        echo '<tr>';
                        echo '<td><strong>' . esc_html($filename) . '</strong></td>';
                        echo '<td>' . esc_html($size) . '</td>';
                        echo '<td><a href="' . esc_url($download_url) . '" class="button button-small">' . esc_html__('Download', 'aiutoma') . '</a></td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                }
                ?>
            </div>
        </div>
<?php
    }
}
