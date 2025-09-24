<?php
/**
 * РЎС‚СЂР°РЅРёС†Р° РЅР°СЃС‚СЂРѕРµРє AI ChatBot РІ Р°РґРјРёРЅ РїР°РЅРµР»Рё
 */

// РћРїСЂРµРґРµР»РµРЅРёРµ РєРѕРЅСЃС‚Р°РЅС‚ РїР»Р°РіРёРЅР° РµСЃР»Рё РѕРЅРё РЅРµ РѕРїСЂРµРґРµР»РµРЅС‹
if (!defined('AI_CHATBOT_PLUGIN_DIR')) {
    define('AI_CHATBOT_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}

// РџСЂРѕРІРµСЂРєР° РїСЂР°РІ РґРѕСЃС‚СѓРїР°
if (!current_user_can('manage_options')) {
    wp_die(__('РЈ РІР°СЃ РЅРµС‚ РїСЂР°РІ РґРѕСЃС‚СѓРїР° Рє СЌС‚РѕР№ СЃС‚СЂР°РЅРёС†Рµ.'));
}

// РћР±СЂР°Р±РѕС‚РєР° С„РѕСЂРјС‹
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
    $inactivity_timeout = intval($_POST['ai_chatbot_inactivity_timeout']);
    if ($inactivity_timeout < 60000) {
        $inactivity_timeout = 300000; // fall back to default 5 minutes
    }
    update_option('ai_chatbot_inactivity_timeout', $inactivity_timeout);
    $color_scheme = sanitize_text_field($_POST['ai_chatbot_color_scheme']);
    update_option('ai_chatbot_color_scheme', $color_scheme);
    
    // РЎРѕС…СЂР°РЅСЏРµРј С†РІРµС‚Р° РІ Р·Р°РІРёСЃРёРјРѕСЃС‚Рё РѕС‚ РІС‹Р±СЂР°РЅРЅРѕР№ СЃС…РµРјС‹
    // Р’СЃРµРіРґР° СЃРѕС…СЂР°РЅСЏРµРј С†РІРµС‚Р°, РЅРµР·Р°РІРёСЃРёРјРѕ РѕС‚ СЃС…РµРјС‹
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
    
    // РџСЂРѕРІРµСЂСЏРµРј РЅР°Р»РёС‡РёРµ РєР»Р°СЃСЃР° РіРµРЅРµСЂР°С‚РѕСЂР° CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Р“РµРЅРµСЂРёСЂСѓРµРј Рё СЃРѕС…СЂР°РЅСЏРµРј CSS СЃ РїРµСЂРµРґР°С‡РµР№ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєРёС… С†РІРµС‚РѕРІ Рё margin
    $css_options = array(
        'primary_color' => $primary_color,
        'secondary_color' => $secondary_color,
        'margin' => get_option('ai_chatbot_margin', 20),
        'widget_size' => get_option('ai_chatbot_widget_size', 60),
        // Р”РѕР±Р°РІСЊС‚Рµ РґСЂСѓРіРёРµ РїР°СЂР°РјРµС‚СЂС‹, РµСЃР»Рё РЅСѓР¶РЅРѕ
    );
    $css_generator = new AI_ChatBot_CSS_Generator($css_options);
    $css_url = $css_generator->save();
    echo '<div class="notice notice-success"><p>РќР°СЃС‚СЂРѕР№РєРё СЃРѕС…СЂР°РЅРµРЅС‹! CSS С„Р°Р№Р» РѕР±РЅРѕРІР»РµРЅ.</p></div>';
}

// РџРѕР»СѓС‡РµРЅРёРµ С‚РµРєСѓС‰РёС… РЅР°СЃС‚СЂРѕРµРє
$enabled = get_option('ai_chatbot_enabled', '1');
$openai_key = get_option('ai_chatbot_openai_key', '');
$openai_model = get_option('ai_chatbot_openai_model', 'gpt-3.5-turbo');
$welcome_message = get_option('ai_chatbot_welcome_message', 'РџСЂРёРІРµС‚! РЇ РІР°С€ AI-РєРѕРЅСЃСѓР»СЊС‚Р°РЅС‚. Р§РµРј РјРѕРіСѓ РїРѕРјРѕС‡СЊ?');
$system_prompt = get_option('ai_chatbot_system_prompt', 'РўС‹ helpful AI-Р°СЃСЃРёСЃС‚РµРЅС‚, РѕС‚РІРµС‡Р°СЋС‰РёР№ РЅР° РІРѕРїСЂРѕСЃС‹ РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ СЃР°Р№С‚Р°.');
$bot_name = get_option('ai_chatbot_bot_name', 'AI РљРѕРЅСЃСѓР»СЊС‚Р°РЅС‚');
$email_to = get_option('ai_chatbot_email_to', 'gordienko.office@gmail.com');
$inactivity_timeout = get_option('ai_chatbot_inactivity_timeout', 300000); // 5 РјРёРЅСѓС‚ РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ
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
    'placeholder' => 'РќР°РїРёС€РёС‚Рµ РІР°С€ РІРѕРїСЂРѕСЃ...',
    'online_status' => 'Р’ СЃРµС‚Рё',
    'offline_status' => 'РќРµ РІ СЃРµС‚Рё',
    'send_button' => 'РћС‚РїСЂР°РІРёС‚СЊ'
));
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-format-chat" style="font-size: 30px; margin-right: 10px; color: #667eea;"></span>
        РќР°СЃС‚СЂРѕР№РєРё AI ChatBot
    </h1>
    
    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- РћСЃРЅРѕРІРЅР°СЏ С„РѕСЂРјР° РЅР°СЃС‚СЂРѕРµРє -->
        <div style="flex: 2;">
            <form method="post" action="">
                <?php wp_nonce_field('ai_chatbot_settings'); ?>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РћСЃРЅРѕРІРЅС‹Рµ РЅР°СЃС‚СЂРѕР№РєРё</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_enabled">Р’РєР»СЋС‡РёС‚СЊ С‡Р°С‚-Р±РѕС‚</label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="ai_chatbot_enabled" 
                                           name="ai_chatbot_enabled" 
                                           value="1" 
                                           <?php checked($enabled, '1'); ?>>
                                    <p class="description">РџРѕРєР°Р·С‹РІР°С‚СЊ РІРёРґР¶РµС‚ С‡Р°С‚Р° РЅР° СЃР°Р№С‚Рµ</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_bot_name">РРјСЏ Р±РѕС‚Р°</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_bot_name" 
                                           name="ai_chatbot_bot_name" 
                                           value="<?php echo esc_attr($bot_name); ?>" 
                                           class="regular-text">
                                    <p class="description">РРјСЏ, РєРѕС‚РѕСЂРѕРµ Р±СѓРґРµС‚ РѕС‚РѕР±СЂР°Р¶Р°С‚СЊСЃСЏ РІ Р·Р°РіРѕР»РѕРІРєРµ С‡Р°С‚Р°</p>
                                    <div style="margin-top: 10px;">
                                        <input type="color" 
                                               id="ai_chatbot_bot_name_color" 
                                               name="ai_chatbot_bot_name_color" 
                                               value="<?php echo esc_attr($bot_name_color); ?>">
                                        <label for="ai_chatbot_bot_name_color">Р¦РІРµС‚ РёРјРµРЅРё Р±РѕС‚Р°</label>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_language">РЇР·С‹Рє РёРЅС‚РµСЂС„РµР№СЃР°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_language" name="ai_chatbot_language" class="regular-text">
                                        <option value="ru" <?php selected($language, 'ru'); ?>>Р СѓСЃСЃРєРёР№</option>
                                        <option value="en" <?php selected($language, 'en'); ?>>English</option>
                                        <option value="uk" <?php selected($language, 'uk'); ?>>РЈРєСЂР°С—РЅСЃСЊРєР°</option>
                                    </select>
                                    <p class="description">РЇР·С‹Рє РёРЅС‚РµСЂС„РµР№СЃР° С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё РІРЅРµС€РЅРµРіРѕ РІРёРґР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_animation">РђРЅРёРјР°С†РёСЏ РІРёРґР¶РµС‚Р°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_animation" name="ai_chatbot_animation" class="regular-text">
                                        <option value="bounce" <?php selected($animation, 'bounce'); ?>>РџРѕРґРїСЂС‹РіРёРІР°РЅРёРµ</option>
                                        <option value="pulse" <?php selected($animation, 'pulse'); ?>>РџСѓР»СЊСЃР°С†РёСЏ</option>
                                        <option value="shake" <?php selected($animation, 'shake'); ?>>РџРѕРєР°С‡РёРІР°РЅРёРµ</option>
                                        <option value="none" <?php selected($animation, 'none'); ?>>Р‘РµР· Р°РЅРёРјР°С†РёРё</option>
                                    </select>
                                    <p class="description">РђРЅРёРјР°С†РёСЏ РёРєРѕРЅРєРё С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_widget_size">Р Р°Р·РјРµСЂ РІРёРґР¶РµС‚Р° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_widget_size" 
                                           name="ai_chatbot_widget_size" 
                                           value="<?php echo esc_attr($widget_size); ?>" 
                                           min="40" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ РєСЂСѓРіР»РѕР№ РёРєРѕРЅРєРё С‡Р°С‚-Р±РѕС‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 60px)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_window_size">Р Р°Р·РјРµСЂ РѕРєРЅР° С‡Р°С‚Р°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_window_size" name="ai_chatbot_window_size" class="regular-text">
                                        <option value="small" <?php selected($window_size, 'small'); ?>>РњР°Р»РµРЅСЊРєРёР№</option>
                                        <option value="default" <?php selected($window_size, 'default'); ?>>РЎСЂРµРґРЅРёР№</option>
                                        <option value="large" <?php selected($window_size, 'large'); ?>>Р‘РѕР»СЊС€РѕР№</option>
                                    </select>
                                    <p class="description">Р Р°Р·РјРµСЂ РѕРєРЅР° С‡Р°С‚Р° РїСЂРё РѕС‚РєСЂС‹С‚РёРё</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_color_scheme">Р¦РІРµС‚РѕРІР°СЏ СЃС…РµРјР°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_color_scheme" name="ai_chatbot_color_scheme" class="regular-text">
                                        <option value="default" <?php selected($color_scheme, 'default'); ?>>РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</option>
                                        <option value="blue" <?php selected($color_scheme, 'blue'); ?>>РЎРёРЅСЏСЏ</option>
                                        <option value="green" <?php selected($color_scheme, 'green'); ?>>Р—РµР»РµРЅР°СЏ</option>
                                        <option value="purple" <?php selected($color_scheme, 'purple'); ?>>Р¤РёРѕР»РµС‚РѕРІР°СЏ</option>
                                        <option value="custom" <?php selected($color_scheme, 'custom'); ?>>РџРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєР°СЏ</option>
                                    </select>
                                    <div id="custom-colors" style="margin-top: 10px; display: <?php echo $color_scheme === 'custom' ? 'block' : 'none'; ?>">
                                        <input type="color" 
                                               id="ai_chatbot_primary_color" 
                                               name="ai_chatbot_primary_color" 
                                               value="<?php echo esc_attr($primary_color); ?>">
                                        <label for="ai_chatbot_primary_color">РћСЃРЅРѕРІРЅРѕР№ С†РІРµС‚</label>
                                        <input type="color" 
                                               id="ai_chatbot_secondary_color" 
                                               name="ai_chatbot_secondary_color" 
                                               value="<?php echo esc_attr($secondary_color); ?>">
                                        <label for="ai_chatbot_secondary_color">Р”РѕРїРѕР»РЅРёС‚РµР»СЊРЅС‹Р№ С†РІРµС‚</label>
                                    </div>
                                    <p class="description">Р’С‹Р±РµСЂРёС‚Рµ С†РІРµС‚РѕРІСѓСЋ СЃС…РµРјСѓ С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_family">РЁСЂРёС„С‚</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_font_family" name="ai_chatbot_font_family" class="regular-text">
                                        <option value="system-default" <?php selected($font_family, 'system-default'); ?>>РЎРёСЃС‚РµРјРЅС‹Р№</option>
                                        <option value="roboto" <?php selected($font_family, 'roboto'); ?>>Roboto</option>
                                        <option value="open-sans" <?php selected($font_family, 'open-sans'); ?>>Open Sans</option>
                                        <option value="lato" <?php selected($font_family, 'lato'); ?>>Lato</option>
                                    </select>
                                    <p class="description">РЁСЂРёС„С‚ РґР»СЏ С‚РµРєСЃС‚Р° РІ С‡Р°С‚Рµ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_size">Р Р°Р·РјРµСЂ С€СЂРёС„С‚Р° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_font_size" 
                                           name="ai_chatbot_font_size" 
                                           value="<?php echo esc_attr($font_size); ?>" 
                                           min="12" 
                                           max="20" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ С€СЂРёС„С‚Р° РґР»СЏ С‚РµРєСЃС‚Р° РІ С‡Р°С‚Рµ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 14px)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_margin">РћС‚СЃС‚СѓРї РѕС‚ РєСЂР°СЏ (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_margin" 
                                           name="ai_chatbot_margin" 
                                           value="<?php echo esc_attr($margin); ?>" 
                                           min="0" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">РћС‚СЃС‚СѓРї РІРёРґР¶РµС‚Р° РѕС‚ РєСЂР°СЏ СЌРєСЂР°РЅР° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 20px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё Р°РІР°С‚Р°СЂР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_url">РђРІР°С‚Р°СЂ Р±РѕС‚Р°</label>
                                </th>
                                <td>
                                    <input type="url" 
                                           id="ai_chatbot_avatar_url" 
                                           name="ai_chatbot_avatar_url" 
                                           value="<?php echo esc_url($avatar_url); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="upload-avatar">Р—Р°РіСЂСѓР·РёС‚СЊ РёР·РѕР±СЂР°Р¶РµРЅРёРµ</button>
                                    <p class="description">URL РёР·РѕР±СЂР°Р¶РµРЅРёСЏ РґР»СЏ Р°РІР°С‚Р°СЂР° Р±РѕС‚Р°</p>
                                    <div id="avatar-preview" style="margin-top: 10px;">
                                        <img src="<?php echo esc_url($avatar_url); ?>" 
                                             style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #ddd;">
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_size">Р Р°Р·РјРµСЂ Р°РІР°С‚Р°СЂР° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_avatar_size" 
                                           name="ai_chatbot_avatar_size" 
                                           value="<?php echo esc_attr($avatar_size); ?>" 
                                           min="30" 
                                           max="80" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ Р°РІР°С‚Р°СЂР° РІ Р·Р°РіРѕР»РѕРІРєРµ С‡Р°С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 40px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё СѓРІРµРґРѕРјР»РµРЅРёР№ Рё С‚Р°Р№РјР°СѓС‚РѕРІ</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_email_to">Email РґР»СЏ СѓРІРµРґРѕРјР»РµРЅРёР№</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="ai_chatbot_email_to" 
                                           name="ai_chatbot_email_to" 
                                           value="<?php echo esc_attr($email_to); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="test-email">РўРµСЃС‚ email</button>
                                    <p class="description">Email РґР»СЏ РїРѕР»СѓС‡РµРЅРёСЏ СѓРІРµРґРѕРјР»РµРЅРёР№ Рѕ РЅРѕРІС‹С… СЃРѕРѕР±С‰РµРЅРёСЏС… РІ С‡Р°С‚Рµ</p>
                                    <div id="email-test-result"></div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_inactivity_timeout">РўР°Р№РјР°СѓС‚ РЅРµР°РєС‚РёРІРЅРѕСЃС‚Рё (РјСЃ)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_inactivity_timeout" 
                                           name="ai_chatbot_inactivity_timeout" 
                                           value="<?php echo esc_attr($inactivity_timeout); ?>" 
                                           min="60000" 
                                           step="60000" 
                                           class="regular-text">
                                    <p class="description">Р’СЂРµРјСЏ РІ РјРёР»Р»РёСЃРµРєСѓРЅРґР°С…, РїРѕСЃР»Рµ РєРѕС‚РѕСЂРѕРіРѕ РЅРµР°РєС‚РёРІРЅС‹Р№ С‡Р°С‚ Р±СѓРґРµС‚ Р·Р°РєСЂС‹С‚ (РјРёРЅРёРјСѓРј 60000 РјСЃ = 1 РјРёРЅСѓС‚Р°)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё OpenAI</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_key">API РљР»СЋС‡ OpenAI</label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="ai_chatbot_openai_key" 
                                           name="ai_chatbot_openai_key" 
                                           value="<?php echo esc_attr($openai_key); ?>" 
                                           class="regular-text" 
                                           placeholder="sk-...">
                                    <button type="button" class="button" id="toggle-api-key">РџРѕРєР°Р·Р°С‚СЊ</button>
                                    <p class="description">
                                        РџРѕР»СѓС‡РёС‚Рµ API РєР»СЋС‡ РЅР° <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                        <br><strong>Р’Р°Р¶РЅРѕ:</strong> РҐСЂР°РЅРёС‚Рµ РєР»СЋС‡ РІ Р±РµР·РѕРїР°СЃРЅРѕСЃС‚Рё!
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_model">РњРѕРґРµР»СЊ OpenAI</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_openai_model" 
                                           name="ai_chatbot_openai_model" 
                                           value="<?php echo esc_attr($openai_model); ?>" 
                                           class="regular-text"
                                           placeholder="gpt-3.5-turbo">
                                    <p class="description">Р’РІРµРґРёС‚Рµ РЅР°Р·РІР°РЅРёРµ РјРѕРґРµР»Рё OpenAI СЃРѕРіР»Р°СЃРЅРѕ <a href="https://platform.openai.com/docs/models" target="_blank">РґРѕРєСѓРјРµРЅС‚Р°С†РёРё</a></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РЎРѕРѕР±С‰РµРЅРёСЏ Рё РїСЂРѕРјС‚</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_welcome_message">РџСЂРёРІРµС‚СЃС‚РІРµРЅРЅРѕРµ СЃРѕРѕР±С‰РµРЅРёРµ</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_welcome_message" 
                                              name="ai_chatbot_welcome_message" 
                                              rows="3" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($welcome_message); ?></textarea>
                                    <p class="description">РџРµСЂРІРѕРµ СЃРѕРѕР±С‰РµРЅРёРµ, РєРѕС‚РѕСЂРѕРµ СѓРІРёРґРёС‚ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊ</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_system_prompt">РЎРёСЃС‚РµРјРЅС‹Р№ РїСЂРѕРјС‚</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_system_prompt" 
                                              name="ai_chatbot_system_prompt" 
                                              rows="6" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                                    <p class="description">
                                        РРЅСЃС‚СЂСѓРєС†РёРё РґР»СЏ AI Рѕ С‚РѕРј, РєР°Рє СЃРµР±СЏ РІРµСЃС‚Рё Рё РѕС‚РІРµС‡Р°С‚СЊ РЅР° РІРѕРїСЂРѕСЃС‹.<br>
                                        <strong>РџСЂРёРјРµСЂС‹ РїСЂРѕРјС‚РѕРІ:</strong><br>
                                        вЂў "РўС‹ РєРѕРЅСЃСѓР»СЊС‚Р°РЅС‚ РёРЅС‚РµСЂРЅРµС‚-РјР°РіР°Р·РёРЅР°. РџРѕРјРѕРіР°Р№ РїРѕРєСѓРїР°С‚РµР»СЏРј СЃ РІС‹Р±РѕСЂРѕРј С‚РѕРІР°СЂРѕРІ."<br>
                                        вЂў "РўС‹ С‚РµС…РЅРёС‡РµСЃРєР°СЏ РїРѕРґРґРµСЂР¶РєР° СЃР°Р№С‚Р°. РћС‚РІРµС‡Р°Р№ РЅР° РІРѕРїСЂРѕСЃС‹ РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ РІРµР¶Р»РёРІРѕ Рё РїСЂРѕС„РµСЃСЃРёРѕРЅР°Р»СЊРЅРѕ."
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєР° С‚РµРєСЃС‚РѕРІ РёРЅС‚РµСЂС„РµР№СЃР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_placeholder">РџРѕРґСЃРєР°Р·РєР° РІ РїРѕР»Рµ РІРІРѕРґР°</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_placeholder" 
                                           name="ai_chatbot_custom_text_placeholder" 
                                           value="<?php echo esc_attr($custom_text['placeholder']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚-РїРѕРґСЃРєР°Р·РєР° РІ РїРѕР»Рµ РІРІРѕРґР° СЃРѕРѕР±С‰РµРЅРёСЏ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_online">РЎС‚Р°С‚СѓСЃ "Р’ СЃРµС‚Рё"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_online" 
                                           name="ai_chatbot_custom_text_online" 
                                           value="<?php echo esc_attr($custom_text['online_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ СЃС‚Р°С‚СѓСЃР°, РєРѕРіРґР° Р±РѕС‚ РґРѕСЃС‚СѓРїРµРЅ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_offline">РЎС‚Р°С‚СѓСЃ "РќРµ РІ СЃРµС‚Рё"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_offline" 
                                           name="ai_chatbot_custom_text_offline" 
                                           value="<?php echo esc_attr($custom_text['offline_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ СЃС‚Р°С‚СѓСЃР°, РєРѕРіРґР° Р±РѕС‚ РЅРµРґРѕСЃС‚СѓРїРµРЅ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_send">РўРµРєСЃС‚ РєРЅРѕРїРєРё РѕС‚РїСЂР°РІРєРё</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_send" 
                                           name="ai_chatbot_custom_text_send" 
                                           value="<?php echo esc_attr($custom_text['send_button']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ РЅР° РєРЅРѕРїРєРµ РѕС‚РїСЂР°РІРєРё СЃРѕРѕР±С‰РµРЅРёСЏ</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('РЎРѕС…СЂР°РЅРёС‚СЊ РЅР°СЃС‚СЂРѕР№РєРё', 'primary', 'submit', false); ?>
                <button type="button" class="button" id="test-connection" style="margin-left: 10px;">РўРµСЃС‚РёСЂРѕРІР°С‚СЊ РїРѕРґРєР»СЋС‡РµРЅРёРµ</button>
                <button type="button" class="button button-secondary" id="clear-cache" style="margin-left: 10px;">РћС‡РёСЃС‚РёС‚СЊ РєРµС€</button>
            </form>
        </div>

        <!-- Р‘РѕРєРѕРІР°СЏ РїР°РЅРµР»СЊ СЃ РёРЅС„РѕСЂРјР°С†РёРµР№ -->
        <div style="flex: 1;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>РРЅС„РѕСЂРјР°С†РёСЏ</h2>
                </div>
                <div class="inside">
                    <h4>рџљЂ Р’РѕР·РјРѕР¶РЅРѕСЃС‚Рё РїР»Р°РіРёРЅР°:</h4>
                    <ul>
                        <li>вњ… РРЅС‚РµРіСЂР°С†РёСЏ СЃ OpenAI GPT</li>
                        <li>вњ… РЎРѕРІСЂРµРјРµРЅРЅС‹Р№ Р°РґР°РїС‚РёРІРЅС‹Р№ РґРёР·Р°Р№РЅ</li>
                        <li>вњ… РќР°СЃС‚СЂРѕР№РєР° РїСЂРѕРјС‚РѕРІ</li>
                        <li>вњ… РљР°СЃС‚РѕРјРЅС‹Р№ Р°РІР°С‚Р°СЂ</li>
                        <li>вњ… РЈРІРµРґРѕРјР»РµРЅРёСЏ Рѕ РЅРѕРІС‹С… СЃРѕРѕР±С‰РµРЅРёСЏС…</li>
                    </ul>
                    
                    <h4>рџ“‹ РЎС‚Р°С‚СѓСЃ:</h4>
                    <p id="connection-status">
                        <?php if (empty($openai_key)): ?>
                            <span style="color: #dc3232;">вќЊ API РєР»СЋС‡ РЅРµ РЅР°СЃС‚СЂРѕРµРЅ</span>
                        <?php else: ?>
                            <span style="color: #46b450;">вњ… API РєР»СЋС‡ РЅР°СЃС‚СЂРѕРµРЅ</span>
                        <?php endif; ?>
                    </p>
                    
                    <h4>рџ’Ў РЎРѕРІРµС‚С‹:</h4>
                    <ul style="font-size: 12px;">
                        <li>РСЃРїРѕР»СЊР·СѓР№С‚Рµ РїРѕРЅСЏС‚РЅС‹Рµ РїСЂРѕРјС‚С‹ РґР»СЏ Р»СѓС‡С€РёС… РѕС‚РІРµС‚РѕРІ</li>
                        <li>РўРµСЃС‚РёСЂСѓР№С‚Рµ СЂР°Р·Р»РёС‡РЅС‹Рµ С„РѕСЂРјСѓР»РёСЂРѕРІРєРё</li>
                        <li>РЎР»РµРґРёС‚Рµ Р·Р° РёСЃРїРѕР»СЊР·РѕРІР°РЅРёРµРј API (С‚Р°СЂРёС„РёРєР°С†РёСЏ)</li>
                    </ul>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2>РџСЂРµРґРїСЂРѕСЃРјРѕС‚СЂ</h2>
                </div>
                <div class="inside">
                    <div class="preview-container">
                        <div class="ai-chatbot-toggle">
                            <span style="color: white; font-size: 20px;">рџ’¬</span>
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
                                <div class="content">РЎРїР°СЃРёР±Рѕ Р·Р° РїРѕРјРѕС‰СЊ!</div>
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

