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
            <h1 class="wp-heading-inline"><?php esc_html_e('SEO/GEO/AEO', 'aiutoma'); ?></h1>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
                <a href="?page=aiutoma-seo&tab=content" class="nav-tab <?php echo esc_attr('content' === $current_tab ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Content', 'aiutoma'); ?></a>
                <a href="?page=aiutoma-seo&tab=media" class="nav-tab <?php echo esc_attr('media' === $current_tab ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Media', 'aiutoma'); ?></a>
                <a href="?page=aiutoma-seo&tab=markdown" class="nav-tab <?php echo esc_attr('markdown' === $current_tab ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Markdown', 'aiutoma'); ?></a>
                <a href="?page=aiutoma-seo&tab=crawlers" class="nav-tab <?php echo esc_attr('crawlers' === $current_tab ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('AI Crawlers & Robots.txt', 'aiutoma'); ?></a>
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

            <?php if ('crawlers' === $current_tab):
                $robots_data = self::get_crawlers_settings();
                $crawlers = $robots_data['crawlers'];
                $training_bots = array_filter($crawlers, function($c) { return $c['type'] === 'training'; });
                $answer_bots = array_filter($crawlers, function($c) { return $c['type'] === 'answer'; });
                $blocked_count = count(array_filter($crawlers, function($c) { return $c['state'] === 'block'; }));
                $total_count = count($crawlers);
            ?>
            <div id="crawlers-settings">
                <?php if ($robots_data['has_static_file']): ?>
                    <div class="notice notice-error" style="margin: 15px 0;">
                        <p><strong>⚠️ <?php esc_html_e('Static robots.txt detected!', 'aiutoma'); ?></strong></p>
                        <p><?php esc_html_e('A physical robots.txt file exists in your WordPress root directory. Web servers (Nginx/Apache) will serve that file directly, ignoring dynamic WordPress rules. To use Aiutoma\'s AI crawler controls, please rename or delete the static file at: ', 'aiutoma'); ?> <code><?php echo esc_html(ABSPATH . 'robots.txt'); ?></code></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success" style="margin: 15px 0;">
                        <p><strong>✅ <?php esc_html_e('Virtual robots.txt is active and managed dynamically.', 'aiutoma'); ?></strong> <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" class="button button-small" style="margin-left: 8px;"><?php esc_html_e('View Public /robots.txt ↗', 'aiutoma'); ?></a></p>
                    </div>
                <?php endif; ?>

                <?php if (!$robots_data['site_public']): ?>
                    <div class="notice notice-warning" style="margin: 15px 0;">
                        <p><strong>ℹ️ <?php esc_html_e('Search engines are currently discouraged in WordPress Settings > Reading.', 'aiutoma'); ?></strong> <?php esc_html_e('WordPress currently serves Disallow: / to all crawlers. The specific AI directives below will still be appended cleanly.', 'aiutoma'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="card aiutoma-seo-card-wide aiutoma-seo-server-alert">
                    <div class="aiutoma-seo-alert-icon">🛡️</div>
                    <div class="aiutoma-seo-alert-content">
                        <h3><?php esc_html_e('Server Protection & AI Crawler Control', 'aiutoma'); ?></h3>
                        <p><?php esc_html_e('Aggressive AI training bots (like ByteDance/Bytespider, Common Crawl, and OpenAI GPTBot) often crawl hundreds of pages simultaneously, depleting PHP workers, RAM, and MySQL connections. Aiutoma allows you to block data scrapers that drain server resources without providing referral traffic, while keeping Answer Engines (ChatGPT Search, Perplexity, Claude) active so your content remains discoverable and cited in generative AI search results (GEO).', 'aiutoma'); ?></p>
                    </div>
                </div>

                <div class="card aiutoma-seo-card-wide">
                    <div class="aiutoma-crawler-header-bar">
                        <div>
                            <h2><?php esc_html_e('AI Crawlers Configuration', 'aiutoma'); ?></h2>
                            <p class="description">
                                <?php printf(
                                    /* translators: 1: number of blocked crawlers, 2: total number of crawlers */
                                    esc_html__('Currently blocking %1$d of %2$d recognized AI crawlers.', 'aiutoma'),
                                    $blocked_count,
                                    $total_count
                                ); ?>
                            </p>
                        </div>
                        <div class="aiutoma-crawler-master-toggle">
                            <label>
                                <input type="checkbox" id="aiutoma-robots-ai-enabled" value="1" <?php checked($robots_data['ai_enabled']); ?>>
                                <strong><?php esc_html_e('Enable AI Robots.txt Management', 'aiutoma'); ?></strong>
                            </label>
                        </div>
                    </div>

                    <!-- Quick Action Presets -->
                    <div class="aiutoma-crawler-presets-bar">
                        <span class="aiutoma-presets-title">⚡ <?php esc_html_e('Quick Presets:', 'aiutoma'); ?></span>
                        <button type="button" class="button button-secondary aiutoma-preset-btn" data-preset="protect-server">
                            🛡️ <?php esc_html_e('Protect Server (Recommended)', 'aiutoma'); ?>
                        </button>
                        <button type="button" class="button button-secondary aiutoma-preset-btn" data-preset="block-all">
                            🛑 <?php esc_html_e('Block All AI Bots', 'aiutoma'); ?>
                        </button>
                        <button type="button" class="button button-secondary aiutoma-preset-btn" data-preset="allow-all">
                            🌐 <?php esc_html_e('Allow All AI Bots', 'aiutoma'); ?>
                        </button>
                        <button type="button" class="button button-secondary aiutoma-preset-btn" data-preset="reset-defaults">
                            🔄 <?php esc_html_e('Reset Defaults', 'aiutoma'); ?>
                        </button>
                    </div>

                    <!-- Training Crawlers Table -->
                    <h3 class="aiutoma-crawler-section-title">
                        <span class="dashicons dashicons-shield-alt" style="color: #d63638;"></span>
                        <?php esc_html_e('AI Training Crawlers & Model Harvesters', 'aiutoma'); ?>
                        <span class="aiutoma-badge-count-training"><?php echo count($training_bots); ?></span>
                    </h3>
                    <p class="description"><?php esc_html_e('These bots scrape site text and media to train machine learning models. They do not generate direct user visits or citation links. Recommended: BLOCK to save server bandwidth and CPU.', 'aiutoma'); ?></p>

                    <table class="wp-list-table widefat fixed striped aiutoma-crawlers-table">
                        <thead>
                            <tr>
                                <th style="width: 28%;"><?php esc_html_e('Crawler Name / Organization', 'aiutoma'); ?></th>
                                <th style="width: 18%;"><?php esc_html_e('User-Agent Token', 'aiutoma'); ?></th>
                                <th style="width: 14%;"><?php esc_html_e('Server Impact', 'aiutoma'); ?></th>
                                <th style="width: 25%;"><?php esc_html_e('Description', 'aiutoma'); ?></th>
                                <th style="width: 15%; text-align: right;"><?php esc_html_e('Access Rule', 'aiutoma'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($training_bots as $slug => $bot): ?>
                            <tr data-slug="<?php echo esc_attr($slug); ?>" data-type="training" data-default="<?php echo esc_attr($bot['default']); ?>">
                                <td>
                                    <strong><?php echo esc_html($bot['label']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo esc_html($bot['user_agent']); ?></code>
                                </td>
                                <td>
                                    <?php if ($bot['impact'] === 'critical'): ?>
                                        <span class="aiutoma-impact-pill impact-critical">🔥 <?php esc_html_e('Critical Impact', 'aiutoma'); ?></span>
                                    <?php elseif ($bot['impact'] === 'high'): ?>
                                        <span class="aiutoma-impact-pill impact-high">⚠️ <?php esc_html_e('High Impact', 'aiutoma'); ?></span>
                                    <?php elseif ($bot['impact'] === 'medium'): ?>
                                        <span class="aiutoma-impact-pill impact-medium"><?php esc_html_e('Medium Impact', 'aiutoma'); ?></span>
                                    <?php else: ?>
                                        <span class="aiutoma-impact-pill impact-low"><?php esc_html_e('Low Impact', 'aiutoma'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="aiutoma-crawler-desc"><?php echo esc_html($bot['description']); ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <select class="aiutoma-crawler-select" data-slug="<?php echo esc_attr($slug); ?>">
                                        <option value="block" <?php selected($bot['state'], 'block'); ?>>🚫 <?php esc_html_e('Block (Disallow)', 'aiutoma'); ?></option>
                                        <option value="allow" <?php selected($bot['state'], 'allow'); ?>>✅ <?php esc_html_e('Allow', 'aiutoma'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Answer Engine & AI Search Crawlers Table -->
                    <h3 class="aiutoma-crawler-section-title" style="margin-top: 35px;">
                        <span class="dashicons dashicons-search" style="color: #00a32a;"></span>
                        <?php esc_html_e('AI Answer Engines & Search Crawlers (GEO)', 'aiutoma'); ?>
                        <span class="aiutoma-badge-count-answer"><?php echo count($answer_bots); ?></span>
                    </h3>
                    <p class="description"><?php esc_html_e('These crawlers ground live search answers and cite pages with clickable links (e.g. in ChatGPT Search or Perplexity). Blocking them will reduce server queries, but your site will not appear as a cited source in AI answer engines.', 'aiutoma'); ?></p>

                    <table class="wp-list-table widefat fixed striped aiutoma-crawlers-table">
                        <thead>
                            <tr>
                                <th style="width: 28%;"><?php esc_html_e('Answer Engine', 'aiutoma'); ?></th>
                                <th style="width: 18%;"><?php esc_html_e('User-Agent Token', 'aiutoma'); ?></th>
                                <th style="width: 14%;"><?php esc_html_e('Traffic Benefit', 'aiutoma'); ?></th>
                                <th style="width: 25%;"><?php esc_html_e('Description', 'aiutoma'); ?></th>
                                <th style="width: 15%; text-align: right;"><?php esc_html_e('Access Rule', 'aiutoma'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answer_bots as $slug => $bot): ?>
                            <tr data-slug="<?php echo esc_attr($slug); ?>" data-type="answer" data-default="<?php echo esc_attr($bot['default']); ?>">
                                <td>
                                    <strong><?php echo esc_html($bot['label']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo esc_html($bot['user_agent']); ?></code>
                                </td>
                                <td>
                                    <span class="aiutoma-impact-pill traffic-citation">🔗 <?php esc_html_e('Direct Citations', 'aiutoma'); ?></span>
                                </td>
                                <td>
                                    <span class="aiutoma-crawler-desc"><?php echo esc_html($bot['description']); ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <select class="aiutoma-crawler-select" data-slug="<?php echo esc_attr($slug); ?>">
                                        <option value="allow" <?php selected($bot['state'], 'allow'); ?>>✅ <?php esc_html_e('Allow', 'aiutoma'); ?></option>
                                        <option value="block" <?php selected($bot['state'], 'block'); ?>>🚫 <?php esc_html_e('Block (Disallow)', 'aiutoma'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Advanced Server Protection Options -->
                    <h3 class="aiutoma-crawler-section-title" style="margin-top: 35px;">
                        <span class="dashicons dashicons-performance" style="color: #2271b1;"></span>
                        <?php esc_html_e('Advanced Server Performance & Rate Limiting', 'aiutoma'); ?>
                    </h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aiutoma-robots-crawl-delay"><?php esc_html_e('Crawl-Delay (Rate Limiting)', 'aiutoma'); ?></label>
                            </th>
                            <td>
                                <select id="aiutoma-robots-crawl-delay">
                                    <option value="0" <?php selected($robots_data['crawl_delay'], 0); ?>><?php esc_html_e('Disabled (Default)', 'aiutoma'); ?></option>
                                    <option value="2" <?php selected($robots_data['crawl_delay'], 2); ?>><?php esc_html_e('2 Seconds (Mild throttling)', 'aiutoma'); ?></option>
                                    <option value="5" <?php selected($robots_data['crawl_delay'], 5); ?>><?php esc_html_e('5 Seconds (Recommended for shared/VPS hosting)', 'aiutoma'); ?></option>
                                    <option value="10" <?php selected($robots_data['crawl_delay'], 10); ?>><?php esc_html_e('10 Seconds (Aggressive throttling for low-resource servers)', 'aiutoma'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Applies a Crawl-delay directive for allowed AI bots. Polite bots will wait the specified interval between requests, preventing sudden traffic spikes from exhausting PHP workers.', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Resource-Intensive Endpoints', 'aiutoma'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="aiutoma-robots-block-endpoints" value="1" <?php checked($robots_data['block_sensitive_endpoints']); ?>>
                                    <?php esc_html_e('Disallow internal search query scanning (/?s=) and REST API (/wp-json/) for automated scrapers', 'aiutoma'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Crawling internal search results triggers un-cached SQL queries that often take down WordPress databases. Recommended: Keep enabled.', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="aiutoma-robots-custom"><?php esc_html_e('Custom Robots.txt Directives', 'aiutoma'); ?></label>
                            </th>
                            <td>
                                <textarea id="aiutoma-robots-custom" rows="4" class="large-text code" placeholder="<?php esc_attr_e("e.g.&#10;User-agent: BadBot&#10;Disallow: /&#10;Disallow: /private-folder/", 'aiutoma'); ?>"><?php echo esc_textarea($robots_data['custom_directives']); ?></textarea>
                                <p class="description"><?php esc_html_e('Enter any custom User-agent rules or Disallow paths you wish to append to your robots.txt.', 'aiutoma'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <!-- Live Robots.txt Preview Box -->
                    <h3 class="aiutoma-crawler-section-title" style="margin-top: 30px;">
                        <span class="dashicons dashicons-visibility" style="color: #646970;"></span>
                        <?php esc_html_e('Live robots.txt Preview', 'aiutoma'); ?>
                    </h3>
                    <p class="description"><?php esc_html_e('This is the exact output that will be served to search engines and AI crawlers at /robots.txt:', 'aiutoma'); ?></p>
                    
                    <div class="aiutoma-robots-preview-wrapper">
                        <div class="aiutoma-robots-preview-actions">
                            <button type="button" class="button button-small" id="aiutoma-copy-robots-preview">📋 <?php esc_html_e('Copy Output', 'aiutoma'); ?></button>
                            <button type="button" class="button button-small" id="aiutoma-refresh-robots-preview">🔄 <?php esc_html_e('Refresh Preview', 'aiutoma'); ?></button>
                            <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" class="button button-small">🌐 <?php esc_html_e('Open Live /robots.txt', 'aiutoma'); ?></a>
                        </div>
                        <pre id="aiutoma-robots-preview" class="aiutoma-robots-preview-pre"><code><?php echo esc_html($this->get_simulated_robots_txt()); ?></code></pre>
                    </div>

                    <div style="margin-top: 25px;">
                        <button type="button" class="button button-primary button-large aiutoma-robots-save-btn">
                            <?php esc_html_e('Save AI Robots Settings', 'aiutoma'); ?>
                        </button>
                        <span class="spinner aiutoma-robots-spinner" style="float: none; margin-left: 8px;"></span>
                        <span id="aiutoma-robots-save-status" style="margin-left: 10px; font-weight: 600; color: #00a32a; display: none;"></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
