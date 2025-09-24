(function($) {
    'use strict';

    $(function() {
        var $settingsForm = $('form').filter(function() {
            return $(this).find('#ai_chatbot_enabled').length > 0;
        }).first();

        if ($settingsForm.length && !$settingsForm.hasClass('ai-chatbot-settings-form')) {
            $settingsForm.addClass('ai-chatbot-settings-form');
        }

        var $colorScheme = $('#ai_chatbot_color_scheme');
        var $customColors = $('#custom-colors');

        function toggleCustomColors() {
            if ($customColors.length === 0) {
                return;
            }

            $customColors.toggle($colorScheme.val() === 'custom');
        }

        if ($colorScheme.length) {
            toggleCustomColors();
            $colorScheme.on('change', toggleCustomColors);
        }
    });
})(jQuery);