/* РЎС‚РёР»Рё РґР»СЏ РїСЂРµРІСЊСЋ С‡Р°С‚Р° */
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
    // РџСЂРѕРІРµСЂСЏРµРј, РїРѕРєР°Р·С‹РІР°Р»СЃСЏ Р»Рё СѓР¶Рµ СЃС‚Р°С‚СѓСЃ
    if (!sessionStorage.getItem('statusShown')) {
        $('.ai-chatbot-online-status').addClass('show');
        sessionStorage.setItem('statusShown', 'true');
    }
    
    // Р—Р°РіСЂСѓР·РєР° РјРµРґРёР° С„Р°Р№Р»РѕРІ
    $('#upload-avatar').click(function(e) {
        e.preventDefault();
        var mediaUploader = wp.media({
            title: 'Р’С‹Р±РµСЂРёС‚Рµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ РґР»СЏ Р°РІР°С‚Р°СЂР°',
            button: {
                text: 'Р’С‹Р±СЂР°С‚СЊ РёР·РѕР±СЂР°Р¶РµРЅРёРµ'
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

    // РџРѕРєР°Р·Р°С‚СЊ/СЃРєСЂС‹С‚СЊ API РєР»СЋС‡
    $('#toggle-api-key').click(function() {
        var $input = $('#ai_chatbot_openai_key');
        var $button = $(this);
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $button.text('РЎРєСЂС‹С‚СЊ');
        } else {
            $input.attr('type', 'password');
            $button.text('РџРѕРєР°Р·Р°С‚СЊ');
        }
    });

    // РћР±СЂР°Р±РѕС‚С‡РёРє РѕС‡РёСЃС‚РєРё РєРµС€Р°
    $('#clear-cache').click(function() {
        var $button = $(this);
        $button.text('РћС‡РёСЃС‚РєР°...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_clear_cache',
                nonce: '<?php echo wp_create_nonce("ai_chatbot_clear_cache"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('вњ… РљРµС€ СѓСЃРїРµС€РЅРѕ РѕС‡РёС‰РµРЅ! РћР±РЅРѕРІРёС‚Рµ СЃС‚СЂР°РЅРёС†Сѓ СЃР°Р№С‚Р°, С‡С‚РѕР±С‹ СѓРІРёРґРµС‚СЊ РёР·РјРµРЅРµРЅРёСЏ.');
                } else {
                    alert('вќЊ РћС€РёР±РєР° РїСЂРё РѕС‡РёСЃС‚РєРµ РєРµС€Р°: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('вќЊ РћС€РёР±РєР° РїСЂРё РѕС‡РёСЃС‚РєРµ РєРµС€Р°: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('РћС‡РёСЃС‚РёС‚СЊ РєРµС€').prop('disabled', false);
            }
        });
    });

    // РўРµСЃС‚ РїРѕРґРєР»СЋС‡РµРЅРёСЏ
    $('#test-connection').click(function() {
        var $button = $(this);
        var apiKey = $('#ai_chatbot_openai_key').val();
        
        if (!apiKey) {
            alert('Р’РІРµРґРёС‚Рµ API РєР»СЋС‡ РґР»СЏ С‚РµСЃС‚РёСЂРѕРІР°РЅРёСЏ');
            return;
        }
        
        $button.text('РўРµСЃС‚РёСЂРѕРІР°РЅРёРµ...').prop('disabled', true);
        
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
                    alert('вњ… РџРѕРґРєР»СЋС‡РµРЅРёРµ СѓСЃРїРµС€РЅРѕ! API СЂР°Р±РѕС‚Р°РµС‚ РєРѕСЂСЂРµРєС‚РЅРѕ.');
                    $('#connection-status').html('<span style="color: #46b450;">вњ… РџРѕРґРєР»СЋС‡РµРЅРёРµ РїСЂРѕС‚РµСЃС‚РёСЂРѕРІР°РЅРѕ</span>');
                } else {
                    alert('вќЊ РћС€РёР±РєР° РїСЂРё С‚РµСЃС‚РёСЂРѕРІР°РЅРёРё: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('вќЊ РћС€РёР±РєР° РїСЂРё С‚РµСЃС‚РёСЂРѕРІР°РЅРёРё РїРѕРґРєР»СЋС‡РµРЅРёСЏ: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('РўРµСЃС‚РёСЂРѕРІР°С‚СЊ РїРѕРґРєР»СЋС‡РµРЅРёРµ').prop('disabled', false);
            }
        });
    });

    // РўРµСЃС‚ email
    $('#test-email').click(function() {
        var $button = $(this);
        var email = $('#ai_chatbot_email_to').val();
        
        if (!email) {
            alert('Р’РІРµРґРёС‚Рµ email РґР»СЏ С‚РµСЃС‚РёСЂРѕРІР°РЅРёСЏ');
            return;
        }
        
        $button.text('РћС‚РїСЂР°РІРєР°...').prop('disabled', true);
        
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
                    $('#email-test-result').html('<div style="color: #46b450; margin-top: 10px;">вњ… РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚РїСЂР°РІР»РµРЅРѕ!</div>');
                } else {
                    $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">вќЊ РћС€РёР±РєР° РїСЂРё РѕС‚РїСЂР°РІРєРµ: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">вќЊ РћС€РёР±РєР°: ' + error + '</div>');
            },
            complete: function() {
                $button.text('РўРµСЃС‚ email').prop('disabled', false);
            }
        });
    });

    // РћР±РЅРѕРІР»РµРЅРёРµ РїСЂРµРІСЊСЋ РїСЂРё РёР·РјРµРЅРµРЅРёРё РЅР°СЃС‚СЂРѕРµРє
    function updatePreview() {
        const margin = $('#ai_chatbot_margin').val();
        const colorScheme = $('#ai_chatbot_color_scheme').val();
        let primaryColor, secondaryColor;
        
        // РћР±РЅРѕРІР»СЏРµРј РѕС‚СЃС‚СѓРїС‹
        $('.preview-container').css('margin-right', margin + 'px');
        $('.ai-chatbot-toggle').css('margin-right', margin + 'px');
        
        // РћРїСЂРµРґРµР»СЏРµРј С†РІРµС‚Р°
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

        // РџРѕРєР°Р·С‹РІР°РµРј/СЃРєСЂС‹РІР°РµРј РІС‹Р±РѕСЂ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєРёС… С†РІРµС‚РѕРІ
        $('#custom-colors').toggle(colorScheme === 'custom');
        
        // РћР±РЅРѕРІР»СЏРµРј Р·РЅР°С‡РµРЅРёСЏ РїРѕР»РµР№ С†РІРµС‚Р°
        if (colorScheme !== 'custom') {
            $('#ai_chatbot_primary_color').val(primaryColor);
            $('#ai_chatbot_secondary_color').val(secondaryColor);
        }
        
        const botNameColor = $('#ai_chatbot_bot_name_color').val();
        
        // РџСЂРёРјРµРЅСЏРµРј С†РІРµС‚Р°
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
        
        // РћР±РЅРѕРІР»СЏРµРј РѕСЃС‚Р°Р»СЊРЅС‹Рµ СЌР»РµРјРµРЅС‚С‹
        const name = $('#ai_chatbot_bot_name').val();
        const message = $('#ai_chatbot_welcome_message').val();
        const avatarSize = $('#ai_chatbot_avatar_size').val();
        const fontSize = $('#ai_chatbot_font_size').val();
        const fontFamily = $('#ai_chatbot_font_family').val();
        const status = $('#ai_chatbot_custom_text_online').val();
        
        // РћР±РЅРѕРІР»СЏРµРј СЂР°Р·РјРµСЂ Р°РІР°С‚Р°СЂР°
        $('#avatar-preview img').css({
            'width': avatarSize + 'px',
            'height': avatarSize + 'px'
        });
        
        // РћР±РЅРѕРІР»СЏРµРј С€СЂРёС„С‚
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
        
        // РћР±РЅРѕРІР»СЏРµРј С‚РµРєСЃС‚С‹
        $('.preview-name').css('color', botNameColor).text(name);
        $('.preview-status').text(status);
        $('.chat-preview .message.bot .content').text(message);
    }

    // РћР±СЂР°Р±РѕС‚С‡РёРєРё РґР»СЏ РѕР±РЅРѕРІР»РµРЅРёСЏ РїСЂРµРІСЊСЋ
    $('#ai_chatbot_bot_name, #ai_chatbot_welcome_message, #ai_chatbot_avatar_size, #ai_chatbot_font_size, #ai_chatbot_color_scheme, #ai_chatbot_font_family, #ai_chatbot_custom_text_online, #ai_chatbot_margin').on('input change', updatePreview);
    
    $('#ai_chatbot_primary_color, #ai_chatbot_secondary_color, #ai_chatbot_bot_name_color').on('input', updatePreview);
    
    // РРЅРёС†РёР°Р»РёР·Р°С†РёСЏ РїСЂРµРІСЊСЋ
    updatePreview();
});
</script>

