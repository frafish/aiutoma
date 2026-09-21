<?php
namespace Aiutoma\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Init {
    public function register_playground_hooks() {

        add_action('rest_api_init', [$this, 'register_playground_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_playground_scripts']);
    }


    public function register_playground_routes() {
        register_rest_route('aiutoma/v1', '/ai-chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_request'],
            'permission_callback' => [\Aiutoma\Modules\Ai\Ai::instance(), 'chat_permission_check']
        ]);
        register_rest_route('aiutoma/v1', '/convert-media', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_convert_media'],
            'permission_callback' => [\Aiutoma\Modules\Ai\Ai::instance(), 'chat_permission_check']
        ]);
        register_rest_route('aiutoma/v1', '/get-abilities', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_abilities'],
            'permission_callback' => function () { return current_user_can('manage_options'); }
        ]);
        register_rest_route('aiutoma/v1', '/save-session', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_save_session'],
            'permission_callback' => [\Aiutoma\Modules\Ai\Ai::instance(), 'chat_permission_check']
        ]);
        register_rest_route('aiutoma/v1', '/get-session', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_session'],
            'permission_callback' => [\Aiutoma\Modules\Ai\Ai::instance(), 'chat_permission_check']
        ]);
    }
}
