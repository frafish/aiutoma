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

call_user_func(function () {
    global $wpdb;

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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aiutoma\_%' OR option_name LIKE '\_site\_transient\_aiutoma\_%'");

    // 3. Delete Custom Tables
    $custom_tables = [
        $wpdb->prefix . 'document_embeddings',
        $wpdb->prefix . 'aiutoma_oauth_clients',
        $wpdb->prefix . 'aiutoma_oauth_tokens',
        $wpdb->prefix . 'aiutoma_request_logs',
    ];

    foreach ($custom_tables as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table) . "`");
    }

    delete_option('aiutoma_oauth_db_version');
    delete_option('aiutoma_skills_migrated_to_cpt');

    // 4. Delete Custom Post Types (aiutoma_task, aiutoma_skill)
    $post_types = ['aiutoma_task', 'aiutoma_skill'];
    foreach ($post_types as $pt) {
        $cpt_posts = get_posts([
            'post_type'   => $pt,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        if (!empty($cpt_posts)) {
            foreach ($cpt_posts as $p) {
                wp_delete_post($p->ID, true); // Force delete
            }
        }
    }
});
