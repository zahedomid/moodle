define(['jquery'], function ($) {
    return {
        init: function () {
            var parent = $('.block_completion_progress');
            var el = parent.find('.popup');
            var height = 0;
            var bottom = 0;

            el.each(function () {
                if (!bottom) {
                    bottom = parseInt($(this).css('bottom'));
                }
                if (height) {
                    $(this).css('bottom', height);
                } else {
                    height += bottom;
                }
                var cur_height = parseInt($(this).outerHeight());
                height += cur_height + bottom;
            });

            setTimeout(function () {
                var time = 800;
                el.fadeOut(time, function () {
                    $(this).delay(time)
                        .removeClass('popup')
                        .slideDown(time);
                });
            }, 5000);
        }
    }
});