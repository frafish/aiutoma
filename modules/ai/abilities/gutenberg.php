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

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/insert-block', [
            'category' => 'gutenberg',
            'label' => __('Insert Gutenberg Block', 'aiutoma'),
            'description' => __('Insert a new block at a specific Block Path in the tree. You must provide the exact raw Gutenberg HTML for the block to insert, including the comment tags.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = parse_blocks($post->post_content);

                $new_blocks_parsed = parse_blocks($input['raw_block_html']);
                $clean_new_blocks = [];
                foreach ($new_blocks_parsed as $b) {
                    if ($b['blockName'] !== null || !empty(trim($b['innerHTML']))) {
                        $clean_new_blocks[] = $b;
                    }
                }
                if (empty($clean_new_blocks)) {
                    return new \WP_Error('invalid_block', 'Could not parse any valid blocks from raw_block_html.');
                }

                $action = 'insert_' . $input['position'];

                $result = $this->modify_block_tree($blocks, $input['target_path'], $action, ['new_blocks' => $clean_new_blocks]);
                if (is_wp_error($result)) return $result;

                $new_content = serialize_blocks($blocks);
                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully inserted.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to target (e.g. 0.2)'],
                    'position' => ['type' => 'string', 'enum' => ['before', 'after', 'prepend', 'append'], 'description' => 'Where to insert relative to the path'],
                    'raw_block_html' => ['type' => 'string', 'description' => 'Raw Gutenberg HTML of the block(s) to insert']
                ],
                'required' => ['post_id', 'target_path', 'position', 'raw_block_html']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/update-block', [
            'category' => 'gutenberg',
            'label' => __('Update Gutenberg Block', 'aiutoma'),
            'description' => __('Update attributes or content of an existing block at a specific Block Path.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = parse_blocks($post->post_content);

                $result = $this->modify_block_tree($blocks, $input['target_path'], 'update', [
                    'attributes' => $input['attributes'] ?? null,
                    'inner_html' => $input['inner_html'] ?? null
                ]);
                if (is_wp_error($result)) return $result;

                $new_content = serialize_blocks($blocks);
                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully updated.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to the block to update (e.g. 0.2)'],
                    'attributes' => ['type' => 'string', 'description' => 'JSON encoded string of attributes to merge (optional)'],
                    'inner_html' => ['type' => 'string', 'description' => 'New HTML content for the block (optional)']
                ],
                'required' => ['post_id', 'target_path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/remove-block', [
            'category' => 'gutenberg',
            'label' => __('Remove Gutenberg Block', 'aiutoma'),
            'description' => __('Remove a block at a specific Block Path from the tree.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post = get_post($input['post_id']);
                if (!$post) return new \WP_Error('not_found', 'Post not found.');

                $blocks = parse_blocks($post->post_content);

                $result = $this->modify_block_tree($blocks, $input['target_path'], 'remove');
                if (is_wp_error($result)) return $result;

                $new_content = serialize_blocks($blocks);
                wp_update_post(['ID' => $post->ID, 'post_content' => wp_slash($new_content)]);

                return ['success' => true, 'message' => 'Block successfully removed.'];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'target_path' => ['type' => 'string', 'description' => 'Path to the block to remove (e.g. 0.2)']
                ],
                'required' => ['post_id', 'target_path']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('gutenberg/wp-patterns', [
            'category' => 'gutenberg',
            'label' => __('WordPress Patterns Library', 'aiutoma'),
            'description' => __('Search and fetch official block patterns from the WordPress.org pattern directory. Returns the ready-to-use Gutenberg HTML content for each pattern.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $search = urlencode($input['search'] ?? '');
                $category = urlencode($input['category'] ?? '');
                $url = 'https://api.wordpress.org/patterns/1.0/?';

                if (!empty($search)) $url .= 'search=' . $search . '&';
                if (!empty($category)) $url .= 'pattern-categories=' . $category . '&';

                $response = wp_remote_get($url, ['timeout' => 15]);
                if (is_wp_error($response)) {
                    return $response;
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (!is_array($data)) {
                    return new \WP_Error('api_error', 'Invalid response from WordPress.org API.');
                }

                $results = [];
                // Return top 5 to avoid token exhaustion
                foreach (array_slice($data, 0, 5) as $pattern) {
                    $results[] = [
                        'title' => $pattern['title']['rendered'] ?? '',
                        'content' => $pattern['content'] ?? '',
                        'categories' => $pattern['pattern-categories'] ?? [],
                        'viewport_width' => $pattern['viewport_width'] ?? ''
                    ];
                }

                if (empty($results)) {
                    return ['success' => true, 'message' => 'No patterns found. Try a different search term.'];
                }

                return [
                    'success' => true,
                    'count' => count($results),
                    'patterns' => $results
                ];
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Keyword to search for patterns (e.g. "header", "hero", "pricing")'],
                ],
                'required' => []
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
            'description' => __('Updates a post\'s content by providing a structured JSON array of Gutenberg blocks (previously read via gutenberg-read). The blocks will be serialized back to HTML.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $post_id = intval($input['post_id']);
                $blocks = $input['blocks'] ?? [];
                if (!is_array($blocks)) return new \WP_Error('invalid_blocks', 'Blocks must be an array.');

                $content = serialize_blocks($blocks);
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
                    ]
                ],
                'required' => ['post_id', 'blocks']
            ]
        ]);
    }
}