<?php
// Р”РѕР±Р°РІР»СЏРµРј РѕР±СЂР°Р±РѕС‚С‡РёРєРё AJAX
add_action('wp_ajax_test_openai_connection', 'ai_chatbot_test_connection');
add_action('wp_ajax_ai_chatbot_clear_cache', 'ai_chatbot_clear_cache');
add_action('wp_ajax_ai_chatbot_test_email', 'ai_chatbot_test_email');

function ai_chatbot_test_email() {
    check_ajax_referer('ai_chatbot_test_email', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    $email = sanitize_email($_POST['email']);
    if (!is_email($email)) {
        wp_send_json_error('РќРµРІРµСЂРЅС‹Р№ С„РѕСЂРјР°С‚ email');
        return;
    }
    
    $subject = 'РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ AI ChatBot';
    $message = "Р­С‚Рѕ С‚РµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚ РїР»Р°РіРёРЅР° AI ChatBot.\n\n";
    $message .= "Р•СЃР»Рё РІС‹ РїРѕР»СѓС‡РёР»Рё СЌС‚Рѕ РїРёСЃСЊРјРѕ, Р·РЅР°С‡РёС‚ РЅР°СЃС‚СЂРѕР№РєРё email СЂР°Р±РѕС‚Р°СЋС‚ РєРѕСЂСЂРµРєС‚РЅРѕ.\n\n";
    $message .= "Р”Р°С‚Р° Рё РІСЂРµРјСЏ РѕС‚РїСЂР°РІРєРё: " . current_time('mysql') . "\n";
    $message .= "РЎР°Р№С‚: " . get_bloginfo('name') . " (" . get_site_url() . ")";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success('РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚РїСЂР°РІР»РµРЅРѕ');
    } else {
        wp_send_json_error('РћС€РёР±РєР° РїСЂРё РѕС‚РїСЂР°РІРєРµ С‚РµСЃС‚РѕРІРѕРіРѕ РїРёСЃСЊРјР°');
    }
}

