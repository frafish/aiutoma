<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

namespace Aiutoma\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait RAG_Processors {
    
    private function cleanup_deleted_objects() {
        global $wpdb;
        
        $stmt = $this->db->query("SELECT DISTINCT post_id, post_type FROM document_embeddings");
        $embedded_objects = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($embedded_objects)) {
            return;
        }

        $deleted_objects = [];
        $categorized = [];
        
        foreach ($embedded_objects as $obj) {
            $categorized[$obj['post_type']][] = $obj['post_id'];
        }

        foreach ($categorized as $type => $ids) {
            $chunked_ids = array_chunk($ids, 100);
            
            if (in_array($type, ['term_category', 'term_post_tag', 'term_product_cat'])) {
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $query = $wpdb->prepare("SELECT term_id FROM {$wpdb->terms} WHERE term_id IN ({$placeholders})", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            } elseif ($type === 'comment') {
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $query = $wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID IN ({$placeholders}) AND comment_approved = '1'", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            } elseif ($type === 'global_setting' || strpos($type, 'plugin_api_') === 0) {
                // Settings and plugins don't get deleted here, they update or get cleaned up during processing
            } elseif ($type === 'attachment') {
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) AND post_type = 'attachment'", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            } else {
                // Regular Post Types (post, page, product, block)
                foreach ($chunked_ids as $id_chunk) {
                    $placeholders = implode(',', array_fill(0, count($id_chunk), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) AND post_status = 'publish'", ...$id_chunk);
                    $valid_ids = $wpdb->get_col($query);
                    $missing = array_diff($id_chunk, $valid_ids);
                    foreach ($missing as $m_id) $deleted_objects[] = ['id' => $m_id, 'type' => $type];
                }
            }
        }

        if (!empty($deleted_objects)) {
            $this->log("Cleaning up " . count($deleted_objects) . " deleted or unpublished objects from vector DB.");
            foreach ($deleted_objects as $del) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = ? AND post_type = ?");
                $del_stmt->execute([$del['id'], $del['type']]);
            }
        }
    }

    private function process_posts() {
        global $wpdb;
        
        $types_to_sync = [];
        if (get_option('aiutoma_rag_sync_contents', 1) == 1) {
            $types_to_sync = array_merge($types_to_sync, ['post', 'page', 'block', 'knowledgebase']);
        }
        if (get_option('aiutoma_rag_sync_products', 1) == 1) {
            $types_to_sync[] = 'product';
        }
        
        if (empty($types_to_sync)) return;
        
        $types_placeholders = implode(', ', array_fill(0, count($types_to_sync), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT ID, post_modified_gmt 
            FROM {$wpdb->posts} 
            WHERE post_type IN ({$types_placeholders}) 
            AND post_status = 'publish' 
            ORDER BY post_modified_gmt DESC",
            $types_to_sync
        );
        
        $posts = $wpdb->get_results($query);
        $processed_count = 0;
        $updated_count = 0;

        foreach ($posts as $post) {
            if ($processed_count >= $this->batch_size) break;

            $post_id = $post->ID;
            $wp_post = get_post($post_id);
            
            $content = $this->extract_post_content($post_id);
            
            if (empty($content)) continue;
            
            $content_hash = md5($content . $wp_post->post_title);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type LIMIT 1");
            $stmt->execute([':post_id' => $post_id, ':post_type' => $wp_post->post_type]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) continue;

            $this->log("Updating Post ID: {$post_id} - {$wp_post->post_title} ({$wp_post->post_type})");

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type");
                $del_stmt->execute([':post_id' => $post_id, ':post_type' => $wp_post->post_type]);
            }

            echo "Length: " . esc_html((string)strlen($content)) . "\n"; $this->insert_chunks($post_id, $wp_post->post_type, $wp_post->post_title, get_permalink($post_id), $content, $content_hash);
            
            $processed_count++;
            $updated_count++;
            clean_post_cache($wp_post);
        }
        $this->log("Processed {$updated_count} advanced posts/products this run.");
    }
    
    private function process_terms() {
        $taxonomies = get_taxonomies(['public' => true]);
        $terms = get_terms([
            'taxonomy' => $taxonomies,
            'hide_empty' => true,
        ]);
        
        $updated_count = 0;
        foreach ($terms as $term) {
            $content = "Taxonomy: {$term->taxonomy}\nName: {$term->name}\nDescription: " . wp_strip_all_tags($term->description);
            $content_hash = md5($content);
            $type = 'term_' . $term->taxonomy;
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type LIMIT 1");
            $stmt->execute([':post_id' => $term->term_id, ':post_type' => $type]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) continue;

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = :post_type");
                $del_stmt->execute([':post_id' => $term->term_id, ':post_type' => $type]);
            }

            $this->log("Updating Term ID: {$term->term_id} - {$term->name} ({$term->taxonomy})");
            echo "Length: " . esc_html((string)strlen($content)) . "\n"; $this->insert_chunks($term->term_id, $type, 'Taxonomy: ' . $term->name, get_term_link($term), $content, $content_hash);
            $updated_count++;
        }
        $this->log("Processed {$updated_count} taxonomies this run.");
    }
    
    private function process_comments() {
        global $wpdb;
        
        $query = "
            SELECT comment_ID, comment_post_ID, comment_author, comment_date, comment_content 
            FROM {$wpdb->comments} 
            WHERE comment_approved = '1'
            ORDER BY comment_date_gmt DESC
        ";
        
        $comments = $wpdb->get_results($query);
        $processed_count = 0;
        $updated_count = 0;

        foreach ($comments as $comment) {
            if ($processed_count >= $this->batch_size) break;

            $comment_id = $comment->comment_ID;
            $post_title = get_the_title($comment->comment_post_ID);
            $content = "Comment by {$comment->comment_author} on {$post_title}:\n" . wp_strip_all_tags($comment->comment_content);
            $content_hash = md5($content);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = 'comment' LIMIT 1");
            $stmt->execute([':post_id' => $comment_id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) continue;

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = 'comment'");
                $del_stmt->execute([':post_id' => $comment_id]);
            }

            $this->log("Updating Comment ID: {$comment_id}");
            $this->insert_chunks($comment_id, 'comment', "Comment by {$comment->comment_author}", get_comment_link($comment), $content, $content_hash);
            
            $processed_count++;
            $updated_count++;
        }
        $this->log("Processed {$updated_count} comments this run.");
    }
    
    private function process_settings() {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $active_plugins = get_option('active_plugins', []);
        
        $content = "Site Name: {$site_name}\nSite Description: {$site_description}\nActive Plugins: " . implode(', ', $active_plugins);
        $content_hash = md5($content);
        
        $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_type = 'global_setting' LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existing && $existing['content_hash'] === $content_hash) return;
        
        if ($existing) {
            $this->db->query("DELETE FROM document_embeddings WHERE post_type = 'global_setting'");
        }
        
        $this->log("Updating Global Site Settings Context");
        echo "Length: " . esc_html((string)strlen($content)) . "\n"; $this->insert_chunks(1, 'global_setting', 'Global Site Configuration', home_url(), $content, $content_hash);
    }
    
    private function process_plugins_apis() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        // Clean up deactivated plugins
        $stmt = $this->db->query("SELECT DISTINCT post_type FROM document_embeddings WHERE post_type LIKE 'plugin_api_%'");
        if ($stmt) {
            $existing_types = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $active_plugin_dirs = [];
            foreach ($active_plugins as $p) {
                $active_plugin_dirs[] = explode('/', $p)[0];
            }
            foreach ($existing_types as $type) {
                $dir = str_replace('plugin_api_', '', $type);
                if (!in_array($dir, $active_plugin_dirs)) {
                    $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_type = ?");
                    $del_stmt->execute([$type]);
                }
            }
        }

        $processed_count = 0;

        foreach ($active_plugins as $plugin_path) {
            $plugin_dir = explode('/', $plugin_path)[0];
            $plugin_name = isset($all_plugins[$plugin_path]['Name']) ? $all_plugins[$plugin_path]['Name'] : $plugin_dir;
            
            $post_type = 'plugin_api_' . $plugin_dir;
            $plugin_full_dir = trailingslashit( dirname( AIUTOMA_PATH ) ) . $plugin_dir;
            
            if (!is_dir($plugin_full_dir)) continue;

            $apis = [
                'hooks' => [],
                'classes' => [],
                'functions' => []
            ];
            
            try {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugin_full_dir, \RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        if (!$content) continue;
                        
                        // Match hooks
                        if (preg_match_all('/(?:do_action|apply_filters)\s*\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]/', $content, $matches)) {
                            foreach ($matches[1] as $hook_name) {
                                $apis['hooks'][$hook_name] = "Hook: {$hook_name}";
                            }
                        }
                        
                        // Match classes
                        if (preg_match_all('/(?:^|\s)class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
                            foreach ($matches[1] as $class_name) {
                                $apis['classes'][$class_name] = "class {$class_name}";
                            }
                        }
                        
                        // Match functions
                        if (preg_match_all('/(?:^|\s)function\s+([a-zA-Z0-9_]+)\s*\(([^)]*)\)/', $content, $matches)) {
                            foreach ($matches[1] as $idx => $func_name) {
                                $apis['functions'][$func_name] = "function {$func_name}(" . trim($matches[2][$idx]) . ")";
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore directory iteration errors
            }
            
            $flat_apis = [];
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['hooks']), 0, 800));
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['classes']), 0, 400));
            $flat_apis = array_merge($flat_apis, array_slice(array_values($apis['functions']), 0, 1000));
            
            if (empty($flat_apis)) continue;
            
            $content = "Plugin API Reference for: {$plugin_name}\n\n" . implode("\n", $flat_apis);
            $content_hash = md5($content);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_type = :post_type LIMIT 1");
            $stmt->execute([':post_type' => $post_type]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) {
                continue;
            }
            
            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_type = ?");
                $del_stmt->execute([$post_type]);
            }
            
            $this->log("Updating Plugin API Context: {$plugin_name}");
            $this->insert_chunks(1, $post_type, "Plugin API: {$plugin_name}", home_url(), $content, $content_hash);
            $processed_count++;
        }
        
        $this->log("Processed {$processed_count} plugin APIs this run.");
    }

    private function export_to_json() {
        $this->log("Exporting vectors to JSON file...");
        $stmt = $this->db->query("SELECT post_id, chunk_index, post_type, post_title, post_url, text_content, embedding FROM document_embeddings");
        
        $data = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['embedding'] = json_decode($row['embedding'], true);
            
            // Dynamic WooCommerce Enrichment
            if ($row['post_type'] === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($row['post_id']);
                if ($product) {
                    $row['text_content'] .= "\n[Live Data] Product Price: " . $product->get_price();
                    $row['text_content'] .= "\n[Live Data] Product SKU: " . $product->get_sku();
                    $row['text_content'] .= "\n[Live Data] Stock Status: " . $product->get_stock_status();
                }
            }
            
            $data[] = $row;
        }

        $data = apply_filters('aiutoma/rag_data', $data);

        $json_path = $this->db_dir . '/rag.json';
        $json_content = wp_json_encode($data);
        
        if (file_put_contents($json_path, $json_content) !== false) {
            $this->log("Successfully exported " . count($data) . " vectors to {$json_path}");
        } else {
            $this->log("Failed to export vectors to {$json_path}");
        }
    }

    private function process_media() {
        global $wpdb;

        $supported_mimes = [
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ];
        $mime_placeholders = implode(', ', array_fill(0, count($supported_mimes), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT ID, post_modified_gmt 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ({$mime_placeholders}) 
            ORDER BY post_modified_gmt DESC",
            $supported_mimes
        );
        
        $posts = $wpdb->get_results($query);
        $processed_count = 0;
        $updated_count = 0;

        foreach ($posts as $post) {
            if ($processed_count >= $this->batch_size) break;

            $post_id = $post->ID;
            $wp_post = get_post($post_id);
            $file_path = get_attached_file($post_id);
            
            if (!$file_path || !file_exists($file_path)) continue;

            $body = $this->parse_document($file_path, $post->post_mime_type);
            
            if (empty(trim($body)) || strpos($body, 'Error') === 0) {
                if (strpos($body, 'Error') === 0) {
                    $this->log("Document extraction failed for ID {$post_id}: " . $body);
                }
                $content = "MEDIA_EXTRACTION_FAILED";
            } else {
                $content = "[Type: Document/Media] [Title: {$wp_post->post_title}] [URL: " . wp_get_attachment_url($post_id) . "]\n\n" . trim($body);
            }
            
            $content_hash = md5($content);
            
            $stmt = $this->db->prepare("SELECT content_hash FROM document_embeddings WHERE post_id = :post_id AND post_type = 'attachment' LIMIT 1");
            $stmt->execute([':post_id' => $post_id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing && $existing['content_hash'] === $content_hash) {
                continue;
            }

            $this->log("Updating Media ID: {$post_id} - {$wp_post->post_title}");

            if ($existing) {
                $del_stmt = $this->db->prepare("DELETE FROM document_embeddings WHERE post_id = :post_id AND post_type = 'attachment'");
                $del_stmt->execute([':post_id' => $post_id]);
            }

            if ($content !== "MEDIA_EXTRACTION_FAILED") {
                echo "Length: " . esc_html((string)strlen($content)) . "\n"; 
                $this->insert_chunks($post_id, 'attachment', $wp_post->post_title, wp_get_attachment_url($post_id), $content, $content_hash);
            } else {
                // To avoid retrying failed extraction over and over, insert a dummy record
                $dummy_stmt = $this->db->prepare("INSERT INTO document_embeddings (post_id, chunk_index, post_type, post_title, post_url, content_hash, text_content, embedding) VALUES (:pid, 0, 'attachment', 'Failed', '', :hash, '', '[]')");
                $dummy_stmt->execute([':pid' => $post_id, ':hash' => $content_hash]);
            }
            
            $processed_count++;
            $updated_count++;
        }
        
        $this->log("Processed {$updated_count} media files this run.");
    }
}
