<?php

namespace Aiutoma\Modules\Ai\Abilities;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

trait WordPress
{
    public function register_wordpress_abilities()
    {
        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/generate-image', [
            'category' => 'aiutoma',
            'label' => __('Generate Image', 'aiutoma'),
            'description' => __('Generates an image based on a prompt and saves it to the WordPress media library. If post_id is provided, sets it as the thumbnail. Otherwise, returns the media ID so you can use it later.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
                $prompt = sanitize_text_field($input['prompt']);

                if (!$prompt) {
                    return new \WP_Error('invalid_input', 'Prompt is required to generate an image.');
                }

                if ($post_id && !get_post($post_id)) {
                    return new \WP_Error('invalid_post', 'The specified post does not exist.');
                }

                $image_url = 'https://image.pollinations.ai/prompt/' . urlencode($prompt) . '?width=1024&height=1024&nologo=true';

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');

                $tmp_file = download_url($image_url);
                if (is_wp_error($tmp_file)) {
                    return $tmp_file;
                }

                $file_array = [
                    'name' => sanitize_title($prompt) . '-' . time() . '.jpg',
                    'tmp_name' => $tmp_file
                ];

                $attach_id = media_handle_sideload($file_array, $post_id ?: 0);

                if (is_wp_error($attach_id)) {
                    @wp_delete_file($file_array['tmp_name']);
                    return $attach_id;
                }

                if ($post_id) {
                    $result = set_post_thumbnail($post_id, $attach_id);
                    if (!$result) {
                        return new \WP_Error('thumbnail_error', 'Failed to set the image as the post thumbnail.');
                    }
                }

                $media_url = wp_get_attachment_url($attach_id);

                return [
                    'success' => true,
                    'message' => $post_id ? 'Image generated and set as thumbnail successfully.' : 'Image generated successfully.',
                    'media_id' => $attach_id,
                    'media_url' => $media_url
                ];
            },
            'permission_callback' => function () {
                return current_user_can('upload_files') && current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'Optional. The ID of the post to set the thumbnail for.'],
                    'prompt' => ['type' => 'string', 'description' => 'The prompt to generate the image.']
                ],
                'required' => ['prompt']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-posts', [
            'category' => 'aiutoma',
            'label' => __('Manage Posts & Pages', 'aiutoma'),
            'description' => __('Create, read, update, or delete posts and pages.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];

                if ($action === 'get') {
                    $posts = get_posts(array_merge(['post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 10], $args));
                    $data = array_map(function ($p) {
                        return $p->to_array();
                    }, $posts);
                    return ['success' => true, 'posts' => $data];
                } elseif ($action === 'create' || $action === 'update') {
                    $post_id = wp_insert_post($args);
                    if (is_wp_error($post_id)) return $post_id;
                    return ['success' => true, 'post_id' => $post_id];
                } elseif ($action === 'delete') {
                    $id = $args['ID'] ?? 0;
                    if (!$id) return new \WP_Error('missing_id', 'Post ID is required.');
                    $result = wp_delete_post($id, $args['force_delete'] ?? false);
                    if (!$result) return new \WP_Error('delete_failed', 'Failed to delete post.');
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get', 'create', 'update', 'delete'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"post_title": "Hello", "post_content": "World", "post_type": "page"} for create.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-comments', [
            'category' => 'aiutoma',
            'label' => __('Manage Comments', 'aiutoma'),
            'description' => __('Get, approve, spam, or trash comments.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];

                if ($action === 'get') {
                    $comments = get_comments($args);
                    $data = array_map(function ($c) {
                        return $c->to_array();
                    }, $comments);
                    return ['success' => true, 'comments' => $data];
                } elseif ($action === 'set_status') {
                    $comment_id = $args['comment_id'] ?? 0;
                    $status = $args['status'] ?? ''; // 'approve', 'hold', 'spam', 'trash', 'delete'
                    if (!$comment_id || !$status) return new \WP_Error('missing_args', 'comment_id and status are required.');
                    $result = wp_set_comment_status($comment_id, $status);
                    if (is_wp_error($result)) return $result;
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('moderate_comments');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get', 'set_status'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"status": "hold"} for get, or {"comment_id": 12, "status": "approve"} for set_status.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-users', [
            'category' => 'aiutoma',
            'label' => __('Manage Users & Roles', 'aiutoma'),
            'description' => __('Manage WordPress users, roles, and capabilities.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];

                if ($action === 'update_user') {
                    if (isset($args['role']) && $args['role'] === 'administrator' && !current_user_can('promote_users')) {
                        return new \WP_Error('unauthorized', __('You cannot promote a user to administrator.', 'aiutoma'));
                    }
                    $user_id = wp_update_user($args);
                    if (is_wp_error($user_id)) return $user_id;
                    return ['success' => true, 'user_id' => $user_id];
                } elseif ($action === 'delete_user') {
                    if (!current_user_can('delete_users')) {
                        return new \WP_Error('unauthorized', __('You do not have permission to delete users.', 'aiutoma'));
                    }
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    $reassign = $args['reassign'] ?? null;
                    $result = wp_delete_user($args['user_id'], $reassign);
                    return ['success' => $result];
                } elseif ($action === 'get_users') {
                    $users = get_users($args);
                    $data = array_map(function ($u) {
                        return $u->to_array();
                    }, $users);
                    return ['success' => true, 'users' => $data];
                } elseif ($action === 'add_role') {
                    add_role($args['role'], $args['display_name'], $args['capabilities'] ?? []);
                    return ['success' => true];
                } elseif ($action === 'remove_role') {
                    remove_role($args['role']);
                    return ['success' => true];
                } elseif ($action === 'add_cap' || $action === 'remove_cap') {
                    $role = get_role($args['role']);
                    if (!$role) return new \WP_Error('invalid_role', 'Role not found.');
                    if ($action === 'add_cap') $role->add_cap($args['cap']);
                    else $role->remove_cap($args['cap']);
                    return ['success' => true];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('edit_users');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get_users', 'update_user', 'delete_user', 'add_role', 'remove_role', 'add_cap', 'remove_cap'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments for the action. For update_user use user data array. For add_role use {"role":"editor", "display_name":"Editor"}. For add_cap/remove_cap use {"role":"editor", "cap":"edit_theme_options"}.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-media', [
            'category' => 'aiutoma',
            'label' => __('Manage Media', 'aiutoma'),
            'description' => __('Manage WordPress media library (update metadata, alt text, sideload images).', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];

                if ($action === 'get_media') {
                    $posts = get_posts(array_merge(['post_type' => 'attachment', 'post_status' => 'inherit'], $args));
                    $data = [];
                    foreach ($posts as $p) {
                        $data[] = [
                            'id' => $p->ID,
                            'title' => $p->post_title,
                            'caption' => $p->post_excerpt,
                            'description' => $p->post_content,
                            'alt_text' => get_post_meta($p->ID, '_wp_attachment_image_alt', true),
                            'url' => wp_get_attachment_url($p->ID)
                        ];
                    }
                    return ['success' => true, 'media' => $data];
                } elseif ($action === 'update_meta') {
                    $id = $args['id'] ?? 0;
                    if (!$id) return new \WP_Error('missing_id', 'Media ID is required.');

                    $update = ['ID' => $id];
                    if (isset($args['title'])) $update['post_title'] = $args['title'];
                    if (isset($args['caption'])) $update['post_excerpt'] = $args['caption'];
                    if (isset($args['description'])) $update['post_content'] = $args['description'];

                    if (count($update) > 1) wp_update_post($update);
                    if (isset($args['alt_text'])) update_post_meta($id, '_wp_attachment_image_alt', $args['alt_text']);

                    return ['success' => true];
                } elseif ($action === 'upload_from_url') {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $url = $args['url'] ?? '';
                    $post_id = $args['post_id'] ?? 0;
                    $desc = $args['description'] ?? '';

                    if (!$url) return new \WP_Error('missing_url', 'URL is required.');

                    $id = media_sideload_image($url, $post_id, $desc, 'id');
                    if (is_wp_error($id)) return $id;
                    return ['success' => true, 'media_id' => $id, 'url' => wp_get_attachment_url($id)];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get_media', 'update_meta', 'upload_from_url'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. for update_meta {"id": 12, "alt_text": "A cool image", "title": "Cool Image"}. For upload_from_url {"url": "https://..."}.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-menus', [
            'category' => 'aiutoma',
            'label' => __('Manage Menus', 'aiutoma'),
            'description' => __('Manage WordPress menus, locations, and nav items.', 'aiutoma'),
            'execute_callback' => function ($input) {
                $action = $input['action'];
                $args = $input['args'] ?? [];

                if ($action === 'get_menus') {
                    $menus = wp_get_nav_menus();
                    $locations = get_nav_menu_locations();
                    return ['success' => true, 'menus' => $menus, 'locations' => $locations];
                } elseif ($action === 'create_menu') {
                    $id = wp_create_nav_menu($args['name']);
                    if (is_wp_error($id)) return $id;
                    if (!empty($args['location'])) {
                        $locations = get_nav_menu_locations();
                        $locations[$args['location']] = $id;
                        set_theme_mod('nav_menu_locations', $locations);
                    }
                    return ['success' => true, 'menu_id' => $id];
                } elseif ($action === 'add_menu_item') {
                    $menu_id = $args['menu_id'] ?? 0;
                    $item_data = $args['item_data'] ?? [];
                    $id = wp_update_nav_menu_item($menu_id, 0, $item_data);
                    if (is_wp_error($id)) return $id;
                    return ['success' => true, 'item_id' => $id];
                }
                return new \WP_Error('invalid_action', 'Unsupported action.');
            },
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['get_menus', 'create_menu', 'add_menu_item'], 'description' => 'Action to perform'],
                    'args' => ['type' => 'object', 'description' => 'Arguments. E.g. {"name": "Main Menu", "location": "primary"} or {"menu_id": 12, "item_data": {"menu-item-title": "Home", "menu-item-url": "/", "menu-item-status": "publish"}}.']
                ],
                'required' => ['action']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('aiutoma/manage-terms', [
            'category' => 'aiutoma',
            'label' => __('Manage Taxonomy Terms', 'aiutoma'),
            'description' => __('List, create, update, or assign taxonomy terms (categories, tags, custom taxonomies) with hierarchy tree resolution and deletion safeguards.', 'aiutoma'),
            'meta' => ['plugin_name' => 'Aiutoma'],
            'execute_callback' => function ($input) {
                $action = $input['action'] ?? '';
                $args = $input['args'] ?? [];

                // Deletion safeguard: block destructive permanent deletion of terms
                if ($action === 'delete') {
                    return new \WP_Error(
                        'deletion_blocked',
                        __('Term deletion is disabled as a safety precaution: deleting terms permanently unlinks content without a trash bin or revision trail.', 'aiutoma')
                    );
                }

                if ($action === 'list') {
                    $taxonomy = sanitize_key($args['taxonomy'] ?? 'category');
                    if (!taxonomy_exists($taxonomy)) {
                        return new \WP_Error('invalid_taxonomy', sprintf(__('Taxonomy "%s" does not exist.', 'aiutoma'), $taxonomy));
                    }

                    $term_args = [
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => !empty($args['hide_empty']),
                    ];

                    if (isset($args['parent'])) {
                        $term_args['parent'] = intval($args['parent']);
                    }
                    if (!empty($args['search'])) {
                        $term_args['search'] = sanitize_text_field($args['search']);
                    }

                    $terms = get_terms($term_args);
                    if (is_wp_error($terms)) {
                        return $terms;
                    }

                    if (!empty($args['tree'])) {
                        // Hierarchy tree resolution
                        $build_tree = function ($parent_id = 0) use (&$build_tree, $terms) {
                            $branch = [];
                            foreach ($terms as $term) {
                                if ((int)$term->parent === (int)$parent_id) {
                                    $children = $build_tree($term->term_id);
                                    $node = [
                                        'term_id'     => (int)$term->term_id,
                                        'name'        => $term->name,
                                        'slug'        => $term->slug,
                                        'description' => $term->description,
                                        'count'       => (int)$term->count,
                                        'parent'      => (int)$term->parent,
                                    ];
                                    if (!empty($children)) {
                                        $node['children'] = $children;
                                    }
                                    $branch[] = $node;
                                }
                            }
                            return $branch;
                        };

                        return [
                            'success'  => true,
                            'taxonomy' => $taxonomy,
                            'tree'     => $build_tree(isset($args['parent']) ? intval($args['parent']) : 0),
                        ];
                    }

                    $data = array_map(function ($t) {
                        return [
                            'term_id'     => (int)$t->term_id,
                            'name'        => $t->name,
                            'slug'        => $t->slug,
                            'description' => $t->description,
                            'count'       => (int)$t->count,
                            'parent'      => (int)$t->parent,
                        ];
                    }, $terms);

                    return ['success' => true, 'taxonomy' => $taxonomy, 'terms' => $data];

                } elseif ($action === 'create') {
                    $taxonomy = sanitize_key($args['taxonomy'] ?? 'category');
                    $name = sanitize_text_field($args['name'] ?? '');
                    if (empty($name)) {
                        return new \WP_Error('missing_name', __('Term name is required.', 'aiutoma'));
                    }
                    if (!taxonomy_exists($taxonomy)) {
                        return new \WP_Error('invalid_taxonomy', sprintf(__('Taxonomy "%s" does not exist.', 'aiutoma'), $taxonomy));
                    }

                    $parent = isset($args['parent']) ? intval($args['parent']) : 0;

                    // Idempotent creation check
                    $existing = term_exists($name, $taxonomy, $parent);
                    if ($existing) {
                        $existing_id = is_array($existing) ? $existing['term_id'] : $existing;
                        $term_obj = get_term($existing_id, $taxonomy);
                        return [
                            'success'  => true,
                            'term_id'  => (int)$existing_id,
                            'existing' => true,
                            'name'     => $term_obj && !is_wp_error($term_obj) ? $term_obj->name : $name,
                            'slug'     => $term_obj && !is_wp_error($term_obj) ? $term_obj->slug : '',
                            'message'  => __('Term already exists.', 'aiutoma'),
                        ];
                    }

                    $insert_args = [];
                    if (!empty($args['slug'])) {
                        $insert_args['slug'] = sanitize_title($args['slug']);
                    }
                    if (isset($args['description'])) {
                        $insert_args['description'] = sanitize_textarea_field($args['description']);
                    }
                    if ($parent) {
                        $insert_args['parent'] = $parent;
                    }

                    $result = wp_insert_term($name, $taxonomy, $insert_args);
                    if (is_wp_error($result)) {
                        return $result;
                    }

                    return [
                        'success'  => true,
                        'term_id'  => (int)$result['term_id'],
                        'existing' => false,
                        'message'  => __('Term created successfully.', 'aiutoma'),
                    ];

                } elseif ($action === 'update') {
                    $term_id = intval($args['term_id'] ?? 0);
                    $taxonomy = sanitize_key($args['taxonomy'] ?? 'category');
                    if (!$term_id) {
                        return new \WP_Error('missing_term_id', __('term_id is required for update.', 'aiutoma'));
                    }

                    $update_args = [];
                    if (isset($args['name'])) {
                        $update_args['name'] = sanitize_text_field($args['name']);
                    }
                    if (isset($args['slug'])) {
                        $update_args['slug'] = sanitize_title($args['slug']);
                    }
                    if (isset($args['description'])) {
                        $update_args['description'] = sanitize_textarea_field($args['description']);
                    }
                    if (isset($args['parent'])) {
                        $update_args['parent'] = intval($args['parent']);
                    }

                    $result = wp_update_term($term_id, $taxonomy, $update_args);
                    if (is_wp_error($result)) {
                        return $result;
                    }

                    return [
                        'success' => true,
                        'term_id' => (int)$result['term_id'],
                        'message' => __('Term updated successfully.', 'aiutoma'),
                    ];

                } elseif ($action === 'assign') {
                    $post_id = intval($args['post_id'] ?? 0);
                    $taxonomy = sanitize_key($args['taxonomy'] ?? 'category');
                    if (!$post_id) {
                        return new \WP_Error('missing_post_id', __('post_id is required to assign terms.', 'aiutoma'));
                    }
                    if (!get_post($post_id)) {
                        return new \WP_Error('invalid_post', __('Post not found.', 'aiutoma'));
                    }
                    if (!isset($args['terms'])) {
                        return new \WP_Error('missing_terms', __('terms argument is required (array of IDs, slugs, or names).', 'aiutoma'));
                    }

                    $terms = $args['terms'];
                    if (is_string($terms)) {
                        $terms = array_map('trim', explode(',', $terms));
                    }
                    $append = !empty($args['append']);

                    $result = wp_set_object_terms($post_id, $terms, $taxonomy, $append);
                    if (is_wp_error($result)) {
                        return $result;
                    }

                    return [
                        'success'        => true,
                        'post_id'        => $post_id,
                        'taxonomy'       => $taxonomy,
                        'assigned_terms' => $result,
                        'message'        => __('Terms assigned successfully.', 'aiutoma'),
                    ];
                }

                return new \WP_Error('invalid_action', __('Unsupported action.', 'aiutoma'));
            },
            'permission_callback' => function ($input) {
                $action = $input['action'] ?? '';
                if ($action === 'assign') {
                    return current_user_can('edit_posts');
                }
                return current_user_can('manage_categories') || current_user_can('edit_posts');
            },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['list', 'create', 'update', 'assign', 'delete'],
                        'description' => 'Action to perform: "list", "create", "update", or "assign". ("delete" is blocked by deletion safeguards).'
                    ],
                    'args' => [
                        'type' => 'object',
                        'description' => 'Arguments for the action. For list: {"taxonomy": "category", "tree": true}. For create: {"taxonomy": "category", "name": "News", "parent": 0}. For update: {"term_id": 5, "taxonomy": "category", "name": "Latest News"}. For assign: {"post_id": 12, "taxonomy": "category", "terms": ["News"], "append": true}.'
                    ]
                ],
                'required' => ['action']
            ]
        ]);

    }
}
