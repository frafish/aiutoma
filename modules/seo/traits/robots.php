<?php
namespace Aiutoma\Modules\Seo\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait Robots {
    /**
     * Complete catalog of known AI crawlers, segmented by Training vs Answer/Search engines.
     */
    public static function get_crawlers_catalog(): array {
        return [
            // ==========================================
            // Training Crawlers (Model Scrapers & Data Harvesters)
            // ==========================================
            'bytespider' => [
                'label'       => __('ByteDance / TikTok (Bytespider)', 'aiutoma'),
                'user_agent'  => 'Bytespider',
                'type'        => 'training',
                'impact'      => 'critical',
                'description' => __('Extremely aggressive scraper harvesting content for ByteDance/TikTok AI. Frequently opens dozens of parallel connections that exhaust PHP workers and CPU.', 'aiutoma'),
                'default'     => 'block',
            ],
            'ccbot' => [
                'label'       => __('Common Crawl (CCBot)', 'aiutoma'),
                'user_agent'  => 'CCBot',
                'type'        => 'training',
                'impact'      => 'high',
                'description' => __('Massive scraper used to build open AI training corpora. Can hammer servers with hundreds of unthrottled requests.', 'aiutoma'),
                'default'     => 'block',
            ],
            'gptbot' => [
                'label'       => __('ChatGPT Training (GPTBot)', 'aiutoma'),
                'user_agent'  => 'GPTBot',
                'type'        => 'training',
                'impact'      => 'high',
                'description' => __('OpenAI scraper collecting training data for foundational GPT models. Does not supply direct referral clicks or citations.', 'aiutoma'),
                'default'     => 'block',
            ],
            'claudebot' => [
                'label'       => __('Claude Training (ClaudeBot)', 'aiutoma'),
                'user_agent'  => 'ClaudeBot',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Anthropic crawler gathering datasets to train Claude models.', 'aiutoma'),
                'default'     => 'block',
            ],
            'google-extended' => [
                'label'       => __('Google Gemini Training (Google-Extended)', 'aiutoma'),
                'user_agent'  => 'Google-Extended',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Scrapes content for Gemini and Vertex AI models. Blocking this does NOT affect regular Google Search indexing.', 'aiutoma'),
                'default'     => 'block',
            ],
            'applebot-extended' => [
                'label'       => __('Apple Intelligence Training (Applebot-Extended)', 'aiutoma'),
                'user_agent'  => 'Applebot-Extended',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Collects content to train Apple Intelligence models. Distinct from Applebot for search queries.', 'aiutoma'),
                'default'     => 'block',
            ],
            'meta-externalagent' => [
                'label'       => __('Meta AI / LLaMA (meta-externalagent)', 'aiutoma'),
                'user_agent'  => 'meta-externalagent',
                'type'        => 'training',
                'impact'      => 'high',
                'description' => __('Meta crawler harvesting website data for LLaMA model training and Meta AI features.', 'aiutoma'),
                'default'     => 'block',
            ],
            'amazonbot' => [
                'label'       => __('Amazon AI (Amazonbot)', 'aiutoma'),
                'user_agent'  => 'Amazonbot',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Scrapes training data for Amazon Titan, Bedrock, and Alexa AI services.', 'aiutoma'),
                'default'     => 'block',
            ],
            'cohere-ai' => [
                'label'       => __('Cohere AI (cohere-ai)', 'aiutoma'),
                'user_agent'  => 'cohere-ai',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Scrapes web data to train Cohere enterprise models.', 'aiutoma'),
                'default'     => 'block',
            ],
            'diffbot' => [
                'label'       => __('Diffbot Knowledge Graph (Diffbot)', 'aiutoma'),
                'user_agent'  => 'Diffbot',
                'type'        => 'training',
                'impact'      => 'high',
                'description' => __('Automated scraper parsing websites into structured data graphs without generating search referrals.', 'aiutoma'),
                'default'     => 'block',
            ],
            'imagesiftbot' => [
                'label'       => __('ImageSift (ImagesiftBot)', 'aiutoma'),
                'user_agent'  => 'ImagesiftBot',
                'type'        => 'training',
                'impact'      => 'high',
                'description' => __('Specialized media and image scraper downloading high volumes of images for computer vision training.', 'aiutoma'),
                'default'     => 'block',
            ],
            'omgilibot' => [
                'label'       => __('Webz.io / Big Data (Omgilibot)', 'aiutoma'),
                'user_agent'  => 'Omgilibot',
                'type'        => 'training',
                'impact'      => 'medium',
                'description' => __('Commercial web scraper reselling website data feeds to third-party AI companies.', 'aiutoma'),
                'default'     => 'block',
            ],
            'turnitinbot' => [
                'label'       => __('Turnitin AI (TurnitinBot)', 'aiutoma'),
                'user_agent'  => 'TurnitinBot',
                'type'        => 'training',
                'impact'      => 'low',
                'description' => __('Scrapes website text for academic plagiarism and AI detection databases.', 'aiutoma'),
                'default'     => 'block',
            ],

            // ==========================================
            // Answer Engines & Live AI Search (GEO)
            // ==========================================
            'oai-searchbot' => [
                'label'       => __('ChatGPT Search (OAI-SearchBot)', 'aiutoma'),
                'user_agent'  => 'OAI-SearchBot',
                'type'        => 'answer',
                'impact'      => 'low',
                'description' => __('Used by OpenAI to search the live web and provide users with direct citations and click-through links to your website. Recommended: Allow.', 'aiutoma'),
                'default'     => 'allow',
            ],
            'claude-searchbot' => [
                'label'       => __('Claude Search (Claude-SearchBot)', 'aiutoma'),
                'user_agent'  => 'Claude-SearchBot',
                'type'        => 'answer',
                'impact'      => 'low',
                'description' => __('Used by Anthropic Claude to ground real-time search queries with links to sources. Recommended: Allow.', 'aiutoma'),
                'default'     => 'allow',
            ],
            'perplexitybot' => [
                'label'       => __('Perplexity AI (PerplexityBot)', 'aiutoma'),
                'user_agent'  => 'PerplexityBot',
                'type'        => 'answer',
                'impact'      => 'low',
                'description' => __('Real-time answer engine crawler that displays citations and source cards to users. Recommended: Allow.', 'aiutoma'),
                'default'     => 'allow',
            ],
            'amzn-searchbot' => [
                'label'       => __('Amazon Alexa Search (Amzn-SearchBot)', 'aiutoma'),
                'user_agent'  => 'Amzn-SearchBot',
                'type'        => 'answer',
                'impact'      => 'low',
                'description' => __('Fetches live web content to answer voice and device queries with citations.', 'aiutoma'),
                'default'     => 'allow',
            ],
            'youbot' => [
                'label'       => __('You.com Search (YouBot)', 'aiutoma'),
                'user_agent'  => 'YouBot',
                'type'        => 'answer',
                'impact'      => 'low',
                'description' => __('Crawler for You.com search and generative answer results.', 'aiutoma'),
                'default'     => 'allow',
            ],
        ];
    }

