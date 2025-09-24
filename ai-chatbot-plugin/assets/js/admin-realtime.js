jQuery(document).ready(function($) {
    var $form = $('.ai-chatbot-settings-form');

    if (!$form.length) {
        return;
    }

    function assignSetting(target, key, value) {
        if (key.indexOf('[') === -1) {
            target[key] = value;
            return;
        }

        var parts = key.replace(/\]/g, '').split('[');
        var cursor = target;

        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];
            if (i === parts.length - 1) {
                cursor[part] = value;
            } else {
                if (typeof cursor[part] !== 'object' || cursor[part] === null) {
                    cursor[part] = {};
                }
                cursor = cursor[part];
            }
        }
    }

    function collectSettings() {
        var settings = {};

        $form.find('input, select, textarea').each(function() {
            var $field = $(this);

            if ($field.is(':disabled')) {
                return;
            }

            var name = $field.attr('name');
            if (!name || name.indexOf('ai_chatbot_') !== 0) {
                return;
            }

            var key = name.replace('ai_chatbot_', '');
            var value;

            if ($field.is(':checkbox')) {
                value = $field.is(':checked') ? ($field.val() ? $field.val() : '1') : '0';
            } else if ($field.is(':radio')) {
                if (!$field.is(':checked')) {
                    return;
                }
                value = $field.val();
            } else {
                value = $field.val();
            }

            assignSetting(settings, key, value);
        });

        return settings;
    }

    function handleRealtimeChange() {
        var settings = collectSettings();
        applySettingsToWidget(settings);
        saveSettings(settings);
    }

    $form.on('change input', 'input, select, textarea', handleRealtimeChange);

    applySettingsToWidget(collectSettings());

    function applySettingsToWidget(settings) {
        var $targets = $('.ai-chatbot-preview, .ai-chatbot-container');

        var gradients = {
            'default': ['#667eea', '#764ba2'],
            'blue': ['#2563eb', '#1d4ed8'],
            'green': ['#059669', '#047857'],
            'purple': ['#7c3aed', '#5b21b6']
        };

        $targets.each(function() {
            var $container = $(this);

            // Применяем отступы
            if (settings.margin) {
                $container.css({
                    'bottom': settings.margin + 'px',
                    'right': settings.margin + 'px'
                });
            }
            
            // Применяем цвета
            var gradient = '';

            if (settings.color_scheme === 'custom') {
                if (settings.primary_color && settings.secondary_color) {
                    gradient = `linear-gradient(135deg, ${settings.primary_color} 0%, ${settings.secondary_color} 100%)`;
                }
            } else if (settings.color_scheme && gradients[settings.color_scheme]) {
                var preset = gradients[settings.color_scheme];
                gradient = `linear-gradient(135deg, ${preset[0]} 0%, ${preset[1]} 100%)`;
            }

            var gradientTargets = $container.find('.ai-chatbot-toggle, .ai-chatbot-header, .ai-chatbot-send, .ai-chatbot-message.user .ai-chatbot-message-content');

            if (gradient) {
                gradientTargets.css('background', gradient);
            } else {
                gradientTargets.css('background', '');
            }
            
            // Цвет имени бота
            if (settings.bot_name_color) {
                $container.find('.ai-chatbot-header h3').css('color', settings.bot_name_color);
            }
            
            // Размер виджета
            if (settings.widget_size) {
                var size = parseInt(settings.widget_size, 10);
                if (!isNaN(size)) {
                    $container.find('.ai-chatbot-toggle').css({
                        'width': size + 'px',
                        'height': size + 'px'
                    });
                }
            }
            
            // Размер окна
            if (settings.window_size) {
                var windowSize = {
                    'small': { width: '300px', height: '400px' },
                    'default': { width: '380px', height: '500px' },
                    'large': { width: '450px', height: '600px' }
                };
                if (windowSize[settings.window_size]) {
                    $container.find('.ai-chatbot-window').css(windowSize[settings.window_size]);
                }
            }
            
            // Размер аватара
            if (settings.avatar_size) {
                var avatarSize = parseInt(settings.avatar_size, 10);
                if (!isNaN(avatarSize)) {
                    $container.find('.ai-chatbot-avatar').css({
                        'width': avatarSize + 'px',
                        'height': avatarSize + 'px'
                    });
                }
            }

            // Шрифт
            if (settings.font_family && settings.font_size) {
                const fonts = {
                    'system-default': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    'roboto': 'Roboto, sans-serif',
                    'open-sans': '"Open Sans", sans-serif',
                    'lato': 'Lato, sans-serif'
                };
                
                var fontSize = parseInt(settings.font_size, 10);

                $container.css({
                    'font-family': fonts[settings.font_family] || fonts['system-default'],
                    'font-size': (isNaN(fontSize) ? settings.font_size : fontSize) + 'px'
                });
            }
        });
    }

    // Функция для сохранения настроек через AJAX
    function saveSettings(settings) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_save_realtime_settings',
                nonce: ai_chatbot_admin.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    // Принудительно обновляем все виджеты на странице
                    $('.ai-chatbot-container').each(function() {
                        applySettingsToWidget(settings);
                    });
                }
            }
        });
    }
});
