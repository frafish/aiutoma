<?php
namespace Aiutoma\Modules\Ai;
if ( ! defined( 'ABSPATH' ) ) exit;
class Abilities {

    public static function register($name, $args) {
        if (!isset($args['meta'])) {
            $args['meta'] = [];
        }
        if (!isset($args['meta']['show_in_rest'])) {
            $args['meta']['show_in_rest'] = true;
        }
        if (!isset($args['meta']['mcp'])) {
            $args['meta']['mcp'] = [
                'public' => true,
                'type'   => 'tool'
            ];
        }
        if (!isset($args['meta']['expose_in_deprecated_woocommerce_mcp'])) {
            $args['meta']['expose_in_deprecated_woocommerce_mcp'] = true;
        }
        if (!isset($args['meta']['plugin_name'])) {
            if (strpos($name, 'woocommerce/') === 0) {
                $args['meta']['plugin_name'] = 'WooCommerce';
            } else {
                $args['meta']['plugin_name'] = 'Aiutoma';
            }
        }

        if (function_exists('wp_has_ability') && wp_has_ability($name)) {
            return;
        }

        if (function_exists('wp_register_ability')) {
            wp_register_ability($name, $args);
        }
    }

    public function __construct() {
        $this->init_schema_resilience();

        add_action('wp_abilities_api_categories_init', function() {
            if (did_action('init')) {
                $this->register_abilities_categories();
            } else {
                add_action('init', [$this, 'register_abilities_categories'], 11);
            }
        });
        add_action('wp_abilities_api_init', function() {
            if (did_action('init')) {
                $this->register_abilities();
            } else {
                add_action('init', [$this, 'register_abilities'], 11);
            }
        });
    }

    /**
     * Initialize schema resilience filters to prevent MCP schema validation blocks.
     */
    public function init_schema_resilience() {
        // 1. Ensure any ability registered without input_schema has a default object schema,
        // and relax woocommerce/products-query price schemas
        add_filter('wp_register_ability_args', function($args, $name) {
            if (!is_array($args)) {
                return $args;
            }

            // Provide default input schema for abilities missing one (e.g. mcp-adapter/discover-abilities)
            if (empty($args['input_schema'])) {
                $args['input_schema'] = [
                    'type' => 'object',
                    'properties' => (object)[],
                ];
            }

            // Relax woocommerce/products-query and related product query output price schemas to allow string, number, null
            if (strpos($name, 'woocommerce/') === 0 && isset($args['output_schema']) && is_array($args['output_schema'])) {
                $relax_prices = function(&$schema) use (&$relax_prices) {
                    if (!is_array($schema)) return;
                    if (isset($schema['properties']) && is_array($schema['properties'])) {
                        foreach (['price', 'regular_price', 'sale_price'] as $p_key) {
                            if (isset($schema['properties'][$p_key]) && is_array($schema['properties'][$p_key])) {
                                $schema['properties'][$p_key]['type'] = ['string', 'number', 'null'];
                            }
                        }
                        foreach ($schema['properties'] as &$sub) {
                            $relax_prices($sub);
                        }
                    }
                    if (isset($schema['items']) && is_array($schema['items'])) {
                        $relax_prices($schema['items']);
                    }
                };
                $relax_prices($args['output_schema']);
            }

            return $args;
        }, 10, 2);

        // 2. Prevent failure when empty input [] or {} is passed to an ability without schema
        add_filter('wp_ability_validate_input', function($is_valid, $input, $ability_name) {
            if (is_wp_error($is_valid) && $is_valid->get_error_code() === 'ability_missing_input_schema') {
                if (empty($input) || (is_array($input) && count($input) === 0)) {
                    return true;
                }
            }
            return $is_valid;
        }, 10, 3);

        // 3. Normalize empty array input to null if the ability defines no input schema
        add_filter('wp_ability_normalize_input', function($input, $ability_name, $ability) {
            if (is_array($input) && empty($input)) {
                $schema = method_exists($ability, 'get_input_schema') ? $ability->get_input_schema() : null;
                if (empty($schema)) {
                    return null;
                }
            }
            return $input;
        }, 10, 3);

        // 4. Ensure minor price type mismatches in output validation don't break response
        add_filter('wp_ability_validate_output', function($is_valid, $output, $ability_name) {
            if (is_wp_error($is_valid)) {
                $msg = $is_valid->get_error_message();
                if ((strpos($msg, '[price]') !== false || strpos($msg, '[regular_price]') !== false || strpos($msg, '[sale_price]') !== false) &&
                    (strpos($msg, 'string') !== false || strpos($msg, 'tipo string') !== false)) {
                    return true;
                }
            }
            return $is_valid;
        }, 10, 3);
    }

    public function register_abilities_categories() {
        if (!wp_has_ability_category('aiutoma')) {
            wp_register_ability_category('aiutoma', [
                'label' => __('Aiutoma', 'aiutoma'),
                'description' => __('Aiutoma Abilities', 'aiutoma')
            ]);
        }
        if (!wp_has_ability_category('wpml')) {
            wp_register_ability_category('wpml', [
                'label' => __('WPML', 'aiutoma'),
                'description' => __('WPML Abilities', 'aiutoma')
            ]);
        }
        if (!wp_has_ability_category('woocommerce')) {
            wp_register_ability_category('woocommerce', [
                'label' => __('WooCommerce', 'aiutoma'),
                'description' => __('WooCommerce Abilities', 'aiutoma')
            ]);
        }
        if (!wp_has_ability_category('gutenberg')) {
            wp_register_ability_category('gutenberg', [
                'label' => __('Gutenberg', 'aiutoma'),
                'description' => __('Gutenberg Abilities', 'aiutoma')
            ]);
        }
        if (!wp_has_ability_category('mcp-adapter')) {
            wp_register_ability_category('mcp-adapter', [
                'label' => __('MCP Adapter', 'aiutoma'),
                'description' => __('MCP Adapter Abilities', 'aiutoma')
            ]);
        }
    }

    public function register_abilities() {
        
        try {
            $registrar = new class {
            use Abilities\Core;
            use Abilities\WordPress;
            use Abilities\WooCommerce;
            use Abilities\Wpml;

            use Abilities\Gutenberg;
        };

        $registrar->register_core_abilities();
        $registrar->register_wordpress_abilities();
        $registrar->register_woocommerce_abilities();
        $registrar->register_wpml_abilities();

        $registrar->register_gutenberg_abilities();
        
        do_action('aiutoma_register_abilities');
        
        } catch (\Throwable $e) {
            
        }
    }
}
