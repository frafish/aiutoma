<?php

namespace Aiutoma\Modules\Ai\Abilities;

if (!defined('ABSPATH')) {
    exit;
}

trait Gutenberg
{
    private function modify_block_tree(&$blocks, $path, $action, $payload = [])
    {
        $path_parts = explode('.', $path);
        $target_index = (int) array_pop($path_parts);

        $parent_block = null;
        $current_array = &$blocks;

        foreach ($path_parts as $index_str) {
            $index = (int) $index_str;
            if (!isset($current_array[$index])) {
                return new \WP_Error('invalid_path', 'Invalid block path at index ' . $index);
            }
            $parent_block = &$current_array[$index];
            if (!isset($parent_block['innerBlocks'])) {
                return new \WP_Error('invalid_path', 'Block at index ' . $index . ' has no innerBlocks');
            }
            $current_array = &$parent_block['innerBlocks'];
        }

        if ($action !== 'insert_prepend' && $action !== 'insert_append' && !isset($current_array[$target_index])) {
            return new \WP_Error('invalid_path', 'Target block not found at index ' . $target_index);
        }

        if ($action === 'update') {
            $target_block = &$current_array[$target_index];
            if (isset($payload['attributes'])) {
                $attrs = json_decode($payload['attributes'], true);
                if (is_array($attrs)) {
                    $target_block['attrs'] = array_merge($target_block['attrs'] ?? [], $attrs);
                }
            }
            if (isset($payload['inner_html'])) {
                $target_block['innerHTML'] = $payload['inner_html'];
                if (empty($target_block['innerBlocks'])) {
                    $target_block['innerContent'] = [$payload['inner_html']];
                } else {
                    $found = false;
                    foreach ($target_block['innerContent'] as $i => $chunk) {
                        if (is_string($chunk)) {
                            $target_block['innerContent'][$i] = $payload['inner_html'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) array_unshift($target_block['innerContent'], $payload['inner_html']);
                }
            }
        } elseif ($action === 'remove') {
            array_splice($current_array, $target_index, 1);
            if ($parent_block !== null) {
                $null_count = 0;
                for ($i = 0; $i < count($parent_block['innerContent']); $i++) {
                    if ($parent_block['innerContent'][$i] === null) {
                        if ($null_count === $target_index) {
                            array_splice($parent_block['innerContent'], $i, 1);
                            break;
                        }
                        $null_count++;
                    }
                }
            }
        } elseif (in_array($action, ['insert_before', 'insert_after', 'insert_prepend', 'insert_append'])) {
            $new_blocks_array = $payload['new_blocks'];
            if (empty($new_blocks_array)) return true;

            $num_new = count($new_blocks_array);

            $insert_index = 0;
            if ($action === 'insert_before') {
                $insert_index = $target_index;
            } elseif ($action === 'insert_after') {
                $insert_index = $target_index + 1;
            } elseif ($action === 'insert_prepend') {
                $parent_block = &$current_array[$target_index];
                if (!isset($parent_block['innerBlocks'])) {
                    $parent_block['innerBlocks'] = [];
                }
                if (!isset($parent_block['innerContent'])) {
                    $parent_block['innerContent'] = [];
                } elseif (count($parent_block['innerBlocks']) === 0 && count($parent_block['innerContent']) === 1 && is_string($parent_block['innerContent'][0])) {
                    if (preg_match('/^(.*)(<\/[a-z0-9]+>\s*)$/si', $parent_block['innerContent'][0], $m)) {
                        $parent_block['innerContent'] = [$m[1], $m[2]];
                    }
                }
                $current_array = &$parent_block['innerBlocks'];
                $insert_index = 0;
            } elseif ($action === 'insert_append') {
                $parent_block = &$current_array[$target_index];
                if (!isset($parent_block['innerBlocks'])) {
                    $parent_block['innerBlocks'] = [];
                }
                if (!isset($parent_block['innerContent'])) {
                    $parent_block['innerContent'] = [];
                } elseif (count($parent_block['innerBlocks']) === 0 && count($parent_block['innerContent']) === 1 && is_string($parent_block['innerContent'][0])) {
                    if (preg_match('/^(.*)(<\/[a-z0-9]+>\s*)$/si', $parent_block['innerContent'][0], $m)) {
                        $parent_block['innerContent'] = [$m[1], $m[2]];
                    }
                }
                $current_array = &$parent_block['innerBlocks'];
                $insert_index = count($current_array);
            }

            array_splice($current_array, $insert_index, 0, $new_blocks_array);

            if ($parent_block !== null) {
                $null_count = 0;
                $content_insert_index = count($parent_block['innerContent']);
                for ($i = 0; $i < count($parent_block['innerContent']); $i++) {
                    if ($parent_block['innerContent'][$i] === null) {
                        if ($null_count === $insert_index) {
                            $content_insert_index = $i;
                            break;
                        }
                        $null_count++;
                    }
                }

                if ($insert_index === (count($current_array) - $num_new)) {
                    $last_idx = count($parent_block['innerContent']) - 1;
                    if ($last_idx >= 0 && is_string($parent_block['innerContent'][$last_idx])) {
                        $content_insert_index = $last_idx;
                    } else {
                        $content_insert_index = $last_idx + 1;
                    }
                }

                $nulls = array_fill(0, $num_new, null);
                array_splice($parent_block['innerContent'], $content_insert_index, 0, $nulls);
            }
        }
        return true;
    }

    /**
     * Validates raw block HTML string or block structure.
     *
     * @param string|array $blocks Raw HTML string or block tree array.
     * @return array|\WP_Error Array with valid blocks and count, or WP_Error on syntax issues.
     */
    private function validate_block_syntax($blocks)
    {
        if (is_string($blocks)) {
            $raw_html = trim($blocks);
            if (empty($raw_html)) {
                return new \WP_Error('empty_block_html', __('Block HTML cannot be empty.', 'aiutoma'));
            }

            // Check for unclosed block comment tags
            $open_comments = preg_match_all('/<!--\s*wp:/i', $raw_html);
            $close_comments = preg_match_all('/-->/i', $raw_html);
            if ($open_comments > $close_comments) {
                return new \WP_Error('unclosed_block_comment', __('Malformed block comment: unclosed "<!-- wp:" block tag.', 'aiutoma'));
            }

            // Parse blocks using WordPress Core parser
            $parsed = parse_blocks($raw_html);
            $clean_blocks = [];
            foreach ($parsed as $b) {
                if ($b['blockName'] !== null || !empty(trim($b['innerHTML'] ?? ''))) {
                    $clean_blocks[] = $b;
                }
            }

            if (empty($clean_blocks)) {
                return new \WP_Error('invalid_block_syntax', __('Could not parse any valid Gutenberg blocks from the provided HTML.', 'aiutoma'));
            }

            return [
                'valid' => true,
                'block_count' => count($clean_blocks),
                'blocks' => $clean_blocks,
            ];
        }

        if (is_array($blocks)) {
            if (empty($blocks)) {
                return new \WP_Error('empty_blocks_array', __('Blocks array cannot be empty.', 'aiutoma'));
            }

            foreach ($blocks as $idx => $b) {
                if (!is_array($b)) {
                    /* translators: %d: block index */
                    return new \WP_Error('malformed_block', sprintf(__('Block at index %d is not a valid object.', 'aiutoma'), $idx));
                }
                if (!array_key_exists('blockName', $b) && !array_key_exists('innerHTML', $b)) {
                    /* translators: %d: block index */
                    return new \WP_Error('malformed_block', sprintf(__('Block at index %d is missing blockName and innerHTML.', 'aiutoma'), $idx));
                }
            }

            return [
                'valid' => true,
                'block_count' => count($blocks),
                'blocks' => $blocks,
            ];
        }

        return new \WP_Error('invalid_input', __('Input must be raw block HTML string or blocks array.', 'aiutoma'));
    }

    public function register_gutenberg_abilities()
    {
        if (!function_exists('wp_register_ability')) return;

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/get-theme-tokens', [
            'category' => 'gutenberg',
            'label' => __('Get Theme Tokens', 'aiutoma'),
            'description' => __('Retrieve the global theme settings (theme.json data) including colors, gradients, typography, and spacing presets. Use these exact slugs as CSS classes (e.g. has-{slug}-color) when building blocks.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                if (!class_exists('WP_Theme_JSON_Resolver')) {
                    return new \WP_Error('unsupported', 'WP_Theme_JSON_Resolver is not available in this WordPress version.');
                }
                $settings = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
                return ['success' => true, 'settings' => $settings];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass()
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/list-blocks', [
            'category' => 'gutenberg',
            'label' => __('List Gutenberg Blocks', 'aiutoma'),
            'description' => __('Get a list of all registered Gutenberg block types and their attributes schema.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $registry = \WP_Block_Type_Registry::get_instance();
                $blocks = $registry->get_all_registered();
                $data = [];
                $search = $input['search'] ?? '';
                foreach ($blocks as $name => $block) {
                    if (!empty($search) && strpos($name, $search) === false) continue;
                    $data[$name] = [
                        'title' => $block->title ?? $name,
                        'attributes' => $block->attributes ?? [],
                        'supports' => $block->supports ?? []
                    ];
                }
                return ['success' => true, 'blocks' => $data];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Optional string to filter block names (e.g. "core/")']
                ]
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/get-structure', [
            'category' => 'gutenberg',
            'label' => __('Get Gutenberg Structure', 'aiutoma'),
            'description' => __('Parse a post content and return a simplified block tree with Block Paths (e.g. 0.2.1) to target specific blocks.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = parse_blocks($post->post_content);

                $build_tree = function ($blocks_array, $parent_path = '') use (&$build_tree) {
                    $tree = [];
                    foreach ($blocks_array as $index => $block) {
                        if (empty($block['blockName'])) continue;
                        $current_path = $parent_path === '' ? (string)$index : $parent_path . '.' . $index;
                        $node = [
                            'path' => $current_path,
                            'name' => $block['blockName'],
                            'attrs' => $block['attrs'] ?? [],
                            'has_inner_html' => !empty(trim($block['innerHTML'])),
                        ];
                        if (!empty($block['innerBlocks'])) {
                            $node['inner_blocks'] = $build_tree($block['innerBlocks'], $current_path);
                        }
                        $tree[] = $node;
                    }
                    return $tree;
                };

                return ['success' => true, 'structure' => $build_tree($blocks)];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Post ID to parse']
                ],
                'required' => ['post_id']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/get-page-structure', [
            'category' => 'gutenberg',
            'label' => __('Get Gutenberg Page Structure', 'aiutoma'),
            'description' => __('Return a lightweight tree of the saved Gutenberg blocks in a post. Use the returned paths to request detailed context for specific blocks with gutenberg/get-block-context.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post_id = absint($input['post_id'] ?? 0);
                $post = $post_id ? get_post($post_id) : null;
                if (!$post) {
                    return new \WP_Error('not_found', __('Post not found.', 'aiutoma'));
                }
                if (!current_user_can('edit_post', $post_id)) {
                    return new \WP_Error('forbidden', __('You do not have permission to read this post.', 'aiutoma'));
                }

                $build_tree = function ($blocks_array, $parent_path = '') use (&$build_tree) {
                    $tree = [];
                    foreach ($blocks_array as $index => $block) {
                        if (empty($block['blockName'])) {
                            continue;
                        }

                        $path = $parent_path === '' ? (string) $index : $parent_path . '.' . $index;
                        $node = [
                            'path' => $path,
                            'name' => $block['blockName'],
                            'attrs' => $block['attrs'] ?? [],
                            'has_inner_html' => trim($block['innerHTML'] ?? '') !== '',
                            'child_count' => count($block['innerBlocks'] ?? []),
                        ];

                        if (!empty($block['innerBlocks'])) {
                            $node['inner_blocks'] = $build_tree($block['innerBlocks'], $path);
                        }
                        $tree[] = $node;
                    }
                    return $tree;
                };

                return [
                    'success' => true,
                    'post_id' => $post_id,
                    'post_modified_gmt' => $post->post_modified_gmt,
                    'structure' => $build_tree(parse_blocks($post->post_content)),
                ];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'The ID of the post or page to inspect.'],
                ],
                'required' => ['post_id'],
            ],
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/get-block-context', [
            'category' => 'gutenberg',
            'label' => __('Get Gutenberg Block Context', 'aiutoma'),
            'description' => __('Return detailed context for selected Gutenberg blocks identified by their structural paths, including the block, parent, siblings, and children. Use this after gutenberg/get-page-structure when local context is insufficient.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post_id = absint($input['post_id'] ?? 0);
                $post = $post_id ? get_post($post_id) : null;
                if (!$post) {
                    return new \WP_Error('not_found', __('Post not found.', 'aiutoma'));
                }
                if (!current_user_can('edit_post', $post_id)) {
                    return new \WP_Error('forbidden', __('You do not have permission to read this post.', 'aiutoma'));
                }

                $paths = $input['paths'] ?? [];
                if (!is_array($paths) || empty($paths) || count($paths) > 20) {
                    return new \WP_Error('invalid_paths', __('Provide between one and twenty block paths.', 'aiutoma'));
                }

                $blocks = parse_blocks($post->post_content);
                $find_block = function ($blocks_array, $path_parts, $current_path = '') use (&$find_block) {
                    $index = array_shift($path_parts);
                    if ($index === null || !isset($blocks_array[(int) $index])) {
                        return null;
                    }

                    $block = $blocks_array[(int) $index];
                    $path = $current_path === '' ? (string) $index : $current_path . '.' . $index;
                    if (empty($path_parts)) {
                        return [
                            'block' => $block,
                            'path' => $path,
                            'siblings' => $blocks_array,
                            'sibling_index' => (int) $index,
                        ];
                    }
                    if (empty($block['innerBlocks'])) {
                        return null;
                    }
                    return $find_block($block['innerBlocks'], $path_parts, $path);
                };

                $contexts = [];
                foreach ($paths as $requested_path) {
                    $path = is_string($requested_path) ? trim($requested_path) : '';
                    if ($path === '' || !preg_match('/^\d+(?:\.\d+)*$/', $path)) {
                        return new \WP_Error('invalid_path', __('Each block path must use dot notation, for example 0.2.1.', 'aiutoma'));
                    }

                    $found = $find_block($blocks, array_map('intval', explode('.', $path)));
                    if (!$found) {
                        /* translators: %s: block path */
                        return new \WP_Error('block_not_found', sprintf(__('Block not found at path %s.', 'aiutoma'), $path));
                    }

                    $path_parts = explode('.', $path);
                    array_pop($path_parts);
                    $parent = null;
                    if (!empty($path_parts)) {
                        $parent_found = $find_block($blocks, array_map('intval', $path_parts));
                        $parent = $parent_found ? $parent_found['block'] : null;
                    }

                    $contexts[] = [
                        'path' => $path,
                        'block' => $found['block'],
                        'parent' => $parent,
                        'siblings' => array_values(array_filter($found['siblings'], function ($sibling, $index) use ($found) {
                            return $index !== $found['sibling_index'];
                        }, ARRAY_FILTER_USE_BOTH)),
                    ];
                }

                return [
                    'success' => true,
                    'post_id' => $post_id,
                    'post_modified_gmt' => $post->post_modified_gmt,
                    'contexts' => $contexts,
                ];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'The ID of the post or page to inspect.'],
                    'paths' => [
                        'type' => 'array',
                        'description' => 'One or more block paths returned by gutenberg/get-page-structure, such as 0 or 0.2.1.',
                        'items' => ['type' => 'string'],
                        'maxItems' => 20,
                    ],
                ],
                'required' => ['post_id', 'paths'],
            ],
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/insert-block', [
            'category' => 'gutenberg',
            'label' => __('Insert Gutenberg Block', 'aiutoma'),
            'description' => __('Insert a new block at a specific Block Path in the tree. Provide raw Gutenberg HTML. Supports dry_run for syntax and path validation without persisting changes.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $validation = $this->validate_block_syntax($input['raw_block_html']);
                if (is_wp_error($validation)) {
                    return $validation;
                }
                $clean_new_blocks = $validation['blocks'];

                $blocks = parse_blocks($post->post_content);
                $blocks_working = $blocks;

                $action = 'insert_' . $input['position'];

                $result = $this->modify_block_tree($blocks_working, $input['target_path'], $action, ['new_blocks' => $clean_new_blocks]);
                if (is_wp_error($result)) return $result;

                $new_content = serialize_blocks($blocks_working);

                if (!empty($input['dry_run'])) {
                    return [
                        'success' => true,
                        'dry_run' => true,
                        'message' => 'Block syntax, position, and insertion path validated successfully. No changes were persisted.',
                        'target_path' => $input['target_path'],
                        'position' => $input['position'],
                        'inserted_blocks_count' => count($clean_new_blocks),
                    ];
                }

                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully inserted.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Target post or page ID.'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to target (e.g. 0.2)'],
                    'position' => ['type' => 'string', 'enum' => ['before', 'after', 'prepend', 'append'], 'description' => 'Where to insert relative to the path'],
                    'raw_block_html' => ['type' => 'string', 'description' => 'Raw Gutenberg HTML of the block(s) to insert'],
                    'dry_run' => ['type' => 'boolean', 'description' => 'If true, validates syntax, target path, and insertion without modifying the database.']
                ],
                'required' => ['post_id', 'target_path', 'position', 'raw_block_html']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/update-block', [
            'category' => 'gutenberg',
            'label' => __('Update Gutenberg Block', 'aiutoma'),
            'description' => __('Update attributes or content of an existing block at a specific Block Path. Supports dry_run for safe validation without saving.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                if (isset($input['attributes']) && is_string($input['attributes'])) {
                    $decoded = json_decode($input['attributes'], true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        return new \WP_Error('invalid_json', 'Attributes must be a valid JSON string: ' . json_last_error_msg());
                    }
                }

                $blocks = parse_blocks($post->post_content);
                $blocks_working = $blocks;

                $result = $this->modify_block_tree($blocks_working, $input['target_path'], 'update', [
                    'attributes' => $input['attributes'] ?? null,
                    'inner_html' => $input['inner_html'] ?? null
                ]);
                if (is_wp_error($result)) return $result;

                $new_content = serialize_blocks($blocks_working);

                if (!empty($input['dry_run'])) {
                    return [
                        'success' => true,
                        'dry_run' => true,
                        'message' => 'Block update validated successfully at path ' . $input['target_path'] . '. No changes were persisted.',
                        'target_path' => $input['target_path'],
                    ];
                }

                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully updated.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Target post or page ID.'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to the block to update (e.g. 0.2)'],
                    'attributes' => ['type' => 'string', 'description' => 'JSON encoded string of attributes to merge (optional)'],
                    'inner_html' => ['type' => 'string', 'description' => 'New HTML content for the block (optional)'],
                    'dry_run' => ['type' => 'boolean', 'description' => 'If true, validates target path and parameters without modifying the database.']
                ],
                'required' => ['post_id', 'target_path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/remove-block', [
            'category' => 'gutenberg',
            'label' => __('Remove Gutenberg Block', 'aiutoma'),
            'description' => __('Remove a block at a specific Block Path from the tree. Supports dry_run.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = parse_blocks($post->post_content);
                $blocks_working = $blocks;

                $result = $this->modify_block_tree($blocks_working, $input['target_path'], 'remove');
                if (is_wp_error($result)) return $result;

                if (!empty($input['dry_run'])) {
                    return [
                        'success' => true,
                        'dry_run' => true,
                        'message' => 'Block removal path validated successfully. No changes were persisted.',
                        'target_path' => $input['target_path'],
                    ];
                }

                $new_content = serialize_blocks($blocks_working);
                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully removed.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Target post or page ID.'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to the block to remove (e.g. 0.2)'],
                    'dry_run' => ['type' => 'boolean', 'description' => 'If true, validates target path without modifying the database.']
                ],
                'required' => ['post_id', 'target_path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/wp-patterns', [
            'category' => 'gutenberg',
            'label' => __('WordPress Patterns Library', 'aiutoma'),
            'description' => __('Search and fetch block patterns from the active theme\'s local patterns registry and official WordPress.org directory. Returns ready-to-use Gutenberg HTML. Supports action="categories" to discover available categories, and source="all"|"local"|"remote".', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $action = $input['action'] ?? 'search';

                // Action: list categories
                if ($action === 'categories') {
                    $categories = [];
                    if (class_exists('\WP_Block_Pattern_Categories_Registry')) {
                        $cat_registry = \WP_Block_Pattern_Categories_Registry::get_instance();
                        $categories = $cat_registry->get_all_registered();
                    }
                    return [
                        'success' => true,
                        'total_categories' => count($categories),
                        'categories' => $categories,
                    ];
                }

                $source = $input['source'] ?? 'all';
                $search = trim($input['search'] ?? '');
                $category = trim($input['category'] ?? '');
                $limit = isset($input['limit']) ? min(max(absint($input['limit']), 1), 30) : 10;

                $results = [];

                // 1. Query local patterns (active theme + registered patterns)
                if ($source === 'all' || $source === 'local') {
                    if (class_exists('\WP_Block_Patterns_Registry')) {
                        $registry = \WP_Block_Patterns_Registry::get_instance();
                        $local_patterns = $registry->get_all_registered();

                        foreach ($local_patterns as $pattern) {
                            if (!empty($category)) {
                                $pat_cats = array_map('sanitize_title', $pattern['categories'] ?? []);
                                if (!in_array(sanitize_title($category), $pat_cats, true)) {
                                    continue;
                                }
                            }

                            if (!empty($search)) {
                                $search_lower = mb_strtolower($search);
                                $matched = false;
                                $fields_to_check = [
                                    $pattern['name'] ?? '',
                                    $pattern['title'] ?? '',
                                    $pattern['description'] ?? '',
                                    implode(' ', $pattern['keywords'] ?? []),
                                ];
                                foreach ($fields_to_check as $field) {
                                    if (mb_stripos($field, $search_lower) !== false) {
                                        $matched = true;
                                        break;
                                    }
                                }
                                if (!$matched) {
                                    continue;
                                }
                            }

                            $results[] = [
                                'title' => $pattern['title'] ?? '',
                                'name' => $pattern['name'] ?? '',
                                'source' => 'local',
                                'categories' => $pattern['categories'] ?? [],
                                'content' => $pattern['content'] ?? '',
                                'viewport_width' => $pattern['viewportWidth'] ?? 1200,
                            ];

                            if (count($results) >= $limit) {
                                break;
                            }
                        }
                    }
                }

                // 2. Query remote patterns from WordPress.org
                if ($source === 'remote' || ($source === 'all' && count($results) < $limit)) {
                    $remote_limit = $limit - count($results);
                    $url = 'https://api.wordpress.org/patterns/1.0/?';
                    if (!empty($search)) $url .= 'search=' . urlencode($search) . '&';
                    if (!empty($category)) $url .= 'pattern-categories=' . urlencode($category) . '&';

                    $response = wp_remote_get($url, ['timeout' => 10]);
                    if (!is_wp_error($response)) {
                        $body = wp_remote_retrieve_body($response);
                        $data = json_decode($body, true);
                        if (is_array($data)) {
                            foreach (array_slice($data, 0, $remote_limit) as $pattern) {
                                $results[] = [
                                    'title' => $pattern['title']['rendered'] ?? '',
                                    'name' => $pattern['slug'] ?? '',
                                    'source' => 'remote',
                                    'categories' => $pattern['pattern-categories'] ?? [],
                                    'content' => $pattern['content'] ?? '',
                                    'viewport_width' => $pattern['viewport_width'] ?? 1200,
                                ];
                            }
                        }
                    }
                }

                if (empty($results)) {
                    return [
                        'success' => true,
                        'count' => 0,
                        'patterns' => [],
                        'message' => __('No patterns found matching your query.', 'aiutoma')
                    ];
                }

                return [
                    'success' => true,
                    'count' => count($results),
                    'patterns' => $results,
                ];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['search', 'categories'],
                        'description' => 'The action to perform: "search" (default) to find patterns, or "categories" to list all registered pattern categories.',
                    ],
                    'search' => [
                        'type' => 'string',
                        'description' => 'Keyword to search for in pattern titles, descriptions, and tags (e.g. "hero", "header", "pricing").',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Filter by category slug (e.g. "headers", "footers", "call-to-action", "pages", "services").',
                    ],
                    'source' => [
                        'type' => 'string',
                        'enum' => ['all', 'local', 'remote'],
                        'description' => 'Where to search: "all" (local theme patterns first, fallback to WordPress.org), "local" (active theme only), or "remote" (WordPress.org directory only). Default: "all".',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of patterns to return (default: 10, max: 30).',
                    ],
                ],
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/read', [
            'category' => 'gutenberg',
            'label' => __('Read Gutenberg Blocks', 'aiutoma'),
            'description' => __('Reads a post and parses its Gutenberg HTML content into a structured JSON array of blocks.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post_id = intval($input['post_id']);
                $post = get_post($post_id);
                if (!$post) return new \WP_Error('invalid_post', 'Post not found.');

                $blocks = parse_blocks($post->post_content);
                // Clean up completely empty blocks
                $clean_blocks = array_values(array_filter($blocks, function ($b) {
                    return !empty($b['blockName']) || trim($b['innerHTML']) !== '';
                }));

                return ['success' => true, 'blocks' => $clean_blocks];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'The ID of the post or page to read.']
                ],
                'required' => ['post_id']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/update', [
            'category' => 'gutenberg',
            'label' => __('Update Gutenberg Blocks', 'aiutoma'),
            'description' => __('Updates a post\'s content by providing a structured JSON array of Gutenberg blocks (previously read via gutenberg-read). The blocks will be serialized back to HTML. Supports dry_run for validation without saving.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post_id = intval($input['post_id']);
                $post = get_post($post_id);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = $input['blocks'] ?? [];
                $validation = $this->validate_block_syntax($blocks);
                if (is_wp_error($validation)) {
                    return $validation;
                }

                $content = serialize_blocks($blocks);

                if (!empty($input['dry_run'])) {
                    return [
                        'success' => true,
                        'dry_run' => true,
                        'message' => 'Blocks array validated and serialized successfully. No changes were persisted.',
                        'post_id' => $post_id,
                        'block_count' => count($blocks),
                    ];
                }

                $updated = wp_update_post([
                    'ID' => $post_id,
                    'post_content' => wp_slash($content)
                ], true);

                if (is_wp_error($updated)) return $updated;
                return ['success' => true, 'post_id' => $post_id];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'The ID of the post or page to update.'],
                    'blocks' => [
                        'type' => 'array',
                        'description' => 'An array of Gutenberg block objects to serialize and save as post content. Each object must have a blockName and innerHTML.',
                        'items' => ['type' => 'object']
                    ],
                    'dry_run' => [
                        'type' => 'boolean',
                        'description' => 'If true, validates the block objects and serialization without modifying the post in the database.'
                    ]
                ],
                'required' => ['post_id', 'blocks']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/manage-templates', [
            'category' => 'gutenberg',
            'label' => __('Manage FSE Templates & Parts', 'aiutoma'),
            'description' => __('List, read, create, or update Full Site Editing (FSE) templates (wp_template), template parts (wp_template_part like header, footer), and synced patterns (wp_block). Supports dry_run for safe preview and validation without saving.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $action = $input['action'] ?? 'list';

                // Action: LIST
                if ($action === 'list') {
                    $type = $input['type'] ?? 'all';
                    $items = [];

                    // Templates
                    if (in_array($type, ['all', 'template'], true) && function_exists('get_block_templates')) {
                        $templates = get_block_templates([], 'wp_template');
                        foreach ($templates as $tmpl) {
                            $items[] = [
                                'id' => $tmpl->id,
                                'slug' => $tmpl->slug,
                                'type' => 'template',
                                'title' => $tmpl->title,
                                'description' => $tmpl->description,
                                'source' => $tmpl->source,
                                'status' => $tmpl->status,
                                'is_custom' => !empty($tmpl->is_custom),
                                'has_theme_file' => !empty($tmpl->has_theme_file),
                            ];
                        }
                    }

                    // Template Parts
                    if (in_array($type, ['all', 'template_part'], true) && function_exists('get_block_templates')) {
                        $parts = get_block_templates([], 'wp_template_part');
                        foreach ($parts as $part) {
                            $items[] = [
                                'id' => $part->id,
                                'slug' => $part->slug,
                                'type' => 'template_part',
                                'title' => $part->title,
                                'area' => $part->area ?? 'general',
                                'description' => $part->description,
                                'source' => $part->source,
                                'status' => $part->status,
                                'is_custom' => !empty($part->is_custom),
                                'has_theme_file' => !empty($part->has_theme_file),
                            ];
                        }
                    }

                    // Synced Patterns (wp_block post type)
                    if (in_array($type, ['all', 'synced_pattern'], true)) {
                        $synced = get_posts([
                            'post_type' => 'wp_block',
                            'post_status' => 'any',
                            'posts_per_page' => 50,
                        ]);
                        foreach ($synced as $sp) {
                            $items[] = [
                                'id' => (string) $sp->ID,
                                'slug' => $sp->post_name,
                                'type' => 'synced_pattern',
                                'title' => $sp->post_title,
                                'source' => 'custom',
                                'status' => $sp->post_status,
                                'is_custom' => true,
                            ];
                        }
                    }

                    return [
                        'success' => true,
                        'total' => count($items),
                        'templates' => $items,
                    ];
                }

                // Action: GET
                if ($action === 'get') {
                    $target_id = $input['id'] ?? $input['slug'] ?? '';
                    if (empty($target_id)) {
                        return new \WP_Error('missing_id', __('Template "id" or "slug" is required for get.', 'aiutoma'));
                    }

                    $type = $input['type'] ?? '';
                    $found = null;

                    if ((empty($type) || $type === 'template') && function_exists('get_block_template')) {
                        $found = get_block_template($target_id, 'wp_template');
                        if ($found) {
                            $type = 'template';
                        }
                    }

                    if (!$found && (empty($type) || $type === 'template_part') && function_exists('get_block_template')) {
                        $found = get_block_template($target_id, 'wp_template_part');
                        if ($found) {
                            $type = 'template_part';
                        }
                    }

                    if (!$found && (empty($type) || $type === 'synced_pattern')) {
                        if (is_numeric($target_id)) {
                            $post = get_post(absint($target_id));
                        } else {
                            $posts = get_posts(['name' => sanitize_title($target_id), 'post_type' => 'wp_block', 'post_status' => 'any', 'posts_per_page' => 1]);
                            $post = !empty($posts) ? $posts[0] : null;
                        }
                        if ($post && $post->post_type === 'wp_block') {
                            return [
                                'success' => true,
                                'id' => (string) $post->ID,
                                'slug' => $post->post_name,
                                'type' => 'synced_pattern',
                                'title' => $post->post_title,
                                'content' => $post->post_content,
                                'status' => $post->post_status,
                            ];
                        }
                    }

                    if (!$found) {
                        /* translators: %s: template or pattern identifier */
                        return new \WP_Error('template_not_found', sprintf(__('Template or pattern "%s" not found.', 'aiutoma'), $target_id));
                    }

                    return [
                        'success' => true,
                        'id' => $found->id,
                        'slug' => $found->slug,
                        'type' => $type,
                        'title' => $found->title,
                        'description' => $found->description,
                        'area' => $found->area ?? null,
                        'source' => $found->source,
                        'content' => $found->content,
                        'status' => $found->status,
                        'is_custom' => !empty($found->is_custom),
                    ];
                }

                // Action: CREATE
                if ($action === 'create') {
                    $type = $input['type'] ?? '';
                    $title = sanitize_text_field($input['title'] ?? '');
                    $slug = sanitize_title($input['slug'] ?? $title);
                    $content = $input['content'] ?? '';
                    $dry_run = !empty($input['dry_run']);

                    if (empty($type) || !in_array($type, ['template', 'template_part', 'synced_pattern'], true)) {
                        return new \WP_Error('invalid_type', __('Valid type ("template", "template_part", or "synced_pattern") is required for create.', 'aiutoma'));
                    }

                    if (empty($content)) {
                        return new \WP_Error('missing_content', __('Content (block HTML) is required to create a template or pattern.', 'aiutoma'));
                    }

                    $validation = $this->validate_block_syntax($content);
                    if (is_wp_error($validation)) {
                        return $validation;
                    }

                    if ($dry_run) {
                        return [
                            'success' => true,
                            'dry_run' => true,
                            'message' => __('Template content validated successfully. No changes were persisted.', 'aiutoma'),
                            'type' => $type,
                            'slug' => $slug,
                            'parsed_blocks_count' => $validation['block_count'],
                        ];
                    }

                    if ($type === 'synced_pattern') {
                        $post_id = wp_insert_post([
                            'post_type' => 'wp_block',
                            'post_title' => $title ?: $slug,
                            'post_name' => $slug,
                            'post_content' => wp_slash($content),
                            'post_status' => 'publish',
                        ], true);

                        if (is_wp_error($post_id)) {
                            return $post_id;
                        }

                        return [
                            'success' => true,
                            'id' => (string) $post_id,
                            'type' => 'synced_pattern',
                            'slug' => $slug,
                            'message' => __('Synced pattern created successfully.', 'aiutoma'),
                        ];
                    }

                    $wp_type = ($type === 'template_part') ? 'wp_template_part' : 'wp_template';
                    $theme = wp_get_theme()->get_stylesheet();
                    $post_args = [
                        'post_type' => $wp_type,
                        'post_status' => 'publish',
                        'post_title' => $title ?: $slug,
                        'post_name' => $slug,
                        'post_content' => wp_slash($content),
                        'tax_input' => [
                            'wp_theme' => [$theme],
                        ],
                    ];

                    if ($type === 'template_part') {
                        $area = sanitize_key($input['area'] ?? 'general');
                        $post_args['tax_input']['wp_template_part_area'] = [$area];
                    }

                    $post_id = wp_insert_post($post_args, true);
                    if (is_wp_error($post_id)) {
                        return $post_id;
                    }

                    return [
                        'success' => true,
                        'id' => $theme . '//' . $slug,
                        'type' => $type,
                        'slug' => $slug,
                        /* translators: %s: template type */
                        'message' => sprintf(__('%s created successfully.', 'aiutoma'), ucfirst(str_replace('_', ' ', $type))),
                    ];
                }

                // Action: UPDATE
                if ($action === 'update') {
                    $target_id = $input['id'] ?? $input['slug'] ?? '';
                    if (empty($target_id)) {
                        return new \WP_Error('missing_id', __('Template "id" or "slug" is required for update.', 'aiutoma'));
                    }

                    $content = $input['content'] ?? '';
                    if (empty($content)) {
                        return new \WP_Error('missing_content', __('Content (block HTML) is required to update a template.', 'aiutoma'));
                    }

                    $validation = $this->validate_block_syntax($content);
                    if (is_wp_error($validation)) {
                        return $validation;
                    }

                    $dry_run = !empty($input['dry_run']);
                    if ($dry_run) {
                        return [
                            'success' => true,
                            'dry_run' => true,
                            'message' => __('Template update validated successfully. No changes were persisted.', 'aiutoma'),
                            'id' => $target_id,
                            'parsed_blocks_count' => $validation['block_count'],
                        ];
                    }

                    $type = $input['type'] ?? '';

                    // Synced pattern
                    if ($type === 'synced_pattern' || (is_numeric($target_id) && get_post_type($target_id) === 'wp_block')) {
                        $post_id = absint($target_id);
                        $update_data = [
                            'ID' => $post_id,
                            'post_content' => wp_slash($content),
                        ];
                        if (!empty($input['title'])) {
                            $update_data['post_title'] = sanitize_text_field($input['title']);
                        }
                        $updated = wp_update_post($update_data, true);
                        if (is_wp_error($updated)) {
                            return $updated;
                        }
                        return [
                            'success' => true,
                            'id' => (string) $post_id,
                            'type' => 'synced_pattern',
                            'message' => __('Synced pattern updated successfully.', 'aiutoma'),
                        ];
                    }

                    $wp_type = ($type === 'template_part') ? 'wp_template_part' : 'wp_template';
                    $template = null;
                    if (function_exists('get_block_template')) {
                        $template = get_block_template($target_id, $wp_type);
                        if (!$template && empty($type)) {
                            $alt_type = ($wp_type === 'wp_template') ? 'wp_template_part' : 'wp_template';
                            $template = get_block_template($target_id, $alt_type);
                            if ($template) {
                                $wp_type = $alt_type;
                            }
                        }
                    }

                    if (!$template) {
                        /* translators: %s: template identifier */
                        return new \WP_Error('template_not_found', sprintf(__('Template "%s" could not be found to update.', 'aiutoma'), $target_id));
                    }

                    if (!empty($template->wp_id)) {
                        $update_data = [
                            'ID' => $template->wp_id,
                            'post_content' => wp_slash($content),
                        ];
                        if (!empty($input['title'])) {
                            $update_data['post_title'] = sanitize_text_field($input['title']);
                        }
                        $res = wp_update_post($update_data, true);
                        if (is_wp_error($res)) {
                            return $res;
                        }
                    } else {
                        $theme = wp_get_theme()->get_stylesheet();
                        $post_args = [
                            'post_type' => $wp_type,
                            'post_status' => 'publish',
                            'post_title' => !empty($input['title']) ? sanitize_text_field($input['title']) : $template->title,
                            'post_name' => $template->slug,
                            'post_content' => wp_slash($content),
                            'tax_input' => [
                                'wp_theme' => [$theme],
                            ],
                        ];
                        if ($wp_type === 'wp_template_part' && !empty($template->area)) {
                            $post_args['tax_input']['wp_template_part_area'] = [$template->area];
                        }
                        $res = wp_insert_post($post_args, true);
                        if (is_wp_error($res)) {
                            return $res;
                        }
                    }

                    return [
                        'success' => true,
                        'id' => $template->id,
                        'type' => ($wp_type === 'wp_template_part') ? 'template_part' : 'template',
                        'message' => __('Template updated successfully.', 'aiutoma'),
                    ];
                }

                return new \WP_Error('invalid_action', __('Invalid action. Supported actions are "list", "get", "create", and "update".', 'aiutoma'));
            },
            'permission_callback' => function () {
                return current_user_can('edit_theme_options') || current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['list', 'get', 'create', 'update'],
                        'description' => 'Action to perform: list, get, create, or update.',
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['template', 'template_part', 'synced_pattern', 'all'],
                        'description' => 'Target type: "template" (wp_template), "template_part" (wp_template_part), "synced_pattern" (wp_block), or "all" (for list).',
                    ],
                    'id' => [
                        'type' => 'string',
                        'description' => 'Identifier of the template/part/pattern (e.g. "twentytwentyfour//header", "single", or post ID for synced pattern).',
                    ],
                    'slug' => [
                        'type' => 'string',
                        'description' => 'Slug of the template/part (e.g. "header", "custom-archive").',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Human-readable title for create/update.',
                    ],
                    'area' => [
                        'type' => 'string',
                        'description' => 'Area for template parts: "header", "footer", "sidebar", or "general".',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Raw Gutenberg block HTML content for create/update.',
                    ],
                    'dry_run' => [
                        'type' => 'boolean',
                        'description' => 'If true, validates block syntax and parameters without persisting to the database.',
                    ],
                ],
                'required' => ['action'],
            ],
        ]);

        $this->register_validate_blocks();
    }

    private function get_html_block_policy_issues($content)
    {
        $block_comment_pattern = '/<!--\s*\/?wp:[^>]*-->/s';
        $single_script_pattern = '/^<script(?:\s[^>]*)?>[\s\S]*<\/script>\s*$/i';
        $single_svg_pattern = '/^<svg(?:\s[^>]*)?>[\s\S]*<\/svg>\s*$/i';
        $interaction_markup_pattern = '/<(marquee)\b/i';
        $form_markup_pattern = '/<(form|input|select|textarea|fieldset)\b/i';

        $html = trim(preg_replace($block_comment_pattern, '', $content));
        if ($html === '') {
            return [];
        }

        if (preg_match($single_script_pattern, $html) || preg_match($single_svg_pattern, $html) || preg_match($interaction_markup_pattern, $html)) {
            return [];
        }

        if (preg_match($form_markup_pattern, $html)) {
            return [__('core/html contains form markup. Load the plugin-recommendations skill and use editable plugin blocks such as Jetpack Forms. This keeps forms editable in the block editor and handles submission without custom backend code.', 'aiutoma')];
        }

        return [__('core/html contains markup that should use editable core blocks. Load the block-content skill and use core/group, core/columns, core/heading, core/paragraph, core/list, core/image, core/buttons, and theme CSS instead. Keep core/html only for inline SVG, interaction markup with no block equivalent (marquee, cursor), or a single script block.', 'aiutoma')];
    }

    private function validate_html_block_policy($content)
    {
        $html_block_pattern = '/<!--\s*wp:html(?:\s+[\s\S]*?)?\s*-->([\s\S]*?)<!--\s*\/wp:html\s*-->/s';
        $invalid_html_blocks = [];
        $total_html_blocks = 0;

        if (preg_match_all($html_block_pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $total_html_blocks = count($matches[0]);
            for ($i = 0; $i < $total_html_blocks; $i++) {
                $block_content = $matches[1][$i][0] ?? '';
                $offset = $matches[0][$i][1] ?? 0;
                $issues = $this->get_html_block_policy_issues($block_content);
                if (empty($issues)) {
                    continue;
                }
                $line_number = substr_count(substr($content, 0, $offset), "\n") + 1;
                $trimmed_content = trim($block_content);
                $compact = preg_replace('/\s+/', ' ', $trimmed_content);
                $preview = strlen($compact) > 500 ? substr($compact, 0, 500) . '…' : $compact;

                $invalid_html_blocks[] = [
                    'blockNumber' => $i + 1,
                    'line'        => $line_number,
                    'content'     => $preview,
                    'issues'      => $issues,
                ];
            }
        }

        return [
            'totalHtmlBlocks'   => $total_html_blocks,
            'invalidHtmlBlocks' => $invalid_html_blocks,
        ];
    }

    private function generate_unified_patch($file_name, $old_str, $new_str)
    {
        if (function_exists('exec')) {
            $temp_old = wp_tempnam('diff_old');
            $temp_new = wp_tempnam('diff_new');
            file_put_contents($temp_old, $old_str);
            file_put_contents($temp_new, $new_str);
            $cmd = sprintf(
                'diff -u --label %s --label %s %s %s',
                escapeshellarg("a/{$file_name}"),
                escapeshellarg("b/{$file_name}"),
                escapeshellarg($temp_old),
                escapeshellarg($temp_new)
            );
            $diff_output = [];
            exec($cmd, $diff_output);
            @unlink($temp_old);
            @unlink($temp_new);
            if (!empty($diff_output)) {
                return implode("\n", $diff_output);
            }
        }

        // Lightweight pure PHP fallback
        $old_lines = explode("\n", str_replace("\r\n", "\n", $old_str));
        $new_lines = explode("\n", str_replace("\r\n", "\n", $new_str));
        $diff = ["--- a/{$file_name}", "+++ b/{$file_name}", "@@ -1," . count($old_lines) . " +1," . count($new_lines) . " @@"];
        foreach ($old_lines as $l) {
            $diff[] = "- {$l}";
        }
        foreach ($new_lines as $l) {
            $diff[] = "+ {$l}";
        }
        return implode("\n", $diff);
    }

    private function register_validate_blocks()
    {
        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/validate-blocks', [
            'category' => 'gutenberg',
            'label' => __('Validate Blocks', 'aiutoma'),
            'meta' => [
                'plugin_name' => 'Aiutoma',
                'mcp' => ['public' => true]
            ],
            'description' => __('Validates WordPress block content in two stages and returns a combined report. First runs a static core/html block policy check; if it finds invalid core/html blocks, it returns only those (rewrite them as editable core or plugin blocks and call again) without touching the editor. Once the policy check passes, it validates the content in the site\'s real block editor: with filePath it applies safe live-editor serialization fixes directly to the file and returns a CSS-review diff; with inline content it returns the exact fixed block content plus the diff.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $block_content = null;
                $file_name = 'inline content';
                $should_apply_fix = false;
                $file_path = null;

                if (!empty($input['filePath']) && is_string($input['filePath'])) {
                    $file_path = wp_normalize_path($input['filePath']);
                    if (!is_file($file_path) || !is_readable($file_path)) {
                        return new \WP_Error('file_not_found', sprintf(__('File not found or unreadable: %s', 'aiutoma'), $file_path));
                    }
                    $block_content = file_get_contents($file_path);
                    $file_name = basename(dirname($file_path)) . '/' . basename($file_path);
                    $should_apply_fix = true;
                } elseif (isset($input['content']) && is_string($input['content'])) {
                    $block_content = $input['content'];
                } else {
                    return new \WP_Error('missing_input', __('Either content or filePath must be provided.', 'aiutoma'));
                }

                // Stage 1: HTML block policy check
                $html_report = $this->validate_html_block_policy($block_content);
                if (!empty($html_report['invalidHtmlBlocks'])) {
                    $lines = [
                        sprintf('HTML block policy: %d/%d core/html blocks invalid', count($html_report['invalidHtmlBlocks']), $html_report['totalHtmlBlocks']),
                        '',
                        'Invalid HTML blocks:'
                    ];
                    foreach ($html_report['invalidHtmlBlocks'] as $block) {
                        $lines[] = sprintf('  - #%d line %d', $block['blockNumber'], $block['line']);
                        foreach ($block['issues'] as $issue) {
                            $lines[] = '    ' . $issue;
                        }
                        $lines[] = '    Content: ' . $block['content'];
                    }
                    $lines[] = '';
                    $lines[] = 'Rewrite each invalid core/html block as editable core or plugin blocks, then call validate_blocks again. Editor validation was skipped until the HTML policy passes.';

                    return implode("\n", $lines);
                }

                $html_summary = ($html_report['totalHtmlBlocks'] === 0)
                    ? 'HTML block policy: no core/html blocks found.'
                    : sprintf('HTML block policy: all %d core/html blocks within policy.', $html_report['totalHtmlBlocks']);

                // Stage 2: Live Editor Validation
                $parsed_blocks = parse_blocks($block_content);
                $results = [];
                $registry = \WP_Block_Type_Registry::get_instance();

                $validate_recursive = function ($blocks) use (&$validate_recursive, &$results, $registry) {
                    foreach ($blocks as $b) {
                        $name = $b['blockName'] ?? null;
                        if (!$name || $name === 'core/freeform' || $name === 'core/missing') {
                            continue;
                        }
                        $is_registered = $registry->is_registered($name);
                        $issues = [];
                        $is_valid = true;
                        if (!$is_registered) {
                            $is_valid = false;
                            $issues[] = sprintf('Block type "%s" is not registered. It may require a plugin that is not active.', $name);
                        }
                        $results[] = [
                            'blockName' => $name,
                            'isValid' => $is_valid,
                            'issues' => $issues
                        ];
                        if (!empty($b['innerBlocks'])) {
                            $validate_recursive($b['innerBlocks']);
                        }
                    }
                };
                $validate_recursive($parsed_blocks);

                $total_blocks = count($results);
                $valid_blocks = count(array_filter($results, function ($r) { return $r['isValid']; }));
                $invalid_blocks = $total_blocks - $valid_blocks;

                // Normalize and serialize
                $fixed_content = serialize_blocks($parsed_blocks);
                $content_matches = (trim($fixed_content) === trim($block_content));

                if ($invalid_blocks === 0 && $content_matches) {
                    return implode("\n", [
                        $html_summary,
                        sprintf('Validation: %d/%d blocks valid', $valid_blocks, $total_blocks),
                        'No editor serialization fixes needed.'
                    ]);
                }

                $lines = [
                    $html_summary,
                    sprintf('Validation: %d/%d blocks valid', $valid_blocks, $total_blocks),
                    ''
                ];

                if ($invalid_blocks > 0) {
                    $lines[] = 'Invalid blocks:';
                    foreach ($results as $r) {
                        if (!$r['isValid']) {
                            $lines[] = '  - ' . $r['blockName'];
                            foreach ($r['issues'] as $iss) {
                                $lines[] = '    ' . $iss;
                            }
                        }
                    }
                    $lines[] = '';
                }

                if (!$content_matches) {
                    $diff = $this->generate_unified_patch($file_name, $block_content, $fixed_content);
                    if ($should_apply_fix && $file_path) {
                        file_put_contents($file_path, $fixed_content);
                        $lines[] = sprintf('Auto-fix applied: %d/%d blocks valid after live-editor serialization.', $valid_blocks, $total_blocks);
                        $lines[] = sprintf('The fixed block content has already been written to %s. Do not replace it manually. Use the diff only to review class/nesting changes and update CSS selectors if needed.', $file_name);
                    } else {
                        $lines[] = sprintf('Auto-fix proposal: %d/%d blocks valid after live-editor serialization.', $valid_blocks, $total_blocks);
                        $lines[] = 'Use the fixed block content below as the replacement block content. Use the diff only to review class/nesting changes and update CSS selectors if needed.';
                        $lines[] = '';
                        $lines[] = 'Fixed block content:';
                        $lines[] = "```html\n{$fixed_content}\n```";
                    }
                    $lines[] = '';
                    $lines[] = 'Diff for CSS review:';
                    $lines[] = "```diff\n{$diff}\n```";
                } else {
                    $lines[] = 'No automatic editor serialization fix was available.';
                }

                return implode("\n", $lines);
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'nameOrPath' => [
                        'type' => 'string',
                        'description' => 'The site name or file system path (defaults to current WordPress site).'
                    ],
                    'filePath' => [
                        'type' => 'string',
                        'description' => 'Path to a file containing WordPress block content to validate and fix.'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Raw WordPress block content (HTML with block comments) to validate and fix.'
                    ]
                ]
            ]
        ]);
    }
}