function ai_chatbot_test_connection() {
    check_ajax_referer('test_openai_connection', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    // РўРµСЃС‚РёСЂСѓРµРј РїРѕРґРєР»СЋС‡РµРЅРёРµ Рє API
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
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    // РћС‡РёС‰Р°РµРј РєРµС€ WordPress
    wp_cache_flush();
    
    // РћС‡РёС‰Р°РµРј РєРµС€ РѕРїС†РёР№
    delete_transient('ai_chatbot_settings');
    
    // РџСЂРѕРІРµСЂСЏРµРј РЅР°Р»РёС‡РёРµ РєР»Р°СЃСЃР° РіРµРЅРµСЂР°С‚РѕСЂР° CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Р“РµРЅРµСЂРёСЂСѓРµРј Рё СЃРѕС…СЂР°РЅСЏРµРј РЅРѕРІС‹Р№ CSS
    $css_generator = new AI_ChatBot_CSS_Generator();
    $css_url = $css_generator->save();
    
    // РћС‡РёС‰Р°РµРј OPcache РµСЃР»Рё РѕРЅ РІРєР»СЋС‡РµРЅ
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    wp_send_json_success('Cache cleared successfully');
}
POST['ai_chatbot_inactivity_timeout']);
    if ($inactivity_timeout < 60000) {
        $inactivity_timeout = 300000; // fall back to default 5 minutes
    }
    update_option('ai_chatbot_inactivity_timeout', $inactivity_timeout);
    $color_scheme = sanitize_text_field($_POST['ai_chatbot_color_scheme']);
    update_option('ai_chatbot_color_scheme', $color_scheme);
    
    // РЎРѕС…СЂР°РЅСЏРµРј С†РІРµС‚Р° РІ Р·Р°РІРёСЃРёРјРѕСЃС‚Рё РѕС‚ РІС‹Р±СЂР°РЅРЅРѕР№ СЃС…РµРјС‹
    // Р’СЃРµРіРґР° СЃРѕС…СЂР°РЅСЏРµРј С†РІРµС‚Р°, РЅРµР·Р°РІРёСЃРёРјРѕ РѕС‚ СЃС…РµРјС‹
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
    
    // РџСЂРѕРІРµСЂСЏРµРј РЅР°Р»РёС‡РёРµ РєР»Р°СЃСЃР° РіРµРЅРµСЂР°С‚РѕСЂР° CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Р“РµРЅРµСЂРёСЂСѓРµРј Рё СЃРѕС…СЂР°РЅСЏРµРј CSS СЃ РїРµСЂРµРґР°С‡РµР№ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєРёС… С†РІРµС‚РѕРІ Рё margin
    $css_options = array(
        'primary_color' => $primary_color,
        'secondary_color' => $secondary_color,
        'margin' => get_option('ai_chatbot_margin', 20),
        'widget_size' => get_option('ai_chatbot_widget_size', 60),
        // Р”РѕР±Р°РІСЊС‚Рµ РґСЂСѓРіРёРµ РїР°СЂР°РјРµС‚СЂС‹, РµСЃР»Рё РЅСѓР¶РЅРѕ
    );
    $css_generator = new AI_ChatBot_CSS_Generator($css_options);
    $css_url = $css_generator->save();
    echo '<div class="notice notice-success"><p>РќР°СЃС‚СЂРѕР№РєРё СЃРѕС…СЂР°РЅРµРЅС‹! CSS С„Р°Р№Р» РѕР±РЅРѕРІР»РµРЅ.</p></div>';
}

