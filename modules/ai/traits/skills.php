<?php
namespace Aiutoma\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Skills {

    public function register_skills_hooks() {
        if (did_action('init')) {
            $this->register_skills_cpt();
        } else {
            add_action('init', [$this, 'register_skills_cpt']);
        }

        add_action('rest_api_init', function() {
            register_rest_route('aiutoma/v1', '/skills', [
                'methods' => 'GET',
                'callback' => [$this, 'api_get_skills'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/skills', [
                'methods' => 'POST',
                'callback' => [$this, 'api_save_skill'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/skills', [
                'methods' => 'DELETE',
                'callback' => [$this, 'api_delete_skill'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
            register_rest_route('aiutoma/v1', '/skills/download/(?P<id>[a-zA-Z0-9_\.-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'api_download_skill'],
                'permission_callback' => function () { return current_user_can('manage_options'); }
            ]);
        });
    }

    public function extract_skill_metadata(string $content, string $fallback_name = ''): array {
        $name = '';
        $description = '';

        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---\s*(.*)$/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $body = $matches[2];

            if (preg_match('/^name:\s*(.+)$/m', $frontmatter, $m)) {
                $name = trim(trim($m[1]), '"\'');
            }
            if (preg_match('/^description:\s*(.+)$/m', $frontmatter, $m)) {
                $description = trim(trim($m[1]), '"\'');
            }

            if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
                $h1 = trim($m[1]);
                if (!empty($h1)) {
                    $name = $h1;
                }
            }
        } else {
            if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
                $name = trim($m[1]);
            }
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (!empty($trimmed) && strpos($trimmed, '#') !== 0 && strpos($trimmed, '---') !== 0) {
                    $description = wp_trim_words($trimmed, 30);
                    break;
                }
            }
        }

        if (empty($name)) {
            $clean_fallback = preg_replace('/^(builtin_)/', '', $fallback_name);
            $clean_fallback = preg_replace('/\.(md|txt)$/', '', $clean_fallback);
            $name = ucwords(str_replace(['-', '_'], ' ', $clean_fallback));
        }

        return [
            'name' => $name,
            'description' => $description,
        ];
    }

    public function get_builtin_skills(): array {
        $skills = [];
        $builtin_dir = dirname(dirname(__FILE__)) . '/skills';
        
        if (is_dir($builtin_dir)) {
            $subdirs = glob($builtin_dir . '/*', GLOB_ONLYDIR);
            if (!empty($subdirs)) {
                foreach ($subdirs as $subdir) {
                    $skill_name = basename($subdir);
                    $skill_file = $subdir . '/SKILL.md';
                    if (file_exists($skill_file)) {
                        $content = file_get_contents($skill_file);
                        
                        $references_dir = $subdir . '/references';
                        if (is_dir($references_dir)) {
                            $pattern = '/(?:(?:Read:\s*\n)?[ \t]*-[ \t]*(?:Read[ \t]*)?|\bRead[ \t]+)`references\/([^`]+\.md)`|`references\/([^`]+\.md)`/i';
                            $content = preg_replace_callback($pattern, function($matches) use ($references_dir) {
                                $file = !empty($matches[1]) ? $matches[1] : $matches[2];
                                $ref_file = $references_dir . '/' . $file;
                                if (file_exists($ref_file)) {
                                    $ref_content = trim(file_get_contents($ref_file));
                                    return "\n\n### " . $file . "\n" . $ref_content . "\n\n";
                                }
                                return $matches[0];
                            }, $content);
                        }
                        
                        $meta = $this->extract_skill_metadata($content, $skill_name);

                        $skills[] = [
                            'id' => 'builtin_' . $skill_name . '.md',
                            'slug' => $skill_name,
                            'name' => $meta['name'],
                            'description' => $meta['description'],
                            'is_builtin' => true,
                            'content' => $content
                        ];
                    }
                }
            }
        }
        
        return $skills;
    }

    public function register_skills_cpt() {
        if (!post_type_exists('aiutoma_skill')) {
            register_post_type('aiutoma_skill', [
                'label'               => __('AI Skills', 'aiutoma'),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'show_in_nav_menus'   => false,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => ['title', 'editor'],
                'can_export'          => true,
            ]);
        }
    }

