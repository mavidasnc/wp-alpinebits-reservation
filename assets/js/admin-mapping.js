/**
 * WP AlpineBits Reservation — Admin JS
 *
 * Gestisce:
 * 1. Toggle visibilità campo testo per valori costanti nel tab Mapping.
 * 2. Ricarica delle select CF7 al cambio del form nel tab Mapping (AJAX).
 * 3. Pulsante "Testa connessione" nel tab Connessione (AJAX).
 * 4. Pulsante "Reinvia" nel tab Invii (AJAX).
 * 5. Toggle dettaglio riga nel tab Invii.
 */
/* global wparAdmin, jQuery */
(function ( $ ) {
	'use strict';

	var ajax   = wparAdmin.ajaxUrl;
	var nonce  = wparAdmin.nonce;
	var i18n   = wparAdmin.i18n;

	// -----------------------------------------------------------------------
	// 1. Toggle "Valore costante"
	// -----------------------------------------------------------------------
	$( document ).on( 'change', '.wpar-source-select', function () {
		var $select   = $( this );
		var $constWrap = $select.closest( 'td' ).find( '.wpar-const-wrap' );

		if ( $select.val() === '__const' ) {
			$constWrap.show();
		} else {
			$constWrap.hide();
			$constWrap.find( '.wpar-const-input' ).val( '' );
		}
	} );

	// -----------------------------------------------------------------------
	// 2. Cambio form nel tab Mapping — ricarica campi CF7 via AJAX
	// -----------------------------------------------------------------------
	$( '#wpar-form-switcher' ).on( 'change', function () {
		var formId  = $( this ).val();
		var $wrap   = $( '#wpar-mapping-table-wrap' );
		var $spinner = $( '.wpar-spinner' );
		var $hidden  = $( 'input[name="wpar_mapping_form_id"]' );

		$hidden.val( formId );
		$spinner.show();
		$wrap.css( 'opacity', '0.4' );

		$.post(
			ajax,
			{
				action:  'wpar_get_form_fields',
				nonce:   nonce,
				form_id: formId
			},
			function ( response ) {
				if ( ! response.success ) {
					alert( response.data && response.data.message
						? response.data.message
						: 'Errore nel caricamento dei campi.' );
					$spinner.hide();
					$wrap.css( 'opacity', '1' );
					return;
				}

				var fields = response.data.fields || {};

				// Ricostruisce le select con i nuovi campi.
				$wrap.find( '.wpar-source-select' ).each( function () {
					var $sel        = $( this );
					var currentVal  = $sel.val();
					var options     = '<option value="">' + i18n.none + '</option>';

					$.each( fields, function ( name, label ) {
						var selected = ( name === currentVal ) ? ' selected' : '';
						options += '<option value="' + escAttr( name ) + '"' + selected + '>'
							+ escHtml( label )
							+ '</option>';
					} );

					options += '<option value="__const"'
						+ ( currentVal === '__const' ? ' selected' : '' )
						+ '>' + i18n.const + '</option>';

					$sel.html( options );

					// Reimposta la visibilità del campo costante.
					var $constWrap = $sel.closest( 'td' ).find( '.wpar-const-wrap' );
					if ( $sel.val() !== '__const' ) {
						$constWrap.hide();
					}
				} );

				$spinner.hide();
				$wrap.css( 'opacity', '1' );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// 3. Test connessione
	// -----------------------------------------------------------------------
	$( '#wpar-test-connection' ).on( 'click', function () {
		var $btn    = $( this );
		var $result = $( '#wpar-test-result' );

		$btn.prop( 'disabled', true ).text( i18n.loading );
		$result.text( '' ).removeClass( 'wpar-msg--ok wpar-msg--fail' );

		$.post(
			ajax,
			{ action: 'wpar_test_connection', nonce: nonce },
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Testa connessione' );

				if ( response.success ) {
					$result
						.addClass( 'wpar-msg wpar-msg--ok' )
						.text( response.data.message || i18n.testOk );
				} else {
					$result
						.addClass( 'wpar-msg wpar-msg--fail' )
						.text( ( response.data && response.data.message ) || i18n.testFail );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Testa connessione' );
			$result.addClass( 'wpar-msg wpar-msg--fail' ).text( i18n.testFail );
		} );
	} );

	// -----------------------------------------------------------------------
	// 4. Reinvio submission
	// -----------------------------------------------------------------------
	$( document ).on( 'click', '.wpar-resend-btn', function () {
		var $btn   = $( this );
		var rowId  = $btn.data( 'id' );

		if ( ! rowId ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( i18n.loading );

		$.post(
			ajax,
			{ action: 'wpar_resend', nonce: nonce, row_id: rowId },
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Reinvia' );

				if ( response.success ) {
					// Aggiorna il badge nella riga senza ricaricare la pagina.
					var $row   = $btn.closest( 'tr.wpar-log-row' );
					var $badge = $row.find( '.wpar-badge' );
					$badge
						.removeClass( 'wpar-badge--error wpar-badge--pending' )
						.addClass( 'wpar-badge--success' )
						.text( 'Successo' );

					// Mostra feedback temporaneo.
					$btn.after(
						'<span class="wpar-msg wpar-msg--ok" style="margin-left:6px;">'
						+ i18n.resendOk
						+ '</span>'
					);
					setTimeout( function () {
						$btn.siblings( '.wpar-msg' ).fadeOut( 400, function () {
							$( this ).remove();
						} );
					}, 3000 );
				} else {
					var msg = ( response.data && response.data.message )
						? response.data.message
						: i18n.resendFail;

					$btn.after(
						'<span class="wpar-msg wpar-msg--fail" style="margin-left:6px;">'
						+ escHtml( msg )
						+ '</span>'
					);
					setTimeout( function () {
						$btn.siblings( '.wpar-msg' ).fadeOut( 400, function () {
							$( this ).remove();
						} );
					}, 5000 );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Reinvia' );
		} );
	} );

	// -----------------------------------------------------------------------
	// 5. Toggle dettaglio riga
	// -----------------------------------------------------------------------
	$( document ).on( 'click', '.wpar-toggle-detail', function () {
		var targetId = $( this ).data( 'target' );
		$( '#' + targetId ).toggle();
	} );

	// -----------------------------------------------------------------------
	// 6. Seleziona/deseleziona tutti (tab Moduli)
	// -----------------------------------------------------------------------
	$( '#wpar-select-all-forms' ).on( 'change', function () {
		var checked = $( this ).prop( 'checked' );
		$( '.wpar-form-checkbox' ).prop( 'checked', checked );
	} );

	// -----------------------------------------------------------------------
	// Utility: escape HTML e attributi (sicurezza output nel DOM)
	// -----------------------------------------------------------------------
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escAttr( str ) {
		return escHtml( str );
	}

}( jQuery ) );
