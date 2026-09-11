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
