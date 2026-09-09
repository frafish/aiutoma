<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Aiutoma
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Delete all options/settings
$options = [
    'aiutoma_active_modules',
    'aiutoma_license_key',
    'aiutoma_license_status',
    'aiutoma_openai_api_key',
    'aiutoma_anthropic_api_key',
    'aiutoma_gemini_api_key',
    'aiutoma_huggingface_api_key',
    'aiutoma_cloudflare_api_token',
    'aiutoma_default_model',
    'aiutoma_mcp_token',
    'aiutoma_mcp_acting_user',
    'aiutoma_mcp_webhook_token',
    'aiutoma_safe_mode_allowlist',
    'aiutoma_tg_bot_token',
    'aiutoma_tg_allowed_chat_id',
    'aiutoma_wa_phone_number_id',
    'aiutoma_wa_access_token',
    'aiutoma_wa_target_number',
    'aiutoma_im_model',
    'aiutoma_im_system_prompt',
    'aiutoma_im_allow_critical',
    'aiutoma_im_acting_user',
    'aiutoma_wpml_model',
    'aiutoma_token_budget_cap',
    'aiutoma_markdown_enabled',
    'aiutoma_markdown_llmstxt_enabled',
    'aiutoma_markdown_cpts'
];

foreach ($options as $option) {
    delete_option($option);
}

// 2. Delete Transients (we clean up ones starting with aiutoma_)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aiutoma\_%' OR option_name LIKE '\_site\_transient\_aiutoma\_%'");

// 3. Delete Custom Tables
$table_name = $wpdb->prefix . 'document_embeddings';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

$oauth_clients = $wpdb->prefix . 'aiutoma_oauth_clients';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$oauth_clients}");

$oauth_tokens = $wpdb->prefix . 'aiutoma_oauth_tokens';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$oauth_tokens}");

// If we are the only one using it, drop it. (WP directory compliance)
$request_logs = $wpdb->prefix . 'aiutoma_request_logs';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$request_logs}");

delete_option('aiutoma_oauth_db_version');

// 4. Delete Custom Post Types (aiutoma_task) if needed. 
// Note: Usually it's better to keep user data unless explicitly asked, but to strictly comply with "no junk left behind", we can delete tasks.
$tasks = get_posts([
    'post_type' => 'aiutoma_task',
    'numberposts' => -1,
    'post_status' => 'any'
]);
foreach ($tasks as $task) {
    wp_delete_post($task->ID, true); // Force delete
}
