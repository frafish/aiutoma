<?php
namespace Aiutoma\Modules\Markdown\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait Settings {
    public function aiutoma_markdown_page_html() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['aiutoma_markdown_save'])) {
            check_admin_referer('aiutoma_markdown_nonce');
            $enabled = isset($_POST['aiutoma_markdown_enabled']) ? '1' : '0';
            $llmstxt_enabled = isset($_POST['aiutoma_markdown_llmstxt_enabled']) ? '1' : '0';
            $cpts = isset($_POST['aiutoma_markdown_cpts']) && is_array($_POST['aiutoma_markdown_cpts']) ? array_map('sanitize_text_field', wp_unslash($_POST['aiutoma_markdown_cpts'])) : [];
            update_option('aiutoma_markdown_enabled', $enabled);
            update_option('aiutoma_markdown_llmstxt_enabled', $llmstxt_enabled);
            update_option('aiutoma_markdown_cpts', $cpts);
            
            // Flush rewrite rules to ensure .md routes are updated
            flush_rewrite_rules();
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'aiutoma') . '</p></div>';
        }

        $enabled = get_option('aiutoma_markdown_enabled', '1');
        $llmstxt_enabled = get_option('aiutoma_markdown_llmstxt_enabled', '1');
        $selected_cpts = get_option('aiutoma_markdown_cpts', false);
        if ($selected_cpts === false) {
            $selected_cpts = array_values(array_diff(array_keys(get_post_types(['public' => true])), ['attachment']));
        }
        
        $post_types = get_post_types(['public' => true], 'objects');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Markdown Settings', 'aiutoma'); ?></h1>
            <p><?php esc_html_e('Configure how Aiutoma exposes your site content as Markdown for AI crawlers.', 'aiutoma'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('aiutoma_markdown_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Markdown', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aiutoma_markdown_enabled" value="1" <?php checked('1', $enabled); ?>>
                                <?php esc_html_e('Enable Markdown conversion and .md endpoints', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable llms.txt', 'aiutoma'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aiutoma_markdown_llmstxt_enabled" value="1" <?php checked('1', $llmstxt_enabled); ?>>
                                <?php esc_html_e('Enable the /llms.txt endpoint for AI crawlers', 'aiutoma'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled Post Types', 'aiutoma'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $pt): ?>
                                    <label style="display:block; margin-bottom: 5px;">
                                        <input type="checkbox" name="aiutoma_markdown_cpts[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_cpts)); ?>>
                                        <?php echo esc_html($pt->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Select which post types should be exposed as Markdown.', 'aiutoma'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="aiutoma_markdown_save" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'aiutoma'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
