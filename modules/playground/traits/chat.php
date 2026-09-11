<?php
namespace Aiutoma\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Chat {
    public function handle_chat_request(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: [];
        $params = array_merge($params, $request->get_params());
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        $requested_model = sanitize_text_field($params['model'] ?? '');
        
        if (!empty($requested_model)) {
            update_user_meta(get_current_user_id(), '_aiutoma_preferred_model', $requested_model);
        }
        
        $conversation_id = sanitize_text_field($params['conversation_id'] ?? '');
        $execute_tools = !empty($params['execute_tools']);
        $cancel_tools = !empty($params['cancel_tools']);
        $modified_tools = $params['modified_tools'] ?? [];
        $fallback_models = !empty($params['fallback_models']);
        
        if (class_exists('\WordPress\AiClient\AiClient')) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            $timeout_filter = function($timeout, $url) {
                return 300;
            };
            add_filter('http_request_timeout', $timeout_filter, 999, 2);

            $messages = [];
            
            if (empty($conversation_id)) {
                $conversation_id = uniqid('aiutoma_');
                $messages = [];
            } else {
                $stored = get_transient($conversation_id);
                if ($stored !== false) {
                    $json_data = json_decode($stored, true);
                    if (is_array($json_data)) {
                        foreach ($json_data as $msg_array) {
                            if (is_array($msg_array) && isset($msg_array['role'])) {
                                $messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray($msg_array);
                            }
                        }
                    }
                }
                
                // Fallback to JSON file if transient expired
                if (empty($messages)) {
                    $upload_dir = wp_upload_dir();
                    $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . sanitize_file_name($conversation_id) . '.json';
                    if (file_exists($file_path)) {
                        $json_data = json_decode(file_get_contents($file_path), true);
                        if (is_array($json_data) && !empty($json_data['raw_messages'])) {
                            $raw = base64_decode($json_data['raw_messages']);
                            $decoded_array = json_decode($raw, true);
                            if (is_array($decoded_array)) {
                                foreach ($decoded_array as $msg_array) {
                                    if (is_array($msg_array) && isset($msg_array['role'])) {
                                        $messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray($msg_array);
                                    }
                                }
                                // Restore transient to speed up future requests
                                set_transient($conversation_id, wp_json_encode($messages), 3600);
                            }
                        }
                    }
                }
                
                if (!empty($messages)) {
                    $reconstructed = [];
                    foreach ($messages as $msg) {
                        if (method_exists($msg, 'getParts')) {
                            $new_parts = [];
                            foreach ($msg->getParts() as $part) {
                                if (method_exists($part, 'toArray')) {
                                    $new_parts[] = \WordPress\AiClient\Messages\DTO\MessagePart::fromArray($part->toArray());
                                } else {
                                    $new_parts[] = clone $part;
                                }
                            }
                            
                            $role = method_exists($msg, 'getRole') ? $msg->getRole() : null;
                            $role_str = (is_object($role) && property_exists($role, 'value')) ? $role->value : (is_object($role) && method_exists($role, '__toString') ? (string)$role : (is_string($role) ? $role : ''));
                            $is_model = ($role_str === 'model' || $role_str === 'assistant');
                            
                            if ($is_model) {
                                $reconstructed[] = new \WordPress\AiClient\Messages\DTO\ModelMessage($new_parts);
                            } else {
                                $reconstructed[] = new \WordPress\AiClient\Messages\DTO\UserMessage($new_parts);
                            }
                        } else {
                            $reconstructed[] = clone $msg;
                        }
                    }
                    $messages = $reconstructed;
                } else {
                    $is_cron = rest_sanitize_boolean($request->get_param('is_cron') ?? false);
                    $is_im = (strpos($conversation_id, 'whatsapp_') === 0 || strpos($conversation_id, 'telegram_') === 0 || strpos($conversation_id, 'cron_') === 0);
                    if (($is_cron || $is_im) && !$execute_tools) {
                        $messages = [];
                    } else {
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('Conversation expired or invalid.', 'aiutoma')], 400);
                    }
                }
            }

            if (!$execute_tools && !$cancel_tools && !empty($prompt)) {
                
                // Strip historical tool calls and responses from the context to save massive token amounts.
                // We only do this when the user sends a NEW prompt, meaning the previous reasoning chain is completed.
                $cleaned_messages = [];
                foreach ($messages as $msg) {
                    $parts = $msg->getParts();
                    $cleaned_parts = [];
                    foreach ($parts as $part) {
                        if ($part->getType()->isFunctionCall() || $part->getType()->isFunctionResponse()) {
                            continue; // Skip technical intermediate steps
                        }
                        $cleaned_parts[] = $part;
                    }
                    if (!empty($cleaned_parts)) {
                        if ($msg instanceof \WordPress\AiClient\Messages\DTO\ModelMessage) {
                            $cleaned_messages[] = new \WordPress\AiClient\Messages\DTO\ModelMessage($cleaned_parts);
                        } else {
                            $cleaned_messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage($cleaned_parts);
                        }
                    }
                }
                $messages = $cleaned_messages;
                
                $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                ]);
            }

            if (isset($params['session_context'])) {
                $env_info = sanitize_textarea_field($params['session_context']);
            } else {
                $env_info = '';
            }
            
            $object_id = isset($params['object_id']) ? intval($params['object_id']) : 0;
            $object_type = isset($params['object_type']) ? sanitize_text_field($params['object_type']) : '';
            
            if ($object_id > 0) {
                $meta_info = "";
                $acf_id = $object_id;
                if ($object_type === 'term' || $object_type === 'edit-tags') {
                    $acf_id = 'term_' . $object_id;
                } elseif (in_array($object_type, ['user', 'profile', 'user-edit'])) {
                    $acf_id = 'user_' . $object_id;
                }
                
                if (function_exists('get_fields')) {
                    $acf_fields = get_fields($acf_id);
                    if (!empty($acf_fields)) {
                        $meta_info .= "SCF/ACF Custom Fields:\n" . wp_json_encode($acf_fields, JSON_PRETTY_PRINT) . "\n";
                    }
                } else {
                    if ($object_type === 'post') {
                        $all_meta = get_post_meta($object_id);
                        $filtered_meta = [];
                        foreach ($all_meta as $k => $v) {
                            if (strpos($k, '_') !== 0) {
                                $filtered_meta[$k] = maybe_unserialize($v[0]);
                            }
                        }
                        if (!empty($filtered_meta)) {
                            $meta_info .= "Custom Fields:\n" . wp_json_encode($filtered_meta, JSON_PRETTY_PRINT) . "\n";
                        }
                    }
                }
                if (!empty($meta_info)) {
                    $env_info .= "\n\nOBJECT METADATA (ID: {$object_id}):\n" . $meta_info;
                }
            }

            if (!empty($params['system_info_context'])) {
                $env_info = sanitize_textarea_field($params['system_info_context']) . "\n\n" . $env_info;
            }
            if (!empty($params['rag_context'])) {
                $env_info .= "\n\nRAG VECTOR DATA CONTEXT:\n" . sanitize_textarea_field($params['rag_context']);
            }
            if (isset($params['permanent_context'])) {
                $permanent_info = sanitize_textarea_field($params['permanent_context']);
                update_option('aiutoma_permanent_context', $permanent_info);
            } else {
                $permanent_info = get_option('aiutoma_permanent_context', '');
            }
            
            // Track and preserve session settings across multi-step tool turns
            $session_meta_key = !empty($conversation_id) ? 'aiutoma_session_meta_' . sanitize_key($conversation_id) : null;
            $session_meta = $session_meta_key ? get_transient($session_meta_key) : false;
            if (!is_array($session_meta)) {
                $session_meta = [];
            }

            if (isset($params['enabled_abilities'])) {
                $session_meta['enabled_abilities'] = $params['enabled_abilities'];
            } elseif (isset($session_meta['enabled_abilities'])) {
                $params['enabled_abilities'] = $session_meta['enabled_abilities'];
            }

            if (isset($params['enable_tools'])) {
                $session_meta['enable_tools'] = (bool) $params['enable_tools'];
            } elseif (isset($session_meta['enable_tools'])) {
                $params['enable_tools'] = $session_meta['enable_tools'];
            }

            if (isset($params['enable_skills'])) {
                $session_meta['enable_skills'] = (bool) $params['enable_skills'];
            } elseif (isset($session_meta['enable_skills'])) {
                $params['enable_skills'] = $session_meta['enable_skills'];
            }

            if (isset($params['enabled_skills'])) {
                $session_meta['enabled_skills'] = $params['enabled_skills'];
            } elseif (isset($session_meta['enabled_skills'])) {
                $params['enabled_skills'] = $session_meta['enabled_skills'];
            }

            if ($session_meta_key) {
                set_transient($session_meta_key, $session_meta, 3600);
            }
            
            $abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
            $abilities = apply_filters('aiutoma/abilities', $abilities);
            
            if (isset($params['enable_tools']) && !$params['enable_tools']) {
                $abilities = [];
            } elseif (isset($params['enabled_abilities']) && is_array($params['enabled_abilities'])) {
                $enabled_map = array_flip($params['enabled_abilities']);
                $abilities = array_filter($abilities, function($ability) use ($enabled_map) {
                    return isset($enabled_map[$ability->get_name()]);
                });
            } else {
                // By default, expose only the meta dispatcher ability to prevent massive prompt bloat (>50,000 tokens)
                $default_map = ['aiutoma/abilities' => 0];
                $abilities = array_filter($abilities, function($ability) use ($default_map) {
                    return isset($default_map[$ability->get_name()]);
                });
            }

            $tools_list = "AVAILABLE TOOLS:\n";
            if (!empty($abilities) && class_exists('\WP_AI_Client_Ability_Function_Resolver')) {
                foreach ($abilities as $ability) {
                    $function_name = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name());
                    $tools_list .= "- " . $function_name . ": " . $ability->get_description() . "\n";
                }
            } else {
                $tools_list .= "None\n";
            }

            $env_info = "\n\n" . $env_info;
            if (!empty($permanent_info)) {
                $env_info .= "\n\nPERMANENT CONTEXT:\n" . $permanent_info;
            }
            $env_info .= "\n\n" . $tools_list;

            $dev_tool_instruction = defined('AIUTOMA_DEV_VERSION')
                ? "Developer tools (execute-php, modify-file, run-wp-cli) are active via the Developer Extension; use them when needed for custom code execution or file edits after user confirmation. "
                : "If a task cannot be performed with native tools and strictly requires executing arbitrary PHP or modifying files, inform the user that the Aiutoma Developer Extension is required. ";

            $system_instruction = "You are an advanced, agentic AI Assistant specialized in WordPress. "
                . "To optimize token usage, you MUST provide extremely concise and direct answers. Avoid unnecessary pleasantries or long explanations. "
                . "You are empowered to act autonomously directly within the WordPress instance using your tools when needed. "
                . "Your capabilities include: 1) Manage and maintain the site (update WP core, install/remove plugins/themes, manage user roles). 2) Create and organize content (add/edit/delete posts/pages, categories, tags, images, comments). 3) Run WooCommerce stores (manage products, orders, customers). 4) Improve performance and security (identify speed issues, audit vulnerabilities, troubleshoot conflicts). 5) Customize and enhance the site (design guidance, snippets, SEO). 6) Adjust technical settings (permalinks, options, multilanguage). "
                . "You have full access to structured WordPress Core functions and plugin APIs (such as WooCommerce, Posts, Options, Media, Users, Full Site Editing). All WordPress system abilities (e.g. `aiutoma/page-snapshot`, `gutenberg/manage-templates`, `gutenberg/wp-patterns`, `aiutoma/manage-posts`, `aiutoma/manage-options`, `aiutoma/manage-system`, `aiutoma/skills`, WooCommerce, WPML) can be discovered, inspected, and executed dynamically on-demand via the `aiutoma/abilities` ability. Call `aiutoma/abilities` with action 'list' (pass 'search' or 'category' to find specific tools), 'get' to inspect parameter schemas, or 'execute' with 'ability_name' and 'ability_input' to run any ability safely. When querying or counting items (like products or posts), always check the returned `total_items` or `total` count in the response rather than paginating through all records. For WooCommerce, filter products with `woocommerce/products-query` (supports `product_type_alias`: 'physical', 'virtual', 'digital', 'affiliate', 'grouped', or 'variable') and manage or list variations with `woocommerce/manage-variations` (pass `product_id` and optional `action`: 'list'). "
                . $dev_tool_instruction
                . "You are an expert in the WordPress ecosystem, its hooks, filters, and best practices. Specialized architecture guidelines and best practices (e.g. WooCommerce, Interactivity API, Block Themes, Performance Tuning, Blueprint, Playground, Hooks & Lifecycle) are available on-demand via the `aiutoma/skills` ability. Call `aiutoma/skills` with action 'list' to see available topics or 'read' with a skill_id to retrieve exact guidelines when needed."
                . "\n\nCRITICAL RULE FOR USING ABILITIES/TOOLS:\n"
                . "You MUST ONLY call ONE tool per response! DO NOT execute multiple tools in parallel in a single response. "
                . "When given a task, you must decompose it into multiple smaller actions (divide et impera). "
                . "You MUST call the first tool, wait for its result, and then call the next tool in subsequent turns until all actions are complete. "
                . "Violating this rule will cause a fatal API error ('The API only allows a single function response').\n"
                . "When answering natively in plain text, use standard conversational human language formatted exclusively in Markdown. DO NOT output HTML tags (like <p>, <b>, etc.) and DO NOT output JSON dictionaries or structured data unless explicitly requested by the user.\n"
                . "When you have finished executing all necessary actions, your final response (without tool calls) must be a comprehensive confirmation report that lists each action you took and describes what was done.\n"
                . "IMPORTANT: If the user asked you to modify, append, or generate content for their editor, you MUST include the required ```gutenberg-insert or ```gutenberg-replace code blocks inside your FINAL confirmation response. The editor is ONLY updated if you explicitly output these code blocks in your final turn.\n"
                . "CRITICAL: You MUST NEVER edit WordPress core files.\n"
                . $env_info;
            $enabled_skills = [];
            if (!empty($params['enable_skills'])) {
                if (isset($params['enabled_skills']) && is_array($params['enabled_skills'])) {
                    $enabled_skills = $params['enabled_skills'];
                }
            }
            
            if (!empty($enabled_skills)) {
                $system_instruction .= \Aiutoma\Modules\Ai\Ai::instance()->get_ai_skills($enabled_skills);
            }

            $is_playground = in_array($object_type, ['toplevel_page_aiutoma']);
            if (!$is_playground && !empty($object_type)) {
                $system_instruction .= "\n\nCRITICAL CONFINEMENT RULE: You are currently active as an embedded Agent inside a specific Page/Post editor. You MUST NOT use PHP tools or database queries to directly update the `post_content` of the current post. Instead, you MUST output Gutenberg blocks in your final response using ```gutenberg-insert, ```gutenberg-edit, or ```gutenberg-replace markdown blocks. To update meta fields or ACF/SCF custom fields, you MUST output a JSON block using ```meta-update containing key-value pairs. The editor UI will automatically apply these blocks and meta updates for the user. You may use tools to fetch data or edit the title/status, but DO NOT save the main body blocks or meta directly to the database via tools. If THEME STYLES are provided in the context, you MUST use their CSS utility classes (e.g., `has-[slug]-color`, `has-[slug]-font-size`) instead of inventing inline styles or hex codes.";
            }

            $functions = [];
            $resolver = null;
            
            if (!empty($abilities) && class_exists('\WP_AI_Client_Ability_Function_Resolver')) {
                $resolver = new \WP_AI_Client_Ability_Function_Resolver(...$abilities);
                $clean_schema = function(&$schema) use (&$clean_schema) {
                    if (!is_array($schema)) return;
                    $allowed_keys = ['type', 'description', 'properties', 'required', 'items', 'enum'];
                    foreach ($schema as $k => $v) {
                        if (!in_array($k, $allowed_keys, true)) {
                            unset($schema[$k]);
                        } elseif (is_array($v)) {
                            if ($k === 'type') {
                                // Google API doesn't support array types (like ['string', 'null']) in JSON schema
                                $schema[$k] = is_array($v) && !empty($v) ? $v[0] : 'string';
                            } elseif ($k === 'properties') {
                                if (empty($v)) {
                                    $schema[$k] = new \stdClass();
                                } else {
                                    foreach ($schema[$k] as $prop_name => &$prop_val) {
                                        $clean_schema($prop_val);
                                        if (is_array($prop_val) && empty($prop_val)) {
                                            $prop_val = new \stdClass();
                                        }
                                    }
                                }
                            } elseif ($k === 'items') {
                                if (empty($v)) {
                                    $schema[$k] = new \stdClass();
                                } else {
                                    if (is_array($v) && isset($v[0])) {
                                        $schema[$k] = $v[0];
                                    }
                                    $clean_schema($schema[$k]);
                                    if (is_array($schema[$k]) && empty($schema[$k])) {
                                        $schema[$k] = new \stdClass();
                                    }
                                }
                            }
                        }
                    }
                    if (isset($schema['type']) && is_string($schema['type'])) {
                        if (strtolower($schema['type']) === 'array' && !isset($schema['items'])) {
                            $schema['items'] = new \stdClass();
                        } elseif (strtolower($schema['type']) !== 'array' && isset($schema['items'])) {
                            unset($schema['items']);
                        }
                        if (strtolower($schema['type']) !== 'object' && isset($schema['properties'])) {
                            unset($schema['properties']);
                        }
                    } elseif (isset($schema['properties'])) {
                        $schema['type'] = 'object';
                    }
                    
                    if (isset($schema['enum']) && is_array($schema['enum'])) {
                        $schema['enum'] = array_values(array_filter($schema['enum'], function($val) {
                            return $val !== '' && $val !== null;
                        }));
                        if (empty($schema['enum'])) {
                            unset($schema['enum']);
                        }
                    }
                };

                foreach ($abilities as $ability) {
                    $function_name = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name());
                    $input_schema = $ability->get_input_schema();
                    if (empty($input_schema)) {
                        $input_schema = ['type' => 'object', 'properties' => new \stdClass()];
                    } else {
                        $clean_schema($input_schema);
                        if (empty($input_schema['properties'])) {
                            $input_schema['properties'] = new \stdClass();
                        }
                        if (empty($input_schema['type'])) {
                            $input_schema['type'] = 'object';
                        }
                    }
                    $functions[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration(
                        $function_name,
                        $ability->get_description(),
                        $input_schema
                    );
                }
            }

            $previous_results = [];
            
            if ($cancel_tools && !empty($messages)) {
                return new \WP_REST_Response([
                    'success' => true,
                    'action' => 'done',
                    'response' => __('Task execution aborted by user.', 'aiutoma'),
                    'previous_results' => []
                ], 200);
            } elseif ($execute_tools && !empty($messages)) {
                $last_message = end($messages);
                $has_any_call = false;
                if ($last_message) {
                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $has_any_call = true;
                            break;
                        }
                    }
                }
                if ($last_message && $resolver && $has_any_call) {
                    if (!empty($modified_tools)) {
                        $new_last_parts = [];
                        foreach ($last_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $fc = $part->getFunctionCall();
                                $id = $fc->getId();
                                if (isset($modified_tools[$id])) {
                                    $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall(
                                        $id,
                                        $fc->getName(),
                                        array_merge($fc->getArgs(), $modified_tools[$id])
                                    );
                                    $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                                }
                            }
                            $new_last_parts[] = $part;
                        }
                        $last_message = new \WordPress\AiClient\Messages\DTO\ModelMessage($new_last_parts);
                        $messages[count($messages) - 1] = $last_message;
                    }
                    $upload_dir = wp_upload_dir();
                    $backup_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/backup';
                    if (!is_dir($backup_dir)) wp_mkdir_p($backup_dir);
                    
                    $is_cron = $request->get_param('is_cron');
                    $backup_data = [
                        'action' => $is_cron ? 'cron-rollback' : 'global-rollback',
                        'options' => [],
                        'posts' => [],
                        'db_changes' => []
                    ];

                    $opt_logger = function($option, $old_value, $value) use (&$backup_data) {
                        if (strpos($option, 'connectors_ai_') === 0) {
                            return;
                        }
                        if (!isset($backup_data['options'][$option])) {
                            $backup_data['options'][$option] = $old_value;
                        }
                    };
                    add_action('updated_option', $opt_logger, 10, 3);
                    
                    $opt_added_logger = function($option, $value) use (&$backup_data) {
                        if (strpos($option, 'connectors_ai_') === 0) {
                            return;
                        }
                        if (!isset($backup_data['options'][$option])) {
                            $backup_data['options'][$option] = false;
                        }
                    };
                    add_action('added_option', $opt_added_logger, 10, 2);
                    
                    $pre_post_update = function($post_ID) {
                        wp_save_post_revision($post_ID);
                    };
                    add_action('pre_post_update', $pre_post_update, 10, 1);

                    $post_logger = function($post_ID, $post_after, $post_before) use (&$backup_data) {
                        if (!isset($backup_data['posts'][$post_ID])) {
                            $backup_data['posts'][$post_ID] = $post_before;
                        }
                    };
                    add_action('post_updated', $post_logger, 10, 3);
                    
                    $post_deleted_logger = function($post_ID, $post) use (&$backup_data) {
                        if (!isset($backup_data['posts'][$post_ID])) {
                            $backup_data['posts'][$post_ID] = $post;
                        }
                    };
                    add_action('before_delete_post', $post_deleted_logger, 10, 2);

                    global $aiutoma_is_executing;
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                    $aiutoma_is_executing = true;
                    
                    foreach ($last_message->getParts() as $part) {
                        if ($part->getFunctionCall() !== null) {
                            $fc = $part->getFunctionCall();
                            $ai_logger = \Aiutoma\Modules\Ai\Ai::instance();
                            if (method_exists($ai_logger, 'log_audit_event')) {
                                $ctx = $is_cron ? 'cron_agent' : 'playground_agent';
                                $ai_logger->log_audit_event($ctx, $fc->getName(), $fc->getArgs(), 'success');
                            }
                        }
                    }

                    ob_start();
                    $response = $resolver->execute_abilities($last_message);
                    $stray_output = ob_get_clean();
                    
                    global $aiutoma_is_executing;
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                    $aiutoma_is_executing = false;

                    remove_action('updated_option', $opt_logger, 10);
                    remove_action('added_option', $opt_added_logger, 10);
                    remove_action('pre_post_update', $pre_post_update, 10);
                    remove_action('post_updated', $post_logger, 10);
                    remove_action('before_delete_post', $post_deleted_logger, 10);

                    $backup_id = null;
                    if (!empty($backup_data['options']) || !empty($backup_data['posts'])) {
                        $filename = 'global_' . time() . '_' . uniqid() . '.json';
                        file_put_contents($backup_dir . '/' . $filename, json_encode($backup_data));
                        $backup_id = $filename;
                    }

                    $new_parts = [];
                    foreach ($response->getParts() as $part) {
                        if ($part->getFunctionResponse() !== null) {
                            $fr = $part->getFunctionResponse();
                            $name = $fr->getName();
                            $fr_response = $fr->getResponse();
                            
                            // Prevent context window overflow (Error 413) on models with small contexts (like Groq Llama 3)
                            // Truncate function responses that exceed ~8000 bytes (roughly ~2000 tokens)
                            $json_str = is_string($fr_response) ? $fr_response : wp_json_encode($fr_response);
                            if (strlen($json_str) > 8000) {
                                $fr_response = [
                                    '_notice' => 'WARNING: The output was too large (' . strlen($json_str) . ' bytes) for the AI context limit and has been truncated. If you need this data, please refine your query (e.g. use pagination, limit results, or search specific keywords).',
                                    'truncated_result' => substr($json_str, 0, 8000) . '... [TRUNCATED]'
                                ];
                                $fr = new \WordPress\AiClient\Tools\DTO\FunctionResponse(
                                    $fr->getId(),
                                    $name,
                                    $fr_response
                                );
                                $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fr);
                            }
                            
                            if ($backup_id && !isset($fr_response['backup_id'])) {
                                if (!is_array($fr_response)) $fr_response = ['result' => $fr_response];
                                $fr_response['backup_id'] = $backup_id;
                                $fr_response['_rollback_notice'] = 'Backup created for rollback.';
                                
                                $fr = new \WordPress\AiClient\Tools\DTO\FunctionResponse(
                                    $fr->getId(),
                                    $name,
                                    $fr_response
                                );
                                $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fr);
                            }

                            if (isset($fr_response['code']) && $fr_response['code'] === 'ability_invalid_permissions') {
                                if (in_array($name, ['wpab__ai__get-post-details', 'wpab__ai__get-post-terms'])) {
                                    $post_id = null;
                                    foreach ($last_message->getParts() as $call_part) {
                                        if ($call_part->getType()->isFunctionCall()) {
                                            $fc = $call_part->getFunctionCall();
                                            if ($fc->getId() === $fr->getId()) {
                                                $args = $fc->getArgs();
                                                $post_id = isset($args['post_id']) ? absint($args['post_id']) : null;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($post_id !== null) {
                                        if (!get_post($post_id)) {
                                            $fr_response['error'] = 'Post not found.';
                                            $fr_response['code'] = 'post_not_found';
                                            $fr = new \WordPress\AiClient\Tools\DTO\FunctionResponse(
                                                $fr->getId(),
                                                $name,
                                                $fr_response
                                            );
                                            $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fr);
                                        }
                                    }
                                }
                            }
                            
                            $previous_results[] = [
                                'name' => $fr->getName(),
                                'response' => $fr->getResponse()
                            ];
                        }
                        $new_parts[] = $part;
                    }
                    
                    if (empty($new_parts)) {
                        $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([new \WordPress\AiClient\Messages\DTO\MessagePart("System: Tool execution returned no output.")]);
                    } else {
                        foreach ($new_parts as $part) {
                            $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([$part]);
                        }
                    }
                }
            }

            $ai_text = '';
            $retry_without_tools = false;
            
            $models_to_try = [];
            if (!empty($requested_model)) {
                $models_to_try[] = $requested_model;
            }
            
            if ($fallback_models) {
                $models_response = \Aiutoma\Modules\Ai\Ai::instance()->get_ai_models($request);
                $models_data = $models_response->get_data();
                if (!empty($models_data['models'])) {
                    $all_models = [];
                    foreach ($models_data['models'] as $provider_models) {
                        foreach (array_keys($provider_models) as $model_id) {
                            $all_models[] = $model_id;
                        }
                    }
                    foreach ($all_models as $m) {
                        if ($m !== $requested_model && !in_array($m, $models_to_try)) {
                            $models_to_try[] = $m;
                        }
                    }
                }
            }
            
            if (empty($models_to_try)) {
                $models_to_try = [''];
            }
            
            $model_index = 0;
            $current_model_to_try = $models_to_try[$model_index];
            
            for ($i = 0; $i < 3; $i++) {
                if ($retry_without_tools) {
                    $functions = [];
                }
                
                $ai_query = \WordPress\AiClient\AiClient::prompt( $messages );
                if (!empty($current_model_to_try)) {
                    if (strpos($current_model_to_try, '|') !== false) {
                        list($provider_id, $model_id) = explode('|', $current_model_to_try, 2);
                        $ai_query->usingModelPreference([$provider_id, $model_id]);
                    } else {
                        $ai_query->usingModelPreference($current_model_to_try);
                    }
                }
                $ai_query->usingSystemInstruction($system_instruction);
                
                if (!empty($functions)) {
                    $ai_query->usingFunctionDeclarations(...$functions);
                }
                
                try {
                    $result = $ai_query->generateResult();
                    $response_message = $result->toMessage();
                    
                    // Fix Gemini dropped namespaces
                    if ($resolver && $resolver->has_ability_calls($response_message)) {
                        $rewritten_parts = [];
                        $modified = false;
                        foreach ($response_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $fc = $part->getFunctionCall();
                                $name = $fc->getName();
                                $original_name = $name;
                                
                                $missing_namespace_map = [
                                    'wpab__execute-php' => 'wpab__ai__execute-php',
                                    'wpab__execute_php' => 'wpab__ai__execute_php',
                                    'wpab__db-query' => 'wpab__ai__db-query',
                                    'wpab__db_query' => 'wpab__ai__db_query',
                                    'wpab__modify-file' => 'wpab__ai__modify-file',
                                    'wpab__modify_file' => 'wpab__ai__modify_file',
                                    'wpab__read-file' => 'wpab__ai__read-file',
                                    'wpab__read_file' => 'wpab__ai__read_file',
                                    'wpab__list-directory' => 'wpab__ai__list-directory',
                                    'wpab__list_directory' => 'wpab__ai__list_directory',
                                    'wpab__generate-image' => 'wpab__ai__generate-image',
                                    'wpab__generate_image' => 'wpab__ai__generate_image'
                                ];
                                
                                if (isset($missing_namespace_map[$name])) {
                                    $name = $missing_namespace_map[$name];
                                }
                                
                                if ($name !== $original_name) {
                                    $modified = true;
                                    $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall($fc->getId(), $name, $fc->getArgs());
                                    $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                                }
                            }
                            $rewritten_parts[] = $part;
                        }
                        
                        if ($modified) {
                            $response_message = new \WordPress\AiClient\Messages\DTO\ModelMessage($rewritten_parts);
                        }
                    }

                    $messages[] = $response_message;
                    
                    set_transient($conversation_id, wp_json_encode($messages), 3600);
                    
                    if ($resolver && $resolver->has_ability_calls($response_message)) {
                        $tool_info = [];
                        foreach ($response_message->getParts() as $part) {
                            if ($part->getFunctionCall() !== null) {
                                $tool_info[] = [
                                    'id' => $part->getFunctionCall()->getId(),
                                    'name' => $part->getFunctionCall()->getName(),
                                    'args' => $part->getFunctionCall()->getArgs()
                                ];
                            }
                        }
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response([
                            'success' => true,
                            'action' => 'tool_calls',
                            'conversation_id' => $conversation_id,
                            'tools' => $tool_info,
                            'previous_results' => $previous_results,
                            'token_usage' => $result->getTokenUsage()->toArray()
                        ], 200);
                    }
                    
                    $ai_text = trim($result->toText());
                    
                    // Fallback for models (like some local ones) that output raw JSON instead of native tool calls
                    $clean_json_text = preg_replace('/^```json\s*|```\s*$/i', '', $ai_text);
                    $clean_json_text = trim($clean_json_text);
                    if (strpos($clean_json_text, '{') === 0) {
                        $parsed_json = json_decode($clean_json_text, true);
                        if (json_last_error() !== JSON_ERROR_NONE && strpos($clean_json_text, '"name"') !== false) {
                            $parsed_json = [
                                'name' => 'wpab__syntax_error',
                                'arguments' => ['error' => 'Invalid JSON structure or unescaped placeholders in tool arguments. Ensure you use strict JSON.']
                            ];
                        }
                        
                        if (is_array($parsed_json) && array_key_exists('name', $parsed_json)) {
                            if (empty($parsed_json['name'])) {
                                $parsed_json = [
                                    'name' => 'wpab__syntax_error',
                                    'arguments' => ['error' => 'Tool name cannot be null or empty. Please provide a valid tool name or answer natively.']
                                ];
                            }
                            if (!array_key_exists('arguments', $parsed_json)) {
                                $parsed_json['arguments'] = new \stdClass();
                            }
                            
                            $tool_info = [
                                [
                                    'id' => 'call_' . substr(md5(uniqid()), 0, 8),
                                    'name' => $parsed_json['name'],
                                    'args' => is_string($parsed_json['arguments']) ? json_decode($parsed_json['arguments'], true) : $parsed_json['arguments']
                                ]
                            ];
                            
                            $fc = new \WordPress\AiClient\Tools\DTO\FunctionCall($tool_info[0]['id'], $tool_info[0]['name'], empty($tool_info[0]['args']) ? new \stdClass() : $tool_info[0]['args']);
                            $part = new \WordPress\AiClient\Messages\DTO\MessagePart($fc);
                            $messages[count($messages) - 1] = new \WordPress\AiClient\Messages\DTO\ModelMessage([$part]);
                            set_transient($conversation_id, wp_json_encode($messages), 3600);
                            
                            remove_filter('http_request_timeout', $timeout_filter, 999);
                            return new \WP_REST_Response([
                                'success' => true,
                                'action' => 'tool_calls',
                                'conversation_id' => $conversation_id,
                                'tools' => $tool_info,
                                'previous_results' => $previous_results,
                                'token_usage' => $result->getTokenUsage()->toArray()
                            ], 200);
                        }
                        
                        if (is_array($parsed_json)) {
                            $extracted_text = "";
                            if (isset($parsed_json['response'])) {
                                $extracted_text = is_string($parsed_json['response']) ? $parsed_json['response'] : json_encode($parsed_json['response']);
                            }
                            if (empty(trim($extracted_text))) {
                                $ai_text = "I'm sorry, I couldn't figure out how to process that request.";
                            } else {
                                $ai_text = $extracted_text;
                            }
                        }
                    }
                    
                    $display_text = $ai_text;
                    if (class_exists('\League\CommonMark\CommonMarkConverter')) {
                        if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
                            $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                        } else {
                            $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
                        }
                        $display_text = $converter->convert($ai_text)->getContent();
                    }

                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response([
                        'success' => true,
                        'action' => 'done',
                        'conversation_id' => $conversation_id,
                        'response' => $display_text,
                        'previous_results' => $previous_results,
                        'token_usage' => $result->getTokenUsage()->toArray()
                    ], 200);
                    
                } catch (\Throwable $e) {
                    $error_msg = str_replace('`', '', strtolower($e->getMessage()));
                    $error_code = $e->getCode();

                    if ($fallback_models) {
                        $is_fallback_error = ($error_code >= 400 && $error_code < 600) || preg_match('/\b[45][0-9]{2}\b/', $error_msg) || strpos($error_msg, 'api error') !== false || strpos($error_msg, 'upstream') !== false;
                        if ($is_fallback_error && isset($models_to_try[$model_index + 1])) {
                            $model_index++;
                            $current_model_to_try = $models_to_try[$model_index];
                            $i--; // Don't count this as a schema retry failure
                            continue;
                        }
                    }

                    if (!empty($functions) && (strpos($error_msg, 'tool calling is not supported') !== false || strpos($error_msg, 'tools are not supported') !== false || strpos($error_msg, 'support tool use') !== false || strpos($error_msg, 'failed to call a function') !== false || strpos($error_msg, 'does not support tools') !== false)) {
                        $retry_without_tools = true;
                        continue;
                    }
                    if (strpos($error_msg, 'does not support chat completions') !== false) {
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('The selected model does not support chat completions. Please select a different model from the dropdown.', 'aiutoma')], 400);
                    }
                    if (strpos($error_msg, 'tool call validation failed') !== false || strpos($error_msg, 'did not match schema') !== false) {
                        if ($i < 2) {
                            $messages[] = new \WordPress\AiClient\Messages\DTO\UserMessage([
                                new \WordPress\AiClient\Messages\DTO\MessagePart("System Error: Your previous function call failed validation: " . $e->getMessage() . "\nPlease strictly adhere to the declared schema and enum values, and try again.")
                            ]);
                            continue;
                        }
                    }
                    if (strpos($error_msg, 'reduce the length') !== false || strpos($error_msg, 'context length') !== false || strpos($error_msg, 'maximum context') !== false || strpos($error_msg, 'Request body too large') !== false || strpos($error_msg, '413') !== false) {
                        
                        // Auto-recover by dropping older conversation history to free up context window
                        if (count($messages) > 1 && $i < 3) {
                            // Keep only the very last message (the current query)
                            $messages = array_values(array_slice($messages, -1));
                            $i--; // Don't count this as a standard failure retry
                            continue;
                        }
                        
                        remove_filter('http_request_timeout', $timeout_filter, 999);
                        return new \WP_REST_Response(['success' => false, 'message' => __('The context is too large for the selected model (Error 413). This usually happens when the chat history is too long or when tool outputs (like reading large files or getting many posts) exceed the model\'s memory limit. Please try using a model with a larger context window, or start a "New Chat" to clear the memory.', 'aiutoma')], 400);
                    }
                    remove_filter('http_request_timeout', $timeout_filter, 999);
                    return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
                }
            }
        }
        
        return new \WP_REST_Response(['success' => false, 'message' => __('AI Client not available', 'aiutoma')], 400);
    }

    public function handle_get_abilities(\WP_REST_Request $request) {
        $abilities = function_exists('wp_get_abilities') ? wp_get_abilities() : [];
        $abilities = apply_filters('aiutoma/abilities', $abilities);
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        
        $data = [];
        foreach ($abilities as $ability) {
            $name = method_exists($ability, 'get_name') ? $ability->get_name() : '';
            if (empty($name)) continue;
            
            $meta = method_exists($ability, 'get_meta') ? $ability->get_meta() : [];
            $parts = explode('/', $name);
            $ns = count($parts) >= 2 ? $parts[0] : 'other';

            $group_label = ucwords(str_replace(['-', '_'], ' ', $ns));
            if (strtolower($group_label) === 'woocommerce') {
                $group_label = 'WooCommerce';
            } elseif (strtolower($group_label) === 'scf') {
                $group_label = 'SCF';
            }
            
            if (in_array($ns, ['wordpress', 'wp', 'core'])) {
                $group_label = 'WordPress Core';
            } elseif (get_stylesheet() === $ns || get_template() === $ns) {
                $group_label = 'Active Theme';
            } elseif ($ns === 'aiutoma') {
                $group_label = 'Aiutoma';
            } elseif ($ns === 'gutenberg') {
                $group_label = 'Gutenberg';
            } else {
                // If a plugin name is explicitly provided in meta, we can use it to format the namespace nicely
                if (!empty($meta['plugin_name'])) {
                    $group_label = $meta['plugin_name'];
                }
            }
            
            $data[] = [
                'name' => $name,
                'label' => method_exists($ability, 'get_label') ? $ability->get_label() : '',
                'group' => $group_label,
                'description' => method_exists($ability, 'get_description') ? $ability->get_description() : ''
            ];
        }
        
        $count = count($data);
        
        return new \WP_REST_Response([
            'success' => true,
            'abilities' => $data
        ], 200);
    }

    public function handle_convert_media(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $attachment_id = isset($params['attachment_id']) ? intval($params['attachment_id']) : 0;
        
        if (!$attachment_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid attachment ID'], 400);
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'File not found'], 404);
        }
        
        $mime_type = get_post_mime_type($attachment_id);
        $text = \Aiutoma\Modules\Ai\Ai::instance()->parse_document($file_path, $mime_type);
        
        if (strpos($text, 'Error:') === 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Conversion Failed: ' . $text
            ], 500);
        } else {
            return new \WP_REST_Response([
                'success' => true,
                'markdown' => $text
            ], 200);
        }
    }
}