// РџРѕР»СѓС‡РµРЅРёРµ С‚РµРєСѓС‰РёС… РЅР°СЃС‚СЂРѕРµРє
$enabled = get_option('ai_chatbot_enabled', '1');
$openai_key = get_option('ai_chatbot_openai_key', '');
$openai_model = get_option('ai_chatbot_openai_model', 'gpt-3.5-turbo');
$welcome_message = get_option('ai_chatbot_welcome_message', 'РџСЂРёРІРµС‚! РЇ РІР°С€ AI-РєРѕРЅСЃСѓР»СЊС‚Р°РЅС‚. Р§РµРј РјРѕРіСѓ РїРѕРјРѕС‡СЊ?');
$system_prompt = get_option('ai_chatbot_system_prompt', 'РўС‹ helpful AI-Р°СЃСЃРёСЃС‚РµРЅС‚, РѕС‚РІРµС‡Р°СЋС‰РёР№ РЅР° РІРѕРїСЂРѕСЃС‹ РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ СЃР°Р№С‚Р°.');
$bot_name = get_option('ai_chatbot_bot_name', 'AI РљРѕРЅСЃСѓР»СЊС‚Р°РЅС‚');
$email_to = get_option('ai_chatbot_email_to', 'gordienko.office@gmail.com');
$inactivity_timeout = get_option('ai_chatbot_inactivity_timeout', 300000); // 5 РјРёРЅСѓС‚ РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ
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
    'placeholder' => 'РќР°РїРёС€РёС‚Рµ РІР°С€ РІРѕРїСЂРѕСЃ...',
    'online_status' => 'Р’ СЃРµС‚Рё',
    'offline_status' => 'РќРµ РІ СЃРµС‚Рё',
    'send_button' => 'РћС‚РїСЂР°РІРёС‚СЊ'
));
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-format-chat" style="font-size: 30px; margin-right: 10px; color: #667eea;"></span>
        РќР°СЃС‚СЂРѕР№РєРё AI ChatBot
    </h1>
    
    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- РћСЃРЅРѕРІРЅР°СЏ С„РѕСЂРјР° РЅР°СЃС‚СЂРѕРµРє -->
        <div style="flex: 2;">
            <form method="post" action="">
                <?php wp_nonce_field('ai_chatbot_settings'); ?>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РћСЃРЅРѕРІРЅС‹Рµ РЅР°СЃС‚СЂРѕР№РєРё</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_enabled">Р’РєР»СЋС‡РёС‚СЊ С‡Р°С‚-Р±РѕС‚</label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="ai_chatbot_enabled" 
                                           name="ai_chatbot_enabled" 
                                           value="1" 
                                           <?php checked($enabled, '1'); ?>>
                                    <p class="description">РџРѕРєР°Р·С‹РІР°С‚СЊ РІРёРґР¶РµС‚ С‡Р°С‚Р° РЅР° СЃР°Р№С‚Рµ</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_bot_name">РРјСЏ Р±РѕС‚Р°</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_bot_name" 
                                           name="ai_chatbot_bot_name" 
                                           value="<?php echo esc_attr($bot_name); ?>" 
                                           class="regular-text">
                                    <p class="description">РРјСЏ, РєРѕС‚РѕСЂРѕРµ Р±СѓРґРµС‚ РѕС‚РѕР±СЂР°Р¶Р°С‚СЊСЃСЏ РІ Р·Р°РіРѕР»РѕРІРєРµ С‡Р°С‚Р°</p>
                                    <div style="margin-top: 10px;">
                                        <input type="color" 
                                               id="ai_chatbot_bot_name_color" 
                                               name="ai_chatbot_bot_name_color" 
                                               value="<?php echo esc_attr($bot_name_color); ?>">
                                        <label for="ai_chatbot_bot_name_color">Р¦РІРµС‚ РёРјРµРЅРё Р±РѕС‚Р°</label>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_language">РЇР·С‹Рє РёРЅС‚РµСЂС„РµР№СЃР°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_language" name="ai_chatbot_language" class="regular-text">
                                        <option value="ru" <?php selected($language, 'ru'); ?>>Р СѓСЃСЃРєРёР№</option>
                                        <option value="en" <?php selected($language, 'en'); ?>>English</option>
                                        <option value="uk" <?php selected($language, 'uk'); ?>>РЈРєСЂР°С—РЅСЃСЊРєР°</option>
                                    </select>
                                    <p class="description">РЇР·С‹Рє РёРЅС‚РµСЂС„РµР№СЃР° С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё РІРЅРµС€РЅРµРіРѕ РІРёРґР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_animation">РђРЅРёРјР°С†РёСЏ РІРёРґР¶РµС‚Р°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_animation" name="ai_chatbot_animation" class="regular-text">
                                        <option value="bounce" <?php selected($animation, 'bounce'); ?>>РџРѕРґРїСЂС‹РіРёРІР°РЅРёРµ</option>
                                        <option value="pulse" <?php selected($animation, 'pulse'); ?>>РџСѓР»СЊСЃР°С†РёСЏ</option>
                                        <option value="shake" <?php selected($animation, 'shake'); ?>>РџРѕРєР°С‡РёРІР°РЅРёРµ</option>
                                        <option value="none" <?php selected($animation, 'none'); ?>>Р‘РµР· Р°РЅРёРјР°С†РёРё</option>
                                    </select>
                                    <p class="description">РђРЅРёРјР°С†РёСЏ РёРєРѕРЅРєРё С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_widget_size">Р Р°Р·РјРµСЂ РІРёРґР¶РµС‚Р° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_widget_size" 
                                           name="ai_chatbot_widget_size" 
                                           value="<?php echo esc_attr($widget_size); ?>" 
                                           min="40" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ РєСЂСѓРіР»РѕР№ РёРєРѕРЅРєРё С‡Р°С‚-Р±РѕС‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 60px)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_window_size">Р Р°Р·РјРµСЂ РѕРєРЅР° С‡Р°С‚Р°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_window_size" name="ai_chatbot_window_size" class="regular-text">
                                        <option value="small" <?php selected($window_size, 'small'); ?>>РњР°Р»РµРЅСЊРєРёР№</option>
                                        <option value="default" <?php selected($window_size, 'default'); ?>>РЎСЂРµРґРЅРёР№</option>
                                        <option value="large" <?php selected($window_size, 'large'); ?>>Р‘РѕР»СЊС€РѕР№</option>
                                    </select>
                                    <p class="description">Р Р°Р·РјРµСЂ РѕРєРЅР° С‡Р°С‚Р° РїСЂРё РѕС‚РєСЂС‹С‚РёРё</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_color_scheme">Р¦РІРµС‚РѕРІР°СЏ СЃС…РµРјР°</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_color_scheme" name="ai_chatbot_color_scheme" class="regular-text">
                                        <option value="default" <?php selected($color_scheme, 'default'); ?>>РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</option>
                                        <option value="blue" <?php selected($color_scheme, 'blue'); ?>>РЎРёРЅСЏСЏ</option>
                                        <option value="green" <?php selected($color_scheme, 'green'); ?>>Р—РµР»РµРЅР°СЏ</option>
                                        <option value="purple" <?php selected($color_scheme, 'purple'); ?>>Р¤РёРѕР»РµС‚РѕРІР°СЏ</option>
                                        <option value="custom" <?php selected($color_scheme, 'custom'); ?>>РџРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєР°СЏ</option>
                                    </select>
                                    <div id="custom-colors" style="margin-top: 10px; display: <?php echo $color_scheme === 'custom' ? 'block' : 'none'; ?>">
                                        <input type="color" 
                                               id="ai_chatbot_primary_color" 
                                               name="ai_chatbot_primary_color" 
                                               value="<?php echo esc_attr($primary_color); ?>">
                                        <label for="ai_chatbot_primary_color">РћСЃРЅРѕРІРЅРѕР№ С†РІРµС‚</label>
                                        <input type="color" 
                                               id="ai_chatbot_secondary_color" 
                                               name="ai_chatbot_secondary_color" 
                                               value="<?php echo esc_attr($secondary_color); ?>">
                                        <label for="ai_chatbot_secondary_color">Р”РѕРїРѕР»РЅРёС‚РµР»СЊРЅС‹Р№ С†РІРµС‚</label>
                                    </div>
                                    <p class="description">Р’С‹Р±РµСЂРёС‚Рµ С†РІРµС‚РѕРІСѓСЋ СЃС…РµРјСѓ С‡Р°С‚-Р±РѕС‚Р°</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_family">РЁСЂРёС„С‚</label>
                                </th>
                                <td>
                                    <select id="ai_chatbot_font_family" name="ai_chatbot_font_family" class="regular-text">
                                        <option value="system-default" <?php selected($font_family, 'system-default'); ?>>РЎРёСЃС‚РµРјРЅС‹Р№</option>
                                        <option value="roboto" <?php selected($font_family, 'roboto'); ?>>Roboto</option>
                                        <option value="open-sans" <?php selected($font_family, 'open-sans'); ?>>Open Sans</option>
                                        <option value="lato" <?php selected($font_family, 'lato'); ?>>Lato</option>
                                    </select>
                                    <p class="description">РЁСЂРёС„С‚ РґР»СЏ С‚РµРєСЃС‚Р° РІ С‡Р°С‚Рµ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_font_size">Р Р°Р·РјРµСЂ С€СЂРёС„С‚Р° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_font_size" 
                                           name="ai_chatbot_font_size" 
                                           value="<?php echo esc_attr($font_size); ?>" 
                                           min="12" 
                                           max="20" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ С€СЂРёС„С‚Р° РґР»СЏ С‚РµРєСЃС‚Р° РІ С‡Р°С‚Рµ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 14px)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_margin">РћС‚СЃС‚СѓРї РѕС‚ РєСЂР°СЏ (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_margin" 
                                           name="ai_chatbot_margin" 
                                           value="<?php echo esc_attr($margin); ?>" 
                                           min="0" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">РћС‚СЃС‚СѓРї РІРёРґР¶РµС‚Р° РѕС‚ РєСЂР°СЏ СЌРєСЂР°РЅР° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 20px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё Р°РІР°С‚Р°СЂР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_url">РђРІР°С‚Р°СЂ Р±РѕС‚Р°</label>
                                </th>
                                <td>
                                    <input type="url" 
                                           id="ai_chatbot_avatar_url" 
                                           name="ai_chatbot_avatar_url" 
                                           value="<?php echo esc_url($avatar_url); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="upload-avatar">Р—Р°РіСЂСѓР·РёС‚СЊ РёР·РѕР±СЂР°Р¶РµРЅРёРµ</button>
                                    <p class="description">URL РёР·РѕР±СЂР°Р¶РµРЅРёСЏ РґР»СЏ Р°РІР°С‚Р°СЂР° Р±РѕС‚Р°</p>
                                    <div id="avatar-preview" style="margin-top: 10px;">
                                        <img src="<?php echo esc_url($avatar_url); ?>" 
                                             style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #ddd;">
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_avatar_size">Р Р°Р·РјРµСЂ Р°РІР°С‚Р°СЂР° (px)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_avatar_size" 
                                           name="ai_chatbot_avatar_size" 
                                           value="<?php echo esc_attr($avatar_size); ?>" 
                                           min="30" 
                                           max="80" 
                                           class="small-text">
                                    <p class="description">Р Р°Р·РјРµСЂ Р°РІР°С‚Р°СЂР° РІ Р·Р°РіРѕР»РѕРІРєРµ С‡Р°С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ: 40px)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё СѓРІРµРґРѕРјР»РµРЅРёР№ Рё С‚Р°Р№РјР°СѓС‚РѕРІ</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_email_to">Email РґР»СЏ СѓРІРµРґРѕРјР»РµРЅРёР№</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="ai_chatbot_email_to" 
                                           name="ai_chatbot_email_to" 
                                           value="<?php echo esc_attr($email_to); ?>" 
                                           class="regular-text">
                                    <button type="button" class="button" id="test-email">РўРµСЃС‚ email</button>
                                    <p class="description">Email РґР»СЏ РїРѕР»СѓС‡РµРЅРёСЏ СѓРІРµРґРѕРјР»РµРЅРёР№ Рѕ РЅРѕРІС‹С… СЃРѕРѕР±С‰РµРЅРёСЏС… РІ С‡Р°С‚Рµ</p>
                                    <div id="email-test-result"></div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_inactivity_timeout">РўР°Р№РјР°СѓС‚ РЅРµР°РєС‚РёРІРЅРѕСЃС‚Рё (РјСЃ)</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="ai_chatbot_inactivity_timeout" 
                                           name="ai_chatbot_inactivity_timeout" 
                                           value="<?php echo esc_attr($inactivity_timeout); ?>" 
                                           min="60000" 
                                           step="60000" 
                                           class="regular-text">
                                    <p class="description">Р’СЂРµРјСЏ РІ РјРёР»Р»РёСЃРµРєСѓРЅРґР°С…, РїРѕСЃР»Рµ РєРѕС‚РѕСЂРѕРіРѕ РЅРµР°РєС‚РёРІРЅС‹Р№ С‡Р°С‚ Р±СѓРґРµС‚ Р·Р°РєСЂС‹С‚ (РјРёРЅРёРјСѓРј 60000 РјСЃ = 1 РјРёРЅСѓС‚Р°)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєРё OpenAI</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_key">API РљР»СЋС‡ OpenAI</label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="ai_chatbot_openai_key" 
                                           name="ai_chatbot_openai_key" 
                                           value="<?php echo esc_attr($openai_key); ?>" 
                                           class="regular-text" 
                                           placeholder="sk-...">
                                    <button type="button" class="button" id="toggle-api-key">РџРѕРєР°Р·Р°С‚СЊ</button>
                                    <p class="description">
                                        РџРѕР»СѓС‡РёС‚Рµ API РєР»СЋС‡ РЅР° <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                        <br><strong>Р’Р°Р¶РЅРѕ:</strong> РҐСЂР°РЅРёС‚Рµ РєР»СЋС‡ РІ Р±РµР·РѕРїР°СЃРЅРѕСЃС‚Рё!
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_openai_model">РњРѕРґРµР»СЊ OpenAI</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_openai_model" 
                                           name="ai_chatbot_openai_model" 
                                           value="<?php echo esc_attr($openai_model); ?>" 
                                           class="regular-text"
                                           placeholder="gpt-3.5-turbo">
                                    <p class="description">Р’РІРµРґРёС‚Рµ РЅР°Р·РІР°РЅРёРµ РјРѕРґРµР»Рё OpenAI СЃРѕРіР»Р°СЃРЅРѕ <a href="https://platform.openai.com/docs/models" target="_blank">РґРѕРєСѓРјРµРЅС‚Р°С†РёРё</a></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РЎРѕРѕР±С‰РµРЅРёСЏ Рё РїСЂРѕРјС‚</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_welcome_message">РџСЂРёРІРµС‚СЃС‚РІРµРЅРЅРѕРµ СЃРѕРѕР±С‰РµРЅРёРµ</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_welcome_message" 
                                              name="ai_chatbot_welcome_message" 
                                              rows="3" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($welcome_message); ?></textarea>
                                    <p class="description">РџРµСЂРІРѕРµ СЃРѕРѕР±С‰РµРЅРёРµ, РєРѕС‚РѕСЂРѕРµ СѓРІРёРґРёС‚ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊ</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_system_prompt">РЎРёСЃС‚РµРјРЅС‹Р№ РїСЂРѕРјС‚</label>
                                </th>
                                <td>
                                    <textarea id="ai_chatbot_system_prompt" 
                                              name="ai_chatbot_system_prompt" 
                                              rows="6" 
                                              cols="50" 
                                              class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                                    <p class="description">
                                        РРЅСЃС‚СЂСѓРєС†РёРё РґР»СЏ AI Рѕ С‚РѕРј, РєР°Рє СЃРµР±СЏ РІРµСЃС‚Рё Рё РѕС‚РІРµС‡Р°С‚СЊ РЅР° РІРѕРїСЂРѕСЃС‹.<br>
                                        <strong>РџСЂРёРјРµСЂС‹ РїСЂРѕРјС‚РѕРІ:</strong><br>
                                        вЂў "РўС‹ РєРѕРЅСЃСѓР»СЊС‚Р°РЅС‚ РёРЅС‚РµСЂРЅРµС‚-РјР°РіР°Р·РёРЅР°. РџРѕРјРѕРіР°Р№ РїРѕРєСѓРїР°С‚РµР»СЏРј СЃ РІС‹Р±РѕСЂРѕРј С‚РѕРІР°СЂРѕРІ."<br>
                                        вЂў "РўС‹ С‚РµС…РЅРёС‡РµСЃРєР°СЏ РїРѕРґРґРµСЂР¶РєР° СЃР°Р№С‚Р°. РћС‚РІРµС‡Р°Р№ РЅР° РІРѕРїСЂРѕСЃС‹ РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ РІРµР¶Р»РёРІРѕ Рё РїСЂРѕС„РµСЃСЃРёРѕРЅР°Р»СЊРЅРѕ."
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2>РќР°СЃС‚СЂРѕР№РєР° С‚РµРєСЃС‚РѕРІ РёРЅС‚РµСЂС„РµР№СЃР°</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_placeholder">РџРѕРґСЃРєР°Р·РєР° РІ РїРѕР»Рµ РІРІРѕРґР°</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_placeholder" 
                                           name="ai_chatbot_custom_text_placeholder" 
                                           value="<?php echo esc_attr($custom_text['placeholder']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚-РїРѕРґСЃРєР°Р·РєР° РІ РїРѕР»Рµ РІРІРѕРґР° СЃРѕРѕР±С‰РµРЅРёСЏ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_online">РЎС‚Р°С‚СѓСЃ "Р’ СЃРµС‚Рё"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_online" 
                                           name="ai_chatbot_custom_text_online" 
                                           value="<?php echo esc_attr($custom_text['online_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ СЃС‚Р°С‚СѓСЃР°, РєРѕРіРґР° Р±РѕС‚ РґРѕСЃС‚СѓРїРµРЅ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_offline">РЎС‚Р°С‚СѓСЃ "РќРµ РІ СЃРµС‚Рё"</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_offline" 
                                           name="ai_chatbot_custom_text_offline" 
                                           value="<?php echo esc_attr($custom_text['offline_status']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ СЃС‚Р°С‚СѓСЃР°, РєРѕРіРґР° Р±РѕС‚ РЅРµРґРѕСЃС‚СѓРїРµРЅ</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="ai_chatbot_custom_text_send">РўРµРєСЃС‚ РєРЅРѕРїРєРё РѕС‚РїСЂР°РІРєРё</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ai_chatbot_custom_text_send" 
                                           name="ai_chatbot_custom_text_send" 
                                           value="<?php echo esc_attr($custom_text['send_button']); ?>" 
                                           class="regular-text">
                                    <p class="description">РўРµРєСЃС‚ РЅР° РєРЅРѕРїРєРµ РѕС‚РїСЂР°РІРєРё СЃРѕРѕР±С‰РµРЅРёСЏ</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('РЎРѕС…СЂР°РЅРёС‚СЊ РЅР°СЃС‚СЂРѕР№РєРё', 'primary', 'submit', false); ?>
                <button type="button" class="button" id="test-connection" style="margin-left: 10px;">РўРµСЃС‚РёСЂРѕРІР°С‚СЊ РїРѕРґРєР»СЋС‡РµРЅРёРµ</button>
                <button type="button" class="button button-secondary" id="clear-cache" style="margin-left: 10px;">РћС‡РёСЃС‚РёС‚СЊ РєРµС€</button>
            </form>
        </div>

        <!-- Р‘РѕРєРѕРІР°СЏ РїР°РЅРµР»СЊ СЃ РёРЅС„РѕСЂРјР°С†РёРµР№ -->
        <div style="flex: 1;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>РРЅС„РѕСЂРјР°С†РёСЏ</h2>
                </div>
                <div class="inside">
                    <h4>рџљЂ Р’РѕР·РјРѕР¶РЅРѕСЃС‚Рё РїР»Р°РіРёРЅР°:</h4>
                    <ul>
                        <li>вњ… РРЅС‚РµРіСЂР°С†РёСЏ СЃ OpenAI GPT</li>
                        <li>вњ… РЎРѕРІСЂРµРјРµРЅРЅС‹Р№ Р°РґР°РїС‚РёРІРЅС‹Р№ РґРёР·Р°Р№РЅ</li>
                        <li>вњ… РќР°СЃС‚СЂРѕР№РєР° РїСЂРѕРјС‚РѕРІ</li>
                        <li>вњ… РљР°СЃС‚РѕРјРЅС‹Р№ Р°РІР°С‚Р°СЂ</li>
                        <li>вњ… РЈРІРµРґРѕРјР»РµРЅРёСЏ Рѕ РЅРѕРІС‹С… СЃРѕРѕР±С‰РµРЅРёСЏС…</li>
                    </ul>
                    
                    <h4>рџ“‹ РЎС‚Р°С‚СѓСЃ:</h4>
                    <p id="connection-status">
                        <?php if (empty($openai_key)): ?>
                            <span style="color: #dc3232;">вќЊ API РєР»СЋС‡ РЅРµ РЅР°СЃС‚СЂРѕРµРЅ</span>
                        <?php else: ?>
                            <span style="color: #46b450;">вњ… API РєР»СЋС‡ РЅР°СЃС‚СЂРѕРµРЅ</span>
                        <?php endif; ?>
                    </p>
                    
                    <h4>рџ’Ў РЎРѕРІРµС‚С‹:</h4>
                    <ul style="font-size: 12px;">
                        <li>РСЃРїРѕР»СЊР·СѓР№С‚Рµ РїРѕРЅСЏС‚РЅС‹Рµ РїСЂРѕРјС‚С‹ РґР»СЏ Р»СѓС‡С€РёС… РѕС‚РІРµС‚РѕРІ</li>
                        <li>РўРµСЃС‚РёСЂСѓР№С‚Рµ СЂР°Р·Р»РёС‡РЅС‹Рµ С„РѕСЂРјСѓР»РёСЂРѕРІРєРё</li>
                        <li>РЎР»РµРґРёС‚Рµ Р·Р° РёСЃРїРѕР»СЊР·РѕРІР°РЅРёРµРј API (С‚Р°СЂРёС„РёРєР°С†РёСЏ)</li>
                    </ul>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2>РџСЂРµРґРїСЂРѕСЃРјРѕС‚СЂ</h2>
                </div>
                <div class="inside">
                    <div class="preview-container">
                        <div class="ai-chatbot-toggle">
                            <span style="color: white; font-size: 20px;">рџ’¬</span>
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
                                <div class="content">РЎРїР°СЃРёР±Рѕ Р·Р° РїРѕРјРѕС‰СЊ!</div>
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

