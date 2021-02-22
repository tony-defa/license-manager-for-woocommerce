(function ( $ ) {
    $( document ).ready( function () {
        const licenseKeysCheckbox = $( '#lmfwc_licensed_product' );

        registerClickHandlers( licenseKeysCheckbox );
        registerChangeHandlers();

        if (license.product_downloads && licenseKeysCheckbox.is( ':checked' )) {
            modifyProductDownloadsTable();
        } else {
            resetProductDownloadsTable();
        }
    } );

    function registerClickHandlers( licenseKeysCheckbox ) {
        $( '.lmfwc-license-key-show' ).click( function () {
            showLicenseKey( this );
        } );

        $( document ).on( 'click', '.license_key .lmfwc-placeholder, .lmfwc-license-list .lmfwc-placeholder', function ( e ) {
            copyLicenseKeyToClipboard( this, e );
        } );

        $( '.lmfwc-license-key-hide' ).click( function () {
            hideLicenseKey( this );
        } );

        $( '.lmfwc-license-keys-show-all' ).click( function () {
            showAllLicenseKeys( this );
        } );

        $( '.lmfwc-license-keys-hide-all' ).click( function () {
            hideAllLicenseKeys( this );
        } );

        if (license.product_downloads) {
            $( '#woocommerce-product-data .downloadable_files table .insert' ).click( function () {
                if (licenseKeysCheckbox.is( ':checked' )) {
                    modifyProductDownloadsTable( true );
                }
            } );

            $( '#woocommerce-product-data' ).on( 'click', '.downloadable_files table tr .delete', function () {
                if (licenseKeysCheckbox.is( ':checked' )) {
                    resetProductDownloadsTable();
                }
            } );
        }
    }

    function registerChangeHandlers() {
        if (license.product_downloads) {
            $( document ).on( 'change', '#lmfwc_licensed_product', function () {
                if ($( this ).is( ':checked' )) {
                    $( '.downloadable_files table tbody' ).find( 'tr:gt(0)' ).remove();
                    modifyProductDownloadsTable();
                } else {
                    resetProductDownloadsTable();
                }
            } );
        }
    }

    function showLicenseKey( el ) {
        const licenseKeyId = parseInt( $( el ).data( 'id' ) );
        const code         = $( el ).closest( '.license_key' ).find( '.lmfwc-placeholder' );

        showLicenseKeyLoadingSpinner( el );

        const data = {
            action: 'lmfwc_show_license_key',
            show: license.show,
            id: licenseKeyId
        };

        $.post( ajaxurl, data, function () {
        } ).done( function ( response ) {
            code.removeClass( 'empty' );
            code.text( response );
        } ).fail( function ( response ) {
            console.log( response );
        } ).always( function () {
            hideLicenseKeyLoadingSpinner( el );
        } );
    }

    function hideLicenseKey( el ) {
        const code = $( el ).closest( '.license_key' ).find( '.lmfwc-placeholder' );

        code.text( '' );
        code.addClass( 'empty' );
    }

    function showAllLicenseKeys( el ) {
        const licenseKeyIds = getAllLicenseKeyIds( el );

        showLicenseKeyLoadingSpinner( el );

        const data = {
            action: 'lmfwc_show_all_license_keys',
            show_all: license.show_all,
            ids: JSON.stringify( licenseKeyIds )
        };

        $.post( ajaxurl, data, function () {
        } ).done( function ( response ) {
            for (const id in response) {
                if (!response.hasOwnProperty( id )) {
                    continue;
                }

                const licenseKey = $( '.lmfwc-placeholder[data-id="' + id + '"]' );

                licenseKey.removeClass( 'empty' );
                licenseKey.text( response[id] );
            }
        } ).fail( function ( response ) {
            console.log( response );
        } ).always( function () {
            hideLicenseKeyLoadingSpinner( el );
        } );
    }

    function hideAllLicenseKeys( el ) {
        const licenseKeyIds = getAllLicenseKeyIds( el );

        $( licenseKeyIds ).each( function ( id, value ) {
            const licenseKey = $( '.lmfwc-placeholder[data-id="' + value + '"]' );

            licenseKey.addClass( 'empty' );
            licenseKey.text( '' );
        } );

        for (const id in licenseKeyIds) {
            if (!licenseKeyIds.hasOwnProperty( id )) {
                continue;
            }

            const licenseKey = $( '.lmfwc-placeholder[data-id="' + id + '"]' );

            licenseKey.addClass( 'empty' );
            licenseKey.text( '' );
        }
    }

    function getAllLicenseKeyIds( el ) {
        const licenseKeyIds = [];
        const codeList      = $( el ).closest( 'td' ).find( '.lmfwc-license-list li' );

        codeList.each( function ( id, li ) {
            licenseKeyIds.push( parseInt( $( li ).find( '.lmfwc-placeholder' ).data( 'id' ) ) );
        } );

        return licenseKeyIds;
    }

    function copyLicenseKeyToClipboard( el, e ) {
        const str = $( el ).text();

        if (str.length === 0) {
            return;
        }

        const textArea = document.createElement( 'textarea' );
        textArea.value = str;
        textArea.setAttribute( 'readonly', '' );
        textArea.style.position = 'absolute';
        textArea.style.left     = '-9999px';
        document.body.appendChild( textArea );
        const selected = document.getSelection().rangeCount > 0 ? document.getSelection().getRangeAt( 0 ) : false;
        textArea.select();
        document.execCommand( 'copy' );
        document.body.removeChild( textArea );
        if (selected) {
            document.getSelection().removeAllRanges();
            document.getSelection().addRange( selected );
        }

        // Display info
        const copied = document.createElement( 'div' );
        copied.classList.add( 'lmfwc-clipboard' );
        copied.style.position = 'absolute';
        copied.style.left     = e.clientX.toString() + 'px';
        copied.style.top      = (window.pageYOffset + e.clientY).toString() + 'px';
        copied.innerText      = document.querySelector( '.lmfwc-txt-copied-to-clipboard' ).innerText.toString();
        document.body.appendChild( copied );

        setTimeout( function () {
            copied.style.opacity = '0';
        }, 700 );
        setTimeout( function () {
            document.body.removeChild( copied );
        }, 1500 );
    }

    function showLicenseKeyLoadingSpinner( el ) {
        $( el ).closest( 'td' ).find( '.lmfwc-spinner' ).css( 'opacity', 1 );
    }

    function hideLicenseKeyLoadingSpinner( el ) {
        $( el ).closest( 'td' ).find( '.lmfwc-spinner' ).css( 'opacity', 0 );
    }

    function modifyProductDownloadsTable( insertButton = false ) {
        let productDownloadsTableRowCount = $( '.downloadable_files table tbody tr' ).length;

        if ((!insertButton && productDownloadsTableRowCount >= 1) || (insertButton && productDownloadsTableRowCount >= 0)) {
            $( '.downloadable_files table tfoot' ).css( 'display', 'none' );
        } else {
            resetProductDownloadsTable();
        }
    }

    function resetProductDownloadsTable() {
        $( '.downloadable_files table tfoot' ).css( 'display', 'table-footer-group' );
    }
})( jQuery );
