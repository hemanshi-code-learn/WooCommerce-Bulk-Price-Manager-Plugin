/**
 * WC Bulk Price Manager — admin.js
 */
( function () {
	'use strict';

	// ── Instantly Hide All Admin Notifications Only On Page Refresh (F5) ──────
    ( function () {
        var isReload = false;

        // Modern check for page reloads
        if ( window.performance && window.performance.getEntriesByType ) {
            var navEntries = window.performance.getEntriesByType( 'navigation' );
            if ( navEntries.length > 0 && navEntries[0].type === 'reload' ) {
                isReload = true;
            }
        }
        // Legacy fallback
        if ( ! isReload && window.performance && window.performance.navigation ) {
            if ( window.performance.navigation.type === 1 ) {
                isReload = true;
            }
        }

        if ( isReload ) {
            // Target core WordPress notice classes alongside plugin-specific selectors
            var selectors = '.notice, .updated, .error, .wc-bpm-notice, .wc-bpm-summary';
            
            // Execute immediately to catch notices parsing into the DOM early
            var hideNotices = function () {
                var notices = document.querySelectorAll( selectors );
                notices.forEach( function ( notice ) {
                    notice.style.setProperty( 'display', 'none', 'important' );
                } );
            };

            hideNotices();
            document.addEventListener( 'DOMContentLoaded', hideNotices );
            window.addEventListener( 'load', hideNotices );
        }

        // Always scrub status query parameters from the visible browser bar 
        document.addEventListener( 'DOMContentLoaded', function () {
            var url = new URL( window.location.href );
            var flashStatuses = [ 'rolled_back', 'done', 'saved', 'error', 'run_error', 'rollback_error' ];
            if ( flashStatuses.indexOf( url.searchParams.get( 'status' ) ) !== -1 ) {
                url.searchParams.delete( 'status' );
                url.searchParams.delete( 'restored' );
                url.searchParams.delete( 'updated' );
                url.searchParams.delete( 'skipped' );
                url.searchParams.delete( 'msg' );
                window.history.replaceState( {}, document.title, url.toString() );
            }
        } );
    }() );

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

	// ── Product search filter + clear button for exclude list ───────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		var searchInput = document.getElementById( 'wc-bpm-product-search' );
		var selectEl    = document.getElementById( 'bpm-exclude' );
		var clearBtn    = document.getElementById( 'wc-bpm-clear-exclude' );
		if ( ! selectEl ) return;

		// Cache all options so search can restore them.
		var allOptions = Array.prototype.slice.call( selectEl.options );

		// Colour excluded options on first load.
		allOptions.forEach( function ( opt ) {
			opt.style.cssText = opt.selected
				? 'background:#fff0f0;color:#8a1010;font-weight:600;'
				: '';
		} );

		// Re-colour on selection change.
		selectEl.addEventListener( 'change', function () {
			Array.prototype.forEach.call( selectEl.options, function ( opt ) {
				opt.style.cssText = opt.selected
					? 'background:#fff0f0;color:#8a1010;font-weight:600;'
					: '';
			} );
		} );

		// Search filter.
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				var query = this.value.toLowerCase().trim();
				selectEl.innerHTML = '';
				allOptions.forEach( function ( opt ) {
					if ( ! query || opt.text.toLowerCase().indexOf( query ) !== -1 ) {
						selectEl.appendChild( opt );
					}
				} );
			} );
		}

		// Clear all exclusions.
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				Array.prototype.forEach.call( selectEl.options, function ( opt ) {
					opt.selected = false;
					opt.style.cssText = '';
				} );
			} );
		}
	} );

	// ── Batch processing ──────────────────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'wc-bpm-progress' );
		if ( ! wrap ) return; // only runs on the progress screen

		var jobId          = wrap.dataset.jobId;
		var totalPages     = parseInt( wrap.dataset.totalPages, 10 );
		var totalProducts  = parseInt( wrap.dataset.totalProducts, 10 ) || 0;
		var totalAll       = parseInt( wrap.dataset.totalAll, 10 ) || totalProducts;
		var excludedCount  = parseInt( wrap.dataset.excludedCount, 10 ) || 0;
		var nonce          = wrap.dataset.nonce;
		var restBase       = wrap.dataset.restBase;

		var barEl          = document.getElementById( 'wc-bpm-bar' );
		var barLabelEl     = document.getElementById( 'wc-bpm-bar-label' );
		var statusEl       = document.getElementById( 'wc-bpm-status' );
		var logEl          = document.getElementById( 'wc-bpm-log' );
		var headingEl      = document.getElementById( 'wc-bpm-heading' );
		var subtitleEl     = document.getElementById( 'wc-bpm-subtitle' );
		var backBtnWrap    = document.getElementById( 'wc-bpm-back-btn-wrap' );
		var statsUpdEl     = document.getElementById( 'wc-bpm-stat-updated' );
		var statsRemEl     = document.getElementById( 'wc-bpm-stat-remaining' );
		var statsSkipEl    = document.getElementById( 'wc-bpm-stat-skipped' );

		var totalUpdated = 0;

		// Validate — guard against bad data attrs.
		if ( ! jobId || isNaN( totalPages ) || totalPages < 1 ) {
			setStatus( 'Error: missing job data. Please try again.', 'error' );
			return;
		}

		// Init stats display: remaining = all products (none updated yet).
		updateStats( 0, totalAll, excludedCount );

		function updateStats( updated, remaining, skipped ) {
			if ( statsUpdEl  ) statsUpdEl.textContent  = updated;
			if ( statsRemEl  ) statsRemEl.textContent  = remaining;
			if ( statsSkipEl ) statsSkipEl.textContent = skipped;
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

		function showBackButton() {
			if ( backBtnWrap ) {
				backBtnWrap.style.display = 'block';
			}
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
					showBackButton();
					return;
				}

				var n    = data.updated || 0;
				totalUpdated += n;
				var pct = Math.round( ( page / totalPages ) * 100 );
				setBar( pct );

				var remaining = Math.max( 0, totalAll - totalUpdated );
				updateStats( totalUpdated, remaining, excludedCount );

				addLog(
					'✓  Batch ' + page + ' complete — ' +
					n + ' product' + ( n !== 1 ? 's' : '' ) + ' updated. ' +
					'(' + totalUpdated + ' total so far)',
					'success'
				);

				if ( data.is_done || page >= totalPages ) {
					setBar( 100 );
					setStatus( '✓ All done! ' + totalUpdated + ' product' + ( totalUpdated !== 1 ? 's' : '' ) + ' updated.', 'done' );
					addLog( '🎉  Job complete!', 'success' );

					// Update heading and subtitle to reflect completion.
					if ( headingEl ) headingEl.textContent = 'Update Complete!';
					if ( subtitleEl ) subtitleEl.textContent = totalUpdated + ' product' + ( totalUpdated !== 1 ? 's' : '' ) + ' were successfully updated.';

					// Show the back button.
					showBackButton();
				} else {
					setTimeout( function () { processBatch( page + 1 ); }, 500 );
				}
			} )
			.catch( function ( err ) {
				addLog( '✗  Network error on batch ' + page + ': ' + err.message, 'error' );
				setStatus( 'Network error — stopped.', 'error' );
				showBackButton();
			} );
		}

		// Kick off first batch immediately.
		setStatus( 'Starting…' );
		setBar( 0 );
		processBatch( 1 );
	} );

}() );