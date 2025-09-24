<?php
/**
 * Обработчик отправки email уведомлений
 */

if (!defined('ABSPATH')) {
    exit;
}

function ai_chatbot_handle_email() {
    check_ajax_referer('ai_chatbot_nonce', 'nonce');
    
    $chat_history = json_decode(stripslashes($_POST['history']), true);
    
    if (empty($chat_history)) {
        wp_send_json_error('История чата пуста');
        return;
    }
    
    // Получаем email из настроек
    $to_email = get_option('ai_chatbot_email_to', 'gordienko.office@gmail.com');
    
    // Формируем текст письма
    $email_content = "История чата с сайта " . get_bloginfo('name') . "\n\n";
    $email_content .= "Дата и время: " . current_time('mysql') . "\n\n";
    $email_content .= "История переписки:\n\n";
    
    foreach ($chat_history as $message) {
        $sender = $message['sender'] === 'user' ? 'Пользователь' : 'Бот';
        $time = isset($message['time']) ? $message['time'] : '';
        $email_content .= "[{$time}] {$sender}: {$message['text']}\n\n";
    }
    
    // Добавляем информацию о пользователе
    $email_content .= "\nИнформация о пользователе:\n";
    $email_content .= "IP адрес: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $email_content .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
    $email_content .= "URL страницы: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Неизвестно') . "\n";
    
    // Отправляем email
    $subject = 'История чата с сайта ' . get_bloginfo('name');
    
    // Формируем заголовки письма
    $site_name = get_bloginfo('name');
    $domain = $_SERVER['HTTP_HOST'];
    
    // Расширенные заголовки для лучшей доставки
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: {$site_name} <noreply@{$domain}>\r\n";
    $headers .= "Reply-To: noreply@{$domain}\r\n";
    $headers .= "Return-Path: noreply@{$domain}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 3\r\n";
    
    // Кодируем тему письма для поддержки UTF-8
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    // Отправляем письмо напрямую через mail()
    $sent = mail($to_email, $subject, $email_content, $headers);
    
    if ($sent) {
        error_log('AI ChatBot: Email sent successfully to ' . $to_email);
        wp_send_json_success('История чата успешно отправлена');
    } else {
        error_log('AI ChatBot: Failed to send email to ' . $to_email);
        wp_send_json_error('Ошибка при отправке истории чата');
    }
}

add_action('wp_ajax_ai_chatbot_send_email', 'ai_chatbot_handle_email');
add_action('wp_ajax_nopriv_ai_chatbot_send_email', 'ai_chatbot_handle_email');
