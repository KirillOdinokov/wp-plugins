/* global jQuery, wp */
( function ( $ ) {
    'use strict';

    var frame;

    $( function () {
        var $uploadBtn = $( '#schema-odinokov-logo-upload' );
        var $removeBtn = $( '#schema-odinokov-logo-remove' );
        var $idInput  = $( '#schema-odinokov-logo-id' );
        var $preview  = $( '.schema-odinokov-logo-preview' );

        if ( ! $uploadBtn.length || typeof wp === 'undefined' || ! wp.media ) {
            return;
        }

        $uploadBtn.on( 'click', function ( e ) {
            e.preventDefault();

            if ( frame ) {
                frame.open();
                return;
            }

            frame = wp.media( {
                title: 'Выберите логотип',
                button: { text: 'Использовать' },
                library: { type: 'image' },
                multiple: false
            } );

            frame.on( 'select', function () {
                var attachment = frame.state().get( 'selection' ).first().toJSON();
                $idInput.val( attachment.id );
                $preview.html( '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;display:block;" />' );
                $removeBtn.show();
            } );

            frame.open();
        } );

        $removeBtn.on( 'click', function ( e ) {
            e.preventDefault();
            $idInput.val( '0' );
            $preview.empty();
            $removeBtn.hide();
        } );
    } );
} )( jQuery );
