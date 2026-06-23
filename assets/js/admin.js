/**
 * WC Bulk Price Manager — admin.js
 */
( function () {
	'use strict';

	// ── Radio button active-class sync ────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wc-bpm-radio-group' ).forEach( function ( group ) {
			group.querySelectorAll( 'input[type="radio"]' ).forEach( function ( radio ) {
				radio.addEventListener( 'change', function () {
					group.querySelectorAll( '.wc-bpm-radio' ).forEach( function ( label ) {
						label.classList.remove( 'is-active' );
					} );
					radio.closest( '.wc-bpm-radio' ).classList.add( 'is-active' );
				} );
			} );
		} );
	} );

	// ── Batch processing ──────────────────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'wc-bpm-progress' );
		if ( ! wrap ) return; // only runs on the progress screen

		var jobId       = wrap.dataset.jobId;
		var totalPages  = parseInt( wrap.dataset.totalPages, 10 );
		var nonce       = wrap.dataset.nonce;
		var restBase    = wrap.dataset.restBase;
		var redirectUrl = wrap.dataset.redirectBase;

		var barEl      = document.getElementById( 'wc-bpm-bar' );
		var barLabelEl = document.getElementById( 'wc-bpm-bar-label' );
		var statusEl   = document.getElementById( 'wc-bpm-status' );
		var logEl      = document.getElementById( 'wc-bpm-log' );

		var totalUpdated = 0;

		// Validate — guard against bad data attrs.
		if ( ! jobId || isNaN( totalPages ) || totalPages < 1 ) {
			setStatus( 'Error: missing job data. Please try again.', 'error' );
			return;
		}

		function setBar( pct ) {
			barEl.style.width      = pct + '%';
			barLabelEl.textContent = Math.round( pct ) + '%';
		}

		function setStatus( msg, type ) {
			statusEl.textContent = msg;
			statusEl.style.color = ( type === 'error' ) ? '#b32d2e'
			                     : ( type === 'done'  ) ? '#1a8a1a'
			                     :                        '#50575e';
			statusEl.style.fontWeight = ( type === 'error' || type === 'done' ) ? '600' : 'normal';
		}

		function addLog( msg, type ) {
			var li = document.createElement( 'li' );
			li.textContent = msg;
			li.style.cssText = [
				'padding:8px 14px',
				'font-size:13px',
				'border-bottom:1px solid #e2e8f0',
				'line-height:1.55',
				type === 'success' ? 'color:#1a7a1a;background:#f0faf0'
				: type === 'error' ? 'color:#b32d2e;background:#fff5f5'
				:                    'color:#50575e',
			].join(';');
			logEl.appendChild( li );
			logEl.scrollTop = logEl.scrollHeight;
		}

		function processBatch( page ) {
			setStatus( 'Processing batch ' + page + ' of ' + totalPages + '…' );
			addLog( '⏳  Batch ' + page + ' / ' + totalPages + ' — sending…', 'info' );

			fetch( restBase + 'wc-bpm/v1/job/batch', {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body:    JSON.stringify( { job_id: jobId, page: page } ),
			} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {

				if ( ! data.success ) {
					addLog( '✗  Batch ' + page + ' failed: ' + ( data.message || 'Unknown error.' ), 'error' );
					setStatus( 'Stopped — error on batch ' + page + '.', 'error' );
					return;
				}

				var n    = data.updated || 0;
				totalUpdated += n;
				var pct = Math.round( ( page / totalPages ) * 100 );
				setBar( pct );

				addLog(
					'✓  Batch ' + page + ' complete — ' +
					n + ' product' + ( n !== 1 ? 's' : '' ) + ' updated. ' +
					'(' + totalUpdated + ' total so far)',
					'success'
				);

				if ( data.is_done || page >= totalPages ) {
					setBar( 100 );
					setStatus( '✓ All done! ' + totalUpdated + ' product' + ( totalUpdated !== 1 ? 's' : '' ) + ' updated.', 'done' );
					addLog( '🎉  Job complete — redirecting…', 'success' );
					setTimeout( function () {
						window.location.href =
							redirectUrl +
							'&status=done' +
							'&job_id='   + encodeURIComponent( jobId ) +
							'&updated='  + totalUpdated;
					}, 2000 );
				} else {
					setTimeout( function () { processBatch( page + 1 ); }, 500 );
				}
			} )
			.catch( function ( err ) {
				addLog( '✗  Network error on batch ' + page + ': ' + err.message, 'error' );
				setStatus( 'Network error — stopped.', 'error' );
			} );
		}

		// Kick off first batch immediately.
		setStatus( 'Starting…' );
		setBar( 0 );
		processBatch( 1 );
	} );

}() );