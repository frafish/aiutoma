<?php
namespace Aiutoma\Modules\Chatbot\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.Security.ValidatedSanitizedInput
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.DB.SlowDBQuery



trait Logs {
    public function handle_chatbot_logs_actions() {
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'aiutoma-chatbot-logs') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';

        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['session_ids']) && is_array($_POST['session_ids'])) {
            check_admin_referer('aiutoma_bulk_delete_logs');
            foreach (wp_unslash($_POST['session_ids']) as $sid) {
                $sid = sanitize_text_field($sid);
                $comment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT comment_id 
                    FROM {$wpdb->commentmeta} 
                    WHERE meta_key = 'aiutoma_session_id' AND meta_value = %s
                ", $sid));
                
                foreach ($comment_ids as $c_id) {
                    wp_delete_comment(intval($c_id), true);
                }
                delete_transient('aiutoma_chatbot_' . $sid);
                delete_transient('aiutoma_chatbot_' . $sid . '_email');
                delete_transient('aiutoma_chatbot_manual_' . $sid);
            }
            wp_safe_redirect(admin_url('admin.php?page=aiutoma-chatbot-logs'));
            exit;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        if (in_array($action, ['delete_message', 'delete_session'], true)) {
            check_admin_referer('aiutoma_delete_log');
            if ($action === 'delete_message' && !empty($_GET['comment_id'])) {
                wp_delete_comment(intval($_GET['comment_id']), true);
                wp_safe_redirect(admin_url('admin.php?page=aiutoma-chatbot-logs&action=view&session_id=' . urlencode($session_id)));
                exit;
            } elseif ($action === 'delete_session' && !empty($session_id)) {
                // Fetch comment IDs directly via SQL to bypass WPML filtering which hides comments with post_ID = 0
                $comment_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT comment_id 
                    FROM {$wpdb->commentmeta} 
                    WHERE meta_key = 'aiutoma_session_id' AND meta_value = %s
                ", $session_id));
                
                foreach ($comment_ids as $c_id) {
                    wp_delete_comment(intval($c_id), true);
                }
                delete_transient('aiutoma_chatbot_' . $session_id);
                delete_transient('aiutoma_chatbot_' . $session_id . '_email');
                delete_transient('aiutoma_chatbot_manual_' . $session_id);
                wp_safe_redirect(admin_url('admin.php?page=aiutoma-chatbot-logs'));
                exit;
            }
        }
    }

    public function aiutoma_chatbot_logs_page_html() {
        global $wpdb;

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';

        echo '<div class="wrap">';
        if ($action === 'view' && !empty($session_id)) {
            // View Single Thread
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chat Session', 'aiutoma') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=aiutoma-chatbot-logs')) . '" class="page-title-action">' . esc_html__('Back to Logs', 'aiutoma') . '</a>';
            echo '<hr class="wp-header-end">';
            
            $comments = get_comments([
                'type' => 'aiutoma_chat',
                'meta_key' => 'aiutoma_session_id',
                'meta_value' => $session_id,
                'orderby' => 'comment_date_gmt',
                'order' => 'ASC',
                'status' => 'all'
            ]);

            if (empty($comments)) {
                echo '<p>' . esc_html__('No messages found for this session.', 'aiutoma') . '</p>';
            } else {
                echo '<div class="aiutoma-chatbot-log-container">';
                $email = '';
                $session_user_id = 0;
                $user_ip = '';
                $user_agent = '';
                $session_total_tokens = 0;
                foreach ($comments as $c) {
                    $tokens = (int) get_comment_meta($c->comment_ID, 'aiutoma_tokens_total', true);
                    $session_total_tokens += $tokens;
                    
                    if ($c->comment_author === 'Aiutoma' || strpos($c->comment_author, 'Operator') !== false) {
                        continue;
                    }
                    if (!empty($c->comment_author_email) && empty($email)) {
                        $email = $c->comment_author_email;
                    }
                    // Try extracting from text if email is not yet set natively
                    if (empty($email) && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $c->comment_content, $m)) {
                        $email = $m[0];
                    }
                    if (!empty($c->user_id) && $c->user_id > 0 && empty($session_user_id)) {
                        $session_user_id = $c->user_id;
                    }
                    if (!empty($c->comment_author_IP) && empty($user_ip)) {
                        $user_ip = $c->comment_author_IP;
                    }
                    if (!empty($c->comment_agent) && empty($user_agent)) {
                        $user_agent = $c->comment_agent;
                    }
                }
                
                /* translators: %s: Session ID */
                echo '<h3>' . sprintf(esc_html__('Session ID: %s', 'aiutoma'), esc_html($session_id)) . '</h3>';
                
                $del_session_url = wp_nonce_url(admin_url('admin.php?page=aiutoma-chatbot-logs&action=delete_session&session_id=' . urlencode($session_id)), 'aiutoma_delete_log');
                echo '<p>';
                echo '<button id="aiutoma_summarize_session_btn" class="button button-primary" data-session="' . esc_attr($session_id) . '" style="margin-right: 10px;">' . esc_html__('Summarize Session', 'aiutoma') . '</button>';
                echo '<a href="' . esc_url($del_session_url) . '" class="button button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'aiutoma')) . '\');" style="color: #b32d2e;">' . esc_html__('Delete Session', 'aiutoma') . '</a>';
                echo '</p>';
                echo '<div id="aiutoma_session_summary_result" class="aiutoma-chatbot-summary-result"></div>';
                
                $total_sessions = 1;
                $last_seen = '';
                
                if (!empty($email)) {
                    $other_sessions_query = $wpdb->prepare("
                        SELECT m.meta_value AS session_id, MAX(c.comment_date) as last_activity
                        FROM {$wpdb->comments} c
                        INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                        WHERE c.comment_type = 'aiutoma_chat' AND m.meta_key = 'aiutoma_session_id' AND c.comment_author_email = %s AND c.comment_author != 'Aiutoma'
                        GROUP BY m.meta_value
                        ORDER BY last_activity DESC
                    ", $email);
                } else if (!empty($user_ip)) {
                    $other_sessions_query = $wpdb->prepare("
                        SELECT m.meta_value AS session_id, MAX(c.comment_date) as last_activity
                        FROM {$wpdb->comments} c
                        INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
                        WHERE c.comment_type = 'aiutoma_chat' AND m.meta_key = 'aiutoma_session_id' AND c.comment_author_IP = %s AND c.comment_author != 'Aiutoma'
                        GROUP BY m.meta_value
                        ORDER BY last_activity DESC
                    ", $user_ip);
                }
                
                if (isset($other_sessions_query)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
                    $user_sessions = $wpdb->get_results($other_sessions_query);
                    if (!empty($user_sessions)) {
                        $total_sessions = count($user_sessions);
                        $last_seen = $user_sessions[0]->last_activity;
                    }
                }
                
                echo '<div class="aiutoma-chatbot-user-panel">';
                
                // Column 1: Info
                echo '<div class="aiutoma-chatbot-user-panel-col">';
                echo '<h4>' . esc_html__('User Info', 'aiutoma') . '</h4>';
                if ($session_user_id > 0) {
                    $user_link = get_edit_user_link($session_user_id);
                    $user_obj = get_userdata($session_user_id);
                    $user_name = $user_obj ? $user_obj->display_name : __('User', 'aiutoma');
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Name:', 'aiutoma') . '</strong> <a href="' . esc_url($user_link) . '">' . esc_html($user_name) . '</a></p>';
                } else {
                    $name_to_show = '';
                    foreach ($comments as $c) {
                        if ($c->comment_author !== 'Aiutoma' && $c->comment_author !== 'Visitor' && strpos($c->comment_author, 'Operator') === false) {
                            $name_to_show = $c->comment_author; break;
                        } else if (preg_match('/^My name is (.*?) and my email is/', $c->comment_content, $nm)) {
                            $name_to_show = trim($nm[1]); break;
                        }
                    }
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Name:', 'aiutoma') . '</strong> ' . esc_html($name_to_show ?: 'Visitor') . '</p>';
                }
                if ($email) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Email:', 'aiutoma') . '</strong> <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
                }
                if ($user_ip) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('IP:', 'aiutoma') . '</strong> ' . esc_html($user_ip) . '</p>';
                }
                if ($user_agent) {
                    echo '<p style="margin: 0; font-size: 12px; color: #666;" title="' . esc_attr($user_agent) . '"><strong>' . esc_html__('Browser:', 'aiutoma') . '</strong> ' . esc_html(substr($user_agent, 0, 50)) . '...</p>';
                }
                echo '</div>';

                // Column 2: Stats
                echo '<div class="aiutoma-chatbot-user-panel-col stats">';
                echo '<h4>' . esc_html__('Chat Stats', 'aiutoma') . '</h4>';
                echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Total Sessions:', 'aiutoma') . '</strong> ' . esc_html($total_sessions) . '</p>';
                if ($last_seen) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Last Seen:', 'aiutoma') . '</strong> ' . esc_html($last_seen) . '</p>';
                }
                
                if ($session_total_tokens > 0) {
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Session Tokens:', 'aiutoma') . '</strong> ' . esc_html(number_format_i18n($session_total_tokens)) . '</p>';
                }
                
                $filter_url = admin_url('admin.php?page=aiutoma-chatbot-logs');
                if ($email) {
                    $filter_url = add_query_arg('filter_email', urlencode($email), $filter_url);
                } else if ($user_ip) {
                    $filter_url = add_query_arg('filter_ip', urlencode($user_ip), $filter_url);
                }
                echo '<p style="margin-top: 10px;"><a href="' . esc_url($filter_url) . '" class="button button-secondary button-small">' . esc_html__('View All User Sessions', 'aiutoma') . '</a></p>';
                echo '</div>';
                
                // Column 3: WooCommerce
                if (class_exists('WooCommerce') && $email) {
                    echo '<div class="aiutoma-chatbot-user-panel-col stats">';
                    echo '<h4>' . esc_html__('WooCommerce', 'aiutoma') . '</h4>';
                    $customer_orders = wc_get_orders([
                        'email' => $email,
                        'limit' => -1,
                        'return' => 'ids'
                    ]);
                    $order_count = count($customer_orders);
                    echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Orders:', 'aiutoma') . '</strong> ' . esc_html($order_count) . '</p>';
                    
                    if ($session_user_id > 0) {
                        $cart_meta = get_user_meta($session_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
                        if (!empty($cart_meta['cart'])) {
                            $cart_count = array_sum(wp_list_pluck($cart_meta['cart'], 'quantity'));
                            echo '<p style="margin: 5px 0 2px 0;"><strong>' . esc_html__('Items in Cart:', 'aiutoma') . '</strong> ' . esc_html($cart_count) . '</p>';
                            echo '<ul style="margin: 0 0 10px 15px; font-size: 12px; padding: 0;">';
                            foreach ($cart_meta['cart'] as $item) {
                                $product = wc_get_product($item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id']);
                                if ($product) {
                                    echo '<li style="margin-bottom: 2px;">' . esc_html($item['quantity']) . 'x <a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></li>';
                                }
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="margin: 5px 0 5px 0;"><strong>' . esc_html__('Cart:', 'aiutoma') . '</strong> <em>' . esc_html__('Empty', 'aiutoma') . '</em></p>';
                        }
                    } else {
                        echo '<p style="margin: 5px 0 5px 0; font-size: 11px; color: #666; font-style: italic;">' . esc_html__('Cart items only visible for logged-in users.', 'aiutoma') . '</p>';
                    }
                    
                    if ($order_count > 0) {
                        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                            $woo_link = admin_url('admin.php?page=wc-orders&_billing_email=' . urlencode($email));
                        } else {
                            $woo_link = admin_url('edit.php?post_type=shop_order&s=' . urlencode($email));
                        }
                        echo '<p style="margin-top: 10px;"><a href="' . esc_url($woo_link) . '" class="button button-secondary button-small">' . esc_html__('View Orders', 'aiutoma') . '</a></p>';
                    }
                    echo '</div>';
                }

                echo '</div>'; // close panel
                echo '<div id="aiutoma_chat_history_container">';
                foreach ($comments as $c) {
                    $is_ai = $c->comment_author === 'Aiutoma';
                    $is_sys = $c->comment_author === 'System';
                    $is_op = strpos($c->comment_author, 'Operator') !== false;
                    
                    if ($is_sys) {
                        $msg_class = 'aiutoma-chatbot-msg-system';
                        $author_name_html = '<em>' . esc_html__('System Notice', 'aiutoma') . '</em>';
                    } else if ($is_op) {
                        $msg_class = 'aiutoma-chatbot-msg-operator';
                        $author_name_html = esc_html($c->comment_author);
                    } else if ($is_ai) {
                        $msg_class = 'aiutoma-chatbot-msg-ai';
                        $ai_name = get_option('aiutoma_chatbot_name', 'AI Bot');
                        if (empty($ai_name)) {
                            $ai_name = 'AI Bot';
                        }
                        $ai_name = apply_filters('wpml_translate_single_string', $ai_name, 'aiutoma', 'chatbot_name');
                        $author_name_html = esc_html($ai_name);
                    } else {
                        $msg_class = 'aiutoma-chatbot-msg-user';
                        $author_display = ($c->comment_author === 'Visitor') ? 'You' : $c->comment_author;
                        $author_name_html = esc_html($author_display);
                        if (!empty($c->user_id) && $c->user_id > 0) {
                            $user_link = get_edit_user_link($c->user_id);
                            $author_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($author_display) . '</a>';
                        }
                    }
                    
                    $del_msg_url = wp_nonce_url(admin_url('admin.php?page=aiutoma-chatbot-logs&action=delete_message&comment_id=' . $c->comment_ID . '&session_id=' . urlencode($session_id)), 'aiutoma_delete_log');
                    $edit_msg_url = get_edit_comment_link($c->comment_ID);
                    
                    echo '<div class="aiutoma-chatbot-chat-message-wrapper ' . esc_attr($msg_class) . '" data-date="' . esc_attr($c->comment_date_gmt) . '">';
                    echo '<div class="aiutoma-chatbot-chat-actions">';
                    echo '<a href="' . esc_url($edit_msg_url) . '" style="color: #2271b1; text-decoration: none; margin-right: 8px;" title="' . esc_attr__('Edit Message natively', 'aiutoma') . '"><span class="dashicons dashicons-edit"></span></a>';
                    echo '<a href="' . esc_url($del_msg_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this message?', 'aiutoma')) . '\');" class="aiutoma-color-danger" style="text-decoration: none;" title="' . esc_attr__('Delete Message', 'aiutoma') . '"><span class="dashicons dashicons-trash"></span></a>';
                    echo '</div>';
                    echo '<strong>' . wp_kses_post($author_name_html) . '</strong> <span class="aiutoma-chatbot-chat-date">(' . esc_html($c->comment_date) . ')</span>';
                    
                    $msg_tokens = (int) get_comment_meta($c->comment_ID, 'aiutoma_tokens_total', true);
                    if ($msg_tokens > 0) {
                        echo '<span style="font-size: 11px; color: #777; margin-left: 10px;" title="' . esc_attr__('Token cost for this message', 'aiutoma') . '"><span class="dashicons dashicons-database" style="font-size: 12px; height: 12px; width: 12px; margin-top: 1px;"></span> ' . esc_html(number_format_i18n($msg_tokens)) . ' tokens</span>';
                    }
                    
                    echo '<div style="margin-top: 8px;">' . wp_kses_post($c->comment_content) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
                
                $manual_mode = get_transient('aiutoma_chatbot_manual_' . $session_id);
                
                echo '<hr>';
                echo '<h4>' . esc_html__('Operator Takeover', 'aiutoma') . '</h4>';
                echo '<p>';
                echo '<label><input type="checkbox" id="aiutoma_manual_mode_toggle" data-session="' . esc_attr($session_id) . '" ' . checked($manual_mode, true, false) . '> ' . esc_html__('Enable Manual Mode (AI will stop replying)', 'aiutoma') . '</label>';
                echo '</p>';
                
                echo '<div id="aiutoma_operator_chat_area" class="aiutoma-chatbot-operator-area" style="display: ' . ($manual_mode ? 'block' : 'none') . ';">';
                echo '<textarea id="aiutoma_operator_message" class="aiutoma-chatbot-operator-textarea" placeholder="' . esc_attr__('Type your message here...', 'aiutoma') . '"></textarea>';
                echo '<br><button id="aiutoma_operator_send_btn" class="button button-primary" style="margin-top: 10px;" data-session="' . esc_attr($session_id) . '">' . esc_html__('Send Message', 'aiutoma') . '</button>';
                echo '</div>';
                
                echo '</div>';
            }
        } else {
            // List Sessions
            echo '<h1 class="wp-heading-inline">' . esc_html__('Chatbot Logs', 'aiutoma') . '</h1>';
            
            $search_val = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            if (!empty($_GET['filter_email']) || !empty($_GET['filter_ip']) || !empty($search_val)) {
                echo ' <a href="' . esc_url(admin_url('admin.php?page=aiutoma-chatbot-logs')) . '" class="page-title-action">' . esc_html__('Clear Filter', 'aiutoma') . '</a>';
            }
            
            // Search Form
            echo '<form method="get" class="aiutoma-chatbot-search-form">';
            echo '<input type="hidden" name="page" value="aiutoma-chatbot-logs">';
            echo '<p class="search-box">';
            echo '<label class="screen-reader-text" for="post-search-input">' . esc_html__('Search Users', 'aiutoma') . ':</label>';
            echo '<input type="search" id="post-search-input" name="s" value="' . esc_attr($search_val) . '" placeholder="' . esc_attr__('Search User/Email', 'aiutoma') . '">';
            echo '<input type="submit" id="search-submit" class="button" value="' . esc_html__('Search Sessions', 'aiutoma') . '">';
            echo '</p>';
            echo '</form>';
            
            echo '<hr class="wp-header-end">';
            
            $per_page = 20;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($paged - 1) * $per_page;
            
            $where_clause = "c.comment_type = 'aiutoma_chat' AND m.meta_key = 'aiutoma_session_id'";
            $having_clause = "HAVING MAX(CASE WHEN c.comment_author != 'Aiutoma' THEN 1 ELSE 0 END) = 1 AND m.meta_value != ''";
            
            $op_like = 'Operator%';
            if (!empty($_GET['filter_email'])) {
                $having_clause .= $wpdb->prepare(" AND MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) = %s", $op_like, sanitize_text_field($_GET['filter_email']));
            } else if (!empty($_GET['filter_ip'])) {
                $having_clause .= $wpdb->prepare(" AND MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author_IP ELSE NULL END) = %s", $op_like, sanitize_text_field($_GET['filter_ip']));
            }
            
            if (!empty($search_val)) {
                $like_val = '%' . $wpdb->esc_like($search_val) . '%';
                $having_clause .= $wpdb->prepare(" AND (
                    MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author ELSE NULL END) LIKE %s
                    OR 
                    MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) LIKE %s
                )", $op_like, $like_val, $op_like, $like_val);
            }

            // Get unique session IDs using SQL since WP_Comment_Query doesn't support GROUP BY meta_value natively
            $query = "
                SELECT m.meta_value AS session_id, 
                       MAX(c.comment_date) AS last_activity, 
                        MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author_email ELSE NULL END) AS comment_author_email, 
                        MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author ELSE NULL END) AS comment_author,
                        MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.user_id ELSE 0 END) AS user_id,
                        MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_author_IP ELSE NULL END) AS comment_author_IP,
                        MAX(CASE WHEN c.comment_author != 'Aiutoma' AND c.comment_author NOT LIKE %s THEN c.comment_agent ELSE NULL END) AS comment_agent
                 FROM {$wpdb->comments} c 
                 INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                 WHERE {$where_clause} 
                 GROUP BY m.meta_value 
                 {$having_clause}
                 ORDER BY last_activity DESC 
                 LIMIT %d OFFSET %d
             ";
             // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
             $sessions = $wpdb->get_results($wpdb->prepare($query, $op_like, $op_like, $op_like, $op_like, $op_like, $per_page, $offset));

            $total_query = "
                SELECT COUNT(*) FROM (
                    SELECT m.meta_value 
                    FROM {$wpdb->comments} c 
                    INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id 
                    WHERE {$where_clause}
                    GROUP BY m.meta_value
                    {$having_clause}
                ) AS count_table
            ";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total_sessions = $wpdb->get_var($total_query);
            $total_pages = ceil($total_sessions / $per_page);

            if (empty($sessions)) {
                echo '<p>' . esc_html__('No chat sessions found.', 'aiutoma') . '</p>';
            } else {
                echo '<form method="post" action="">';
                wp_nonce_field('aiutoma_bulk_delete_logs');
                echo '<div class="tablenav top">';
                echo '<div class="alignleft actions bulkactions">';
                echo '<select name="action">';
                echo '<option value="-1">' . esc_html__('Bulk actions', 'aiutoma') . '</option>';
                echo '<option value="bulk_delete">' . esc_html__('Delete', 'aiutoma') . '</option>';
                echo '</select>';
                echo '<input type="submit" class="button action" value="' . esc_html__('Apply', 'aiutoma') . '" onclick="if(document.querySelector(\'select[name=action]\').value === \'bulk_delete\'){ return confirm(\'' . esc_js(__('Are you sure you want to delete selected sessions?', 'aiutoma')) . '\'); } return true;">';
                echo '</div>';
                echo '</div>';

                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>';
                echo '<th>' . esc_html__('Session ID', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('User', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('Email', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('IP', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('Browser', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('Last Activity', 'aiutoma') . '</th>';
                echo '<th>' . esc_html__('Actions', 'aiutoma') . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($sessions as $s) {
                    $view_url = admin_url('admin.php?page=aiutoma-chatbot-logs&action=view&session_id=' . urlencode($s->session_id));
                    
                    $display_name = !empty($s->comment_author) ? $s->comment_author : 'Visitor';
                    
                    if (!empty($s->user_id) && $s->user_id > 0) {
                        $user_link = get_edit_user_link($s->user_id);
                        $display_name_html = '<a href="' . esc_url($user_link) . '">' . esc_html($display_name) . '</a>';
                    } else {
                        $display_name_html = esc_html($display_name);
                    }
                    
                    echo '<tr>';
                    echo '<th scope="row" class="check-column"><input type="checkbox" name="session_ids[]" value="' . esc_attr($s->session_id) . '"></th>';
                    echo '<td><strong><a href="' . esc_url($view_url) . '">' . esc_html($s->session_id) . '</a></strong></td>';
                    echo '<td>' . wp_kses_post($display_name_html) . '</td>';
                    echo '<td>' . esc_html($s->comment_author_email) . '</td>';
                    echo '<td>' . esc_html($s->comment_author_IP) . '</td>';
                    echo '<td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . esc_attr($s->comment_agent) . '">' . esc_html($s->comment_agent) . '</td>';
                    echo '<td>' . esc_html($s->last_activity) . '</td>';
                    
                    $del_session_url = wp_nonce_url(admin_url('admin.php?page=aiutoma-chatbot-logs&action=delete_session&session_id=' . urlencode($s->session_id)), 'aiutoma_delete_log');
                    
                    echo '<td>';
                    echo '<a href="' . esc_url($view_url) . '" class="button dashicons-before dashicons-visibility" style="margin-right: 5px;" title="' . esc_attr__('View Thread', 'aiutoma') . '"></a>';
                    echo '<a href="' . esc_url($del_session_url) . '" class="button dashicons-before dashicons-trash" style="color: #b32d2e; border-color: #b32d2e;" title="' . esc_attr__('Delete', 'aiutoma') . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this entire session?', 'aiutoma')) . '\');"></a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                if ($total_pages > 1) {
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'aiutoma'),
                        'next_text' => __('&raquo;', 'aiutoma'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    if ($page_links) {
                        echo '<div class="tablenav"><div class="tablenav-pages" style="float:left; margin-top:10px;">' . wp_kses_post($page_links) . '</div></div>';
                    }
                }
                echo '</form>';
            }
        }
        
        // Add new activity alert logic
        ?>
        <div id="aiutoma-new-activity-alert" class="notice notice-info is-dismissible aiutoma-chatbot-alert">
            <p><strong><?php esc_html_e('New chat activity detected!', 'aiutoma'); ?></strong> <a href="javascript:void(0)" onclick="window.location.reload();" class="aiutoma-chatbot-alert-link"><?php esc_html_e('Refresh page to see updates', 'aiutoma'); ?></a></p>
        </div>
        <?php
        
        echo '</div>';
    }

    public function handle_summarize_session_request(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        if (empty($session_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing session ID.', 'aiutoma')], 400);
        }

        $comments = get_comments([
            'type' => 'aiutoma_chat',
            'meta_key' => 'aiutoma_session_id',
            'meta_value' => $session_id,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC',
            'status' => 'all'
        ]);

        if (empty($comments)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('No messages found for this session.', 'aiutoma')], 404);
        }

        $chat_transcript = "";
        foreach ($comments as $c) {
            $author = ($c->comment_author === 'Aiutoma') ? 'AI' : 'User';
            $chat_transcript .= "{$author}: " . wp_strip_all_tags($c->comment_content) . "\n";
        }

        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_REST_Response(['success' => false, 'message' => __('AI Client not available.', 'aiutoma')], 500);
        }

        $prompt = "Please provide a brief and concise summary (digest) of the following chat session between a User and an AI Assistant. Highlight the main topics discussed and any conclusions or resolutions.\n\n";
        $prompt .= "CHAT TRANSCRIPT:\n" . $chat_transcript;

        try {
            $ai_query = \WordPress\AiClient\AiClient::prompt([
                new \WordPress\AiClient\Messages\DTO\UserMessage([
                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                ])
            ]);
            
            $configured_model = get_option('aiutoma_chatbot_model', '');
            if ($configured_model && strpos($configured_model, '|') !== false) {
                list($selectedProvider, $selectedModel) = explode('|', $configured_model);
                $ai_query->usingModelPreference([$selectedProvider, $selectedModel]);
            }
            
            $res = $ai_query->generateResult();
            $summary = $res->toText();

            // Format markdown to simple HTML for display
            if (class_exists('Parsedown')) {
                $parsedown = new \Parsedown();
                $summary_html = $parsedown->text($summary);
            } else {
                $summary_html = nl2br(esc_html($summary));
            }

            return new \WP_REST_Response(['success' => true, 'summary' => wp_kses_post($summary_html)], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function handle_chatbot_toggle_manual(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $manual = (bool)$request->get_param('manual');
        if (empty($session_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing session ID.', 'aiutoma')], 400);
        }

        if ($manual) {
            set_transient('aiutoma_chatbot_manual_' . $session_id, 1, 12 * HOUR_IN_SECONDS);
        } else {
            delete_transient('aiutoma_chatbot_manual_' . $session_id);
        }

        // Insert a system notice for the handover
        global $wpdb;
        $post_id = (int) $wpdb->get_var($wpdb->prepare("
            SELECT c.comment_post_ID 
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
            WHERE m.meta_key = 'aiutoma_session_id' AND m.meta_value = %s
            LIMIT 1
        ", $session_id));

        $current_user = wp_get_current_user();
        $operator_label = $current_user->exists() ? $current_user->display_name : __('Operator', 'aiutoma');
        
        if ($manual) {
            /* translators: %s: operator name */
            $notice_text = sprintf(__('A human operator (%s) has joined the chat.', 'aiutoma'), $operator_label);
        } else {
            $notice_text = __('The human operator has left the chat. The AI assistant is active again.', 'aiutoma');
        }

        $comment_id = wp_insert_comment([
            'comment_post_ID' => $post_id,
            'comment_author' => 'System',
            'comment_author_email' => '',
            'comment_author_url' => '',
            'comment_parent' => 0,
            'user_id' => 0,
            'comment_content' => wp_slash($notice_text),
            'comment_type' => 'aiutoma_chat',
            'comment_approved' => 1
        ]);

        if ($comment_id) {
            update_comment_meta($comment_id, 'aiutoma_session_id', $session_id);
            update_comment_meta($comment_id, 'aiutoma_chat_log', 1);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function handle_chatbot_operator_send(\WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $message = sanitize_text_field($request->get_param('message'));

        if (empty($session_id) || empty($message)) {
            return new \WP_REST_Response(['success' => false, 'message' => __('Missing parameters.', 'aiutoma')], 400);
        }

        $current_user = wp_get_current_user();
        $author_name = 'Operator (' . $current_user->display_name . ')';

        global $wpdb;
        $post_id = (int) $wpdb->get_var($wpdb->prepare("
            SELECT c.comment_post_ID 
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
            WHERE m.meta_key = 'aiutoma_session_id' AND m.meta_value = %s
            LIMIT 1
        ", $session_id));

        $comment_id = wp_insert_comment([
            'comment_post_ID' => $post_id,
            'comment_author' => wp_slash($author_name),
            'comment_author_email' => $current_user->user_email,
            'comment_author_url' => '',
            'comment_parent' => 0,
            'user_id' => $current_user->ID,
            'comment_content' => wp_slash($message),
            'comment_type' => 'aiutoma_chat',
            'comment_approved' => 1
        ]);

        if ($comment_id) {
            update_comment_meta($comment_id, 'aiutoma_session_id', $session_id);
            update_comment_meta($comment_id, 'aiutoma_chat_log', 1);
            return new \WP_REST_Response(['success' => true, 'comment_id' => $comment_id], 200);
        }

        return new \WP_REST_Response(['success' => false, 'message' => __('Failed to insert message.', 'aiutoma')], 500);
    }
}
