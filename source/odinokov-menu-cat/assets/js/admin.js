(function($) {
    'use strict';

    var $parentCat = $('#omc-parent-cat');
    var $subcats   = $('#omc-subcats');
    var $selectAll = $('#omc-select-all');
    var $deselectAll = $('#omc-deselect-all');
    var $selectSep = $('#omc-select-sep');

    function syncHidden() {
        var menuId = $('#omc-menu-id').val();
        var parentId = $parentCat.val();
        $('#omc-menu-id-selected').val(menuId);
        $('#omc-menu-id-all').val(menuId);
        $('#omc-parent-cat-selected').val(parentId);

        var $container = $('#omc-checked-container');
        $container.empty();
        $subcats.find('input[type="checkbox"]:checked').each(function() {
            $container.append('<input type="hidden" name="omc_cats[]" value="' + $(this).val() + '">');
        });
    }

    function loadSubcats(termId) {
        if (!termId) {
            $subcats.html('<p style="color:#888;margin:0;">' + OMC.empty + '</p>');
            $selectAll.hide();
            $deselectAll.hide();
            $selectSep.hide();
            return;
        }

        $subcats.addClass('loading').html('<p style="color:#888;margin:0;">' + OMC.loading + '</p>');

        $.post(ajaxurl, {
            action: 'omc_load_subcats',
            nonce: OMC.nonce,
            term_id: termId
        }, function(res) {
            $subcats.removeClass('loading');
            if (res.success) {
                $subcats.html(res.data.html);
                $selectAll.show();
                $deselectAll.show();
                $selectSep.show();
            } else {
                $subcats.html('<p style="color:#c00;margin:0;">' + OMC.error + '</p>');
                $selectAll.hide();
                $deselectAll.hide();
                $selectSep.hide();
            }
        }).fail(function() {
            $subcats.removeClass('loading').html('<p style="color:#c00;margin:0;">' + OMC.error + '</p>');
        });
    }

    $parentCat.on('change', function() {
        loadSubcats($(this).val());
    });

    $selectAll.on('click', function(e) {
        e.preventDefault();
        $subcats.find('input[type="checkbox"]').prop('checked', true);
    });

    $deselectAll.on('click', function(e) {
        e.preventDefault();
        $subcats.find('input[type="checkbox"]').prop('checked', false);
    });

    $('form').on('submit', function() {
        syncHidden();
    });

    if ($parentCat.val()) {
        loadSubcats($parentCat.val());
    }
})(jQuery);
