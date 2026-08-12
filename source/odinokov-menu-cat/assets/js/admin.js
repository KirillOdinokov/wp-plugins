(function($) {
    'use strict';

    var $parentCat = $('#omc-parent-cat');
    var $subcats   = $('#omc-subcats');
    var $selectAll = $('#omc-select-all');
    var $deselectAll = $('#omc-deselect-all');
    var $selectSep = $('#omc-select-sep');
    var $levelBtns = $('.omc-select-level');
    var $levelSeps = $('.omc-level-sep');

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

    function getMaxLevel() {
        var max = 0;
        $subcats.find('input[type="checkbox"]').each(function() {
            var lv = parseInt($(this).data('level'), 10) || 0;
            if (lv > max) max = lv;
        });
        return max;
    }

    function showLevelBtns(maxLevel) {
        $levelBtns.hide();
        $levelSeps.hide();
        if (maxLevel >= 2) {
            $levelBtns.filter('[data-level="2"]').show().prev('.omc-level-sep').show();
        }
        if (maxLevel >= 3) {
            $levelBtns.filter('[data-level="3"]').show().prev('.omc-level-sep').show();
        }
        if (maxLevel >= 4) {
            $levelBtns.filter('[data-level="4"]').show().prev('.omc-level-sep').show();
        }
        if (maxLevel >= 5) {
            $levelBtns.filter('[data-level="5"]').show().prev('.omc-level-sep').show();
        }
    }

    function loadSubcats(termId) {
        if (!termId) {
            $subcats.html('<p style="color:#888;margin:0;">' + OMC.empty + '</p>');
            $selectAll.hide();
            $deselectAll.hide();
            $selectSep.hide();
            $levelBtns.hide();
            $levelSeps.hide();
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
                showLevelBtns(getMaxLevel());
            } else {
                $subcats.html('<p style="color:#c00;margin:0;">' + OMC.error + '</p>');
                $selectAll.hide();
                $deselectAll.hide();
                $selectSep.hide();
                $levelBtns.hide();
                $levelSeps.hide();
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

    $levelBtns.on('click', function(e) {
        e.preventDefault();
        var level = parseInt($(this).data('level'), 10);
        $subcats.find('input[type="checkbox"]').each(function() {
            var cbLevel = parseInt($(this).data('level'), 10) || 0;
            $(this).prop('checked', cbLevel <= level);
        });
    });

    $('form').on('submit', function() {
        syncHidden();
    });

    if ($parentCat.val()) {
        loadSubcats($parentCat.val());
    }
})(jQuery);
