<?php
namespace Aiutoma\Modules\Seo\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Hooks {
    public function add_seo_media_button($form_fields, $post) {
        if (strpos($post->post_mime_type, 'image/') === 0) {
            $html = '<div class="aiutoma-seo-media-btn-wrap">
                <button type="button" class="button button-primary aiutoma-seo-optimize-btn" data-id="' . esc_attr($post->ID) . '">' . esc_html__('Generate SEO Meta (AI)', 'aiutoma') . '</button>
                <span class="spinner aiutoma-seo-spinner"></span>
                <p class="description">' . esc_html__('Auto-generate Title, Alt Text, Description, and Caption using AI Vision.', 'aiutoma') . '</p>
            </div>';
            
            $form_fields['aiutoma_seo_btn'] = [
                'label' => __('Aiutoma SEO', 'aiutoma'),
                'input' => 'html',
                'html'  => $html,
            ];
        }
        return $form_fields;
    }

    public function on_add_attachment($post_id) {
        $auto_optimize = get_option('aiutoma_auto_optimize_media', false);
        if ($auto_optimize && wp_attachment_is_image($post_id)) {
            // Schedule an immediate event to process this image
            wp_schedule_single_event(time() + 10, 'aiutoma_cron_optimize_media', [$post_id]);
        }
    }

    public function cron_optimize_media($attachment_id = null) {
        if ($attachment_id) {
            $this->process_image_seo($attachment_id, get_option('aiutoma_seo_preferred_model', ''));
        } else {
            // Bulk cron (e.g. process 5 oldest unoptimized images)
            $args = [
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => 5,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query' => [
                    [
                        'key' => '_aiutoma_seo_optimized',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];
            $query = new \WP_Query($args);
            foreach ($query->posts as $post) {
                $this->process_image_seo($post->ID, get_option('aiutoma_seo_preferred_model', ''));
            }
        }
    }

    function custom_gutenberg_image_alt($block_content, $block) {
        if (isset($block['blockName']) && $block['blockName'] === 'core/image') {
            $attributes = $block['attrs'];
            if (!empty($attributes['id']) && empty($attributes['alt'])) {
                $image_id = $attributes['id'];
                $final_alt = $post_title = '';

                $media_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

                if (!empty($media_alt)) {
                    $final_alt = $media_alt;
                } else {
                    $current_post_id = get_the_ID();
                    $is_first_image = $is_featured = false;
                    if ($current_post_id) {
                        $post_title = get_the_title($current_post_id);
                        $is_featured = (int) get_post_thumbnail_id($current_post_id) === (int) $image_id;
                    
                        $all_blocks = parse_blocks(get_the_content());
                        foreach ($all_blocks as $current_block) {
                            if ($current_block['blockName'] === 'core/image' && isset($current_block['attrs']['id'])) {
                                if ((int) $current_block['attrs']['id'] === (int) $image_id) {
                                    $is_first_image = true;
                                }
                                break;
                            }
                        }
                    }

                    if ($is_featured || $is_first_image) {
                        $final_alt = $post_title;
                    } else {
                        $attachment = get_post($image_id);
                        if ($attachment) {
                            $file_name = basename($attachment->guid);
                            $file_name_without_ext = pathinfo($file_name, PATHINFO_FILENAME);
                            $generated_alt = ucwords(str_replace(['-', '_'], ' ', $file_name_without_ext));
                            $final_alt = $generated_alt;
                        }
                    }
                }

                if (!empty($final_alt)) {
                    $block_content = str_replace(' alt="" ', ' alt="' . esc_attr($final_alt) . '" ', $block_content);
                }
            }
        }
        return $block_content;
    }
}