    /**
     * Check if a physical static robots.txt file exists in the web root.
     */
    public static function has_static_robots_txt(): bool {
        return file_exists( ABSPATH . 'robots.txt' );
    }

    /**
     * Get compiled crawler settings and state.
     */
    public static function get_crawlers_settings(): array {
        $catalog = self::get_crawlers_catalog();
        $saved_states = get_option('aiutoma_robots_crawlers', []);
        if (!is_array($saved_states)) {
            $saved_states = [];
        }

        $crawlers = [];
        foreach ($catalog as $slug => $info) {
            $state = $saved_states[$slug] ?? $info['default'];
            $crawlers[$slug] = [
                'slug'        => $slug,
                'label'       => $info['label'],
                'user_agent'  => $info['user_agent'],
                'type'        => $info['type'],
                'impact'      => $info['impact'],
                'description' => $info['description'],
                'default'     => $info['default'],
                'state'       => $state,
            ];
        }

        return [
            'ai_enabled'                 => get_option('aiutoma_robots_ai_enabled', '0') === '1',
            'crawlers'                   => $crawlers,
            'crawl_delay'                => absint(get_option('aiutoma_robots_crawl_delay', 0)),
            'block_sensitive_endpoints'  => get_option('aiutoma_robots_block_sensitive_endpoints', '1') === '1',
            'custom_directives'          => (string) get_option('aiutoma_robots_custom_directives', ''),
            'has_static_file'            => self::has_static_robots_txt(),
            'site_public'                => (int) get_option('blog_public', 1) === 1,
        ];
    }

