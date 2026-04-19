/* global jQuery, MUF */
( function ( $ ) {
	'use strict';

	function post( action, data ) {
		return $.post(
			MUF.ajax_url,
			$.extend( { action: action, nonce: MUF.nonce }, data )
		);
	}

	function getAreas() {
		return $( '.muf-area:checked' )
			.map( function () {
				return this.value;
			} )
			.get();
	}

	function log( line ) {
		var $log = $( '#muf-log' );
		$log.text( $log.text() + line + '\n' );
		$log.scrollTop( $log[ 0 ].scrollHeight );
	}

	function renderCounts( counts ) {
		var rows = '';
		var total = 0;
		Object.keys( counts ).forEach( function ( area ) {
			total += counts[ area ];
			rows +=
				'<tr><td><code>' +
				area +
				'</code></td><td>' +
				counts[ area ] +
				'</td></tr>';
		} );
		$( '#muf-scan-results' ).html(
			'<table class="widefat striped"><thead><tr><th>Area</th><th>Matching rows (approx.)</th></tr></thead><tbody>' +
				rows +
				'</tbody><tfoot><tr><th>Total</th><th>' +
				total +
				'</th></tr></tfoot></table>'
		);
	}

	function setProgress( area, result ) {
		var pct = result.total ? Math.round( ( ( result.next === -1 ? result.total : result.next ) / result.total ) * 100 ) : 100;
		$( '#muf-progress' ).html(
			'<div>' +
				area +
				': ' +
				( result.next === -1 ? result.total : result.next ) +
				' / ' +
				result.total +
				' (' +
				pct +
				'%)</div><div class="muf-progress-bar"><div class="muf-progress-bar-fill" style="width:' +
				pct +
				'%"></div></div>'
		);
	}

	$( '#muf-scan' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#muf-scan-results' ).html( MUF.i18n.scanning );
		post( 'muf_scan', {
			from: $( '#muf-from' ).val(),
			areas: getAreas(),
		} )
			.done( function ( res ) {
				if ( res && res.success ) {
					renderCounts( res.data.counts );
				} else {
					$( '#muf-scan-results' ).text( ( res && res.data && res.data.message ) || 'Error' );
				}
			} )
			.fail( function () {
				$( '#muf-scan-results' ).text( 'Request failed.' );
			} );
	} );

	function runArea( from, to, areas, idx, runId ) {
		if ( idx >= areas.length ) {
			log( MUF.i18n.done );
			return;
		}
		var area = areas[ idx ];
		log( '--- ' + area + ' ---' );

		function tick( offset ) {
			post( 'muf_batch', {
				from: from,
				to: to,
				area: area,
				offset: offset,
				run_id: runId,
			} )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						log( 'Error: ' + ( res && res.data && res.data.message ) );
						return;
					}
					runId = res.data.run_id || runId;
					setProgress( area, res.data );
					log(
						'processed=' +
							res.data.processed +
							' changed=' +
							res.data.changed +
							' next=' +
							res.data.next
					);
					if ( res.data.next === -1 ) {
						runArea( from, to, areas, idx + 1, runId );
					} else {
						tick( res.data.next );
					}
				} )
				.fail( function () {
					log( 'Request failed at offset ' + offset );
				} );
		}
		tick( 0 );
	}

	$( '#muf-run' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! window.confirm( MUF.i18n.confirm ) ) {
			return;
		}
		$( '#muf-log' ).text( '' );
		var from = $( '#muf-from' ).val();
		var to = $( '#muf-to' ).val();
		var areas = getAreas();
		runArea( from, to, areas, 0, '' );
	} );

	$( document ).on( 'click', '.muf-rollback', function ( e ) {
		e.preventDefault();
		if ( ! window.confirm( MUF.i18n.confirm_rb ) ) {
			return;
		}
		var run = $( this ).data( 'run' );
		post( 'muf_rollback', { run_id: run } ).done( function ( res ) {
			if ( res && res.success ) {
				log( 'Restored: ' + res.data.restored.join( ', ' ) );
			} else {
				log( 'Rollback failed.' );
			}
		} );
	} );

	$( document ).on( 'click', '.muf-discard', function ( e ) {
		e.preventDefault();
		if ( ! window.confirm( MUF.i18n.confirm_dis ) ) {
			return;
		}
		var run = $( this ).data( 'run' );
		post( 'muf_discard', { run_id: run } ).done( function ( res ) {
			if ( res && res.success ) {
				location.reload();
			}
		} );
	} );
} )( jQuery );
