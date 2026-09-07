/**
 * AI Insights behaviour (vanilla JS).
 *
 * Generates analyses/recommendations from the current user's REAL data via
 * POST /ai/analyze and POST /ai/recommendation, renders the structured result
 * (summary, key findings, risks, opportunities, recommendations, actions), and
 * lists previous reports from GET /ai/reports. Uses the wp_rest cookie nonce.
 * The Gemini key is never referenced here — only a boolean "configured" flag.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_AI || { analyzeUrl: '', recommendationUrl: '', reportsUrl: '', restNonce: '', configured: false };

	function $( id ) { return document.getElementById( id ); }

	function esc( v ) {
		return String( v == null ? '' : v ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function fmtDate( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( String( s ).replace( ' ', 'T' ) );
		return isNaN( d.getTime() ) ? esc( s ) : ( d.toLocaleDateString() + ' ' + d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } ) );
	}

	function show( id, on ) { var n = $( id ); if ( n ) { n.hidden = ! on; } }

	function call( url, opts ) {
		opts = opts || {};
		var headers = { 'X-WP-Nonce': cfg.restNonce, Accept: 'application/json' };
		if ( opts.body ) { headers['Content-Type'] = 'application/json'; }
		return fetch( url, {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: headers,
			body: opts.body ? JSON.stringify( opts.body ) : undefined
		} ).then( function ( res ) {
			return res.json().catch( function () { return null; } ).then( function ( json ) {
				if ( ! res.ok ) { throw new Error( ( json && json.message ) ? json.message : ( 'Request failed (' + res.status + ')' ) ); }
				return json;
			} );
		} );
	}

	function notify( msg, type ) {
		var n = $( 'sbms-ai-notice' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.className = 'sbms-notice sbms-notice--' + ( type || 'success' );
		n.hidden = false;
		if ( type !== 'error' ) { clearTimeout( notify._t ); notify._t = setTimeout( function () { n.hidden = true; }, 4000 ); }
	}

	var TITLES = {
		business: 'Business overview',
		sales: 'Sales analysis',
		expense: 'Expense analysis',
		inventory: 'Inventory analysis',
		recommendation: 'Recommendations'
	};

	function listSection( title, items, cls ) {
		if ( ! items || ! items.length ) { return ''; }
		var lis = items.map( function ( i ) { return '<li>' + esc( i ) + '</li>'; } ).join( '' );
		return '<div class="sbms-ai-section">' +
			'<h3 class="sbms-ai-section__title ' + ( cls || '' ) + '">' + esc( title ) + '</h3>' +
			'<ul class="sbms-ai-list">' + lis + '</ul></div>';
	}

	function renderResult( r ) {
		var title = TITLES[ r.type ] || 'Analysis';
		$( 'sbms-ai-result-title' ).textContent = title;
		$( 'sbms-ai-result-meta' ).textContent = fmtDate( r.created_at );

		var html = '';
		if ( r.summary ) { html += '<p class="sbms-ai-summary">' + esc( r.summary ) + '</p>'; }
		html += listSection( 'Key findings', r.key_findings );
		html += listSection( 'Risks', r.risks, 'sbms-ai-risk' );
		html += listSection( 'Opportunities', r.opportunities, 'sbms-ai-opp' );
		html += listSection( 'Recommendations', r.recommendations );
		html += listSection( 'Suggested actions', r.suggested_actions );
		if ( ! html ) { html = '<div class="sbms-state">The analysis returned no content. Try again.</div>'; }

		$( 'sbms-ai-result-body' ).innerHTML = html;
		show( 'sbms-ai-result', true );
	}

	function generate( url, body ) {
		show( 'sbms-ai-result', false );
		show( 'sbms-ai-loading', true );
		setButtons( true );
		call( url, { method: 'POST', body: body } ).then( function ( json ) {
			show( 'sbms-ai-loading', false );
			setButtons( false );
			var r = ( json && json.data ) ? json.data : null;
			if ( ! r ) { notify( 'No analysis was returned.', 'error' ); return; }
			renderResult( r );
			notify( 'Analysis ready.', 'success' );
			loadReports();
		} ).catch( function ( err ) {
			show( 'sbms-ai-loading', false );
			setButtons( false );
			notify( err.message, 'error' );
		} );
	}

	function setButtons( busy ) {
		var g = $( 'sbms-ai-generate' ), r = $( 'sbms-ai-recommend' );
		if ( g ) { g.disabled = busy; }
		if ( r ) { r.disabled = busy; }
	}

	function loadReports() {
		var box = $( 'sbms-ai-reports' );
		call( cfg.reportsUrl ).then( function ( json ) {
			var d = ( json && json.data ) ? json.data : { items: [] };
			var items = d.items || [];
			if ( ! items.length ) {
				box.innerHTML = '<div class="sbms-state"><p>No reports yet. Generate your first analysis above.</p></div>';
				return;
			}
			var rows = items.map( function ( r ) {
				return '<tr>' +
					'<td>' + esc( TITLES[ r.type ] || r.type ) + '</td>' +
					'<td>' + esc( fmtDate( r.created_at ) ) + '</td>' +
					'<td>' + esc( ( r.summary || '' ).slice( 0, 120 ) ) + ( ( r.summary || '' ).length > 120 ? '…' : '' ) + '</td>' +
					'<td class="sbms-num"><button type="button" class="sbms-btn" data-report="' + esc( r.id ) + '">View</button></td>' +
					'</tr>';
			} ).join( '' );
			box.innerHTML = '<table class="sbms-table"><thead><tr><th>Type</th><th>Date</th><th>Summary</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
			box._items = items;
		} ).catch( function ( err ) {
			box.innerHTML = '<div class="sbms-state sbms-state--error"><p>' + esc( err.message ) + '</p></div>';
		} );
	}

	function viewReport( id ) {
		var box = $( 'sbms-ai-reports' );
		var items = box._items || [];
		for ( var i = 0; i < items.length; i++ ) {
			if ( String( items[ i ].id ) === String( id ) ) { renderResult( items[ i ] ); window.scrollTo( { top: 0, behavior: 'smooth' } ); return; }
		}
	}

	function init() {
		if ( ! $( 'sbms-ai-app' ) ) { return; }

		if ( ! cfg.configured ) {
			show( 'sbms-ai-config-notice', true );
			setButtons( true );
		}

		$( 'sbms-ai-generate' ).addEventListener( 'click', function () {
			generate( cfg.analyzeUrl, { type: $( 'sbms-ai-type' ).value, question: $( 'sbms-ai-question' ).value } );
		} );
		$( 'sbms-ai-recommend' ).addEventListener( 'click', function () {
			generate( cfg.recommendationUrl, { question: $( 'sbms-ai-question' ).value } );
		} );
		$( 'sbms-ai-reports' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-report' ) ) { viewReport( e.target.getAttribute( 'data-report' ) ); }
		} );

		loadReports();
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
