(function ($) {
    'use strict';
    $(function () {
        var $list = $('#wc-sale-discord-field-order');
        if (!$list.length) {
            return;
        }
        $list.sortable({
            axis: 'y',
            handle: '.wc-sale-discord-field-order-handle',
            placeholder: 'wc-sale-discord-sortable-placeholder',
        });
    });
})(jQuery);
