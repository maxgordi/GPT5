<?php
/**
 * Страница настроек AI ChatBot в админ панели
 */

// Определение констант плагина если они не определены
if (!defined('AI_CHATBOT_PLUGIN_DIR')) {
    define('AI_CHATBOT_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}

// Проверка прав доступа
if (!current_user_can('manage_options')) {
    wp_die(__('У вас нет прав доступа к этой странице.'));
}

// Обработка формы
if (isset($_POST['submit'])) {
    check_admin_referer('ai_chatbot_settings');
    
    update_option('ai_chatbot_enabled', isset($_POST['ai_chatbot_enabled']) ? '1' : '0');
    update_option('ai_chatbot_openai_key', sanitize_text_field($_POST['ai_chatbot_openai_key']));
    update_option('ai_chatbot_openai_model', sanitize_text_field($_POST['ai_chatbot_openai_model']));
    update_option('ai_chatbot_welcome_message', sanitize_textarea_field($_POST['ai_chatbot_welcome_message']));
    update_option('ai_chatbot_system_prompt', sanitize_textarea_field($_POST['ai_chatbot_system_prompt']));
    update_option('ai_chatbot_bot_name', sanitize_text_field($_POST['ai_chatbot_bot_name']));
    update_option('ai_chatbot_avatar_url', esc_url_raw($_POST['ai_chatbot_avatar_url']));
    update_option('ai_chatbot_avatar_size', intval($_POST['ai_chatbot_avatar_size']));
    update_option('ai_chatbot_widget_size', intval($_POST['ai_chatbot_widget_size']));
    update_option('ai_chatbot_window_size', sanitize_text_field($_POST['ai_chatbot_window_size']));
    update_option('ai_chatbot_animation', sanitize_text_field($_POST['ai_chatbot_animation']));
    update_option('ai_chatbot_margin', intval($_POST['ai_chatbot_margin']));
    update_option('ai_chatbot_email_to', sanitize_email($_POST['ai_chatbot_email_to']));
    update_option('ai_chatbot_inactivity_timeout', intval($_POST['ai_chatbot_inactivity_timeout']));
    $color_scheme = sanitize_text_field($_POST['ai_chatbot_color_scheme']);
    update_option('ai_chatbot_color_scheme', $color_scheme);
    
    // Сохраняем цвета в зависимости от выбранной схемы
    // Всегда сохраняем цвета, независимо от схемы
    if ($color_scheme === 'custom') {
        $primary_color = sanitize_text_field($_POST['ai_chatbot_primary_color']);
        $secondary_color = sanitize_text_field($_POST['ai_chatbot_secondary_color']);
    } else {
        switch($color_scheme) {
            case 'default':
                $primary_color = '#667eea';
                $secondary_color = '#764ba2';
                break;
            case 'blue':
                $primary_color = '#2563eb';
                $secondary_color = '#1d4ed8';
                break;
            case 'green':
                $primary_color = '#059669';
                $secondary_color = '#047857';
                break;
            case 'purple':
                $primary_color = '#7c3aed';
                $secondary_color = '#5b21b6';
                break;
        }
    }
    update_option('ai_chatbot_primary_color', $primary_color);
    update_option('ai_chatbot_secondary_color', $secondary_color);
    update_option('ai_chatbot_bot_name_color', sanitize_text_field($_POST['ai_chatbot_bot_name_color']));
    update_option('ai_chatbot_font_family', sanitize_text_field($_POST['ai_chatbot_font_family']));
    update_option('ai_chatbot_font_size', intval($_POST['ai_chatbot_font_size']));
    update_option('ai_chatbot_language', sanitize_text_field($_POST['ai_chatbot_language']));
    update_option('ai_chatbot_custom_text', array(
        'placeholder' => sanitize_text_field($_POST['ai_chatbot_custom_text_placeholder']),
        'online_status' => sanitize_text_field($_POST['ai_chatbot_custom_text_online']),
        'offline_status' => sanitize_text_field($_POST['ai_chatbot_custom_text_offline']),
        'send_button' => sanitize_text_field($_POST['ai_chatbot_custom_text_send'])
    ));
    
    // Проверяем наличие класса генератора CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Генерируем и сохраняем CSS с передачей пользовательских цветов и margin
    $css_options = array(
        'primary_color' => $primary_color,
        'secondary_color' => $secondary_color,
        'margin' => get_option('ai_chatbot_margin', 20),
        'widget_size' => get_option('ai_chatbot_widget_size', 60),
        // Добавьте другие параметры, если нужно
    );
    $css_generator = new AI_ChatBot_CSS_Generator($css_options);
    $css_url = $css_generator->save();
    echo '<div class="notice notice-success"><p>Настройки сохранены! CSS файл обновлен.</p></div>';
}

// Получение текущих настроек
$enabled = get_option('ai_chatbot_enabled', '1');
$openai_key = get_option('ai_chatbot_openai_key', '');
$openai_model = get_option('ai_chatbot_openai_model', 'gpt-3.5-turbo');
$welcome_message = get_option('ai_chatbot_welcome_message', 'Привет! Я ваш AI-консультант. Чем могу помочь?');
$system_prompt = get_option('ai_chatbot_system_prompt', 'Ты helpful AI-ассистент, отвечающий на вопросы пользователей сайта.');
$bot_name = get_option('ai_chatbot_bot_name', 'AI Консультант');
$email_to = get_option('ai_chatbot_email_to', 'gordienko.office@gmail.com');
$inactivity_timeout = get_option('ai_chatbot_inactivity_timeout', 300000); // 5 минут по умолчанию
$avatar_url = get_option('ai_chatbot_avatar_url', AI_CHATBOT_PLUGIN_URL . 'assets/img/default-avatar.png');
$avatar_size = get_option('ai_chatbot_avatar_size', 40);
$widget_size = get_option('ai_chatbot_widget_size', 60);
$window_size = get_option('ai_chatbot_window_size', 'default');
$animation = get_option('ai_chatbot_animation', 'bounce');
$color_scheme = get_option('ai_chatbot_color_scheme', 'default');
$margin = get_option('ai_chatbot_margin', 20);
$primary_color = get_option('ai_chatbot_primary_color', '#667eea');
$secondary_color = get_option('ai_chatbot_secondary_color', '#764ba2');
$bot_name_color = get_option('ai_chatbot_bot_name_color', '#000000');
$font_family = get_option('ai_chatbot_font_family', 'system-default');
$font_size = get_option('ai_chatbot_font_size', 14);
$language = get_option('ai_chatbot_language', 'ru');
$custom_text = get_option('ai_chatbot_custom_text', array(
    'placeholder' => 'Напишите ваш вопрос...',
    'online_status' => 'В сети',
    'offline_status' => 'Не в сети',
    'send_button' => 'Отправить'
));
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-format-chat" style="font-size: 30px; margin-right: 10px; color: #667eea;"></span>
        Настройки AI ChatBot
    </h1>
    
    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- Основная форма настроек -->
        <div style="flex: 2;">
            <form method="post" action="">
                <?php wp_nonce_field('ai_chatbot_settings'); ?>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Основные настройки</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_enabled">Включить чат-бот</label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="ai_chatbot_enabled" 
                                           name="ai_chatbot_enabled" 
                                           value="1" 
                                           <?php checked($enabled, '1'); ?>>
                                    <p class="description">Показывать виджет чата на сайте</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_bot_name">Имя бота</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_bot_name" 
                                           name="ai_chatbot_bot_name" 
                                           value="<?php echo esc_attr($bot_name); ?>" 
                                           class="regular-text">
                                    <p class="description">Имя, которое будет отображаться в заголовке чата</p>
                                    <div style="margin-top: 10px;">
                                        <input type="color" 
                                               id="ai_chatbot_bot_name_color" 
                                               name="ai_chatbot_bot_name_color" 
                                               value="<?php echo esc_attr($bot_name_color); ?>">
                                        <label for="ai_chatbot_bot_name_color">Цвет имени бота</label>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_language">Язык интерфейса</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_language" name="ai_chatbot_language" class="regular-text">
                                        <option value="ru" <?php selected($language, 'ru'); ?>>Русский</option>
                                        <option value="en" <?php selected($language, 'en'); ?>>English</option>
                                        <option value="uk" <?php selected($language, 'uk'); ?>>Українська</option>
                                    </select>
                                    <p class="description">Язык интерфейса чат-бота</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Настройки внешнего вида</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_animation">Анимация виджета</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_animation" name="ai_chatbot_animation" class="regular-text">
                                        <option value="bounce" <?php selected($animation, 'bounce'); ?>>Подпрыгивание</option>
                                        <option value="pulse" <?php selected($animation, 'pulse'); ?>>Пульсация</option>
                                        <option value="shake" <?php selected($animation, 'shake'); ?>>Покачивание</option>
                                        <option value="none" <?php selected($animation, 'none'); ?>>Без анимации</option>
                                    </select>
                                    <p class="description">Анимация иконки чат-бота</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_widget_size">Размер виджета (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_widget_size" 
                                           name="ai_chatbot_widget_size" 
                                           value="<?php echo esc_attr($widget_size); ?>" 
                                           min="40" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">Размер круглой иконки чат-бота (по умолчанию: 60px)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_window_size">Размер окна чата</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_window_size" name="ai_chatbot_window_size" class="regular-text">
                                        <option value="small" <?php selected($window_size, 'small'); ?>>Маленький</option>
                                        <option value="default" <?php selected($window_size, 'default'); ?>>Средний</option>
                                        <option value="large" <?php selected($window_size, 'large'); ?>>Большой</option>
                                    </select>
                                    <p class="description">Размер окна чата при открытии</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_color_scheme">Цветовая схема</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_color_scheme" name="ai_chatbot_color_scheme" class="regular-text">
                                        <option value="default" <?php selected($color_scheme, 'default'); ?>>По умолчанию</option>
                                        <option value="blue" <?php selected($color_scheme, 'blue'); ?>>Синяя</option>
                                        <option value="green" <?php selected($color_scheme, 'green'); ?>>Зеленая</option>
                                        <option value="purple" <?php selected($color_scheme, 'purple'); ?>>Фиолетовая</option>
                                        <option value="custom" <?php selected($color_scheme, 'custom'); ?>>Пользовательская</option>
                                    </select>
                                    <div id="custom-colors" style="margin-top: 10px; display: <?php echo $color_scheme === 'custom' ? 'block' : 'none'; ?>">
                                        <input type="color" 
                                               id="ai_chatbot_primary_color" 
                                               name="ai_chatbot_primary_color" 
                                               value="<?php echo esc_attr($primary_color); ?>">
                                        <label for="ai_chatbot_primary_color">Основной цвет</label>
                                        <input type="color" 
                                               id="ai_chatbot_secondary_color" 
                                               name="ai_chatbot_secondary_color" 
                                               value="<?php echo esc_attr($secondary_color); ?>">
                                        <label for="ai_chatbot_secondary_color">Дополнительный цвет</label>
                                    </div>
                                    <p class="description">Выберите цветовую схему чат-бота</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_family">Шрифт</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_font_family" name="ai_chatbot_font_family" class="regular-text">
                                        <option value="system-default" <?php selected($font_family, 'system-default'); ?>>Системный</option>
                                        <option value="roboto" <?php selected($font_family, 'roboto'); ?>>Roboto</option>
                                        <option value="open-sans" <?php selected($font_family, 'open-sans'); ?>>Open Sans</option>
                                        <option value="lato" <?php selected($font_family, 'lato'); ?>>Lato</option>
                                    </select>
                                    <p class="description">Шрифт для текста в чате</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_size">Размер шрифта (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_font_size" 
                                           name="ai_chatbot_font_size" 
                                           value="<?php echo esc_attr($font_size); ?>" 
                                           min="12" 
                                           max="20" 
                                           class="small-text">
                                    <p class="description">Размер шрифта для текста в чате (по умолчанию: 14px)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_margin">Отступ от края (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_margin" 
                                           name="ai_chatbot_margin" 
                                           value="<?php echo esc_attr($margin); ?>" 
                                           min="0" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">Отступ виджета от края экрана (по умолчанию: 20px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Настройки аватара</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_url">Аватар бота</label>
                                </th>
                                <td>
                                    <input type="url" 
                                           id="ai_chatbot_avatar_url" 
                                           name="ai_chatbot_avatar_url" 
                                           value="<?php echo esc_url($avatar_url); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="upload-avatar">Загрузить изображение</button>
                                    <p class="description">URL изображения для аватара бота</p>
                                    <div id="avatar-preview" style="margin-top: 10px;">
                                        <img src="<?php echo esc_url($avatar_url); ?>" 
                                             style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #ddd;">
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_size">Размер аватара (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_avatar_size" 
                                           name="ai_chatbot_avatar_size" 
                                           value="<?php echo esc_attr($avatar_size); ?>" 
                                           min="30" 
                                           max="80" 
                                           class="small-text">
                                    <p class="description">Размер аватара в заголовке чата (по умолчанию: 40px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Настройки уведомлений и таймаутов</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_email_to">Email для уведомлений</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="ai_chatbot_email_to" 
                                           name="ai_chatbot_email_to" 
                                           value="<?php echo esc_attr($email_to); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="test-email">Тест email</button>
                                    <p class="description">Email для получения уведомлений о новых сообщениях в чате</p>
                                    <div id="email-test-result"></div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_inactivity_timeout">Таймаут неактивности (мс)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_inactivity_timeout" 
                                           name="ai_chatbot_inactivity_timeout" 
                                           value="<?php echo esc_attr($inactivity_timeout); ?>" 
                                           min="60000" 
                                           step="60000" 
                                           class="regular-text">
                                    <p class="description">Время в миллисекундах, после которого неактивный чат будет закрыт (минимум 60000 мс = 1 минута)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Настройки OpenAI</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_key">API Ключ OpenAI</label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="ai_chatbot_openai_key" 
                                           name="ai_chatbot_openai_key" 
                                           value="<?php echo esc_attr($openai_key); ?>" 
                                           class="regular-text" 
                                           placeholder="sk-...">
                                    <button type="button" class="button" id="toggle-api-key">Показать</button>
                                    <p class="description">
                                        Получите API ключ на <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                        <br><strong>Важно:</strong> Храните ключ в безопасности!
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_model">Модель OpenAI</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_openai_model" 
                                           name="ai_chatbot_openai_model" 
                                           value="<?php echo esc_attr($openai_model); ?>" 
                                           class="regular-text"
                                           placeholder="gpt-3.5-turbo">
                                    <p class="description">Введите название модели OpenAI согласно <a href="https://platform.openai.com/docs/models" target="_blank">документации</a></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Сообщения и промт</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_welcome_message">Приветственное сообщение</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_welcome_message" 
                                              name="ai_chatbot_welcome_message" 
                                              rows="3" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($welcome_message); ?></textarea>
                                    <p class="description">Первое сообщение, которое увидит пользователь</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_system_prompt">Системный промт</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_system_prompt" 
                                              name="ai_chatbot_system_prompt" 
                                              rows="6" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                                    <p class="description">
                                        Инструкции для AI о том, как себя вести и отвечать на вопросы.<br>
                                        <strong>Примеры промтов:</strong><br>
                                        • "Ты консультант интернет-магазина. Помогай покупателям с выбором товаров."<br>
                                        • "Ты техническая поддержка сайта. Отвечай на вопросы пользователей вежливо и профессионально."
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>Настройка текстов интерфейса</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_placeholder">Подсказка в поле ввода</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_placeholder" 
                                           name="ai_chatbot_custom_text_placeholder" 
                                           value="<?php echo esc_attr($custom_text['placeholder']); ?>" 
                                           class="regular-text">
                                    <p class="description">Текст-подсказка в поле ввода сообщения</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_online">Статус "В сети"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_online" 
                                           name="ai_chatbot_custom_text_online" 
                                           value="<?php echo esc_attr($custom_text['online_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">Текст статуса, когда бот доступен</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_offline">Статус "Не в сети"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_offline" 
                                           name="ai_chatbot_custom_text_offline" 
                                           value="<?php echo esc_attr($custom_text['offline_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">Текст статуса, когда бот недоступен</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_send">Текст кнопки отправки</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_send" 
                                           name="ai_chatbot_custom_text_send" 
                                           value="<?php echo esc_attr($custom_text['send_button']); ?>" 
                                           class="regular-text">
                                    <p class="description">Текст на кнопке отправки сообщения</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('Сохранить настройки', 'primary', 'submit', false); ?>
                <button type="button" class="button" id="test-connection" style="margin-left: 10px;">Тестировать подключение</button>
                <button type="button" class="button button-secondary" id="clear-cache" style="margin-left: 10px;">Очистить кеш</button>
            </form>
        </div>

        <!-- Боковая панель с информацией -->
        <div style="flex: 1;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Информация</h2>
                </div>
                <div class="inside">
                    <h4>🚀 Возможности плагина:</h4>
                    <ul>
                        <li>✅ Интеграция с OpenAI GPT</li>
                        <li>✅ Современный адаптивный дизайн</li>
                        <li>✅ Настройка промтов</li>
                        <li>✅ Кастомный аватар</li>
                        <li>✅ Уведомления о новых сообщениях</li>
                    </ul>
                    
                    <h4>📋 Статус:</h4>
                    <p id="connection-status">
                        <?php if (empty($openai_key)): ?>
                            <span style="color: #dc3232;">❌ API ключ не настроен</span>
                        <?php else: ?>
                            <span style="color: #46b450;">✅ API ключ настроен</span>
                        <?php endif; ?>
                    </p>
                    
                    <h4>💡 Советы:</h4>
                    <ul style="font-size: 12px;">
                        <li>Используйте понятные промты для лучших ответов</li>
                        <li>Тестируйте различные формулировки</li>
                        <li>Следите за использованием API (тарификация)</li>
                    </ul>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2>Предпросмотр</h2>
                </div>
                <div class="inside">
                    <div class="preview-container">
                        <div class="ai-chatbot-toggle">
                            <span style="color: white; font-size: 20px;">💬</span>
                        </div>
                        <p class="preview-name" style="margin: 10px 0 0; text-align: center; font-weight: bold;">
                            <?php echo esc_html($bot_name); ?>
                        </p>
                        <p class="preview-status" style="margin: 5px 0 0; text-align: center; font-size: 12px; color: #666;">
                            <?php echo esc_html($custom_text['online_status']); ?>
                        </p>
                        
                        <div class="chat-preview">
                            <div class="message bot">
                                <div class="sender"><?php echo esc_html($bot_name); ?></div>
                                <div class="content"><?php echo esc_html($welcome_message); ?></div>
                            </div>
                            <div class="message user">
                                <div class="content">Спасибо за помощь!</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.postbox {
    margin-bottom: 20px;
}
.postbox-header h2 {
    font-size: 14px;
    padding: 8px 12px;
    margin: 0;
    line-height: 1.4;
}
#avatar-preview img {
    transition: all 0.3s ease;
}
#avatar-preview img:hover {
    transform: scale(1.1);
}

/* Стили для превью чата */
.chat-preview {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 15px 0;
    padding: 15px;
    width: 100%;
    box-sizing: border-box;
}

.chat-preview .message {
    margin: 10px 0;
    padding: 10px;
    border-radius: 8px;
    max-width: 80%;
}

.chat-preview .message.bot {
    margin-right: auto;
}

.chat-preview .message.user {
    margin-left: auto;
}

.chat-preview .sender {
    font-weight: bold;
    margin-bottom: 5px;
}

.ai-chatbot-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.ai-chatbot-toggle:hover {
    transform: scale(1.05);
}
</style>

<script>
jQuery(document).ready(function($) {
    // Проверяем, показывался ли уже статус
    if (!sessionStorage.getItem('statusShown')) {
        $('.ai-chatbot-online-status').addClass('show');
        sessionStorage.setItem('statusShown', 'true');
    }
    
    // Загрузка медиа файлов
    $('#upload-avatar').click(function(e) {
        e.preventDefault();
        var mediaUploader = wp.media({
            title: 'Выберите изображение для аватара',
            button: {
                text: 'Выбрать изображение'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#ai_chatbot_avatar_url').val(attachment.url);
            $('#avatar-preview img').attr('src', attachment.url);
        });

        mediaUploader.open();
    });

    // Показать/скрыть API ключ
    $('#toggle-api-key').click(function() {
        var $input = $('#ai_chatbot_openai_key');
        var $button = $(this);
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $button.text('Скрыть');
        } else {
            $input.attr('type', 'password');
            $button.text('Показать');
        }
    });

    // Обработчик очистки кеша
    $('#clear-cache').click(function() {
        var $button = $(this);
        $button.text('Очистка...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_clear_cache',
                nonce: '<?php echo wp_create_nonce("ai_chatbot_clear_cache"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Кеш успешно очищен! Обновите страницу сайта, чтобы увидеть изменения.');
                } else {
                    alert('❌ Ошибка при очистке кеша: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Ошибка при очистке кеша: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('Очистить кеш').prop('disabled', false);
            }
        });
    });

    // Тест подключения
    $('#test-connection').click(function() {
        var $button = $(this);
        var apiKey = $('#ai_chatbot_openai_key').val();
        
        if (!apiKey) {
            alert('Введите API ключ для тестирования');
            return;
        }
        
        $button.text('Тестирование...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_openai_connection',
                api_key: apiKey,
                nonce: '<?php echo wp_create_nonce("test_openai_connection"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Подключение успешно! API работает корректно.');
                    $('#connection-status').html('<span style="color: #46b450;">✅ Подключение протестировано</span>');
                } else {
                    alert('❌ Ошибка при тестировании: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Ошибка при тестировании подключения: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('Тестировать подключение').prop('disabled', false);
            }
        });
    });

    // Тест email
    $('#test-email').click(function() {
        var $button = $(this);
        var email = $('#ai_chatbot_email_to').val();
        
        if (!email) {
            alert('Введите email для тестирования');
            return;
        }
        
        $button.text('Отправка...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_test_email',
                email: email,
                nonce: '<?php echo wp_create_nonce("ai_chatbot_test_email"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#email-test-result').html('<div style="color: #46b450; margin-top: 10px;">✅ Тестовое письмо отправлено!</div>');
                } else {
                    $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">❌ Ошибка при отправке: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">❌ Ошибка: ' + error + '</div>');
            },
            complete: function() {
                $button.text('Тест email').prop('disabled', false);
            }
        });
    });

    // Обновление превью при изменении настроек
    function updatePreview() {
        const margin = $('#ai_chatbot_margin').val();
        const colorScheme = $('#ai_chatbot_color_scheme').val();
        let primaryColor, secondaryColor;
        
        // Обновляем отступы
        $('.preview-container').css('margin-right', margin + 'px');
        $('.ai-chatbot-toggle').css('margin-right', margin + 'px');
        
        // Определяем цвета
        if (colorScheme === 'custom') {
            primaryColor = $('#ai_chatbot_primary_color').val();
            secondaryColor = $('#ai_chatbot_secondary_color').val();
        } else {
            const colors = {
                'default': ['#667eea', '#764ba2'],
                'blue': ['#2563eb', '#1d4ed8'],
                'green': ['#059669', '#047857'],
                'purple': ['#7c3aed', '#5b21b6']
            };
            [primaryColor, secondaryColor] = colors[colorScheme] || colors['default'];
        }

        // Показываем/скрываем выбор пользовательских цветов
        $('#custom-colors').toggle(colorScheme === 'custom');
        
        // Обновляем значения полей цвета
        if (colorScheme !== 'custom') {
            $('#ai_chatbot_primary_color').val(primaryColor);
            $('#ai_chatbot_secondary_color').val(secondaryColor);
        }
        
        const botNameColor = $('#ai_chatbot_bot_name_color').val();
        
        // Применяем цвета
        $('.ai-chatbot-toggle').css('background', `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)`);
        $('.chat-preview .message.bot').css({
            'background-color': primaryColor,
            'color': '#ffffff'
        });
        $('.chat-preview .message.user').css({
            'background-color': secondaryColor,
            'color': '#ffffff'
        });
        $('.chat-preview .message.bot .sender').css('color', botNameColor);
        
        // Обновляем остальные элементы
        const name = $('#ai_chatbot_bot_name').val();
        const message = $('#ai_chatbot_welcome_message').val();
        const avatarSize = $('#ai_chatbot_avatar_size').val();
        const fontSize = $('#ai_chatbot_font_size').val();
        const fontFamily = $('#ai_chatbot_font_family').val();
        const status = $('#ai_chatbot_custom_text_online').val();
        
        // Обновляем размер аватара
        $('#avatar-preview img').css({
            'width': avatarSize + 'px',
            'height': avatarSize + 'px'
        });
        
        // Обновляем шрифт
        const fonts = {
            'system-default': '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif',
            'roboto': 'Roboto, sans-serif',
            'open-sans': '"Open Sans", sans-serif',
            'lato': 'Lato, sans-serif'
        };
        
        $('.preview-container').css({
            'font-family': fonts[fontFamily] || fonts['system-default'],
            'font-size': fontSize + 'px'
        });
        
        // Обновляем тексты
        $('.preview-name').css('color', botNameColor).text(name);
        $('.preview-status').text(status);
        $('.chat-preview .message.bot .content').text(message);
    }

    // Обработчики для обновления превью
    $('#ai_chatbot_bot_name, #ai_chatbot_welcome_message, #ai_chatbot_avatar_size, #ai_chatbot_font_size, #ai_chatbot_color_scheme, #ai_chatbot_font_family, #ai_chatbot_custom_text_online, #ai_chatbot_margin').on('input change', updatePreview);
    
    $('#ai_chatbot_primary_color, #ai_chatbot_secondary_color, #ai_chatbot_bot_name_color').on('input', updatePreview);
    
    // Инициализация превью
    updatePreview();
});
</script>

