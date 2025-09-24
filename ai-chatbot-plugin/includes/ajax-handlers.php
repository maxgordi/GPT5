<?php
// Безопасность
if (!defined('ABSPATH')) {
    exit;
}

// Обработчики AJAX
add_action('wp_ajax_test_openai_connection', 'ai_chatbot_test_connection');
add_action('wp_ajax_ai_chatbot_clear_cache', 'ai_chatbot_clear_cache');

/**
 * Тестирование подключения к OpenAI API
 */
function ai_chatbot_test_connection() {
    if (!check_ajax_referer('test_openai_connection', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    
    if (empty($api_key)) {
        wp_send_json_error('API key is required');
        return;
    }
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => 'Test connection')
            )
        )),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        wp_send_json_error('API Error: ' . $error_message);
        return;
    }
    
    wp_send_json_success('Connection successful');
}

/**
 * Очистка кеша плагина
 */
function ai_chatbot_clear_cache() {
    if (!check_ajax_referer('ai_chatbot_clear_cache', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Очищаем transients
        delete_transient('ai_chatbot_settings');
        
        // Очищаем кеш объектов WordPress
        wp_cache_flush();
        
        // Обновляем версию стилей
        $style_version = get_option('ai_chatbot_style_version', 1);
        update_option('ai_chatbot_style_version', $style_version + 1);
        
        // Очищаем OPcache если он включен
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        wp_send_json_success('Cache cleared successfully');
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
