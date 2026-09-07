/**
 * Inventory admin behaviour (vanilla JS).
 *
 * Talks to the REST API at SBMS_Inventory.restBase with the wp_rest nonce.
 * Handles listing (with backend search + low-stock filter + paging), add/edit
 * via a modal form, delete with confirmation, and all UI states (loading,
 * empty, error) plus success/error notifications. No product data is hardcoded.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_Inventory || { restBase: '', restNonce: '', currency: '$' };

	var state = {
		page: 1,
		perPage: 20,
		search: '',
		lowStock: false,
		totalPages: 1,
		items: [],
		deletingId: null
	};

	function $( id ) { return document.getElementById( id ); }

	function esc( value ) {
		return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function money( n ) {
		var v = Number( n );
		if ( isNaN( v ) ) { v = 0; }
		return cfg.currency + v.toFixed( 2 );
	}

	function show( id, on ) {
		var n = $( id );
		if ( n ) { n.hidden = ! on; }
	}

	/* ------------------------------------------------------------------ *
	 * API
	 * ------------------------------------------------------------------ */

	function api( path, opts ) {
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
				if ( ! res.ok ) {
					var msg = ( json && json.message ) ? json.message : ( 'Request failed (' + res.status + ')' );
					throw new Error( msg );
				}
				return json;
			} );
		} );
	}

	function buildQuery() {
		var p = new URLSearchParams();
		if ( state.search ) { p.set( 'search', state.search ); }
		if ( state.lowStock ) { p.set( 'low_stock', 'true' ); }
		p.set( 'page', String( state.page ) );
		p.set( 'per_page', String( state.perPage ) );
		// rest_url() returns a "?rest_route=..." URL when the site uses plain
		// permalinks, so it may already contain a "?". Pick the right separator.
		var sep = ( cfg.restBase.indexOf( '?' ) === -1 ) ? '?' : '&';
		return sep + p.toString();
	}

	/* ------------------------------------------------------------------ *
	 * Notifications
	 * ------------------------------------------------------------------ */

	function notify( msg, type ) {
		var n = $( 'sbms-inv-notice' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.className = 'sbms-inv__notice sbms-inv__notice--' + ( type || 'info' );
		n.hidden = false;
		if ( type !== 'error' ) {
			clearTimeout( notify._t );
			notify._t = setTimeout( function () { n.hidden = true; }, 4000 );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Load + render
	 * ------------------------------------------------------------------ */

	function load() {
		show( 'sbms-inv-empty', false );
		show( 'sbms-inv-error', false );
		show( 'sbms-inv-loading', true );

		api( buildQuery() ).then( function ( json ) {
			show( 'sbms-inv-loading', false );
			var data = ( json && json.data ) ? json.data : { items: [], pagination: {} };
			state.items = data.items || [];
			renderRows( state.items );
			renderPagination( data.pagination || {} );
		} ).catch( function ( err ) {
			show( 'sbms-inv-loading', false );
			$( 'sbms-inv-rows' ).innerHTML = '';
			show( 'sbms-inv-pagination', false );
			$( 'sbms-inv-error-msg' ).textContent = err.message;
			show( 'sbms-inv-error', true );
		} );
	}

	function renderRows( items ) {
		var tb = $( 'sbms-inv-rows' );
		tb.innerHTML = '';

		if ( ! items.length ) {
			show( 'sbms-inv-empty', true );
			return;
		}
		show( 'sbms-inv-empty', false );

		items.forEach( function ( p ) {
			var tr = document.createElement( 'tr' );
			if ( p.low_stock ) { tr.className = 'sbms-inv__row-low'; }

			var statusClass = ( p.status === 'inactive' ) ? 'inactive' : 'active';
			var statusBadge = '<span class="sbms-inv__badge sbms-inv__badge--' + statusClass + '">' + esc( p.status ) + '</span>';
			var lowBadge = p.low_stock ? ' <span class="sbms-inv__badge sbms-inv__badge--low">Low</span>' : '';

			tr.innerHTML =
				'<td>' + esc( p.name ) + '</td>' +
				'<td>' + esc( p.sku ) + '</td>' +
				'<td>' + esc( p.category ) + '</td>' +
				'<td class="sbms-inv__num">' + esc( money( p.price ) ) + '</td>' +
				'<td class="sbms-inv__num">' + esc( money( p.cost_price ) ) + '</td>' +
				'<td class="sbms-inv__num"><span class="sbms-inv__stock">' + esc( p.stock_quantity ) + '</span>' + lowBadge + '</td>' +
				'<td>' + statusBadge + '</td>' +
				'<td class="sbms-inv__actions">' +
					'<button type="button" class="button button-small" data-edit="' + esc( p.id ) + '">Edit</button> ' +
					'<button type="button" class="button button-small button-link-delete" data-del="' + esc( p.id ) + '">Delete</button>' +
				'</td>';

			tb.appendChild( tr );
		} );
	}

	function renderPagination( pg ) {
		state.totalPages = pg.total_pages || 1;
		var wrap = $( 'sbms-inv-pagination' );
		if ( ! pg.total || state.totalPages <= 1 ) {
			wrap.hidden = true;
			return;
		}
		wrap.hidden = false;
		$( 'sbms-inv-pageinfo' ).textContent = 'Page ' + ( pg.page || 1 ) + ' of ' + state.totalPages + ' (' + pg.total + ' items)';
		$( 'sbms-inv-prev' ).disabled = ( pg.page || 1 ) <= 1;
		$( 'sbms-inv-next' ).disabled = ( pg.page || 1 ) >= state.totalPages;
	}

	function findItem( id ) {
		id = parseInt( id, 10 );
		for ( var i = 0; i < state.items.length; i++ ) {
			if ( parseInt( state.items[ i ].id, 10 ) === id ) { return state.items[ i ]; }
		}
		return null;
	}

	/* ------------------------------------------------------------------ *
	 * Modal (add / edit)
	 * ------------------------------------------------------------------ */

	function openModal() { show( 'sbms-inv-modal', true ); }
	function closeModal() { show( 'sbms-inv-modal', false ); }

	function resetForm() {
		[ 'name', 'sku', 'category', 'price', 'cost', 'stock', 'threshold', 'description' ].forEach( function ( f ) {
			var n = $( 'sbms-f-' + f );
			if ( n ) { n.value = ''; }
		} );
		$( 'sbms-f-status' ).value = 'active';
		$( 'sbms-inv-form-error' ).hidden = true;
		$( 'sbms-inv-form-error' ).textContent = '';
	}

	function openAdd() {
		resetForm();
		$( 'sbms-f-id' ).value = '';
		$( 'sbms-inv-modal-title' ).textContent = 'Add Product';
		openModal();
		$( 'sbms-f-name' ).focus();
	}

	function openEdit( id ) {
		var p = findItem( id );
		if ( ! p ) { return; }
		resetForm();
		$( 'sbms-f-id' ).value = p.id;
		$( 'sbms-inv-modal-title' ).textContent = 'Edit Product';
		$( 'sbms-f-name' ).value = p.name;
		$( 'sbms-f-sku' ).value = p.sku;
		$( 'sbms-f-category' ).value = p.category;
		$( 'sbms-f-price' ).value = p.price;
		$( 'sbms-f-cost' ).value = p.cost_price;
		$( 'sbms-f-stock' ).value = p.stock_quantity;
		$( 'sbms-f-threshold' ).value = p.low_stock_threshold;
		$( 'sbms-f-status' ).value = p.status;
		$( 'sbms-f-description' ).value = p.description;
		openModal();
		$( 'sbms-f-name' ).focus();
	}

	function collectForm() {
		function num( id ) { var v = $( id ).value; return v === '' ? 0 : parseFloat( v ); }
		function int( id ) { var v = $( id ).value; return v === '' ? 0 : parseInt( v, 10 ); }
		return {
			name: $( 'sbms-f-name' ).value.trim(),
			sku: $( 'sbms-f-sku' ).value.trim(),
			category: $( 'sbms-f-category' ).value.trim(),
			price: num( 'sbms-f-price' ),
			cost_price: num( 'sbms-f-cost' ),
			stock_quantity: int( 'sbms-f-stock' ),
			low_stock_threshold: int( 'sbms-f-threshold' ),
			status: $( 'sbms-f-status' ).value,
			description: $( 'sbms-f-description' ).value
		};
	}

	function submitForm( e ) {
		e.preventDefault();
		var data = collectForm();
		var formErr = $( 'sbms-inv-form-error' );
		formErr.hidden = true;
		formErr.textContent = '';

		if ( ! data.name ) {
			formErr.textContent = 'Product name is required.';
			formErr.hidden = false;
			return;
		}
		if ( data.price < 0 || data.cost_price < 0 ) {
			formErr.textContent = 'Prices cannot be negative.';
			formErr.hidden = false;
			return;
		}
		if ( data.stock_quantity < 0 ) { data.stock_quantity = 0; }
		if ( data.low_stock_threshold < 0 ) { data.low_stock_threshold = 0; }

		var id = $( 'sbms-f-id' ).value;
		var save = $( 'sbms-inv-save' );
		save.disabled = true;

		var req = id ? api( '/' + id, { method: 'PUT', body: data } ) : api( '', { method: 'POST', body: data } );

		req.then( function () {
			save.disabled = false;
			closeModal();
			notify( id ? 'Product updated.' : 'Product added.', 'success' );
			if ( ! id ) { state.page = 1; }
			load();
		} ).catch( function ( err ) {
			save.disabled = false;
			formErr.textContent = err.message;
			formErr.hidden = false;
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Delete confirmation
	 * ------------------------------------------------------------------ */

	function openConfirm() { show( 'sbms-inv-confirm', true ); }
	function closeConfirm() { show( 'sbms-inv-confirm', false ); }

	function askDelete( id ) {
		var p = findItem( id );
		state.deletingId = id;
		$( 'sbms-inv-confirm-text' ).textContent = p
			? ( 'Delete "' + p.name + '"? This cannot be undone.' )
			: 'Delete this product? This cannot be undone.';
		openConfirm();
	}

	function doDelete() {
		var id = state.deletingId;
		if ( ! id ) { return; }
		var yes = $( 'sbms-inv-confirm-yes' );
		yes.disabled = true;

		api( '/' + id, { method: 'DELETE' } ).then( function () {
			yes.disabled = false;
			closeConfirm();
			notify( 'Product deleted.', 'success' );
			// If we deleted the last item on a page, step back a page.
			if ( state.items.length === 1 && state.page > 1 ) { state.page--; }
			load();
		} ).catch( function ( err ) {
			yes.disabled = false;
			closeConfirm();
			notify( err.message, 'error' );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Wiring
	 * ------------------------------------------------------------------ */

	function debounce( fn, ms ) {
		var t;
		return function () {
			var args = arguments, ctx = this;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, ms );
		};
	}

	function init() {
		if ( ! $( 'sbms-inventory-app' ) ) { return; }

		$( 'sbms-inv-add' ).addEventListener( 'click', openAdd );
		var emptyAdd = $( 'sbms-inv-empty-add' );
		if ( emptyAdd ) { emptyAdd.addEventListener( 'click', openAdd ); }
		$( 'sbms-inv-retry' ).addEventListener( 'click', load );

		$( 'sbms-inv-search' ).addEventListener( 'input', debounce( function ( e ) {
			state.search = e.target.value.trim();
			state.page = 1;
			load();
		}, 300 ) );

		$( 'sbms-inv-lowstock' ).addEventListener( 'change', function ( e ) {
			state.lowStock = !! e.target.checked;
			state.page = 1;
			load();
		} );

		$( 'sbms-inv-form' ).addEventListener( 'submit', submitForm );

		// Row action delegation.
		$( 'sbms-inv-rows' ).addEventListener( 'click', function ( e ) {
			var t = e.target;
			if ( t.hasAttribute( 'data-edit' ) ) { openEdit( t.getAttribute( 'data-edit' ) ); }
			else if ( t.hasAttribute( 'data-del' ) ) { askDelete( t.getAttribute( 'data-del' ) ); }
		} );

		// Modal close (backdrop + cancel).
		$( 'sbms-inv-modal' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-close' ) ) { closeModal(); }
		} );
		$( 'sbms-inv-confirm' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-close-confirm' ) ) { closeConfirm(); }
		} );
		$( 'sbms-inv-confirm-yes' ).addEventListener( 'click', doDelete );

		// Pagination.
		$( 'sbms-inv-prev' ).addEventListener( 'click', function () {
			if ( state.page > 1 ) { state.page--; load(); }
		} );
		$( 'sbms-inv-next' ).addEventListener( 'click', function () {
			if ( state.page < state.totalPages ) { state.page++; load(); }
		} );

		// Close modals on Escape.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeModal(); closeConfirm(); }
		} );

		load();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
