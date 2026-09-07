/**
 * Expenses behaviour (vanilla JS).
 *
 * CRUD against the /expenses REST API with backend search, add/edit modal,
 * delete confirmation, pagination and all UI states. Uses the wp_rest cookie
 * nonce. No hardcoded data.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_Expenses || { restBase: '', restNonce: '', currency: '$' };
	var state = { page: 1, perPage: 20, search: '', totalPages: 1, items: [], deletingId: null };

	function $( id ) { return document.getElementById( id ); }

	function esc( v ) {
		return String( v == null ? '' : v ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function money( n ) {
		var v = Number( n ); if ( isNaN( v ) ) { v = 0; }
		return cfg.currency + v.toFixed( 2 );
	}

	function fmtDate( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( String( s ).replace( ' ', 'T' ) );
		return isNaN( d.getTime() ) ? esc( s ) : d.toLocaleDateString();
	}

	function show( id, on ) { var n = $( id ); if ( n ) { n.hidden = ! on; } }

	function withQuery( base, params ) {
		var qs = params ? params.toString() : '';
		if ( ! qs ) { return base; }
		return base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + qs;
	}

	function call( path, opts ) {
		opts = opts || {};
		var headers = { 'X-WP-Nonce': cfg.restNonce, Accept: 'application/json' };
		if ( opts.body ) { headers['Content-Type'] = 'application/json'; }
		return fetch( cfg.restBase + ( path || '' ), {
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
		var n = $( 'sbms-exp-notice' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.className = 'sbms-notice sbms-notice--' + ( type || 'success' );
		n.hidden = false;
		if ( type !== 'error' ) { clearTimeout( notify._t ); notify._t = setTimeout( function () { n.hidden = true; }, 4000 ); }
	}

	function buildQuery() {
		var p = new URLSearchParams();
		if ( state.search ) { p.set( 'search', state.search ); }
		p.set( 'page', String( state.page ) );
		p.set( 'per_page', String( state.perPage ) );
		return withQuery( cfg.restBase, p );
	}

	function load() {
		show( 'sbms-exp-empty', false );
		show( 'sbms-exp-error', false );
		show( 'sbms-exp-loading', true );
		fetch( buildQuery(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.restNonce, Accept: 'application/json' } } )
			.then( function ( res ) { return res.json().catch( function () { return null; } ).then( function ( j ) { if ( ! res.ok ) { throw new Error( ( j && j.message ) || ( 'Request failed (' + res.status + ')' ) ); } return j; } ); } )
			.then( function ( json ) {
				show( 'sbms-exp-loading', false );
				var d = ( json && json.data ) ? json.data : { items: [], pagination: {} };
				state.items = d.items || [];
				renderRows( state.items );
				renderPagination( d.pagination || {} );
			} ).catch( function ( err ) {
				show( 'sbms-exp-loading', false );
				$( 'sbms-exp-rows' ).innerHTML = '';
				show( 'sbms-exp-pagination', false );
				$( 'sbms-exp-error-msg' ).textContent = err.message;
				show( 'sbms-exp-error', true );
			} );
	}

	function renderRows( items ) {
		var tb = $( 'sbms-exp-rows' );
		tb.innerHTML = '';
		if ( ! items.length ) { show( 'sbms-exp-empty', true ); return; }
		show( 'sbms-exp-empty', false );
		items.forEach( function ( e ) {
			var tr = document.createElement( 'tr' );
			tr.innerHTML =
				'<td>' + esc( fmtDate( e.expense_date ) ) + '</td>' +
				'<td>' + esc( e.category || '—' ) + '</td>' +
				'<td>' + esc( e.vendor || '—' ) + '</td>' +
				'<td>' + esc( e.description || '—' ) + '</td>' +
				'<td class="sbms-num sbms-exp-amount">' + esc( money( e.amount ) ) + '</td>' +
				'<td class="sbms-num">' +
					'<button type="button" class="sbms-btn" data-edit="' + esc( e.id ) + '">Edit</button> ' +
					'<button type="button" class="sbms-btn sbms-btn--danger" data-del="' + esc( e.id ) + '">Delete</button>' +
				'</td>';
			tb.appendChild( tr );
		} );
	}

	function renderPagination( pg ) {
		state.totalPages = pg.total_pages || 1;
		var wrap = $( 'sbms-exp-pagination' );
		if ( ! pg.total || state.totalPages <= 1 ) { wrap.hidden = true; return; }
		wrap.hidden = false;
		$( 'sbms-exp-pageinfo' ).textContent = 'Page ' + ( pg.page || 1 ) + ' of ' + state.totalPages + ' (' + pg.total + ')';
		$( 'sbms-exp-prev' ).disabled = ( pg.page || 1 ) <= 1;
		$( 'sbms-exp-next' ).disabled = ( pg.page || 1 ) >= state.totalPages;
	}

	function findItem( id ) {
		id = parseInt( id, 10 );
		for ( var i = 0; i < state.items.length; i++ ) { if ( parseInt( state.items[ i ].id, 10 ) === id ) { return state.items[ i ]; } }
		return null;
	}

	function openModal() { show( 'sbms-exp-modal', true ); }
	function closeModal() { show( 'sbms-exp-modal', false ); }

	function resetForm() {
		[ 'amount', 'category', 'vendor', 'description' ].forEach( function ( f ) { var n = $( 'sbms-ef-' + f ); if ( n ) { n.value = ''; } } );
		$( 'sbms-ef-payment' ).value = '';
		$( 'sbms-exp-form-error' ).hidden = true;
		$( 'sbms-exp-form-error' ).textContent = '';
	}

	function nowLocal() {
		var n = new Date();
		n.setMinutes( n.getMinutes() - n.getTimezoneOffset() );
		return n.toISOString().slice( 0, 16 );
	}

	function openAdd() {
		resetForm();
		$( 'sbms-ef-id' ).value = '';
		$( 'sbms-exp-modal-title' ).textContent = 'Add expense';
		$( 'sbms-ef-date' ).value = nowLocal();
		openModal();
		$( 'sbms-ef-amount' ).focus();
	}

	function openEdit( id ) {
		var e = findItem( id );
		if ( ! e ) { return; }
		resetForm();
		$( 'sbms-ef-id' ).value = e.id;
		$( 'sbms-exp-modal-title' ).textContent = 'Edit expense';
		$( 'sbms-ef-amount' ).value = e.amount;
		$( 'sbms-ef-category' ).value = e.category;
		$( 'sbms-ef-vendor' ).value = e.vendor;
		$( 'sbms-ef-payment' ).value = e.payment_method || '';
		$( 'sbms-ef-description' ).value = e.description;
		if ( e.expense_date ) { $( 'sbms-ef-date' ).value = String( e.expense_date ).replace( ' ', 'T' ).slice( 0, 16 ); }
		openModal();
		$( 'sbms-ef-amount' ).focus();
	}

	function collectForm() {
		var dateVal = $( 'sbms-ef-date' ).value;
		return {
			amount: $( 'sbms-ef-amount' ).value === '' ? '' : parseFloat( $( 'sbms-ef-amount' ).value ),
			category: $( 'sbms-ef-category' ).value.trim(),
			vendor: $( 'sbms-ef-vendor' ).value.trim(),
			payment_method: $( 'sbms-ef-payment' ).value,
			description: $( 'sbms-ef-description' ).value,
			expense_date: dateVal ? dateVal.replace( 'T', ' ' ) + ':00' : ''
		};
	}

	function submitForm( e ) {
		e.preventDefault();
		var data = collectForm();
		var err = $( 'sbms-exp-form-error' );
		err.hidden = true; err.textContent = '';
		if ( data.amount === '' || isNaN( data.amount ) ) { err.textContent = 'A numeric amount is required.'; err.hidden = false; return; }
		if ( data.amount < 0 ) { err.textContent = 'Amount cannot be negative.'; err.hidden = false; return; }

		var id = $( 'sbms-ef-id' ).value;
		var save = $( 'sbms-exp-save' );
		save.disabled = true;
		var req = id ? call( '/' + id, { method: 'PUT', body: data } ) : call( '', { method: 'POST', body: data } );
		req.then( function () {
			save.disabled = false;
			closeModal();
			notify( id ? 'Expense updated.' : 'Expense added.', 'success' );
			if ( ! id ) { state.page = 1; }
			load();
		} ).catch( function ( e2 ) {
			save.disabled = false;
			err.textContent = e2.message; err.hidden = false;
		} );
	}

	function openConfirm() { show( 'sbms-exp-confirm', true ); }
	function closeConfirm() { show( 'sbms-exp-confirm', false ); }

	function askDelete( id ) {
		var e = findItem( id );
		state.deletingId = id;
		$( 'sbms-exp-confirm-text' ).textContent = e
			? ( 'Delete this ' + money( e.amount ) + ' expense' + ( e.category ? ' (' + e.category + ')' : '' ) + '? This cannot be undone.' )
			: 'Delete this expense? This cannot be undone.';
		openConfirm();
	}

	function doDelete() {
		var id = state.deletingId;
		if ( ! id ) { return; }
		var yes = $( 'sbms-exp-confirm-yes' );
		yes.disabled = true;
		call( '/' + id, { method: 'DELETE' } ).then( function () {
			yes.disabled = false; closeConfirm();
			notify( 'Expense deleted.', 'success' );
			if ( state.items.length === 1 && state.page > 1 ) { state.page--; }
			load();
		} ).catch( function ( err ) {
			yes.disabled = false; closeConfirm();
			notify( err.message, 'error' );
		} );
	}

	function debounce( fn, ms ) {
		var t;
		return function () { var a = arguments, c = this; clearTimeout( t ); t = setTimeout( function () { fn.apply( c, a ); }, ms ); };
	}

	function init() {
		if ( ! $( 'sbms-expenses-app' ) ) { return; }
		$( 'sbms-exp-add' ).addEventListener( 'click', openAdd );
		var ea = $( 'sbms-exp-empty-add' ); if ( ea ) { ea.addEventListener( 'click', openAdd ); }
		$( 'sbms-exp-retry' ).addEventListener( 'click', load );
		$( 'sbms-exp-search' ).addEventListener( 'input', debounce( function ( e ) { state.search = e.target.value.trim(); state.page = 1; load(); }, 300 ) );
		$( 'sbms-exp-form' ).addEventListener( 'submit', submitForm );
		$( 'sbms-exp-rows' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-edit' ) ) { openEdit( e.target.getAttribute( 'data-edit' ) ); }
			else if ( e.target.hasAttribute( 'data-del' ) ) { askDelete( e.target.getAttribute( 'data-del' ) ); }
		} );
		$( 'sbms-exp-modal' ).addEventListener( 'click', function ( e ) { if ( e.target.hasAttribute( 'data-close' ) ) { closeModal(); } } );
		$( 'sbms-exp-confirm' ).addEventListener( 'click', function ( e ) { if ( e.target.hasAttribute( 'data-close-confirm' ) ) { closeConfirm(); } } );
		$( 'sbms-exp-confirm-yes' ).addEventListener( 'click', doDelete );
		$( 'sbms-exp-prev' ).addEventListener( 'click', function () { if ( state.page > 1 ) { state.page--; load(); } } );
		$( 'sbms-exp-next' ).addEventListener( 'click', function () { if ( state.page < state.totalPages ) { state.page++; load(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { closeModal(); closeConfirm(); } } );
		load();
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
