<?php
class AI_Chatbot_Telegram_Handler {
    private $bot_token;
    private $chat_id;

    public function __construct() {
        $this->bot_token = get_option('ai_chatbot_telegram_token', '');
        $this->chat_id = get_option('ai_chatbot_telegram_chat_id', '');
        
        // Добавляем поля в настройки
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting('ai_chatbot_settings', 'ai_chatbot_telegram_token');
        register_setting('ai_chatbot_settings', 'ai_chatbot_telegram_chat_id');
        
        add_settings_section(
            'ai_chatbot_telegram_section',
            'Настройки Telegram',
            array($this, 'telegram_section_callback'),
            'ai_chatbot_settings'
        );

        add_settings_field(
            'ai_chatbot_telegram_token',
            'Telegram Bot Token',
            array($this, 'telegram_token_callback'),
            'ai_chatbot_settings',
            'ai_chatbot_telegram_section'
        );

        add_settings_field(
            'ai_chatbot_telegram_chat_id',
            'Telegram Chat ID',
            array($this, 'telegram_chat_id_callback'),
            'ai_chatbot_settings',
            'ai_chatbot_telegram_section'
        );
    }

    public function telegram_section_callback() {
        echo 'Настройте интеграцию с Telegram для получения уведомлений о сообщениях.';
    }

    public function telegram_token_callback() {
        $token = get_option('ai_chatbot_telegram_token', '');
        echo '<input type="text" name="ai_chatbot_telegram_token" value="' . esc_attr($token) . '" class="regular-text">';
        echo '<p class="description">Введите токен вашего Telegram бота</p>';
    }

    public function telegram_chat_id_callback() {
        $chat_id = get_option('ai_chatbot_telegram_chat_id', '');
        echo '<input type="text" name="ai_chatbot_telegram_chat_id" value="' . esc_attr($chat_id) . '" class="regular-text">';
        echo '<p class="description">Введите ID чата, куда будут приходить уведомления</p>';
    }

    public function send_message($message, $is_user = true) {
        if (empty($this->bot_token) || empty($this->chat_id)) {
            return false;
        }

        $sender = $is_user ? '👤 User' : '🤖 AI Bot';
        $text = "$sender:\n$message";

        $url = "https://api.telegram.org/bot{$this->bot_token}/sendMessage";
        $data = array(
            'chat_id' => $this->chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        );

        $args = array(
            'body' => $data,
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => false,
            'headers' => array(),
            'cookies' => array()
        );

        $response = wp_remote_post($url, $args);
        return !is_wp_error($response);
    }
}
