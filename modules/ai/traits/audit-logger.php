<?php
namespace Aiutoma\Modules\Ai\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AuditLogger {
    private $log_file = null;

    public function init_audit_log() {
        $upload_dir = wp_upload_dir();
        $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $this->log_file = $log_dir . '/audit.log';
        return true;
    }

    public function log_audit_event($context, $tool_name, $parameters, $status, $error_msg = null) {
        if (!$this->log_file) {
            $this->init_audit_log();
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $params_str = is_array($parameters) || is_object($parameters) ? wp_json_encode($parameters) : $parameters;
        
        $log_entry = sprintf(
            "[%s] CONTEXT: %s | TOOL: %s | STATUS: %s | PARAMS: %s | ERROR: %s\n",
            $timestamp,
            $context,
            $tool_name,
            $status,
            $params_str,
            $error_msg ? $error_msg : 'None'
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        return error_log($log_entry, 3, $this->log_file);
    }
    
    public function get_audit_logs($limit = 100, $offset = 0) {
        if (!$this->log_file) {
            $this->init_audit_log();
        }
        
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        
        $lines = array_reverse($lines);
        return array_slice($lines, $offset, $limit);
    }
}
