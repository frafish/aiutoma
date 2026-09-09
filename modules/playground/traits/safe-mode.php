<?php
namespace Aiutoma\Modules\Playground\Traits;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

trait SafeMode {

    public function enable_safe_mode() {
        do_action('aiutoma_enable_safe_mode');
    }

    public function disable_safe_mode() {
        do_action('aiutoma_disable_safe_mode');
    }

    public function toggle_safe_mode(\WP_REST_Request $request) {
        $default_response = new \WP_REST_Response([
            'success' => false,
            'message' => __('Safe Mode is handled by the Aiutoma Developer Extension companion plugin.', 'aiutoma'),
        ], 400);

        return apply_filters('aiutoma_toggle_safe_mode_response', $default_response, $request);
    }
}