/* РЎС‚РёР»Рё РґР»СЏ РїСЂРµРІСЊСЋ С‡Р°С‚Р° */
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
    // РџСЂРѕРІРµСЂСЏРµРј, РїРѕРєР°Р·С‹РІР°Р»СЃСЏ Р»Рё СѓР¶Рµ СЃС‚Р°С‚СѓСЃ
    if (!sessionStorage.getItem('statusShown')) {
        $('.ai-chatbot-online-status').addClass('show');
        sessionStorage.setItem('statusShown', 'true');
    }
    
    // Р—Р°РіСЂСѓР·РєР° РјРµРґРёР° С„Р°Р№Р»РѕРІ
    $('#upload-avatar').click(function(e) {
        e.preventDefault();
        var mediaUploader = wp.media({
            title: 'Р’С‹Р±РµСЂРёС‚Рµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ РґР»СЏ Р°РІР°С‚Р°СЂР°',
            button: {
                text: 'Р’С‹Р±СЂР°С‚СЊ РёР·РѕР±СЂР°Р¶РµРЅРёРµ'
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

    // РџРѕРєР°Р·Р°С‚СЊ/СЃРєСЂС‹С‚СЊ API РєР»СЋС‡
    $('#toggle-api-key').click(function() {
        var $input = $('#ai_chatbot_openai_key');
        var $button = $(this);
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $button.text('РЎРєСЂС‹С‚СЊ');
        } else {
            $input.attr('type', 'password');
            $button.text('РџРѕРєР°Р·Р°С‚СЊ');
        }
    });

    // РћР±СЂР°Р±РѕС‚С‡РёРє РѕС‡РёСЃС‚РєРё РєРµС€Р°
    $('#clear-cache').click(function() {
        var $button = $(this);
        $button.text('РћС‡РёСЃС‚РєР°...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_clear_cache',
                nonce: '<?php echo wp_create_nonce("ai_chatbot_clear_cache"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('вњ… РљРµС€ СѓСЃРїРµС€РЅРѕ РѕС‡РёС‰РµРЅ! РћР±РЅРѕРІРёС‚Рµ СЃС‚СЂР°РЅРёС†Сѓ СЃР°Р№С‚Р°, С‡С‚РѕР±С‹ СѓРІРёРґРµС‚СЊ РёР·РјРµРЅРµРЅРёСЏ.');
                } else {
                    alert('вќЊ РћС€РёР±РєР° РїСЂРё РѕС‡РёСЃС‚РєРµ РєРµС€Р°: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('вќЊ РћС€РёР±РєР° РїСЂРё РѕС‡РёСЃС‚РєРµ РєРµС€Р°: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('РћС‡РёСЃС‚РёС‚СЊ РєРµС€').prop('disabled', false);
            }
        });
    });

    // РўРµСЃС‚ РїРѕРґРєР»СЋС‡РµРЅРёСЏ
    $('#test-connection').click(function() {
        var $button = $(this);
        var apiKey = $('#ai_chatbot_openai_key').val();
        
        if (!apiKey) {
            alert('Р’РІРµРґРёС‚Рµ API РєР»СЋС‡ РґР»СЏ С‚РµСЃС‚РёСЂРѕРІР°РЅРёСЏ');
            return;
        }
        
        $button.text('РўРµСЃС‚РёСЂРѕРІР°РЅРёРµ...').prop('disabled', true);
        
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
                    alert('вњ… РџРѕРґРєР»СЋС‡РµРЅРёРµ СѓСЃРїРµС€РЅРѕ! API СЂР°Р±РѕС‚Р°РµС‚ РєРѕСЂСЂРµРєС‚РЅРѕ.');
                    $('#connection-status').html('<span style="color: #46b450;">вњ… РџРѕРґРєР»СЋС‡РµРЅРёРµ РїСЂРѕС‚РµСЃС‚РёСЂРѕРІР°РЅРѕ</span>');
                } else {
                    alert('вќЊ РћС€РёР±РєР° РїСЂРё С‚РµСЃС‚РёСЂРѕРІР°РЅРёРё: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('вќЊ РћС€РёР±РєР° РїСЂРё С‚РµСЃС‚РёСЂРѕРІР°РЅРёРё РїРѕРґРєР»СЋС‡РµРЅРёСЏ: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.text('РўРµСЃС‚РёСЂРѕРІР°С‚СЊ РїРѕРґРєР»СЋС‡РµРЅРёРµ').prop('disabled', false);
            }
        });
    });

    // РўРµСЃС‚ email
    $('#test-email').click(function() {
        var $button = $(this);
        var email = $('#ai_chatbot_email_to').val();
        
        if (!email) {
            alert('Р’РІРµРґРёС‚Рµ email РґР»СЏ С‚РµСЃС‚РёСЂРѕРІР°РЅРёСЏ');
            return;
        }
        
        $button.text('РћС‚РїСЂР°РІРєР°...').prop('disabled', true);
        
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
                    $('#email-test-result').html('<div style="color: #46b450; margin-top: 10px;">вњ… РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚РїСЂР°РІР»РµРЅРѕ!</div>');
                } else {
                    $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">вќЊ РћС€РёР±РєР° РїСЂРё РѕС‚РїСЂР°РІРєРµ: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#email-test-result').html('<div style="color: #dc3232; margin-top: 10px;">вќЊ РћС€РёР±РєР°: ' + error + '</div>');
            },
            complete: function() {
                $button.text('РўРµСЃС‚ email').prop('disabled', false);
            }
        });
    });

    // РћР±РЅРѕРІР»РµРЅРёРµ РїСЂРµРІСЊСЋ РїСЂРё РёР·РјРµРЅРµРЅРёРё РЅР°СЃС‚СЂРѕРµРє
    function updatePreview() {
        const margin = $('#ai_chatbot_margin').val();
        const colorScheme = $('#ai_chatbot_color_scheme').val();
        let primaryColor, secondaryColor;
        
        // РћР±РЅРѕРІР»СЏРµРј РѕС‚СЃС‚СѓРїС‹
        $('.preview-container').css('margin-right', margin + 'px');
        $('.ai-chatbot-toggle').css('margin-right', margin + 'px');
        
        // РћРїСЂРµРґРµР»СЏРµРј С†РІРµС‚Р°
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

        // РџРѕРєР°Р·С‹РІР°РµРј/СЃРєСЂС‹РІР°РµРј РІС‹Р±РѕСЂ РїРѕР»СЊР·РѕРІР°С‚РµР»СЊСЃРєРёС… С†РІРµС‚РѕРІ
        $('#custom-colors').toggle(colorScheme === 'custom');
        
        // РћР±РЅРѕРІР»СЏРµРј Р·РЅР°С‡РµРЅРёСЏ РїРѕР»РµР№ С†РІРµС‚Р°
        if (colorScheme !== 'custom') {
            $('#ai_chatbot_primary_color').val(primaryColor);
            $('#ai_chatbot_secondary_color').val(secondaryColor);
        }
        
        const botNameColor = $('#ai_chatbot_bot_name_color').val();
        
        // РџСЂРёРјРµРЅСЏРµРј С†РІРµС‚Р°
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
        
        // РћР±РЅРѕРІР»СЏРµРј РѕСЃС‚Р°Р»СЊРЅС‹Рµ СЌР»РµРјРµРЅС‚С‹
        const name = $('#ai_chatbot_bot_name').val();
        const message = $('#ai_chatbot_welcome_message').val();
        const avatarSize = $('#ai_chatbot_avatar_size').val();
        const fontSize = $('#ai_chatbot_font_size').val();
        const fontFamily = $('#ai_chatbot_font_family').val();
        const status = $('#ai_chatbot_custom_text_online').val();
        
        // РћР±РЅРѕРІР»СЏРµРј СЂР°Р·РјРµСЂ Р°РІР°С‚Р°СЂР°
        $('#avatar-preview img').css({
            'width': avatarSize + 'px',
            'height': avatarSize + 'px'
        });
        
        // РћР±РЅРѕРІР»СЏРµРј С€СЂРёС„С‚
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
        
        // РћР±РЅРѕРІР»СЏРµРј С‚РµРєСЃС‚С‹
        $('.preview-name').css('color', botNameColor).text(name);
        $('.preview-status').text(status);
        $('.chat-preview .message.bot .content').text(message);
    }

    // РћР±СЂР°Р±РѕС‚С‡РёРєРё РґР»СЏ РѕР±РЅРѕРІР»РµРЅРёСЏ РїСЂРµРІСЊСЋ
    $('#ai_chatbot_bot_name, #ai_chatbot_welcome_message, #ai_chatbot_avatar_size, #ai_chatbot_font_size, #ai_chatbot_color_scheme, #ai_chatbot_font_family, #ai_chatbot_custom_text_online, #ai_chatbot_margin').on('input change', updatePreview);
    
    $('#ai_chatbot_primary_color, #ai_chatbot_secondary_color, #ai_chatbot_bot_name_color').on('input', updatePreview);
    
    // РРЅРёС†РёР°Р»РёР·Р°С†РёСЏ РїСЂРµРІСЊСЋ
    updatePreview();
});
</script>

