<?php
namespace Aiutoma\Modules\Editor\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait InlinePrompt {
    public function register_inline_prompt_hooks() {
        add_action('init', [$this, 'register_inline_prompt_block']);
    }

    public function register_inline_prompt_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'aiutoma-inline-prompt-block',
            AIUTOMA_URL . 'modules/editor/assets/js/inline-prompt-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data'],
            file_exists(AIUTOMA_PATH . 'modules/editor/assets/js/inline-prompt-block.js') ? filemtime(AIUTOMA_PATH . 'modules/editor/assets/js/inline-prompt-block.js') : '1.0',
            true
        );

        register_block_type('aiutoma/prompt', [
            'editor_script' => 'aiutoma-inline-prompt-block',
            'attributes' => [
                'prompt' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);
    }
}
