<?php
namespace Aiutoma\Modules\Ai\Abilities;
if ( ! defined( 'ABSPATH' ) ) exit;
trait WooCommerce {
    public function register_woocommerce_abilities() {
        if (!class_exists('WooCommerce')) {
            return;
        }        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/create-product', [
            'category' => 'woocommerce',
            'label' => __('Create WooCommerce Product', 'aiutoma'),
            'description' => __('Create a new WooCommerce simple product.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $product = new \WC_Product_Simple();
                if (isset($input['name'])) $product->set_name($input['name']);
                if (isset($input['price'])) { $product->set_regular_price($input['price']); $product->set_price($input['price']); }
                if (isset($input['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($input['stock_quantity']); }
                $product->save();
                return ['success' => true, 'product_id' => $product->get_id()];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'price' => ['type' => 'string', 'description' => 'Product price'],
                    'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity']
                ],
                'required' => ['name']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/create-variable-product', [
            'category' => 'woocommerce',
            'label' => __('Create WooCommerce Variable Product', 'aiutoma'),
            'description' => __('Create a new WooCommerce variable product with defined attributes and optional variations.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                if (!class_exists('WC_Product_Variable')) {
                    return new \WP_Error('unsupported', 'WooCommerce Variable Products are not supported or available.');
                }

                $product = new \WC_Product_Variable();
                if (isset($input['name'])) $product->set_name($input['name']);
                if (isset($input['description'])) $product->set_description($input['description']);
                if (isset($input['short_description'])) $product->set_short_description($input['short_description']);
                if (isset($input['sku'])) $product->set_sku($input['sku']);
                if (isset($input['status'])) $product->set_status($input['status']);

                // Process attributes
                if (!empty($input['attributes']) && is_array($input['attributes'])) {
                    $product_attributes = [];
                    foreach ($input['attributes'] as $position => $attr_data) {
                        $attr_name = sanitize_text_field($attr_data['name'] ?? '');
                        if (empty($attr_name)) continue;

                        $options = $attr_data['options'] ?? [];
                        if (is_string($options)) {
                            $options = array_map('trim', explode('|', $options));
                        }

                        $attribute = new \WC_Product_Attribute();
                        $attribute->set_id(0);
                        $attribute->set_name($attr_name);
                        $attribute->set_options((array) $options);
                        $attribute->set_position($position);
                        $attribute->set_visible(isset($attr_data['is_visible']) ? (bool)$attr_data['is_visible'] : true);
                        $attribute->set_variation(isset($attr_data['is_variation']) ? (bool)$attr_data['is_variation'] : true);
                        $product_attributes[] = $attribute;
                    }
                    $product->set_attributes($product_attributes);
                }

                $product_id = $product->save();
                if (is_wp_error($product_id) || !$product_id) {
                    return new \WP_Error('save_failed', 'Failed to save variable product.');
                }

                // Process initial variations if provided
                $variation_ids = [];
                if (!empty($input['variations']) && is_array($input['variations']) && class_exists('WC_Product_Variation')) {
                    foreach ($input['variations'] as $var_data) {
                        $variation = new \WC_Product_Variation();
                        $variation->set_parent_id($product_id);

                        if (isset($var_data['attributes']) && is_array($var_data['attributes'])) {
                            $sanitized_attrs = [];
                            foreach ($var_data['attributes'] as $k => $v) {
                                $sanitized_attrs[sanitize_title($k)] = $v;
                            }
                            $variation->set_attributes($sanitized_attrs);
                        }

                        if (isset($var_data['price']) || isset($var_data['regular_price'])) {
                            $price = (string)($var_data['regular_price'] ?? $var_data['price']);
                            $variation->set_regular_price($price);
                            $variation->set_price($price);
                        }

                        if (isset($var_data['stock_quantity'])) {
                            $variation->set_manage_stock(true);
                            $variation->set_stock_quantity((int)$var_data['stock_quantity']);
                        } else {
                            $variation->set_stock_status('instock');
                        }

                        if (!empty($var_data['sku'])) {
                            $variation->set_sku($var_data['sku']);
                        }

                        $var_id = $variation->save();
                        if ($var_id && !is_wp_error($var_id)) {
                            $variation_ids[] = $var_id;
                        }
                    }

                    \WC_Product_Variable::sync($product_id);
                }

                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'variation_ids' => $variation_ids,
                    'message' => 'Variable product created successfully.'
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'description' => ['type' => 'string', 'description' => 'Product description'],
                    'short_description' => ['type' => 'string', 'description' => 'Product short description'],
                    'sku' => ['type' => 'string', 'description' => 'Product SKU'],
                    'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private'], 'description' => 'Product status'],
                    'attributes' => [
                        'type' => 'array',
                        'description' => 'Product attributes configuration',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Attribute name (e.g. "Size", "Color")'],
                                'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of option values (e.g. ["S", "M", "L"])'],
                                'is_variation' => ['type' => 'boolean', 'description' => 'Whether this attribute is used for variations (default: true)'],
                                'is_visible' => ['type' => 'boolean', 'description' => 'Whether this attribute is visible on product page (default: true)']
                            ],
                            'required' => ['name', 'options']
                        ]
                    ],
                    'variations' => [
                        'type' => 'array',
                        'description' => 'Optional list of initial variations to create',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'attributes' => ['type' => 'object', 'description' => 'Key-value map of attribute options (e.g. {"Size": "M", "Color": "Blue"})'],
                                'price' => ['type' => 'string', 'description' => 'Variation regular price'],
                                'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity'],
                                'sku' => ['type' => 'string', 'description' => 'Variation SKU']
                            ]
                        ]
                    ]
                ],
                'required' => ['name', 'attributes']
            ]
        ]);

        // Support variable products in woocommerce/products-query
        add_filter('wp_pre_execute_ability', function($pre, $ability_name, $input, $ability) {
            if ($ability_name === 'woocommerce/products-query' && is_array($input)) {
                $type = $input['product_type_alias'] ?? ($input['type'] ?? '');
                if ($type === 'variable') {
                    if (!function_exists('wc_get_products')) {
                        return $pre;
                    }
                    $page = (int)($input['page'] ?? 1);
                    $per_page = (int)($input['per_page'] ?? 10);
                    $args = [
                        'type' => 'variable',
                        'limit' => $per_page,
                        'page' => $page,
                        'paginate' => true,
                        'return' => 'objects',
                    ];
                    if (!empty($input['status'])) $args['status'] = wc_clean($input['status']);
                    if (!empty($input['sku'])) $args['sku'] = wc_clean($input['sku']);
                    if (!empty($input['stock_status'])) $args['stock_status'] = wc_clean($input['stock_status']);
                    if (!empty($input['search'])) $args['s'] = wc_clean($input['search']);

                    $results = wc_get_products($args);
                    $products = is_object($results) && isset($results->products) ? $results->products : [];
                    $pages = is_object($results) && isset($results->max_num_pages) ? (int)$results->max_num_pages : (count($products) > 0 ? 1 : 0);
                    $total = is_object($results) && isset($results->total) ? (int)$results->total : count($products);

                    $fmt_price = function($p) {
                        return (is_numeric($p) || (is_string($p) && $p !== '')) ? (string)$p : null;
                    };
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
                                'price' => $fmt_price($product->get_price()),
                                'regular_price' => $fmt_price($product->get_regular_price()),
                                'sale_price' => $fmt_price($product->get_sale_price()),
                            'stock_status' => $product->get_stock_status(),
                            'stock_quantity' => null === $stock_quantity ? null : (function_exists('wc_stock_amount') ? wc_stock_amount($stock_quantity) : (int)$stock_quantity),
                            'manage_stock' => (bool)$product->get_manage_stock(),
                            'virtual' => (bool)$product->get_virtual(),
                            'downloadable' => (bool)$product->get_downloadable(),
                            'date_created' => function_exists('wc_rest_prepare_date_response') ? wc_rest_prepare_date_response($product->get_date_created(), false) : null,
                            'date_created_gmt' => function_exists('wc_rest_prepare_date_response') ? wc_rest_prepare_date_response($product->get_date_created()) : null,
                            'date_modified' => function_exists('wc_rest_prepare_date_response') ? wc_rest_prepare_date_response($product->get_date_modified(), false) : null,
                            'date_modified_gmt' => function_exists('wc_rest_prepare_date_response') ? wc_rest_prepare_date_response($product->get_date_modified()) : null,
                        ];
                    }

                    return [
                        'products' => $formatted,
                        'total_pages' => $pages,
                        'page' => $page,
                        'per_page' => $per_page,
                        'total_items' => $total,
                        'total' => $total,
                    ];
                }
            }
            return $pre;
        }, 10, 4);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/manage-variations', [
            'category' => 'woocommerce',
            'label' => __('Manage WooCommerce Product Variations', 'aiutoma'),
            'description' => __('List, create, update, or delete variations for a variable product.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                if (!class_exists('WC_Product_Variation') || !class_exists('WC_Product_Variable')) {
                    return new \WP_Error('unsupported', 'WooCommerce product variations are not available.');
                }

                $input = is_array($input) ? $input : [];
                $nested = isset($input['args']) && is_array($input['args']) ? $input['args'] : [];
                $params = array_merge($input, $nested);

                $action = !empty($params['action']) ? strtolower(trim($params['action'])) : 'list';

                if ($action === 'list') {
                    $parent_id = intval($params['parent_id'] ?? ($params['product_id'] ?? ($params['id'] ?? 0)));
                    if (!$parent_id) return new \WP_Error('missing_id', 'parent_id or product_id is required.');
                    $parent = wc_get_product($parent_id);
                    if (!$parent || !$parent->is_type('variable')) {
                        return new \WP_Error('invalid_parent', sprintf('Parent product (ID %d) must be a variable product.', $parent_id));
                    }

                    $children_ids = $parent->get_children();
                    $variations = [];
                    foreach ($children_ids as $cid) {
                        $v = wc_get_product($cid);
                        if (!$v) continue;
                        $variations[] = [
                            'id' => $v->get_id(),
                            'attributes' => $v->get_attributes(),
                            'regular_price' => $v->get_regular_price(),
                            'price' => $v->get_price(),
                            'stock_quantity' => $v->get_stock_quantity(),
                            'stock_status' => $v->get_stock_status(),
                            'sku' => $v->get_sku(),
                            'status' => $v->get_status()
                        ];
                    }

                    return [
                        'success' => true,
                        'parent_id' => $parent_id,
                        'total_variations' => count($variations),
                        'variations' => $variations
                    ];

                } elseif ($action === 'create') {
                    $parent_id = intval($params['parent_id'] ?? ($params['product_id'] ?? ($params['id'] ?? 0)));
                    if (!$parent_id) return new \WP_Error('missing_id', 'parent_id or product_id is required.');
                    $parent = wc_get_product($parent_id);
                    if (!$parent || !$parent->is_type('variable')) {
                        return new \WP_Error('invalid_parent', sprintf('Parent product (ID %d) must be a variable product.', $parent_id));
                    }

                    $variation = new \WC_Product_Variation();
                    $variation->set_parent_id($parent_id);

                    if (isset($params['attributes']) && is_array($params['attributes'])) {
                        $sanitized_attrs = [];
                        foreach ($params['attributes'] as $k => $v) {
                            $sanitized_attrs[sanitize_title($k)] = $v;
                        }
                        $variation->set_attributes($sanitized_attrs);
                    }

                    if (isset($params['price']) || isset($params['regular_price'])) {
                        $price = (string)($params['regular_price'] ?? $params['price']);
                        $variation->set_regular_price($price);
                        $variation->set_price($price);
                    }

                    if (isset($params['stock_quantity'])) {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity((int)$params['stock_quantity']);
                    } else {
                        $variation->set_stock_status($params['stock_status'] ?? 'instock');
                    }

                    if (!empty($params['sku'])) {
                        $variation->set_sku($params['sku']);
                    }

                    $var_id = $variation->save();
                    if (is_wp_error($var_id) || !$var_id) {
                        return new \WP_Error('save_failed', 'Failed to create product variation.');
                    }

                    \WC_Product_Variable::sync($parent_id);
                    return ['success' => true, 'variation_id' => $var_id, 'message' => 'Variation created successfully.'];

                } elseif ($action === 'update') {
                    $variation_id = intval($params['variation_id'] ?? ($params['id'] ?? ($params['product_id'] ?? 0)));
                    if (!$variation_id) return new \WP_Error('missing_id', 'variation_id is required.');
                    $variation = wc_get_product($variation_id);
                    if (!$variation || !$variation->is_type('variation')) {
                        return new \WP_Error('invalid_variation', 'Variation not found.');
                    }

                    if (isset($params['attributes']) && is_array($params['attributes'])) {
                        $attrs = $variation->get_attributes();
                        foreach ($params['attributes'] as $k => $v) {
                            $attrs[sanitize_title($k)] = $v;
                        }
                        $variation->set_attributes($attrs);
                    }

                    if (isset($params['price']) || isset($params['regular_price'])) {
                        $price = (string)($params['regular_price'] ?? $params['price']);
                        $variation->set_regular_price($price);
                        $variation->set_price($price);
                    }

                    if (isset($params['stock_quantity'])) {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity((int)$params['stock_quantity']);
                    }

                    if (!empty($params['stock_status'])) {
                        $variation->set_stock_status($params['stock_status']);
                    }

                    if (isset($params['sku'])) {
                        $variation->set_sku($params['sku']);
                    }

                    $variation->save();
                    $parent_id = $variation->get_parent_id();
                    if ($parent_id) {
                        \WC_Product_Variable::sync($parent_id);
                    }

                    return ['success' => true, 'variation_id' => $variation_id, 'message' => 'Variation updated successfully.'];

                } elseif ($action === 'delete') {
                    $variation_id = intval($params['variation_id'] ?? ($params['id'] ?? ($params['product_id'] ?? 0)));
                    if (!$variation_id) return new \WP_Error('missing_id', 'variation_id is required.');
                    $variation = wc_get_product($variation_id);
                    if (!$variation || !$variation->is_type('variation')) {
                        return new \WP_Error('invalid_variation', 'Variation not found.');
                    }

                    $parent_id = $variation->get_parent_id();
                    $variation->delete(true);
                    if ($parent_id) {
                        \WC_Product_Variable::sync($parent_id);
                    }

                    return ['success' => true, 'message' => 'Variation deleted successfully.'];
                }

                return new \WP_Error('invalid_action', 'Unsupported action. Supported actions: list, create, update, delete.');
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['list', 'create', 'update', 'delete'],
                        'default' => 'list',
                        'description' => 'Action to perform: "list" to view variations of a variable product, "create" to add a new variation, "update" to modify an existing variation, or "delete" to remove a variation. Defaults to "list".'
                    ],
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'Parent variable product ID (used for "list" and "create"). Alias of parent_id.'
                    ],
                    'parent_id' => [
                        'type' => 'integer',
                        'description' => 'Parent variable product ID (used for "list" and "create").'
                    ],
                    'variation_id' => [
                        'type' => 'integer',
                        'description' => 'Variation ID to update or delete.'
                    ],
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Variation ID (alias of variation_id for "update" and "delete").'
                    ],
                    'attributes' => [
                        'type' => 'object',
                        'description' => 'Key-value map of variation attributes, e.g. {"Size": "L", "Color": "Blue"} or {"pa_size": "l"}.'
                    ],
                    'price' => [
                        'type' => 'string',
                        'description' => 'Price for the variation (e.g. "29.99").'
                    ],
                    'regular_price' => [
                        'type' => 'string',
                        'description' => 'Regular price for the variation.'
                    ],
                    'stock_quantity' => [
                        'type' => 'integer',
                        'description' => 'Stock quantity for the variation.'
                    ],
                    'stock_status' => [
                        'type' => 'string',
                        'enum' => ['instock', 'outofstock', 'onbackorder'],
                        'description' => 'Stock status for the variation.'
                    ],
                    'sku' => [
                        'type' => 'string',
                        'description' => 'SKU string for the variation.'
                    ],
                    'args' => [
                        'type' => 'object',
                        'description' => 'Optional nested arguments object for backward compatibility.'
                    ]
                ],
                'additionalProperties' => true
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/bulk-update-variations', [
            'category' => 'woocommerce',
            'label' => __('Bulk Update WooCommerce Variations', 'aiutoma'),
            'description' => __('Bulk update parameters (prices, stock, status, dimensions, and custom metadata) across all or selected variations of a variable product in a single call.', 'aiutoma'),
            'meta' => [
                'plugin_name' => 'WooCommerce',
                'annotations' => [
                    'readonly' => false,
                    'destructive' => false,
                    'idempotent' => true,
                ],
            ],
            'execute_callback' => function($input) {
                if (!class_exists('WC_Product_Variable') || !class_exists('WC_Product_Variation')) {
                    return new \WP_Error('unsupported', __('WooCommerce product variations are not available.', 'aiutoma'));
                }

                $input = is_array($input) ? $input : [];
                $parent_id = intval($input['product_id'] ?? ($input['parent_id'] ?? ($input['id'] ?? 0)));
                if (!$parent_id) {
                    return new \WP_Error('missing_product_id', __('product_id is required.', 'aiutoma'));
                }

                $parent = wc_get_product($parent_id);
                if (!$parent || !$parent->is_type('variable')) {
                    return new \WP_Error('invalid_parent', sprintf(__('Product ID %d must be a variable product.', 'aiutoma'), $parent_id));
                }

                $all_children = $parent->get_children();
                if (empty($all_children)) {
                    return new \WP_Error('no_variations', sprintf(__('Variable product ID %d has no variations to update.', 'aiutoma'), $parent_id));
                }

                if (!empty($input['variation_ids']) && is_array($input['variation_ids'])) {
                    $requested_ids = array_map('intval', $input['variation_ids']);
                    $target_ids = array_values(array_intersect($all_children, $requested_ids));
                    if (empty($target_ids)) {
                        return new \WP_Error('no_matching_variations', __('None of the specified variation_ids belong to this product.', 'aiutoma'));
                    }
                } else {
                    $target_ids = $all_children;
                }

                $applied_changes = [];
                $updated_ids = [];

                foreach ($target_ids as $var_id) {
                    $variation = wc_get_product($var_id);
                    if (!$variation || !$variation->is_type('variation')) {
                        continue;
                    }

                    $changed = false;

                    // Regular price
                    if (isset($input['regular_price'])) {
                        $reg_price = (string)$input['regular_price'];
                        $variation->set_regular_price($reg_price);
                        if (!isset($input['sale_price']) && !$variation->get_sale_price()) {
                            $variation->set_price($reg_price);
                        }
                        $applied_changes['regular_price'] = $reg_price;
                        $changed = true;
                    }

                    // Sale price
                    if (array_key_exists('sale_price', $input)) {
                        if ($input['sale_price'] === null || $input['sale_price'] === '') {
                            $variation->set_sale_price('');
                            $variation->set_price($variation->get_regular_price());
                            $applied_changes['sale_price'] = null;
                        } else {
                            $sale_price = (string)$input['sale_price'];
                            $variation->set_sale_price($sale_price);
                            $variation->set_price($sale_price);
                            $applied_changes['sale_price'] = $sale_price;
                        }
                        $changed = true;
                    }

                    // Stock status
                    if (!empty($input['stock_status'])) {
                        $stock_status = sanitize_text_field($input['stock_status']);
                        if (in_array($stock_status, ['instock', 'outofstock', 'onbackorder'], true)) {
                            $variation->set_stock_status($stock_status);
                            $applied_changes['stock_status'] = $stock_status;
                            $changed = true;
                        }
                    }

                    // Stock quantity
                    if (isset($input['stock_quantity'])) {
                        $quantity = (int)$input['stock_quantity'];
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity($quantity);
                        $applied_changes['stock_quantity'] = $quantity;
                        $applied_changes['manage_stock'] = true;
                        $changed = true;
                    } elseif (isset($input['manage_stock'])) {
                        $manage = (bool)$input['manage_stock'];
                        $variation->set_manage_stock($manage);
                        $applied_changes['manage_stock'] = $manage;
                        $changed = true;
                    }

                    // Status
                    if (!empty($input['status'])) {
                        $status = sanitize_text_field($input['status']);
                        if (in_array($status, ['publish', 'private'], true)) {
                            $variation->set_status($status);
                            $applied_changes['status'] = $status;
                            $changed = true;
                        }
                    }

                    // Virtual & Downloadable
                    if (isset($input['virtual'])) {
                        $virtual = (bool)$input['virtual'];
                        $variation->set_virtual($virtual);
                        $applied_changes['virtual'] = $virtual;
                        $changed = true;
                    }
                    if (isset($input['downloadable'])) {
                        $downloadable = (bool)$input['downloadable'];
                        $variation->set_downloadable($downloadable);
                        $applied_changes['downloadable'] = $downloadable;
                        $changed = true;
                    }

                    // Weight
                    if (isset($input['weight'])) {
                        $weight = (string)$input['weight'];
                        $variation->set_weight($weight);
                        $applied_changes['weight'] = $weight;
                        $changed = true;
                    }

                    // Dimensions
                    if (!empty($input['dimensions']) && (is_array($input['dimensions']) || is_object($input['dimensions']))) {
                        $dims = (array)$input['dimensions'];
                        if (isset($dims['length'])) $variation->set_length((string)$dims['length']);
                        if (isset($dims['width'])) $variation->set_width((string)$dims['width']);
                        if (isset($dims['height'])) $variation->set_height((string)$dims['height']);
                        $applied_changes['dimensions'] = $dims;
                        $changed = true;
                    }

                    // Description
                    if (isset($input['description'])) {
                        $desc = wp_kses_post($input['description']);
                        $variation->set_description($desc);
                        $applied_changes['description'] = $desc;
                        $changed = true;
                    }

                    // Generic Custom Metadata
                    if (!empty($input['meta_data']) && (is_array($input['meta_data']) || is_object($input['meta_data']))) {
                        $meta_entries = (array)$input['meta_data'];
                        foreach ($meta_entries as $meta_key => $meta_val) {
                            $clean_key = sanitize_text_field($meta_key);
                            if ($clean_key !== '') {
                                $variation->update_meta_data($clean_key, $meta_val);
                                $applied_changes['meta_data'][$clean_key] = $meta_val;
                                $changed = true;
                            }
                        }
                    }

                    if ($changed) {
                        $variation->save();
                        $updated_ids[] = $var_id;
                    }
                }

                // Synchronize parent variable product prices and stock
                \WC_Product_Variable::sync($parent_id);
                wc_delete_product_transients($parent_id);

                return [
                    'success' => true,
                    'product_id' => $parent_id,
                    'total_updated' => count($updated_ids),
                    'variation_ids' => $updated_ids,
                    'applied_changes' => $applied_changes,
                    'message' => sprintf(__('Successfully updated %d variation(s) for product %d.', 'aiutoma'), count($updated_ids), $parent_id),
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'ID of the parent variable product (required).'
                    ],
                    'parent_id' => [
                        'type' => 'integer',
                        'description' => 'Alias of product_id.'
                    ],
                    'variation_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Optional list of specific variation IDs to update. If omitted, all variations of the product are updated.'
                    ],
                    'regular_price' => [
                        'type' => ['string', 'number'],
                        'description' => 'Regular price to set across target variations (e.g. "49.90").'
                    ],
                    'sale_price' => [
                        'type' => ['string', 'number', 'null'],
                        'description' => 'Sale price to set across target variations. Pass null or empty string to remove sale price.'
                    ],
                    'stock_status' => [
                        'type' => 'string',
                        'enum' => ['instock', 'outofstock', 'onbackorder'],
                        'description' => 'Stock status for target variations.'
                    ],
                    'stock_quantity' => [
                        'type' => 'integer',
                        'description' => 'Stock quantity for target variations (automatically enables manage_stock).'
                    ],
                    'manage_stock' => [
                        'type' => 'boolean',
                        'description' => 'Explicitly enable or disable stock management at variation level.'
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['publish', 'private'],
                        'description' => 'Status for target variations.'
                    ],
                    'virtual' => [
                        'type' => 'boolean',
                        'description' => 'Whether target variations are virtual.'
                    ],
                    'downloadable' => [
                        'type' => 'boolean',
                        'description' => 'Whether target variations are downloadable.'
                    ],
                    'weight' => [
                        'type' => ['string', 'number'],
                        'description' => 'Weight for target variations.'
                    ],
                    'dimensions' => [
                        'type' => 'object',
                        'properties' => [
                            'length' => ['type' => ['string', 'number']],
                            'width' => ['type' => ['string', 'number']],
                            'height' => ['type' => ['string', 'number']]
                        ],
                        'description' => 'Dimensions (length, width, height) for target variations.'
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Description for target variations.'
                    ],
                    'meta_data' => [
                        'type' => 'object',
                        'description' => 'Generic key-value map of post metadata to set on each target variation (e.g. {"_b2c_price": "29.99", "_b2b_price": "24.99", "supplier_sku": "SUP-123"}).'
                    ]
                ],
                'required' => ['product_id']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/product-diagnostics', [
            'category' => 'woocommerce',
            'label' => __('WooCommerce Product Diagnostics', 'aiutoma'),
            'description' => __('Performs comprehensive health and purchasability diagnostics on a product, pinpointing cart blockers, missing variation prices, stock issues, and active filter hooks.', 'aiutoma'),
            'meta' => [
                'plugin_name' => 'WooCommerce',
                'annotations' => [
                    'readonly' => true,
                    'destructive' => false,
                    'idempotent' => true,
                ],
            ],
            'execute_callback' => function($input) {
                if (!function_exists('wc_get_product')) {
                    return new \WP_Error('unsupported', __('WooCommerce is not available.', 'aiutoma'));
                }

                $input = is_array($input) ? $input : [];
                $product_id = intval($input['product_id'] ?? ($input['id'] ?? 0));
                $sku = !empty($input['sku']) && is_string($input['sku']) ? trim($input['sku']) : '';

                if (!$product_id && $sku !== '') {
                    $found_id = wc_get_product_id_by_sku($sku);
                    if ($found_id) {
                        $product_id = $found_id;
                    }
                }

                if (!$product_id) {
                    return new \WP_Error('missing_parameter', __('Either product_id or sku must be provided.', 'aiutoma'));
                }

                $product = wc_get_product($product_id);
                if (!$product) {
                    return new \WP_Error('not_found', sprintf(__('Product ID %d was not found.', 'aiutoma'), $product_id));
                }

                $issues = [];
                $recommendations = [];
                $type = $product->get_type();
                $status = $product->get_status();
                $visibility = $product->get_catalog_visibility();
                $is_variable = $product->is_type('variable');

                // Basic status checks
                if ($status !== 'publish') {
                    $issues[] = [
                        'severity' => 'critical',
                        'code' => 'status_not_published',
                        'message' => sprintf(__('Product status is "%s". Only "publish" status products are available to shoppers.', 'aiutoma'), $status),
                    ];
                    $recommendations[] = sprintf(__('Publish product ID %d using woocommerce/update-product (status: "publish").', 'aiutoma'), $product_id);
                }

                if (in_array($visibility, ['hidden', 'search'], true)) {
                    $issues[] = [
                        'severity' => 'warning',
                        'code' => 'catalog_hidden',
                        'message' => sprintf(__('Product catalog visibility is set to "%s" and may not appear in shop catalog listings.', 'aiutoma'), $visibility),
                    ];
                }

                // Price diagnostics
                $price_diag = [];
                if ($is_variable) {
                    $children_ids = $product->get_children();
                    $unpriced_ids = [];
                    $in_stock_vars = 0;
                    $out_of_stock_vars = 0;
                    $backorder_vars = 0;
                    $purchasable_vars = 0;

                    foreach ($children_ids as $cid) {
                        $v = wc_get_product($cid);
                        if (!$v || !$v->is_type('variation')) {
                            continue;
                        }

                        $v_price = $v->get_price();
                        $v_regular = $v->get_regular_price();
                        $v_stock = $v->get_stock_status();
                        $v_purchasable = $v->is_purchasable();

                        if ($v_regular === '' && $v_price === '') {
                            $unpriced_ids[] = $cid;
                        }

                        if ($v_stock === 'instock') {
                            $in_stock_vars++;
                        } elseif ($v_stock === 'outofstock') {
                            $out_of_stock_vars++;
                        } elseif ($v_stock === 'onbackorder') {
                            $backorder_vars++;
                        }

                        if ($v_purchasable) {
                            $purchasable_vars++;
                        }
                    }

                    $min_reg = $product->get_variation_regular_price('min');
                    $max_reg = $product->get_variation_regular_price('max');
                    $min_active = $product->get_variation_price('min');
                    $max_active = $product->get_variation_price('max');

                    $price_diag = [
                        'type' => 'variable',
                        'total_variations' => count($children_ids),
                        'unpriced_variations_count' => count($unpriced_ids),
                        'unpriced_variation_ids' => $unpriced_ids,
                        'regular_price_min' => ($min_reg !== '' && $min_reg !== false) ? (string)$min_reg : null,
                        'regular_price_max' => ($max_reg !== '' && $max_reg !== false) ? (string)$max_reg : null,
                        'price_min' => ($min_active !== '' && $min_active !== false) ? (string)$min_active : null,
                        'price_max' => ($max_active !== '' && $max_active !== false) ? (string)$max_active : null,
                    ];

                    if (empty($children_ids)) {
                        $issues[] = [
                            'severity' => 'critical',
                            'code' => 'no_variations',
                            'message' => __('Variable product has 0 variations.', 'aiutoma'),
                        ];
                        $recommendations[] = __('Create variations using woocommerce/manage-variations (action: "create") or generate from attributes.', 'aiutoma');
                    } elseif (!empty($unpriced_ids)) {
                        $issues[] = [
                            'severity' => 'critical',
                            'code' => 'variations_missing_price',
                            'message' => sprintf(__('%d variation(s) do not have a price set (IDs: %s). Variations without price cannot be added to cart.', 'aiutoma'), count($unpriced_ids), implode(', ', $unpriced_ids)),
                        ];
                        $recommendations[] = sprintf(__('Run woocommerce/bulk-update-variations with product_id %d and regular_price to set pricing on unpriced variations.', 'aiutoma'), $product_id);
                    }

                    if ($purchasable_vars === 0 && !empty($children_ids)) {
                        $issues[] = [
                            'severity' => 'critical',
                            'code' => 'no_purchasable_variations',
                            'message' => __('None of the variations are purchasable (missing prices, disabled, or out of stock).', 'aiutoma'),
                        ];
                    }
                } else {
                    $reg_p = $product->get_regular_price();
                    $sale_p = $product->get_sale_price();
                    $active_p = $product->get_price();

                    $price_diag = [
                        'type' => 'simple_or_other',
                        'regular_price' => $reg_p !== '' ? (string)$reg_p : null,
                        'sale_price' => $sale_p !== '' ? (string)$sale_p : null,
                        'price' => $active_p !== '' ? (string)$active_p : null,
                        'has_price' => ($active_p !== '' && $active_p !== null),
                    ];

                    if ($active_p === '' || $active_p === null) {
                        $issues[] = [
                            'severity' => 'critical',
                            'code' => 'missing_price',
                            'message' => __('Product has no active price defined.', 'aiutoma'),
                        ];
                        $recommendations[] = sprintf(__('Set product price using woocommerce/update-product (id: %d, price: "...").', 'aiutoma'), $product_id);
                    }
                }

                // Stock diagnostics
                $stock_status = $product->get_stock_status();
                $manage_stock = $product->get_manage_stock();
                $stock_qty = $product->get_stock_quantity();
                $in_stock = $product->is_in_stock();

                $stock_diag = [
                    'manage_stock' => (bool)$manage_stock,
                    'stock_status' => $stock_status,
                    'stock_quantity' => $stock_qty !== null ? (int)$stock_qty : null,
                    'backorders_allowed' => (bool)$product->backorders_allowed(),
                    'is_in_stock' => (bool)$in_stock,
                ];

                if ($is_variable) {
                    $stock_diag['variations_instock'] = $in_stock_vars;
                    $stock_diag['variations_outofstock'] = $out_of_stock_vars;
                    $stock_diag['variations_onbackorder'] = $backorder_vars;
                }

                if (!$in_stock) {
                    $issues[] = [
                        'severity' => 'warning',
                        'code' => 'out_of_stock',
                        'message' => __('Product is currently out of stock and backorders are not allowed.', 'aiutoma'),
                    ];
                    $recommendations[] = sprintf(__('Update stock status using woocommerce/update-product or woocommerce/bulk-update-variations (stock_status: "instock").', 'aiutoma'));
                }

                // Purchasability & Cart blockers
                $is_purchasable = (bool)$product->is_purchasable();
                $can_add_to_cart = $is_purchasable && $in_stock;

                // Inspect active filter hooks on woocommerce_is_purchasable
                global $wp_filter;
                $active_filters = [];

                $hooks_to_check = ['woocommerce_is_purchasable'];
                if ($is_variable) {
                    $hooks_to_check[] = 'woocommerce_variation_is_purchasable';
                }

                foreach ($hooks_to_check as $hook_name) {
                    if (isset($wp_filter[$hook_name])) {
                        $hook = $wp_filter[$hook_name];
                        $callbacks = is_object($hook) && property_exists($hook, 'callbacks') ? $hook->callbacks : (is_array($hook) ? $hook : []);
                        foreach ($callbacks as $priority => $priority_callbacks) {
                            foreach ($priority_callbacks as $cb_key => $cb_data) {
                                $function = $cb_data['function'] ?? null;
                                $cb_desc = 'unknown';

                                if (is_string($function)) {
                                    $cb_desc = $function;
                                } elseif (is_array($function) && count($function) === 2) {
                                    $class_name = is_object($function[0]) ? get_class($function[0]) : (string)$function[0];
                                    $cb_desc = $class_name . '::' . $function[1];
                                } elseif ($function instanceof \Closure) {
                                    try {
                                        $rf = new \ReflectionFunction($function);
                                        $cb_desc = sprintf('Closure at %s:%d', basename($rf->getFileName()), $rf->getStartLine());
                                    } catch (\Throwable $e) {
                                        $cb_desc = 'Closure';
                                    }
                                }

                                $active_filters[] = [
                                    'hook' => $hook_name,
                                    'priority' => $priority,
                                    'callback' => $cb_desc,
                                ];
                            }
                        }
                    }
                }

                if (!$is_purchasable) {
                    $issues[] = [
                        'severity' => 'critical',
                        'code' => 'not_purchasable',
                        'message' => __('is_purchasable() returned false. Shoppers cannot purchase this product.', 'aiutoma'),
                    ];

                    if (!empty($active_filters)) {
                        $recommendations[] = sprintf(__('Review %d active filter callback(s) hooked to woocommerce_is_purchasable which may be altering purchasability.', 'aiutoma'), count($active_filters));
                    }
                }

                // Overall Health Status
                $has_critical = false;
                $has_warning = false;
                foreach ($issues as $iss) {
                    if ($iss['severity'] === 'critical') $has_critical = true;
                    if ($iss['severity'] === 'warning') $has_warning = true;
                }

                $health_status = 'healthy';
                if ($has_critical) {
                    $health_status = 'critical';
                } elseif ($has_warning) {
                    $health_status = 'warning';
                }

                $summary = sprintf(
                    __('Product #%d "%s" (%s) is %s. Cart addition: %s. %d issue(s) detected.', 'aiutoma'),
                    $product_id,
                    $product->get_name(),
                    $type,
                    $is_purchasable ? __('purchasable', 'aiutoma') : __('NOT purchasable', 'aiutoma'),
                    $can_add_to_cart ? __('ENABLED', 'aiutoma') : __('BLOCKED', 'aiutoma'),
                    count($issues)
                );

                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'name' => $product->get_name(),
                    'slug' => $product->get_slug(),
                    'type' => $type,
                    'status' => $status,
                    'catalog_visibility' => $visibility,
                    'is_purchasable' => $is_purchasable,
                    'is_in_stock' => $in_stock,
                    'can_be_added_to_cart' => $can_add_to_cart,
                    'health_status' => $health_status,
                    'summary' => $summary,
                    'price_diagnostics' => $price_diag,
                    'stock_diagnostics' => $stock_diag,
                    'issues' => $issues,
                    'active_purchasable_filters' => $active_filters,
                    'recommendations' => $recommendations,
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'ID of the product to diagnose.'
                    ],
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Alias of product_id.'
                    ],
                    'sku' => [
                        'type' => 'string',
                        'description' => 'Product SKU to diagnose (used if product_id is not specified).'
                    ]
                ]
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/create-grouped-product', [
            'category' => 'woocommerce',
            'label' => __('Create WooCommerce Grouped Product', 'aiutoma'),
            'description' => __('Create a new WooCommerce grouped product bundle containing child products.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                if (!class_exists('WC_Product_Grouped')) {
                    return new \WP_Error('unsupported', 'WooCommerce Grouped Products are not supported or available.');
                }

                $product = new \WC_Product_Grouped();
                if (isset($input['name'])) $product->set_name($input['name']);
                if (isset($input['description'])) $product->set_description($input['description']);
                if (isset($input['short_description'])) $product->set_short_description($input['short_description']);
                if (isset($input['sku'])) $product->set_sku($input['sku']);
                if (isset($input['status'])) $product->set_status($input['status']);
                if (isset($input['children']) && is_array($input['children'])) {
                    $product->set_children(array_map('intval', $input['children']));
                }

                $product_id = $product->save();
                if (is_wp_error($product_id) || !$product_id) {
                    return new \WP_Error('save_failed', 'Failed to save grouped product.');
                }

                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'message' => 'Grouped product created successfully.'
                ];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Grouped product name'],
                    'children' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Array of child product IDs associated with this group'
                    ],
                    'description' => ['type' => 'string', 'description' => 'Product description'],
                    'short_description' => ['type' => 'string', 'description' => 'Product short description'],
                    'sku' => ['type' => 'string', 'description' => 'Product SKU'],
                    'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private'], 'description' => 'Product status']
                ],
                'required' => ['name']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/update-product', [
            'category' => 'woocommerce',
            'label' => __('Update WooCommerce Product', 'aiutoma'),
            'description' => __('Update an existing WooCommerce product (simple, variable, or grouped).', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $product = wc_get_product($input['id']);
                if (!$product) return new \WP_Error('invalid_product', 'Product not found.');
                
                if (isset($input['price'])) { $product->set_regular_price($input['price']); $product->set_price($input['price']); }
                if (isset($input['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($input['stock_quantity']); }
                if (isset($input['name'])) $product->set_name($input['name']);
                if (isset($input['status'])) $product->set_status($input['status']);
                if (isset($input['description'])) $product->set_description($input['description']);
                if (isset($input['short_description'])) $product->set_short_description($input['short_description']);
                if ($product->is_type('grouped') && isset($input['children']) && is_array($input['children'])) {
                    $product->set_children(array_map('intval', $input['children']));
                }
                
                $product->save();
                return ['success' => true, 'message' => 'Product updated successfully.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Product ID'],
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'price' => ['type' => 'string', 'description' => 'Product price'],
                    'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity'],
                    'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private'], 'description' => 'Product status'],
                    'description' => ['type' => 'string', 'description' => 'Product description'],
                    'short_description' => ['type' => 'string', 'description' => 'Product short description'],
                    'children' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Array of child product IDs (for grouped products)'
                    ]
                ],
                'required' => ['id']
            ]
        ]);



        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/update-order', [
            'category' => 'woocommerce',
            'label' => __('Update WooCommerce Order', 'aiutoma'),
            'description' => __('Update an existing WooCommerce order.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $order = wc_get_order($input['id']);
                if (!$order) return new \WP_Error('invalid_order', 'Order not found.');
                
                if (isset($input['status'])) $order->set_status($input['status']);
                
                $order->save();
                return ['success' => true, 'message' => 'Order updated successfully.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Order ID'],
                    'status' => ['type' => 'string', 'description' => 'New order status (e.g. processing, completed, cancelled, refunded)']
                ],
                'required' => ['id']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/get-coupons', [
            'category' => 'woocommerce',
            'label' => __('Get WooCommerce Coupons', 'aiutoma'),
            'description' => __('Retrieve a list of WooCommerce coupons.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $args = isset($input['args']) && is_array($input['args']) ? $input['args'] : [];
                $coupons = wc_get_coupons(array_merge(['limit' => 10], $args));
                $data = [];
                foreach ($coupons as $c) {
                    $data[] = [
                        'id' => $c->get_id(),
                        'code' => $c->get_code(),
                        'amount' => $c->get_amount(),
                        'discount_type' => $c->get_discount_type(),
                    ];
                }
                return ['success' => true, 'coupons' => $data];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'args' => ['type' => 'object', 'description' => 'Arguments for wc_get_coupons (e.g. limit).']
                ]
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/create-coupon', [
            'category' => 'woocommerce',
            'label' => __('Create WooCommerce Coupon', 'aiutoma'),
            'description' => __('Create a new WooCommerce coupon.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $coupon = new \WC_Coupon();
                if (isset($input['code'])) $coupon->set_code($input['code']);
                if (isset($input['amount'])) $coupon->set_amount($input['amount']);
                if (isset($input['discount_type'])) $coupon->set_discount_type($input['discount_type']);
                $coupon->save();
                return ['success' => true, 'coupon_id' => $coupon->get_id()];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'Coupon code'],
                    'amount' => ['type' => 'string', 'description' => 'Discount amount'],
                    'discount_type' => ['type' => 'string', 'description' => 'Discount type (e.g. percent, fixed_cart, fixed_product)']
                ],
                'required' => ['code', 'amount']
            ]
        ]);

        \Aiutoma\Modules\Ai\Abilities::register('woocommerce/update-coupon', [
            'category' => 'woocommerce',
            'label' => __('Update WooCommerce Coupon', 'aiutoma'),
            'description' => __('Update an existing WooCommerce coupon.', 'aiutoma'),
            'meta' => ['plugin_name' => 'WooCommerce'],
            'execute_callback' => function($input) {
                $coupon = new \WC_Coupon($input['id']);
                if (!$coupon->get_id()) return new \WP_Error('invalid_coupon', 'Coupon not found.');
                if (isset($input['code'])) $coupon->set_code($input['code']);
                if (isset($input['amount'])) $coupon->set_amount($input['amount']);
                if (isset($input['discount_type'])) $coupon->set_discount_type($input['discount_type']);
                $coupon->save();
                return ['success' => true, 'message' => 'Coupon updated.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Coupon ID'],
                    'code' => ['type' => 'string', 'description' => 'Coupon code'],
                    'amount' => ['type' => 'string', 'description' => 'Discount amount'],
                    'discount_type' => ['type' => 'string', 'description' => 'Discount type (e.g. percent, fixed_cart, fixed_product)']
                ],
                'required' => ['id']
            ]
        ]);

    }
}
