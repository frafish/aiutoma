<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
/**
 * Cron script to generate and store embeddings for WordPress content.
 * Incremental RAG Vector DB Update.
 * 
 * Usage: php cron/rag.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once dirname(__FILE__) . '/traits/rag-processors.php';
require_once dirname(__FILE__) . '/traits/rag-embeddings.php';
require_once dirname(__FILE__) . '/traits/content-extractor.php';
require_once dirname(__FILE__) . '/traits/document-parser.php';

class Aiutoma_RAG_Embeddings_Cron {
    use \Aiutoma\Modules\Ai\Traits\RAG_Processors;
    use \Aiutoma\Modules\Ai\Traits\RAG_Embeddings;
    use \Aiutoma\Modules\Ai\Traits\ContentExtractor;
    use \Aiutoma\Modules\Ai\Traits\DocumentParser;

    private $db;
    private $db_dir;
    private $api_key;
    private $batch_size = 50; 
    private $chunk_size = 1500; 

    private $provider;

    public function __construct() {
        $this->provider = get_option('aiutoma_rag_embedding_provider', '');
        
        if (empty($this->provider)) {
            $error_msg = "Error: No Embedding Provider selected. Please configure a provider in the Aiutoma RAG Settings.";
            if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                die(esc_html($error_msg) . "\n");
            } else {
                wp_die(esc_html($error_msg));
            }
        }
        
        $this->api_key = '';
        if (class_exists('\WordPress\AiClient\AiClient')) {
            try {
                $provider_id = $this->provider === 'gemini' ? 'google' : $this->provider;
                $auth = \WordPress\AiClient\AiClient::defaultRegistry()->getProviderRequestAuthentication($provider_id);
                if ($auth instanceof \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication) {
                    $this->api_key = $auth->getApiKey();
                }
            } catch (\Throwable $e) {
                // Ignore if not yet configured
            }
        }
        
        if ($this->provider === 'huggingface') {
            if (empty($this->api_key) && defined('HUGGINGFACE_API_KEY')) {
                $this->api_key = constant('HUGGINGFACE_API_KEY');
            }
            if (empty($this->api_key)) {
                $error_msg = "Error: HuggingFace API key is missing. Please configure it in the WordPress Connectors settings.";
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die(esc_html($error_msg) . "\n");
                } else {
                    wp_die(esc_html($error_msg));
                }
            }
        } elseif ($this->provider === 'openai') {
            if (empty($this->api_key) && defined('OPENAI_API_KEY')) {
                $this->api_key = constant('OPENAI_API_KEY');
            }
            if (empty($this->api_key)) {
                $error_msg = "Error: OpenAI API key is missing. Please configure it in the WordPress Connectors settings.";
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die(esc_html($error_msg) . "\n");
                } else {
                    wp_die(esc_html($error_msg));
                }
            }
        } else {
            // Default Gemini
            if (empty($this->api_key) && defined('GEMINI_API_KEY')) {
                $this->api_key = constant('GEMINI_API_KEY');
            } elseif (empty($this->api_key) && defined('GOOGLE_API_KEY')) {
                $this->api_key = constant('GOOGLE_API_KEY');
            }

            if (empty($this->api_key)) {
                $core_ai_url = admin_url('options-connectors.php');
                $error_msg_cli = "Error: Gemini API key is missing. Please configure it in the WordPress Connectors page or define GEMINI_API_KEY in wp-config.php.";
                $error_msg_web = "<h3>Error: Gemini API key is missing</h3>"
                    . "<p>To generate vector embeddings for RAG, you must configure a Gemini API key.</p>"
                    . "<p>You can set it up in one of the following ways:</p>"
                    . "<ul>"
                    . "<li>Use the <a href='" . esc_url($core_ai_url) . "'>WordPress Core AI Connectors</a> settings.</li>"
                    . "<li>Define <code>GEMINI_API_KEY</code> in your <code>wp-config.php</code> file.</li>"
                    . "</ul>"
                    . "<p>If you don't have a key, you can get one for free at <a href='https://aistudio.google.com/app/apikey' target='_blank'>Google AI Studio</a>.</p>";
                
                if (php_sapi_name() === 'cli' || defined('DOING_CRON')) {
                    die(esc_html($error_msg_cli) . "\n");
                } else {
                    wp_die(wp_kses_post($error_msg_web));
                }
            }
        }

        $this->init_db();
    }

    private function init_db() {
        // Place the SQLite database securely in the plugin's uploads or data directory
        $upload_dir = wp_upload_dir();
        $this->db_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '';
        
        if (!file_exists($this->db_dir)) {
            wp_mkdir_p($this->db_dir);
            // Protect directory from web access
            file_put_contents($this->db_dir . '/.htaccess', "Deny from all\n");
            file_put_contents($this->db_dir . '/index.php', "<?php // Silence is golden.");
        }

        $db_path = $this->db_dir . '/rag.sqlite';
        
        try {
            $this->db = new PDO('sqlite:' . $db_path);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "
                CREATE TABLE IF NOT EXISTS document_embeddings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    chunk_index INTEGER NOT NULL,
                    post_type TEXT NOT NULL,
                    post_title TEXT NOT NULL,
                    post_url TEXT NOT NULL,
                    content_hash TEXT NOT NULL,
                    text_content TEXT NOT NULL,
                    embedding TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_post_id ON document_embeddings(post_id);
            ";
            $this->db->exec($query);
        } catch (PDOException $e) {
            die("SQLite Connection failed: " . esc_html($e->getMessage()) . "\n");
        }
    }

    public function run() {
        // Prepare execution limits for batch processing
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        wp_suspend_cache_addition(true);

        $this->log("Starting advanced incremental RAG embeddings update...");
        
        $this->cleanup_deleted_objects();
        
        // Distribute batch limits across different domains
        $this->batch_size = 50; 
        
        if (get_option('aiutoma_rag_sync_contents', 1) == 1 || get_option('aiutoma_rag_sync_products', 1) == 1) {
            $this->process_posts();
        }
        if (get_option('aiutoma_rag_sync_terms', 1) == 1) {
            $this->process_terms();
        }
        if (get_option('aiutoma_rag_sync_settings', 1) == 1) {
            $this->process_settings();
        }
        if (get_option('aiutoma_rag_sync_plugins', 0) == 1) {
            $this->process_plugins_apis();
        }
        if (get_option('aiutoma_rag_sync_comments', 0) == 1) {
            $this->process_comments();
        }
        if (get_option('aiutoma_rag_sync_media', 0) == 1) {
            $this->process_media();
        }
        
        $this->export_to_json();
        
        $this->log("Update completed.");
    }

    private function log($message) {
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] [Aiutoma RAG] " . $message . "\n";
        
        if (php_sapi_name() === 'cli') {
            echo esc_html($log_message);
        }
        
        if (!empty($this->db_dir)) {
            $log_dir = $this->db_dir . '/logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
                file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.");
            }
            $log_file = $log_dir . '/sync.log';
            file_put_contents($log_file, $log_message, FILE_APPEND);
        }
    }
}
