<?php
namespace Aiutoma\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AiTokensLog trait
 *
 * Integration with AI Request Logging components.
 *
 * @package Aiutoma
 */

use Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Manager;
use Aiutoma\Modules\Ai\Classes\TokensLog\AI_Request_Log_Page;
use Aiutoma\Modules\Ai\Classes\TokensLog\REST\AI_Request_Log_Controller;
use Aiutoma\Modules\Ai\Classes\TokensLog\Logging_Integration;

trait AiTokensLog {

    public function register_ai_tokens_log_hooks() {
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Schema.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Repository.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Manager.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/Log_Data_Extractor.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/Logging_Http_Transporter.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/Logging_Integration.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/AI_Request_Log_Page.php';
        require_once AIUTOMA_PATH . 'modules/ai/classes/tokens-log/REST/AI_Request_Log_Controller.php';

        $manager = new AI_Request_Log_Manager();
        $manager->init();
        Logging_Integration::init($manager);

        $controller = new AI_Request_Log_Controller($manager);
        $page       = new AI_Request_Log_Page($manager);

        add_action('rest_api_init', [$controller, 'register_routes']);
        add_action('admin_menu', [$page, 'register_menu'], 999);
    }
}