    /**
     * Generate the AI crawler directives string for robots.txt.
     */
    public function generate_ai_robots_directives(): string {
        if (get_option('aiutoma_robots_ai_enabled', '0') !== '1') {
            return '';
        }

        $catalog = self::get_crawlers_catalog();
        $saved_states = get_option('aiutoma_robots_crawlers', []);
        if (!is_array($saved_states)) {
            $saved_states = [];
        }

        $crawl_delay = absint(get_option('aiutoma_robots_crawl_delay', 0));
        $block_endpoints = get_option('aiutoma_robots_block_sensitive_endpoints', '1') === '1';
        $custom = trim((string) get_option('aiutoma_robots_custom_directives', ''));

        $blocked = [];
        $allowed_with_delay = [];

        foreach ($catalog as $slug => $info) {
            $state = $saved_states[$slug] ?? $info['default'];
            if ($state === 'block') {
                $blocked[] = $info['user_agent'];
            } elseif ($state === 'allow' && $crawl_delay > 0) {
                $allowed_with_delay[] = $info['user_agent'];
            }
        }

        $lines = [];
        $lines[] = '';
        $lines[] = '# =============================================================';
        $lines[] = '# Aiutoma SEO - Advanced AI Crawlers & Server Protection';
        $lines[] = '# =============================================================';

        if (!empty($blocked)) {
            $lines[] = '# Blocked AI Training Bots & Resource-Intensive Scrapers:';
            foreach ($blocked as $ua) {
                $lines[] = 'User-agent: ' . $ua;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        if (!empty($allowed_with_delay)) {
            $lines[] = '# Rate-limited AI Answer & Search Engines:';
            foreach ($allowed_with_delay as $ua) {
                $lines[] = 'User-agent: ' . $ua;
                $lines[] = 'Crawl-delay: ' . $crawl_delay;
                $lines[] = '';
            }
        }

        if ($block_endpoints) {
            $lines[] = '# Protect heavy WordPress queries from aggressive automated scrapers:';
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /?s=';
            $lines[] = 'Disallow: /*?s=';
            $lines[] = 'Disallow: /wp-json/';
            $lines[] = '';
        }

        if (!empty($custom)) {
            $lines[] = '# Custom User Directives:';
            $lines[] = $custom;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Filter WordPress robots.txt output.
     */
    public function filter_robots_txt($output, $public) {
        $directives = $this->generate_ai_robots_directives();
        if (!empty($directives)) {
            $output .= $directives;
        }
        return $output;
    }

    /**
     * Generate simulated full robots.txt for live preview.
     */
    public function get_simulated_robots_txt(): string {
        $public = (int) get_option('blog_public', 1);
        $output = '';

        if ($public) {
            $output .= "User-agent: *\n";
            $site_path = (string) wp_parse_url(site_url(), PHP_URL_PATH);
            $output .= "Disallow: " . ($site_path ? $site_path . '/wp-admin/' : '/wp-admin/') . "\n";
            $output .= "Allow: " . ($site_path ? $site_path . '/wp-admin/admin-ajax.php' : '/wp-admin/admin-ajax.php') . "\n";
        } else {
            $output .= "User-agent: *\n";
            $output .= "Disallow: /\n";
        }

        // Apply filters including other plugins and our own
        $output = apply_filters('robots_txt', $output, $public);

        return trim($output) . "\n";
    }
}
