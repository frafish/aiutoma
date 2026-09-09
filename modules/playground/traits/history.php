<?php
namespace Aiutoma\Modules\Playground\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait History {
    public function handle_save_session(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (empty($params['conversation_id'])) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing data.'], 400);
        }
        
        $upload_dir = wp_upload_dir();
        $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions';
        if (!is_dir($log_dir)) wp_mkdir_p($log_dir);
        
        $session_id = sanitize_file_name($params['conversation_id']);
        $file_path = $log_dir . '/' . $session_id . '.json';
        
        $messages = [];
        if (!empty($params['messages']) && is_array($params['messages'])) {
            foreach ($params['messages'] as $msg) {
                if (isset($msg['role']) && is_string($msg['role']) && isset($msg['parts']) && is_array($msg['parts'])) {
                    $valid_parts = [];
                    foreach ($msg['parts'] as $part) {
                        if (is_array($part)) {
                            // Basic sanitization on text parts could be applied here if needed
                            $valid_parts[] = $part;
                        }
                    }
                    $messages[] = [
                        'role' => sanitize_text_field($msg['role']),
                        'parts' => $valid_parts
                    ];
                }
            }
        }
        
        $data = [
            'id' => $session_id,
            'owner_id' => wp_get_current_user()->ID,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'messages' => $messages,
            'context' => isset($params['context']) && is_array($params['context']) ? $params['context'] : [],
            'session_prompts' => isset($params['session_prompts']) && is_array($params['session_prompts']) ? array_map('sanitize_textarea_field', $params['session_prompts']) : [],
            'html' => isset($params['html']) ? wp_kses_post($params['html']) : ''
        ];
        
        file_put_contents($file_path, json_encode($data));
        return new \WP_REST_Response(['success' => true]);
    }

    public function get_recent_sessions($limit = 10) {
        $upload_dir = wp_upload_dir();
        $log_dir = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions';
        if (!is_dir($log_dir)) return [];
        
        $files = glob($log_dir . '/*.json');
        if (empty($files)) return [];
        
        $sessions = [];
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && !empty($data['id'])) {
                
                $first_prompt = 'Empty session';
                if (!empty($data['messages']) && is_array($data['messages']) && count($data['messages']) > 0) {
                    if (!empty($data['messages'][0]['parts'][0]['text'])) {
                        $first_prompt = $data['messages'][0]['parts'][0]['text'];
                    }
                } elseif (!empty($data['first_prompt'])) { // Legacy format
                    $first_prompt = $data['first_prompt'];
                }

                $sessions[] = [
                    'id' => $data['id'],
                    'date' => $data['created_at'] ?? ($data['date'] ?? filemtime($file)),
                    'user' => $data['owner_id'] ?? ($data['user'] ?? 'unknown'),
                    'first_prompt' => $first_prompt,
                    'file' => basename($file)
                ];
            }
        }
        
        usort($sessions, function($a, $b) {
            $time_a = is_numeric($a['date']) ? $a['date'] : strtotime($a['date']);
            $time_b = is_numeric($b['date']) ? $b['date'] : strtotime($b['date']);
            return $time_b - $time_a;
        });
        
        return array_slice($sessions, 0, $limit);
    }
    
    public function handle_get_session(\WP_REST_Request $request) {
        $id = sanitize_file_name($request->get_param('id'));
        $upload_dir = wp_upload_dir();
        $file_path = \Aiutoma\Modules\Ai\Ai::get_storage_dir() . '/logs/sessions/' . $id . '.json';
        if (file_exists($file_path)) {
            return new \WP_REST_Response(json_decode(file_get_contents($file_path), true));
        }
        return new \WP_REST_Response(['success' => false, 'message' => 'Not found.'], 404);
    }

}
