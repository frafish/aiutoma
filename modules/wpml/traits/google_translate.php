<?php
namespace Aiutoma\Modules\Wpml\traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait Google_Translate {

    /**
     * make the request to the translator service
     */
    public function google_translate_free($source, $target, $text) {
        if (trim($text) === '') return '';
        if (strlen($text) >= 5000) {
            // For large texts, we might want to split or just return error.
            // But let's keep it simple as per original class
            return new \WP_Error('google_length', "Maximum number of characters exceeded: 5000");
        }

        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&dt=t";

        $fields = array(
            'sl' => urlencode($source),
            'tl' => urlencode($target),
            'q' => urlencode($text)
        );

        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= '&' . $key . '=' . $value;
        }
        $fields_string = rtrim($fields_string, '&');

        $response = wp_remote_post($url, array(
            'body'    => $fields_string,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return new \WP_Error('google_error', $response->get_error_message());
        }

        $result = wp_remote_retrieve_body($response);

        $sentencesArray = json_decode($result, true);
        $sentences = "";

        if (!$sentencesArray || !isset($sentencesArray[0])) {
            return new \WP_Error('google_error', "Google detected unusual traffic or error.");
        }

        foreach ($sentencesArray[0] as $s) {
            $sentences .= isset($s[0]) ? $s[0] : '';
        }

        return $sentences;
    }
}
