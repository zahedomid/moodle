define(['jquery'], function ($) {
    return {
        init: function () {
            var bar = $('.attendance-bar');

            $('.attendance-names span', bar).hover(function(){
                var ul = $('.attendance-details ul', bar);
                var id = $(this).data('id');
                ul.hide();
                $('#attendance-detail-' + id, bar).show();
            });
        }
    };
});