<?php
// Добавляем обработчики AJAX
add_action('wp_ajax_test_openai_connection', 'ai_chatbot_test_connection');
add_action('wp_ajax_ai_chatbot_clear_cache', 'ai_chatbot_clear_cache');
add_action('wp_ajax_ai_chatbot_test_email', 'ai_chatbot_test_email');

function ai_chatbot_test_email() {
    check_ajax_referer('ai_chatbot_test_email', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $email = sanitize_email($_POST['email']);
    if (!is_email($email)) {
        wp_send_json_error('Неверный формат email');
        return;
    }
    
    $subject = 'Тестовое письмо AI ChatBot';
    $message = "Это тестовое письмо от плагина AI ChatBot.\n\n";
    $message .= "Если вы получили это письмо, значит настройки email работают корректно.\n\n";
    $message .= "Дата и время отправки: " . current_time('mysql') . "\n";
    $message .= "Сайт: " . get_bloginfo('name') . " (" . get_site_url() . ")";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success('Тестовое письмо отправлено');
    } else {
        wp_send_json_error('Ошибка при отправке тестового письма');
    }
}

function ai_chatbot_test_connection() {
    check_ajax_referer('test_openai_connection', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    // Тестируем подключение к API
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
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['error'])) {
        wp_send_json_error($body['error']['message']);
        return;
    }
    
    wp_send_json_success('Connection successful');
}

function ai_chatbot_clear_cache() {
    check_ajax_referer('ai_chatbot_clear_cache', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    // Очищаем кеш WordPress
    wp_cache_flush();
    
    // Очищаем кеш опций
    delete_transient('ai_chatbot_settings');
    
    // Проверяем наличие класса генератора CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Генерируем и сохраняем новый CSS
    $css_generator = new AI_ChatBot_CSS_Generator();
    $css_url = $css_generator->save();
    
    // Очищаем OPcache если он включен
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    wp_send_json_success('Cache cleared successfully');
}