<?php
// Р”РѕР±Р°РІР»СЏРµРј РѕР±СЂР°Р±РѕС‚С‡РёРєРё AJAX
add_action('wp_ajax_test_openai_connection', 'ai_chatbot_test_connection');
add_action('wp_ajax_ai_chatbot_clear_cache', 'ai_chatbot_clear_cache');
add_action('wp_ajax_ai_chatbot_test_email', 'ai_chatbot_test_email');

function ai_chatbot_test_email() {
    check_ajax_referer('ai_chatbot_test_email', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    $email = sanitize_email($_POST['email']);
    if (!is_email($email)) {
        wp_send_json_error('РќРµРІРµСЂРЅС‹Р№ С„РѕСЂРјР°С‚ email');
        return;
    }
    
    $subject = 'РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ AI ChatBot';
    $message = "Р­С‚Рѕ С‚РµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚ РїР»Р°РіРёРЅР° AI ChatBot.\n\n";
    $message .= "Р•СЃР»Рё РІС‹ РїРѕР»СѓС‡РёР»Рё СЌС‚Рѕ РїРёСЃСЊРјРѕ, Р·РЅР°С‡РёС‚ РЅР°СЃС‚СЂРѕР№РєРё email СЂР°Р±РѕС‚Р°СЋС‚ РєРѕСЂСЂРµРєС‚РЅРѕ.\n\n";
    $message .= "Р”Р°С‚Р° Рё РІСЂРµРјСЏ РѕС‚РїСЂР°РІРєРё: " . current_time('mysql') . "\n";
    $message .= "РЎР°Р№С‚: " . get_bloginfo('name') . " (" . get_site_url() . ")";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success('РўРµСЃС‚РѕРІРѕРµ РїРёСЃСЊРјРѕ РѕС‚РїСЂР°РІР»РµРЅРѕ');
    } else {
        wp_send_json_error('РћС€РёР±РєР° РїСЂРё РѕС‚РїСЂР°РІРєРµ С‚РµСЃС‚РѕРІРѕРіРѕ РїРёСЃСЊРјР°');
    }
}

