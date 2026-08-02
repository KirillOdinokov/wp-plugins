jQuery(document).ready(function($) {
    $('.cromo-color-picker').wpColorPicker();

    var iconFrame;
    $('#cromo_upload_icon').on('click', function(e) {
        e.preventDefault();
        if (iconFrame) {
            iconFrame.open();
            return;
        }
        iconFrame = wp.media({
            title: 'Выберите иконку',
            button: { text: 'Использовать' },
            multiple: false,
            library: { type: 'image' }
        });
        iconFrame.on('select', function() {
            var attachment = iconFrame.state().get('selection').first().toJSON();
            $('#cromo_icon_url').val(attachment.url);
            $('#cromo_icon_preview').html('<img src="' + attachment.url + '" style="max-width:100px;max-height:100px;display:block;">');
            $('#cromo_remove_icon').show();
        });
        iconFrame.open();
    });

    $('#cromo_remove_icon').on('click', function(e) {
        e.preventDefault();
        $('#cromo_icon_url').val('');
        $('#cromo_icon_preview').html('');
        $(this).hide();
    });
});
