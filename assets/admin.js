/**
 * Admin JS for Woo AI Blurb.
 * Handles the Generate button click on the product edit screen.
 */
( function ( $ ) {

	$( document ).ready( function () {

		$( '#woo-ai-blurb-btn' ).on( 'click', function () {
			var $btn    = $( this );
			var $status = $( '#woo-ai-blurb-status' );

			// Pull title and first category from the product form
			var title    = $( '#title' ).val();
			var category = $( '.product_cat-checklist input:checked' ).first()
				.closest( 'label' ).text().trim();

			if ( ! title ) {
				$status.text( 'Add a product title first.' );
				return;
			}

			$btn.prop( 'disabled', true ).text( 'Generating…' );
			$status.text( '' );

			$.post(
				wooAiBlurb.ajaxUrl,
				{
					action:     'woo_ai_blurb_generate',
					nonce:      wooAiBlurb.nonce,
					product_id: $( '#post_ID' ).val(),
					title:      title,
					category:   category,
				},
				function ( response ) {
					$btn.prop( 'disabled', false ).text( 'Generate Blurb' );

					if ( ! response.success ) {
						$status.text( 'Error: ' + response.data.message );
						return;
					}

					// Drop the blurb into the short description editor.
					// Works whether the classic editor or a plain textarea is active.
					if ( typeof tinyMCE !== 'undefined' && tinyMCE.get( 'excerpt' ) ) {
						tinyMCE.get( 'excerpt' ).setContent( response.data.blurb );
					} else {
						$( '#excerpt' ).val( response.data.blurb );
					}

					$status.text( 'Done! Review and edit as needed.' );
				}
			).fail( function () {
				$btn.prop( 'disabled', false ).text( 'Generate Blurb' );
				$status.text( 'Request failed. Check your connection.' );
			} );
		} );

	} );

} )( jQuery );