function ai_chatbot_test_connection() {
    check_ajax_referer('test_openai_connection', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    // РўРµСЃС‚РёСЂСѓРµРј РїРѕРґРєР»СЋС‡РµРЅРёРµ Рє API
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
        wp_send_json_error('РќРµРґРѕСЃС‚Р°С‚РѕС‡РЅРѕ РїСЂР°РІ');
        return;
    }
    
    // РћС‡РёС‰Р°РµРј РєРµС€ WordPress
    wp_cache_flush();
    
    // РћС‡РёС‰Р°РµРј РєРµС€ РѕРїС†РёР№
    delete_transient('ai_chatbot_settings');
    
    // РџСЂРѕРІРµСЂСЏРµРј РЅР°Р»РёС‡РёРµ РєР»Р°СЃСЃР° РіРµРЅРµСЂР°С‚РѕСЂР° CSS
    if (!class_exists('AI_ChatBot_CSS_Generator')) {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-css-generator.php';
    }
    
    // Р“РµРЅРµСЂРёСЂСѓРµРј Рё СЃРѕС…СЂР°РЅСЏРµРј РЅРѕРІС‹Р№ CSS
    $css_generator = new AI_ChatBot_CSS_Generator();
    $css_url = $css_generator->save();
    
    // РћС‡РёС‰Р°РµРј OPcache РµСЃР»Рё РѕРЅ РІРєР»СЋС‡РµРЅ
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    wp_send_json_success('Cache cleared successfully');
}