    private function find_skill_post(string $title): ?\WP_Post {
        $posts = get_posts([
            'post_type'      => 'aiutoma_skill',
            'post_status'    => 'publish',
            'title'          => $title,
            'posts_per_page' => 1,
        ]);
        if (!empty($posts)) {
            return $posts[0];
        }

        $slug = sanitize_title(preg_replace('/\.(md|txt)$/i', '', $title));
        $posts = get_posts([
            'post_type'      => 'aiutoma_skill',
            'post_status'    => 'publish',
            'name'           => $slug,
            'posts_per_page' => 1,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    public function get_custom_skills(): array {

        $posts = get_posts([
            'post_type'      => 'aiutoma_skill',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $custom_skills = [];
        foreach ($posts as $post) {
            $filename = $post->post_title;
            if (empty($filename)) {
                $filename = $post->post_name . '.md';
            }
            if (strpos($filename, '.md') === false && strpos($filename, '.txt') === false) {
                $filename .= '.md';
            }
            $content = $post->post_content;
            $meta = $this->extract_skill_metadata($content, $filename);
            $custom_skills[] = [
                'id'          => $filename,
                'slug'        => preg_replace('/\.(md|txt)$/i', '', $filename),
                'name'        => $meta['name'],
                'description' => $meta['description'],
                'is_builtin'  => false,
                'content'     => $content,
            ];
        }

        return $custom_skills;
    }

    public function get_all_skills(): array {
        $skills = array_merge(
            $this->get_builtin_skills(),
            $this->get_custom_skills()
        );

        return apply_filters('aiutoma/skills', $skills);
    }

    public function get_all_skills_summary(): array {
        $skills = $this->get_all_skills();
        $summary = [];
        foreach ($skills as $skill) {
            $words = preg_split('/\s+/', trim($skill['content'] ?? ''));
            $tokens = ceil(count($words) / 0.75);
            $summary[] = [
                'id' => $skill['id'],
                'slug' => $skill['slug'] ?? preg_replace('/\.(md|txt)$/i', '', preg_replace('/^(builtin_)/', '', $skill['id'])),
                'name' => $skill['name'] ?? $skill['id'],
                'description' => $skill['description'] ?? '',
                'is_builtin' => !empty($skill['is_builtin']),
                'estimated_tokens' => (int) $tokens,
            ];
        }
        return $summary;
    }

    public function get_skill_by_id(string $id): ?array {
        $skills = $this->get_all_skills();
        $clean_id = strtolower(trim($id));
        $clean_id_normalized = preg_replace('/^(builtin_)/', '', $clean_id);
        $clean_id_normalized = preg_replace('/\.(md|txt)$/', '', $clean_id_normalized);
        $clean_id_normalized = str_replace('_', '-', $clean_id_normalized);

        foreach ($skills as $skill) {
            if ($skill['id'] === $id || (!empty($skill['slug']) && $skill['slug'] === $id)) {
                return $skill;
            }
            $skill_slug = strtolower($skill['slug'] ?? '');
            $skill_id_norm = preg_replace('/^(builtin_)/', '', strtolower($skill['id']));
            $skill_id_norm = preg_replace('/\.(md|txt)$/', '', $skill_id_norm);
            $skill_id_norm = str_replace('_', '-', $skill_id_norm);

            if ($skill_slug === $clean_id_normalized || $skill_id_norm === $clean_id_normalized) {
                return $skill;
            }
            if (!empty($skill['name']) && strtolower($skill['name']) === $clean_id) {
                return $skill;
            }
        }
        return null;
    }

    public function api_get_skills(\WP_REST_Request $request) {
        $skills = $this->get_all_skills();
        return new \WP_REST_Response(['success' => true, 'skills' => $skills], 200);
    }

    public function api_download_skill(\WP_REST_Request $request) {
        $id = sanitize_file_name($request->get_param('id') ?? '');
        if (empty($id)) {
            return new \WP_REST_Response(['message' => __('ID is required.', 'aiutoma')], 400);
        }
        $skill = $this->get_skill_by_id($id);
        if (!$skill) {
            return new \WP_REST_Response(['message' => __('Skill not found.', 'aiutoma')], 404);
        }
        $filename = $skill['id'];
        if (strpos($filename, '.md') === false && strpos($filename, '.txt') === false) {
            $filename .= '.md';
        }
        return new \WP_REST_Response([
            'success'  => true,
            'id'       => $id,
            'filename' => $filename,
            'content'  => $skill['content'],
        ], 200);
    }

    public function api_save_skill(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: [];
        $id = sanitize_file_name($request->get_param('id') ?? ($params['id'] ?? ''));
        $content = $request->get_param('content') ?? ($params['content'] ?? '');
        $old_id = sanitize_file_name($request->get_param('old_id') ?? ($params['old_id'] ?? ''));

        if (empty($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('ID is required.', 'aiutoma')], 400);
        }

        if (strpos($id, 'builtin_') === 0 || strpos($old_id, 'builtin_') === 0) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Built-in skills cannot be modified.', 'aiutoma')], 403);
        }

        if (strpos($id, '.md') === false && strpos($id, '.txt') === false) {
            $id .= '.md';
        }

        $target_lookup = !empty($old_id) ? $old_id : $id;
        $existing = $this->find_skill_post($target_lookup);

        if ($existing) {
            wp_update_post([
                'ID'           => $existing->ID,
                'post_title'   => $id,
                'post_name'    => sanitize_title($id),
                'post_content' => $content,
            ]);
        } else {
            wp_insert_post([
                'post_type'    => 'aiutoma_skill',
                'post_title'   => $id,
                'post_name'    => sanitize_title($id),
                'post_content' => $content,
                'post_status'  => 'publish',
            ]);
        }

        return new \WP_REST_Response(['success' => true, 'message' => __('Skill saved.', 'aiutoma')], 200);
    }

    public function api_delete_skill(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: [];
        $id = sanitize_file_name($request->get_param('id') ?? ($params['id'] ?? ''));

        if (empty($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('ID is required.', 'aiutoma')], 400);
        }
        
        if (strpos($id, 'builtin_') === 0) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Built-in skills cannot be deleted.', 'aiutoma')], 403);
        }

        $post = $this->find_skill_post($id);
        if ($post) {
            wp_delete_post($post->ID, true);
            return new \WP_REST_Response(['success' => true, 'message' => __('Skill deleted.', 'aiutoma')], 200);
        }

        return new \WP_REST_Response(['success' => false, 'message' => __('Skill not found.', 'aiutoma')], 404);
    }

    public function aiutoma_skills_page_html() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap aiutoma-wrap">
            <h1><span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Skills', 'aiutoma'); ?></h1>
            <p class="description"><?php esc_html_e('Manage custom skills and guidelines for your AI Agents. These skills will be injected into the system prompt of the Playground, Editor Agent, and Block Agent.', 'aiutoma'); ?></p>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Sidebar: List of skills -->
                <div style="width: 300px; background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px;">
                    <h3><?php esc_html_e('Your Skills', 'aiutoma'); ?></h3>
                    <ul id="aiutoma-skills-list" style="margin: 0; padding: 0; list-style: none;">
                        <!-- Populated by JS -->
                    </ul>
                    <button type="button" class="button button-primary" id="aiutoma-add-skill-btn" style="margin-top: 15px; width: 100%;">+ <?php esc_html_e('Create New Skill', 'aiutoma'); ?></button>
                </div>

                <!-- Main area: Editor -->
                <div style="flex-grow: 1; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; display: none;" id="aiutoma-skill-editor">
                    <input type="hidden" id="aiutoma-skill-old-id" value="">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="aiutoma-skill-id" style="font-weight: bold; display: block; margin-bottom: 5px;"><?php esc_html_e('Skill Name (filename)', 'aiutoma'); ?></label>
                        <input type="text" id="aiutoma-skill-id" class="regular-text" placeholder="e.g. how_to_create_blocks.md" style="width: 100%;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="aiutoma-skill-content" style="font-weight: bold; display: block; margin-bottom: 5px;"><?php esc_html_e('Instructions / Content', 'aiutoma'); ?></label>
                        <textarea id="aiutoma-skill-content" rows="15" style="width: 100%; font-family: monospace;"></textarea>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <button type="button" class="button button-primary" id="aiutoma-save-skill-btn"><?php esc_html_e('Save Skill', 'aiutoma'); ?></button>
                        <button type="button" class="button button-link-delete" id="aiutoma-delete-skill-btn" style="color: #a00;"><?php esc_html_e('Delete Skill', 'aiutoma'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    public function get_ai_skills($enabled_skills = null) {
        if (is_array($enabled_skills) && empty($enabled_skills)) {
            return "";
        }

        $skills_text = "";
        $all_skills = $this->get_all_skills();

        if (!empty($all_skills)) {
            $has_skills = false;
            foreach ($all_skills as $skill) {
                if (is_array($enabled_skills)) {
                    $slug = $skill['slug'] ?? '';
                    if (!in_array($skill['id'], $enabled_skills, true) && !in_array($slug, $enabled_skills, true)) {
                        continue;
                    }
                }
                if (!$has_skills) {
                    $skills_text .= "\n\nCUSTOM SKILLS & INSTRUCTIONS:\n";
                    $has_skills = true;
                }
                $skills_text .= "--- Skill: " . ($skill['name'] ?: $skill['id']) . " ---\n";
                $skills_text .= $skill['content'] . "\n\n";
            }
        }
        return $skills_text;
    }
}
