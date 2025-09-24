<?php

class AI_ChatBot_Chat_History {
    private $cache_dir;
    private $cleanup_interval = 300; // 5 РјРёРЅСѓС‚
    private $last_cleanup = 0;
    private $inactivity_timeout;
    
    public function __construct() {
        // РџРѕР»СѓС‡Р°РµРј С‚Р°Р№РјР°СѓС‚ РЅРµР°РєС‚РёРІРЅРѕСЃС‚Рё РёР· РЅР°СЃС‚СЂРѕРµРє (РІ РјРёР»Р»РёСЃРµРєСѓРЅРґР°С…) Рё РєРѕРЅРІРµСЂС‚РёСЂСѓРµРј РІ СЃРµРєСѓРЅРґС‹
        $timeout_ms = intval(get_option('ai_chatbot_inactivity_timeout', 300000));
        if ($timeout_ms < 60000) {
            $timeout_ms = 60000;
        }
        $this->inactivity_timeout = $timeout_ms / 1000;
        
        // РЎРѕР·РґР°РµРј РґРёСЂРµРєС‚РѕСЂРёСЋ РґР»СЏ С…СЂР°РЅРµРЅРёСЏ С‡Р°С‚РѕРІ РІ wp-content/uploads
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/ai-chatbot-history/';
        
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            
            // РЎРѕР·РґР°РµРј .htaccess РґР»СЏ Р·Р°С‰РёС‚С‹ РґРёСЂРµРєС‚РѕСЂРёРё
            $htaccess = $this->cache_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
        
        // Р РµРіРёСЃС‚СЂРёСЂСѓРµРј РѕР±СЂР°Р±РѕС‚С‡РёРє РґР»СЏ РїСЂРѕРІРµСЂРєРё РєРѕРЅРєСЂРµС‚РЅРѕРіРѕ С‡Р°С‚Р°
        add_action('ai_chatbot_check_chat', array($this, 'check_and_send_chat'));
        
        // Р РµРіРёСЃС‚СЂРёСЂСѓРµРј СЂРµРіСѓР»СЏСЂРЅСѓСЋ РїСЂРѕРІРµСЂРєСѓ СЃС‚Р°СЂС‹С… С‡Р°С‚РѕРІ
        if (!wp_next_scheduled('ai_chatbot_cleanup_chats')) {
            wp_schedule_event(time(), 'every_minute', 'ai_chatbot_cleanup_chats');
        }
        add_action('ai_chatbot_cleanup_chats', array($this, 'cleanup_old_chats'));
    }
    
    public function save_message($session_id, $message_data) {
        $file_path = $this->get_chat_file_path($session_id);
        $meta_path = $this->get_chat_meta_path($session_id);
        
        // РџРѕР»СѓС‡Р°РµРј СЃСѓС‰РµСЃС‚РІСѓСЋС‰РёРµ СЃРѕРѕР±С‰РµРЅРёСЏ
        $messages = $this->get_chat_messages($session_id);
        if (!is_array($messages)) {
            $messages = array();
        }
        
        // Р”РѕР±Р°РІР»СЏРµРј РЅРѕРІРѕРµ СЃРѕРѕР±С‰РµРЅРёРµ
        $messages[] = $message_data;
        
        // РЎРѕС…СЂР°РЅСЏРµРј СЃРѕРѕР±С‰РµРЅРёСЏ
        file_put_contents($file_path, json_encode($messages));
        
        // РћР±РЅРѕРІР»СЏРµРј РёР»Рё СЃРѕР·РґР°РµРј РјРµС‚Р°-РёРЅС„РѕСЂРјР°С†РёСЋ
        $meta = array(
            'last_update' => time(),
            'needs_email' => true,
            'message_count' => count($messages),
            'first_message_time' => isset($messages[0]) ? $messages[0]['timestamp'] : time(),
            'retry_count' => 0
        );
        file_put_contents($meta_path, json_encode($meta));
        
        // РџР»Р°РЅРёСЂСѓРµРј РїСЂРѕРІРµСЂРєСѓ С‡РµСЂРµР· Р·Р°РґР°РЅРЅРѕРµ РІСЂРµРјСЏ
        $scheduled = wp_next_scheduled('ai_chatbot_check_chat', array($session_id));
        if ($scheduled === false) {
            wp_schedule_single_event(time() + $this->inactivity_timeout, 'ai_chatbot_check_chat', array($session_id));
        }
    }
    
    public function get_chat_messages($session_id) {
        $file_path = $this->get_chat_file_path($session_id);
        
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            return json_decode($content, true);
        }
        
