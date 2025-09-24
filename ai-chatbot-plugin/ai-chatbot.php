<?php
/**
 * Plugin Name: AI ChatBot Assistant
 * Description: Умный чат-бот консультант с интеграцией OpenAI GPT
 * Version: 1.0.0
 * Author: Your Name
 */

// Предотвращение прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант
define('AI_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Подключаем необходимые файлы
require_once AI_CHATBOT_PLUGIN_PATH . 'includes/email-handler.php';
require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-chat-history.php';

class AIChatBot {
    private $chat_history;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_chat_widget'));
        add_action('wp_ajax_ai_chatbot_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_ai_chatbot_session_end', array($this, 'handle_session_end'));
        add_action('wp_ajax_nopriv_ai_chatbot_session_end', array($this, 'handle_session_end'));
        add_action('wp_ajax_ai_chatbot_save_realtime_settings', array($this, 'handle_realtime_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Добавляем интервал в одну минуту
        add_filter('cron_schedules', function($schedules) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display' => 'Каждую минуту'
            );
            return $schedules;
        });
    }
    
    public function init() {
        $this->chat_history = new AI_ChatBot_Chat_History();
    }
    
    public function activate() {
        // Установка значений по умолчанию
        add_option('ai_chatbot_openai_key', '');
        add_option('ai_chatbot_openai_model', 'gpt-3.5-turbo');
        add_option('ai_chatbot_welcome_message', 'Привет! Я ваш AI-консультант. Чем могу помочь?');
        add_option('ai_chatbot_system_prompt', 'Ты helpful AI-ассистент, отвечающий на вопросы пользователей сайта.');
        add_option('ai_chatbot_bot_name', 'AI Консультант');
        add_option('ai_chatbot_avatar_url', AI_CHATBOT_PLUGIN_URL . 'assets/img/default-avatar.png');
        add_option('ai_chatbot_enabled', '1');
        add_option('ai_chatbot_avatar_size', 40);
        add_option('ai_chatbot_widget_size', 60);
        add_option('ai_chatbot_window_size', 'default');
        add_option('ai_chatbot_animation', 'bounce');
        add_option('ai_chatbot_color_scheme', 'default');
        add_option('ai_chatbot_font_family', 'system-default');
        add_option('ai_chatbot_font_size', 14);
        add_option('ai_chatbot_language', 'ru');
        add_option('ai_chatbot_custom_text', array(
            'placeholder' => 'Напишите ваш вопрос...',
            'online_status' => 'В сети',
            'offline_status' => 'Не в сети',
            'send_button' => 'Отправить'
        ));
    }
    
    public function enqueue_scripts() {
        if (get_option('ai_chatbot_enabled') == '1') {
            wp_enqueue_script('ai-chatbot-js', AI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('ai-chatbot-css', AI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css', array(), '1.0.0');

            $generated_css = get_option('ai_chatbot_generated_css');
            if ($generated_css) {
                wp_enqueue_style('ai-chatbot-generated-css', $generated_css, array('ai-chatbot-css'), null);
            }
            
            wp_localize_script('ai-chatbot-js', 'ai_chatbot_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_chatbot_nonce'),
                'welcome_message' => get_option('ai_chatbot_welcome_message'),
                'bot_name' => get_option('ai_chatbot_bot_name'),
                'avatar_url' => get_option('ai_chatbot_avatar_url')
            ));

            // Добавляем все настройки для JavaScript
            // Получаем все необходимые настройки
            $color_scheme = get_option('ai_chatbot_color_scheme', 'default');
            $primary_color = get_option('ai_chatbot_primary_color', '#667eea');
            $secondary_color = get_option('ai_chatbot_secondary_color', '#764ba2');
            $bot_name_color = get_option('ai_chatbot_bot_name_color', '#000000');
            $margin = intval(get_option('ai_chatbot_margin', 20));
            
            wp_localize_script('ai-chatbot-js', 'ai_chatbot_options', array(
                'animation' => get_option('ai_chatbot_animation', 'bounce'),
                'widget_size' => intval(get_option('ai_chatbot_widget_size', 60)),
                'window_size' => get_option('ai_chatbot_window_size', 'default'),
                'avatar_size' => intval(get_option('ai_chatbot_avatar_size', 40)),
                'color_scheme' => $color_scheme,
                'primary_color' => $primary_color,
                'secondary_color' => $secondary_color,
                'bot_name_color' => $bot_name_color,
                'margin' => $margin,
                'font_family' => get_option('ai_chatbot_font_family', 'system-default'),
                'font_size' => intval(get_option('ai_chatbot_font_size', 14)),
                'language' => get_option('ai_chatbot_language', 'ru'),
                'inactivity_timeout' => intval(get_option('ai_chatbot_inactivity_timeout', 300000)),
                'text' => get_option('ai_chatbot_custom_text', array(
                    'placeholder' => 'Напишите ваш вопрос...',
                    'online_status' => 'В сети',
                    'offline_status' => 'Не в сети',
                    'send_button' => 'Отправить'
                ))
            ));
        }
    }
    
    public function display_chat_widget() {
        if (get_option('ai_chatbot_enabled') == '1') {
            include AI_CHATBOT_PLUGIN_PATH . 'templates/chat-widget.php';
        }
    }
    
    public function handle_chat_message() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $openai_key = get_option('ai_chatbot_openai_key');
        
        if (empty($openai_key)) {
            wp_send_json_error('OpenAI ключ не настроен');
            return;
        }
        
        // Сохраняем сообщение пользователя
        $this->chat_history->save_message($session_id, array(
            'sender' => 'user',
            'text' => $message,
            'timestamp' => time()
        ));
        
        // Получаем ответ от API
        $response = $this->call_openai_api($message, $openai_key);
        
        if ($response) {
            // Сохраняем ответ бота
            $this->chat_history->save_message($session_id, array(
                'sender' => 'bot',
                'text' => $response,
                'timestamp' => time()
            ));
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Ошибка получения ответа от AI');
        }
    }
    
    private function call_openai_api($message, $api_key) {
        $system_prompt = get_option('ai_chatbot_system_prompt');
        
        $data = array(
            'model' => get_option('ai_chatbot_openai_model', 'gpt-3.5-turbo'),
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $message
                )
            ),
            'max_tokens' => 1000,
            'temperature' => 0.7
        );
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data)
        );
        
        $response = wp_remote_request('https://api.openai.com/v1/chat/completions', $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return false;
    }
    
    public function add_admin_menu() {
        add_options_page(
            'AI ChatBot Settings',
            'AI ChatBot',
            'manage_options',
            'ai-chatbot-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        // Регистрируем настройки
        register_setting('ai_chatbot_settings', 'ai_chatbot_enabled');
        register_setting('ai_chatbot_settings', 'ai_chatbot_openai_key');
        register_setting('ai_chatbot_settings', 'ai_chatbot_openai_model');
        register_setting('ai_chatbot_settings', 'ai_chatbot_welcome_message');
        register_setting('ai_chatbot_settings', 'ai_chatbot_system_prompt');
        register_setting('ai_chatbot_settings', 'ai_chatbot_bot_name');
        register_setting('ai_chatbot_settings', 'ai_chatbot_avatar_url');
        register_setting('ai_chatbot_settings', 'ai_chatbot_enabled');
        register_setting('ai_chatbot_settings', 'ai_chatbot_inactivity_timeout');
        register_setting('ai_chatbot_settings', 'ai_chatbot_avatar_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_widget_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_window_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_animation');
        register_setting('ai_chatbot_settings', 'ai_chatbot_color_scheme');
        register_setting('ai_chatbot_settings', 'ai_chatbot_font_family');
        register_setting('ai_chatbot_settings', 'ai_chatbot_font_size');
        register_setting('ai_chatbot_settings', 'ai_chatbot_language');
        register_setting('ai_chatbot_settings', 'ai_chatbot_custom_text');
        
        add_settings_section(
            'ai_chatbot_main_section',
            'Основные настройки',
            null,
            'ai-chatbot-settings'
        );

        // Подключаем скрипты для админки
        if (isset($_GET['page']) && $_GET['page'] === 'ai-chatbot-settings') {
            wp_enqueue_style('ai-chatbot-admin', AI_CHATBOT_PLUGIN_URL . 'assets/css/admin.css');
            wp_enqueue_script(
                'ai-chatbot-admin',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/admin-settings.js',
                array('jquery'),
                '1.0.0',
                true
            );
            wp_enqueue_script(
                'ai-chatbot-admin-realtime',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/admin-realtime.js',
                array('jquery'),
                '1.0.0',
                true
            );
            wp_localize_script('ai-chatbot-admin', 'ai_chatbot_admin', array(
                'nonce' => wp_create_nonce('ai_chatbot_nonce')
            ));
        }
    }
    
    public function admin_page() {
        include AI_CHATBOT_PLUGIN_PATH . 'admin/settings-page.php';
    }

    public function handle_realtime_settings() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        // Сохраняем все настройки
        foreach ($settings as $key => $value) {
            $option_name = 'ai_chatbot_' . $key;

            switch ($key) {
                case 'bot_name_color':
                case 'primary_color':
                case 'secondary_color':
                    update_option($option_name, sanitize_hex_color($value));
                    break;

                case 'margin':
                case 'widget_size':
                case 'avatar_size':
                case 'font_size':
                    update_option($option_name, intval($value));
                    break;

                case 'inactivity_timeout':
                    $timeout = max(60000, intval($value));
                    update_option($option_name, $timeout);
                    break;

                case 'color_scheme':
                case 'window_size':
                case 'font_family':
                case 'animation':
                case 'language':
                case 'openai_model':
                    update_option($option_name, sanitize_text_field($value));
                    break;

                case 'bot_name':
                    update_option($option_name, sanitize_text_field($value));
                    break;

                case 'welcome_message':
                case 'system_prompt':
                    update_option($option_name, sanitize_textarea_field($value));
                    break;

                case 'avatar_url':
                    update_option($option_name, esc_url_raw($value));
                    break;

                case 'email_to':
                    update_option($option_name, sanitize_email($value));
                    break;

                case 'openai_key':
                    update_option($option_name, sanitize_text_field($value));
                    break;

                case 'enabled':
                    update_option($option_name, $value === '1' ? '1' : '0');
                    break;

                case 'custom_text':
                    if (is_array($value)) {
                        $sanitized_text = array_map('sanitize_text_field', $value);
                        update_option($option_name, $sanitized_text);
                    }
                    break;

                default:
                    if (is_array($value)) {
                        $sanitized = array_map('sanitize_text_field', $value);
                        update_option($option_name, $sanitized);
                    } else {
                        update_option($option_name, sanitize_text_field($value));
                    }
                    break;
            }
        }
        
        // Генерируем новый CSS
        if (!class_exists('AI_ChatBot_CSS_Generator')) {
            require_once AI_CHATBOT_PLUGIN_PATH . 'includes/class-css-generator.php';
        }
        
        $css_generator = new AI_ChatBot_CSS_Generator();
        $css_url = $css_generator->save();
        
        wp_send_json_success(array(
            'css_url' => $css_url,
            'settings' => $settings
        ));
    }

    public function handle_session_end() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        if (!empty($session_id)) {
            // Проверяем есть ли сообщения в этой сессии
            $messages = $this->chat_history->get_chat_messages($session_id);
            if (!empty($messages)) {
                // Отправляем на email
                $this->chat_history->send_chat_to_email($session_id, $messages);
                // Удаляем файлы чата
                $this->chat_history->delete_chat_files($session_id);
            }
        }
        wp_send_json_success();
    }
}

// Инициализация плагина
new AIChatBot();