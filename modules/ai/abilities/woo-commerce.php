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
