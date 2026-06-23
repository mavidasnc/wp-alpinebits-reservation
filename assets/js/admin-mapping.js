/**
 * WP AlpineBits Reservation — Admin JS
 *
 * Gestisce:
 * 1. Toggle visibilità campo testo per valori costanti nel tab Mapping.
 * 2. Ricarica delle select CF7 al cambio del form nel tab Mapping (AJAX).
 * 3. Pulsante "Testa connessione" nel tab Connessione (AJAX).
 * 4. Pulsante "Controlla aggiornamenti" nel tab Connessione (AJAX).
 * 5. Pulsante "Reinvia" nel tab Invii (AJAX).
 * 6. Toggle dettaglio riga nel tab Invii.
 */
/* global wparAdmin, jQuery */
(function ( $ ) {
	'use strict';

	var ajax   = wparAdmin.ajaxUrl;
	var nonce  = wparAdmin.nonce;
	var i18n   = wparAdmin.i18n;

	// -----------------------------------------------------------------------
	// 1. Toggle "Valore costante" / "Raccolta multi-campo"
	// -----------------------------------------------------------------------
	$( document ).on( 'change', '.wpar-source-select', function () {
		var $select      = $( this );
		var $td          = $select.closest( 'td' );
		var $constWrap   = $td.find( '.wpar-const-wrap' );
		var $collectWrap = $td.find( '.wpar-collect-wrap' );
		var val          = $select.val();

		if ( val === '__const' ) {
			$constWrap.show();
			$collectWrap.hide();
		} else if ( val === '__collect' ) {
			$constWrap.hide();
			$collectWrap.show();
			// Inizializza la UI tag se non ancora costruita.
			if ( $collectWrap.length && ! $collectWrap.find( '.wpar-tags-list' ).length ) {
				initCollectUI( $collectWrap );
			}
		} else {
			$constWrap.hide();
			$constWrap.find( '.wpar-const-input' ).val( '' );
			$collectWrap.hide();
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

				// Ricostruisce le source-select con i nuovi campi CF7.
				$wrap.find( '.wpar-source-select' ).each( function () {
					var $sel        = $( this );
					var $td         = $sel.closest( 'td' );
					var currentVal  = $sel.val();
					var hasCollect  = $td.find( '.wpar-collect-wrap' ).length > 0;
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

					if ( hasCollect ) {
						options += '<option value="__collect"'
							+ ( currentVal === '__collect' ? ' selected' : '' )
							+ '>' + i18n.collect + '</option>';
					}

					$sel.html( options );

					// Reimposta la visibilità dei pannelli.
					var $constWrap   = $td.find( '.wpar-const-wrap' );
					var $collectWrap = $td.find( '.wpar-collect-wrap' );
					if ( $sel.val() !== '__const' ) { $constWrap.hide(); }
					if ( $sel.val() !== '__collect' ) { $collectWrap.hide(); }
				} );

				// Ricostruisce i tag input per la raccolta (array_int).
				$wrap.find( '.wpar-collect-wrap' ).each( function () {
					var $collectWrap = $( this );
					var $hidden      = $collectWrap.find( '.wpar-collect-select' );
					var currentVals  = $hidden.val() || [];
					var options      = '';

					$.each( fields, function ( name, label ) {
						var selected = currentVals.indexOf( name ) !== -1 ? ' selected' : '';
						options += '<option value="' + escAttr( name ) + '"' + selected + '>'
							+ escHtml( label )
							+ '</option>';
					} );

					$hidden.html( options );
					initCollectUI( $collectWrap );
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
	// 4. Controlla aggiornamenti
	// -----------------------------------------------------------------------
	$( '#wpar-check-version' ).on( 'click', function () {
		var $btn    = $( this );
		var $result = $( '#wpar-version-result' );

		$btn.prop( 'disabled', true ).text( i18n.versionChecking );
		$result.html( '' );

		$.post(
			ajax,
			{ action: 'wpar_check_version', nonce: nonce },
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Controlla aggiornamenti' );

				if ( ! response.success ) {
					$result.html(
						'<span class="wpar-msg wpar-msg--fail">'
						+ escHtml( ( response.data && response.data.message ) || i18n.versionError )
						+ '</span>'
					);
					return;
				}

				var data = response.data;

				if ( data.update_available ) {
					var html = '<span class="wpar-version-available">'
						+ escHtml( i18n.versionAvailable ) + ' v' + escHtml( data.latest_version )
						+ '</span>';

					if ( data.update_url ) {
						html += ' <a href="' + escAttr( data.update_url ) + '" class="button button-primary wpar-update-link">'
							+ escHtml( i18n.versionUpdate )
							+ '</a>';
					}

					$result.html( html );
				} else {
					$result.html(
						'<span class="wpar-version-ok">' + escHtml( i18n.versionUpToDate ) + '</span>'
					);
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Controlla aggiornamenti' );
			$result.html( '<span class="wpar-msg wpar-msg--fail">' + escHtml( i18n.versionError ) + '</span>' );
		} );
	} );

	// -----------------------------------------------------------------------
	// 5. Reinvio submission
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
	// 6. Toggle dettaglio riga
	// -----------------------------------------------------------------------
	$( document ).on( 'click', '.wpar-toggle-detail', function () {
		var targetId = $( this ).data( 'target' );
		$( '#' + targetId ).toggle();
	} );

	// -----------------------------------------------------------------------
	// 7. Seleziona/deseleziona tutti (tab Moduli)
	// -----------------------------------------------------------------------
	$( '#wpar-select-all-forms' ).on( 'change', function () {
		var checked = $( this ).prop( 'checked' );
		$( '.wpar-form-checkbox' ).prop( 'checked', checked );
	} );

	// -----------------------------------------------------------------------
	// 8. Tag input per la raccolta multi-campo (array_int)
	// -----------------------------------------------------------------------

	/**
	 * Inizializza (o re-inizializza) la UI a tag per un .wpar-collect-wrap.
	 * Legge i valori già selezionati dal hidden select e costruisce:
	 *   .wpar-tags-list  → riga con un tag per ogni campo già scelto
	 *   .wpar-tags-add   → riga separata con la dropdown per aggiungere nuovi campi
	 */
	function initCollectUI( $wrap ) {
		var $hidden = $wrap.find( '.wpar-collect-select' );

		// Rimuove eventuale UI precedente (per re-init dopo AJAX).
		$wrap.find( '.wpar-tags-list, .wpar-tags-add' ).remove();

		// Lista tag dai valori selezionati.
		var $tagsList = $( '<div class="wpar-tags-list"></div>' );
		$hidden.find( 'option' ).each( function () {
			if ( $( this ).prop( 'selected' ) ) {
				$tagsList.append( makeTag( $( this ).val(), $( this ).text() ) );
			}
		} );

		// Dropdown con i campi non ancora selezionati (riga separata).
		var $addRow   = $( '<div class="wpar-tags-add"></div>' );
		var $dropdown = $( '<select class="wpar-tags-dropdown"></select>' );
		$dropdown.append( $( '<option>', { value: '', text: i18n.addField || '+ Aggiungi campo...' } ) );
		$hidden.find( 'option' ).each( function () {
			if ( ! $( this ).prop( 'selected' ) ) {
				$dropdown.append( $( '<option>', { value: $( this ).val(), text: $( this ).text() } ) );
			}
		} );
		$addRow.append( $dropdown );

		$hidden.before( $tagsList ).before( $addRow );
	}

	/** Crea un elemento tag (pill) per il campo specificato. */
	function makeTag( val, label ) {
		return $( '<span class="wpar-tag"></span>' )
			.attr( 'data-value', val )
			.append( $( '<span class="wpar-tag-label"></span>' ).text( label ) )
			.append(
				$( '<button type="button" class="wpar-tag-remove">&times;</button>' )
					.attr( 'data-value', String( val ) )
					.attr( 'aria-label', 'Rimuovi' )
			);
	}

	// Inizializza tutti i collect-wrap al caricamento della pagina.
	$( '.wpar-collect-wrap' ).each( function () {
		initCollectUI( $( this ) );
	} );

	// Aggiunge un campo tramite la dropdown.
	$( document ).on( 'change', '.wpar-tags-dropdown', function () {
		var val = $( this ).val();
		if ( ! val ) {
			return;
		}

		var $dropdown = $( this );
		var $wrap     = $dropdown.closest( '.wpar-collect-wrap' );
		var $hidden   = $wrap.find( '.wpar-collect-select' );
		var $tagsList = $wrap.find( '.wpar-tags-list' );
		var label     = $dropdown.find( 'option:selected' ).text();

		$tagsList.append( makeTag( val, label ) );
		$hidden.find( 'option[value="' + escAttr( val ) + '"]' ).prop( 'selected', true );
		$dropdown.find( 'option[value="' + escAttr( val ) + '"]' ).remove();
		$dropdown.val( '' );
	} );

	// Rimuove un campo tramite il pulsante ×.
	$( document ).on( 'click', '.wpar-tag-remove', function () {
		var val       = String( $( this ).data( 'value' ) );
		var $tag      = $( this ).closest( '.wpar-tag' );
		var $wrap     = $tag.closest( '.wpar-collect-wrap' );
		var $hidden   = $wrap.find( '.wpar-collect-select' );
		var $dropdown = $wrap.find( '.wpar-tags-dropdown' );
		var $opt      = $hidden.find( 'option[value="' + escAttr( val ) + '"]' );

		$opt.prop( 'selected', false );
		$dropdown.append( $( '<option>', { value: val, text: $opt.text() } ) );
		$tag.remove();
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