        return array();
    }
    
    public function delete_chat_files($session_id) {
        $file_path = $this->get_chat_file_path($session_id);
        $meta_path = $this->get_chat_meta_path($session_id);
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        if (file_exists($meta_path)) {
            unlink($meta_path);
        }
        
        // РћС‚РјРµРЅСЏРµРј Р·Р°РїР»Р°РЅРёСЂРѕРІР°РЅРЅС‹Рµ СЃРѕР±С‹С‚РёСЏ РґР»СЏ СЌС‚РѕРіРѕ С‡Р°С‚Р°
        wp_clear_scheduled_hook('ai_chatbot_check_chat', array($session_id));
    }
    
    public function cleanup_old_chats() {
        $current_time = time();
        
        // РџСЂРѕРІРµСЂСЏРµРј РІСЃРµ С„Р°Р№Р»С‹ РІ РґРёСЂРµРєС‚РѕСЂРёРё
        $files = glob($this->cache_dir . '*.meta');
        foreach ($files as $file) {
            $session_id = basename($file, '.meta');
            $meta = json_decode(file_get_contents($file), true);
            
            if (!$meta) continue;
            
            $time_passed = $current_time - $meta['last_update'];
            
            // РџСЂРѕРІРµСЂСЏРµРј С‡Р°С‚С‹ РєРѕС‚РѕСЂС‹Рµ:
            // 1. РџРѕРјРµС‡РµРЅС‹ РєР°Рє С‚СЂРµР±СѓСЋС‰РёРµ РѕС‚РїСЂР°РІРєРё
            // 2. РџСЂРѕС€Р»Рѕ РґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РІСЂРµРјРµРЅРё СЃ РїРѕСЃР»РµРґРЅРµРіРѕ РѕР±РЅРѕРІР»РµРЅРёСЏ
            if ($meta['needs_email'] && $time_passed > $this->inactivity_timeout) {
                $this->check_and_send_chat($session_id);
            }
        }
    }
    
    public function check_and_send_chat($session_id) {
        $meta_path = $this->get_chat_meta_path($session_id);
        
        if (!file_exists($meta_path)) {
            return;
        }
        
        $meta = json_decode(file_get_contents($meta_path), true);
        if (!$meta || !isset($meta['needs_email']) || !$meta['needs_email']) {
            return;
        }
        
        $messages = $this->get_chat_messages($session_id);
        if (empty($messages)) {
            return;
        }
        
        // РџС‹С‚Р°РµРјСЃСЏ РѕС‚РїСЂР°РІРёС‚СЊ email
        if ($this->send_chat_to_email($session_id, $messages)) {
            // Р•СЃР»Рё СѓСЃРїРµС€РЅРѕ РѕС‚РїСЂР°РІР»РµРЅРѕ
            $meta['needs_email'] = false;
            file_put_contents($meta_path, json_encode($meta));
            
            // РЈРґР°Р»СЏРµРј С„Р°Р№Р»С‹ С‚РѕР»СЊРєРѕ РµСЃР»Рё РїСЂРѕС€Р»Рѕ РґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РІСЂРµРјРµРЅРё
            $time_passed = time() - $meta['last_update'];
            if ($time_passed > $this->inactivity_timeout) {
                $this->delete_chat_files($session_id);
            }
        } else {
            // Р•СЃР»Рё РѕС‚РїСЂР°РІРєР° РЅРµ СѓРґР°Р»Р°СЃСЊ
            $meta['retry_count'] = isset($meta['retry_count']) ? $meta['retry_count'] + 1 : 1;
            file_put_contents($meta_path, json_encode($meta));
            
            // РџР»Р°РЅРёСЂСѓРµРј СЃР»РµРґСѓСЋС‰СѓСЋ РїРѕРїС‹С‚РєСѓ С‡РµСЂРµР· 5 РјРёРЅСѓС‚, РЅРѕ РЅРµ Р±РѕР»РµРµ 5 РїРѕРїС‹С‚РѕРє
            if ($meta['retry_count'] < 5) {
                wp_schedule_single_event(time() + 300, 'ai_chatbot_check_chat', array($session_id));
            }
        }
    }
    
    public function send_chat_to_email($session_id, $messages) {
        if (empty($messages)) {
            return false;
        }
        
        $recipient = get_option('ai_chatbot_email_to');
        if (!$recipient || !is_email($recipient)) {
            $recipient = get_option('admin_email');
        }
        
        if (!$recipient || !is_email($recipient)) {
            return false;
        }
        
        $subject = sprintf('Чат с пользователем (ID: %s)', $session_id);
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        
        $lines = array();
        if (!empty($site_name)) {
            $lines[] = 'Сайт: ' . $site_name;
        }
        $lines[] = 'История переписки:';
        $lines[] = '';
        
        foreach ($messages as $message) {
            $timestamp = isset($message['timestamp']) ? intval($message['timestamp']) : time();
            $time = date_i18n('d.m.Y H:i:s', $timestamp);
            $sender = isset($message['sender']) && $message['sender'] === 'user' ? 'Пользователь' : 'Бот';
            $text = isset($message['text']) ? wp_strip_all_tags($message['text']) : '';
            $text = preg_replace("/\r?\n/", "\r\n", $text);
        
            $lines[] = sprintf('[%s] %s:', $time, $sender);
            if ($text !== '') {
                $lines[] = $text;
            }
            $lines[] = '';
        }
        
        $body = implode("\r\n", $lines);
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        return wp_mail($recipient, $subject, $body, $headers);
    }
    
    private function get_chat_file_path($session_id) {
        return $this->cache_dir . sanitize_file_name($session_id) . '.chat';
    }
    
    private function get_chat_meta_path($session_id) {
        return $this->cache_dir . sanitize_file_name($session_id) . '.meta';
    }
}
