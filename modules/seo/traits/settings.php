<?php
namespace Aiutoma\Modules\Seo\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Settings {
    public function aiutoma_seo_page_html() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'content';
        
        $auto_optimize = get_option('aiutoma_auto_optimize_media', false);
        $preferred_model = get_option('aiutoma_seo_preferred_model', '');
        $text_model = get_option('aiutoma_seo_text_model', '');
        
        $md_enabled = get_option('aiutoma_markdown_enabled', '1');
        $md_llmstxt_enabled = get_option('aiutoma_markdown_llmstxt_enabled', '1');
        $md_selected_cpts = get_option('aiutoma_markdown_cpts', false);
        if ($md_selected_cpts === false) {
            $md_selected_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
        }
        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('SEO', 'aiutoma'); ?></h1>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
                <a href="?page=aiutoma-seo&tab=content" class="nav-tab <?php echo 'content' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Content', 'aiutoma'); ?></a>
                <a href="?page=aiutoma-seo&tab=media" class="nav-tab <?php echo 'media' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Media', 'aiutoma'); ?></a>
                <a href="?page=aiutoma-seo&tab=markdown" class="nav-tab <?php echo 'markdown' === $current_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Markdown', 'aiutoma'); ?></a>
            </nav>

            <?php if ('content' === $current_tab): ?>
            <div id="content-settings">
                <div class="card aiutoma-seo-card-wide">
                    <h2><?php esc_html_e('Bulk Content Optimization', 'aiutoma'); ?></h2>
                    <p><?php esc_html_e('Generate missing slugs, meta titles, descriptions, and excerpts for all your posts, pages, and custom post types. Compatible with Yoast, RankMath, and AIOSEO.', 'aiutoma'); ?></p>
                    
                    <div class="aiutoma-seo-flex-row">
                        <select id="aiutoma-content-seo-type">
                            <?php foreach ($post_types as $pt): ?>
                                <?php if ($pt->name === 'attachment') continue; ?>
                                <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->labels->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="aiutoma-content-seo-model">
                            <option value=""><?php esc_html_e('Automatic Text Model', 'aiutoma'); ?></option>
                        </select>
                        <button type="button" id="aiutoma-content-seo-load" class="button"><?php esc_html_e('Load Content', 'aiutoma'); ?></button>
                        <button type="button" id="aiutoma-content-seo-bulk" class="button button-primary" style="display:none;"><?php esc_html_e('Optimize Selected', 'aiutoma'); ?></button>
                    </div>

                    <table class="wp-list-table widefat fixed striped" id="aiutoma-content-seo-table" style="display:none;">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all" type="checkbox"></td>
                                <th scope="col"><?php esc_html_e('Title', 'aiutoma'); ?></th>
                                <th scope="col"><?php esc_html_e('Excerpt', 'aiutoma'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'aiutoma'); ?></th>
                                <th scope="col"><?php esc_html_e('Action', 'aiutoma'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ('media' === $current_tab): ?>
            <div id="media-settings">
            <div class="card aiutoma-seo-card">
                <h2><?php esc_html_e('Media Settings', 'aiutoma'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model (Vision)', 'aiutoma'); ?></th>
                        <td>
                            <select id="aiutoma-seo-model">
                                <option value=""><?php esc_html_e('Automatic (Recommended)', 'aiutoma'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select an AI model with vision capabilities (e.g. GPT-4o, Claude 3.5 Sonnet, Gemini 1.5).', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Model (Text)', 'aiutoma'); ?></th>
                        <td>
                            <select id="aiutoma-seo-text-model">
                                <option value=""><?php esc_html_e('Automatic (Recommended)', 'aiutoma'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select an AI model for text and logic processing (e.g. GPT-4, Claude 3, Llama 3).', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic Optimization', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="aiutoma-seo-auto" <?php checked($auto_optimize, 'true'); ?>>
                                <?php esc_html_e('Automatically optimize new images uploaded to the Media Library via Cron', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary aiutoma-seo-save-btn"><?php esc_html_e('Save Settings', 'aiutoma'); ?></button>
                </p>
            </div>

            <div class="card aiutoma-seo-card">
                <h2><?php esc_html_e('Bulk Optimization', 'aiutoma'); ?></h2>
                <p><?php esc_html_e('Scan your media library for images that have missing SEO metadata (Alt Text, Title, Description, Caption) and optimize them using AI.', 'aiutoma'); ?></p>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="aiutoma-seo-scan" class="button button-secondary"><?php esc_html_e('Scan Media Library', 'aiutoma'); ?></button>
                    <button type="button" id="aiutoma-seo-start-bulk" class="button button-primary" style="display:none;"><?php esc_html_e('Start Bulk Optimization', 'aiutoma'); ?></button>
                </div>

                <div id="aiutoma-seo-log" class="aiutoma-seo-log-container">
                    <ul id="aiutoma-seo-log-list" class="aiutoma-seo-log-list"></ul>
                </div>
            </div>
            </div>
            <?php endif; ?>

            <?php if ('markdown' === $current_tab): ?>
            <div id="markdown-settings">
            <div class="card aiutoma-seo-card">
                <h2><?php esc_html_e('Markdown Settings', 'aiutoma'); ?></h2>
                <p><?php esc_html_e('Configure how Aiutoma exposes your site content as Markdown for AI crawlers.', 'aiutoma'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Markdown', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="aiutoma-md-enabled" value="1" <?php checked('1', $md_enabled); ?>>
                                <?php esc_html_e('Enable Markdown conversion and .md endpoints', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable llms.txt', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="aiutoma-md-llmstxt-enabled" value="1" <?php checked('1', $md_llmstxt_enabled); ?>>
                                <?php esc_html_e('Enable the /llms.txt endpoint for AI crawlers', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled Post Types', 'aiutoma'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $pt): ?>
                                    <label class="aiutoma-seo-checkbox-row">
                                        <input type="checkbox" class="aiutoma-md-cpt-checkbox" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $md_selected_cpts)); ?>>
                                        <?php echo esc_html($pt->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Select which post types should be exposed as Markdown.', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary aiutoma-seo-save-btn"><?php esc_html_e('Save Settings', 'aiutoma'); ?></button>
                </p>
            </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
