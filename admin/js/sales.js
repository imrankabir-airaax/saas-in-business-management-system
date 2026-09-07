/**
 * Sales admin behaviour (vanilla JS).
 *
 * Lists sales, and drives the create-sale form: it loads real products from the
 * inventory API, lets the user add line items, previews subtotals/total live,
 * and blocks submission when a quantity exceeds available stock. On submit it
 * sends only product ids + quantities — the server recomputes every money value
 * and enforces stock atomically, so the preview is advisory only.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_Sales || { restBase: '', inventoryUrl: '', restNonce: '', currency: '$' };

	var state = { page: 1, perPage: 20, totalPages: 1, sales: [], products: [] };

	function $( id ) { return document.getElementById( id ); }

	function esc( v ) {
		return String( v == null ? '' : v ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function money( n ) {
		var v = Number( n );
		if ( isNaN( v ) ) { v = 0; }
		return cfg.currency + v.toFixed( 2 );
	}

	function fmtDate( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( String( s ).replace( ' ', 'T' ) );
		if ( isNaN( d.getTime() ) ) { return esc( s ); }
		return d.toLocaleDateString() + ' ' + d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	}

	function show( id, on ) { var n = $( id ); if ( n ) { n.hidden = ! on; } }

	function withQuery( base, params ) {
		var qs = params ? params.toString() : '';
		if ( ! qs ) { return base; }
		var sep = ( base.indexOf( '?' ) === -1 ) ? '?' : '&';
		return base + sep + qs;
	}

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
				if ( ! res.ok ) {
					throw new Error( ( json && json.message ) ? json.message : ( 'Request failed (' + res.status + ')' ) );
				}
				return json;
			} );
		} );
	}

	function notify( msg, type ) {
		var n = $( 'sbms-sales-notice' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.className = 'sbms-sales__notice sbms-sales__notice--' + ( type || 'info' );
		n.hidden = false;
		if ( type !== 'error' ) {
			clearTimeout( notify._t );
			notify._t = setTimeout( function () { n.hidden = true; }, 4000 );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Sales list
	 * ------------------------------------------------------------------ */

	function loadSales() {
		show( 'sbms-sales-empty', false );
		show( 'sbms-sales-error', false );
		show( 'sbms-sales-loading', true );

		var url = withQuery( cfg.restBase, new URLSearchParams( { page: String( state.page ), per_page: String( state.perPage ) } ) );

		call( url ).then( function ( json ) {
			show( 'sbms-sales-loading', false );
			var d = ( json && json.data ) ? json.data : { items: [], pagination: {} };
			state.sales = d.items || [];
			renderSales( state.sales );
			renderPagination( d.pagination || {} );
		} ).catch( function ( err ) {
			show( 'sbms-sales-loading', false );
			$( 'sbms-sales-rows' ).innerHTML = '';
			show( 'sbms-sales-pagination', false );
			$( 'sbms-sales-error-msg' ).textContent = err.message;
			show( 'sbms-sales-error', true );
		} );
	}

	function renderSales( items ) {
		var tb = $( 'sbms-sales-rows' );
		tb.innerHTML = '';
		if ( ! items.length ) { show( 'sbms-sales-empty', true ); return; }
		show( 'sbms-sales-empty', false );

		items.forEach( function ( s ) {
			var tr = document.createElement( 'tr' );
			var statusClass = ( s.status === 'refunded' || s.status === 'cancelled' ) ? ( 'sbms-sales__badge--' + s.status ) : '';
			tr.innerHTML =
				'<td>' + esc( s.invoice_number ) + '</td>' +
				'<td>' + esc( fmtDate( s.sale_date ) ) + '</td>' +
				'<td>' + esc( s.customer_name || '—' ) + '</td>' +
				'<td class="sbms-sales__num">' + esc( money( s.total_amount ) ) + '</td>' +
				'<td><span class="sbms-sales__badge ' + statusClass + '">' + esc( s.status ) + '</span></td>' +
				'<td><button type="button" class="button button-small" data-view="' + esc( s.id ) + '">View</button></td>';
			tb.appendChild( tr );
		} );
	}

	function renderPagination( pg ) {
		state.totalPages = pg.total_pages || 1;
		var wrap = $( 'sbms-sales-pagination' );
		if ( ! pg.total || state.totalPages <= 1 ) { wrap.hidden = true; return; }
		wrap.hidden = false;
		$( 'sbms-sales-pageinfo' ).textContent = 'Page ' + ( pg.page || 1 ) + ' of ' + state.totalPages + ' (' + pg.total + ' sales)';
		$( 'sbms-sales-prev' ).disabled = ( pg.page || 1 ) <= 1;
		$( 'sbms-sales-next' ).disabled = ( pg.page || 1 ) >= state.totalPages;
	}

	/* ------------------------------------------------------------------ *
	 * Create sale
	 * ------------------------------------------------------------------ */

	function openSale() {
		$( 'sbms-sale-form' ).reset();
		$( 'sbms-sale-lines' ).innerHTML = '';
		$( 'sbms-sale-form-error' ).hidden = true;
		$( 'sbms-sale-form-error' ).textContent = '';
		// Default date = now (local, for datetime-local input).
		var now = new Date();
		now.setMinutes( now.getMinutes() - now.getTimezoneOffset() );
		$( 'sbms-sf-date' ).value = now.toISOString().slice( 0, 16 );

		show( 'sbms-sale-modal', true );

		// Load products, then add a first empty line.
		loadProducts().then( function () {
			addLine();
			recompute();
		} ).catch( function ( err ) {
			formError( err.message );
		} );
	}

	function closeSale() { show( 'sbms-sale-modal', false ); }

	function loadProducts() {
		var url = withQuery( cfg.inventoryUrl, new URLSearchParams( { per_page: '100', page: '1' } ) );
		return call( url ).then( function ( json ) {
			state.products = ( json && json.data && json.data.items ) ? json.data.items : [];
			return state.products;
		} );
	}

	function productOptions( selectedId ) {
		var html = '<option value="">' + '— select product —' + '</option>';
		state.products.forEach( function ( p ) {
			if ( p.status && p.status === 'inactive' ) { return; }
			var sel = ( String( p.id ) === String( selectedId ) ) ? ' selected' : '';
			html += '<option value="' + esc( p.id ) + '" data-price="' + esc( p.price ) + '" data-stock="' + esc( p.stock_quantity ) + '"' + sel + '>' +
				esc( p.name ) + ' (' + esc( p.stock_quantity ) + ' in stock)</option>';
		} );
		return html;
	}

	function addLine() {
		var tr = document.createElement( 'tr' );
		tr.className = 'sbms-line';
		tr.innerHTML =
			'<td><select class="sbms-line-product">' + productOptions( '' ) + '</select></td>' +
			'<td class="sbms-sales__num sbms-line-stock">—</td>' +
			'<td class="sbms-sales__num sbms-line-price">—</td>' +
			'<td class="sbms-sales__num"><input type="number" class="sbms-line-qty" min="1" step="1" value="1" /><span class="sbms-line-warn" hidden></span></td>' +
			'<td class="sbms-sales__num sbms-line-subtotal">—</td>' +
			'<td><button type="button" class="button-link sbms-line-remove" aria-label="Remove">✕</button></td>';
		$( 'sbms-sale-lines' ).appendChild( tr );
	}

	function lineData( tr ) {
		var sel = tr.querySelector( '.sbms-line-product' );
		var opt = sel.options[ sel.selectedIndex ];
		var pid = parseInt( sel.value, 10 );
		var price = opt ? parseFloat( opt.getAttribute( 'data-price' ) ) : NaN;
		var stock = opt ? parseInt( opt.getAttribute( 'data-stock' ), 10 ) : NaN;
		var qty = parseInt( tr.querySelector( '.sbms-line-qty' ).value, 10 );
		return { tr: tr, pid: pid, price: price, stock: stock, qty: qty };
	}

	function recompute() {
		var subtotal = 0;
		var rows = $( 'sbms-sale-lines' ).querySelectorAll( 'tr.sbms-line' );
		Array.prototype.forEach.call( rows, function ( tr ) {
			var d = lineData( tr );
			var priceCell = tr.querySelector( '.sbms-line-price' );
			var stockCell = tr.querySelector( '.sbms-line-stock' );
			var subCell = tr.querySelector( '.sbms-line-subtotal' );
			var warn = tr.querySelector( '.sbms-line-warn' );

			if ( ! d.pid || isNaN( d.price ) ) {
				priceCell.textContent = '—'; stockCell.textContent = '—'; subCell.textContent = '—';
				warn.hidden = true; tr.classList.remove( 'sbms-line-bad' );
				return;
			}

			priceCell.textContent = money( d.price );
			stockCell.textContent = String( d.stock );

			var qty = ( isNaN( d.qty ) || d.qty < 1 ) ? 0 : d.qty;
			var lineSub = qty * d.price;
			subCell.textContent = money( lineSub );

			// Stock validation.
			if ( qty > d.stock ) {
				warn.textContent = 'Only ' + d.stock + ' in stock';
				warn.hidden = false;
				tr.classList.add( 'sbms-line-bad' );
			} else {
				warn.hidden = true;
				tr.classList.remove( 'sbms-line-bad' );
			}

			subtotal += lineSub;
		} );

		var tax = parseFloat( $( 'sbms-sf-tax' ).value );
		var discount = parseFloat( $( 'sbms-sf-discount' ).value );
		if ( isNaN( tax ) || tax < 0 ) { tax = 0; }
		if ( isNaN( discount ) || discount < 0 ) { discount = 0; }
		if ( discount > subtotal + tax ) { discount = subtotal + tax; }
		var total = subtotal + tax - discount;
		if ( total < 0 ) { total = 0; }

		$( 'sbms-sale-subtotal' ).textContent = money( subtotal );
		$( 'sbms-sale-total' ).textContent = money( total );
	}

	function collectItems() {
		var items = [];
		var bad = false;
		var rows = $( 'sbms-sale-lines' ).querySelectorAll( 'tr.sbms-line' );
		Array.prototype.forEach.call( rows, function ( tr ) {
			var d = lineData( tr );
			if ( ! d.pid ) { return; } // skip empty lines
			if ( isNaN( d.qty ) || d.qty < 1 || d.qty > d.stock ) { bad = true; return; }
			items.push( { product_id: d.pid, quantity: d.qty } );
		} );
		return { items: items, bad: bad };
	}

	function formError( msg ) {
		var e = $( 'sbms-sale-form-error' );
		e.textContent = msg;
		e.hidden = false;
	}

	function submitSale( e ) {
		e.preventDefault();
		$( 'sbms-sale-form-error' ).hidden = true;

		var collected = collectItems();
		if ( ! collected.items.length ) {
			formError( 'Add at least one product with a valid quantity.' );
			return;
		}
		if ( collected.bad ) {
			formError( 'Some lines exceed available stock or have an invalid quantity.' );
			return;
		}

		var dateVal = $( 'sbms-sf-date' ).value;
		var payload = {
			items: collected.items,
			customer_name: $( 'sbms-sf-customer' ).value,
			payment_method: $( 'sbms-sf-payment' ).value,
			note: $( 'sbms-sf-note' ).value,
			sale_date: dateVal ? dateVal.replace( 'T', ' ' ) + ':00' : '',
			tax: parseFloat( $( 'sbms-sf-tax' ).value ) || 0,
			discount: parseFloat( $( 'sbms-sf-discount' ).value ) || 0
		};

		var save = $( 'sbms-sale-save' );
		save.disabled = true;

		call( cfg.restBase, { method: 'POST', body: payload } ).then( function () {
			save.disabled = false;
			closeSale();
			notify( 'Sale recorded.', 'success' );
			state.page = 1;
			loadSales();
		} ).catch( function ( err ) {
			save.disabled = false;
			formError( err.message );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Sale details
	 * ------------------------------------------------------------------ */

	function openDetail( id ) {
		var body = $( 'sbms-detail-body' );
		body.innerHTML = '<p>Loading…</p>';
		show( 'sbms-detail-modal', true );

		call( cfg.restBase + '/' + id ).then( function ( json ) {
			var s = ( json && json.data ) ? json.data : null;
			if ( ! s ) { body.innerHTML = '<p>Not found.</p>'; return; }
			renderDetail( s );
		} ).catch( function ( err ) {
			body.innerHTML = '<p>' + esc( err.message ) + '</p>';
		} );
	}

	function closeDetail() { show( 'sbms-detail-modal', false ); }

	function renderDetail( s ) {
		var rows = '';
		( s.items || [] ).forEach( function ( it ) {
			rows +=
				'<tr>' +
				'<td>' + esc( it.name || ( 'Product #' + it.product_id ) ) + '</td>' +
				'<td class="sbms-sales__num">' + esc( it.quantity ) + '</td>' +
				'<td class="sbms-sales__num">' + esc( money( it.unit_price ) ) + '</td>' +
				'<td class="sbms-sales__num">' + esc( money( it.subtotal ) ) + '</td>' +
				'</tr>';
		} );

		$( 'sbms-detail-title' ).textContent = 'Sale ' + ( s.invoice_number || ( '#' + s.id ) );

		$( 'sbms-detail-body' ).innerHTML =
			'<p class="sbms-detail__meta">' +
				esc( fmtDate( s.sale_date ) ) +
				( s.customer_name ? ' · ' + esc( s.customer_name ) : '' ) +
				( s.payment_method ? ' · ' + esc( s.payment_method ) : '' ) +
			'</p>' +
			'<table class="sbms-detail__table"><thead><tr>' +
				'<th>Product</th><th class="sbms-sales__num">Qty</th><th class="sbms-sales__num">Unit</th><th class="sbms-sales__num">Subtotal</th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>' +
			'<div class="sbms-sales__totals">' +
				'<div class="sbms-sales__totrow"><span>Subtotal</span><span>' + money( s.subtotal ) + '</span></div>' +
				'<div class="sbms-sales__totrow"><span>Tax</span><span>' + money( s.tax ) + '</span></div>' +
				'<div class="sbms-sales__totrow"><span>Discount</span><span>' + money( s.discount ) + '</span></div>' +
				'<div class="sbms-sales__totrow sbms-sales__totrow--grand"><span>Total</span><span>' + money( s.total_amount ) + '</span></div>' +
			'</div>' +
			( s.note ? '<p class="sbms-detail__meta">' + esc( s.note ) + '</p>' : '' );
	}

	/* ------------------------------------------------------------------ *
	 * Wiring
	 * ------------------------------------------------------------------ */

	function init() {
		if ( ! $( 'sbms-sales-app' ) ) { return; }

		$( 'sbms-sales-new' ).addEventListener( 'click', openSale );
		var emptyNew = $( 'sbms-sales-empty-new' );
		if ( emptyNew ) { emptyNew.addEventListener( 'click', openSale ); }
		$( 'sbms-sales-retry' ).addEventListener( 'click', loadSales );

		// Sales table -> view details.
		$( 'sbms-sales-rows' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-view' ) ) { openDetail( e.target.getAttribute( 'data-view' ) ); }
		} );

		// Create form.
		$( 'sbms-sale-add-line' ).addEventListener( 'click', function () { addLine(); recompute(); } );
		$( 'sbms-sale-form' ).addEventListener( 'submit', submitSale );
		$( 'sbms-sf-tax' ).addEventListener( 'input', recompute );
		$( 'sbms-sf-discount' ).addEventListener( 'input', recompute );

		// Line item delegation (change product / qty / remove).
		var lines = $( 'sbms-sale-lines' );
		lines.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'sbms-line-product' ) ) { recompute(); }
		} );
		lines.addEventListener( 'input', function ( e ) {
			if ( e.target.classList.contains( 'sbms-line-qty' ) ) { recompute(); }
		} );
		lines.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'sbms-line-remove' ) ) {
				var tr = e.target.closest( 'tr.sbms-line' );
				if ( tr ) { tr.parentNode.removeChild( tr ); recompute(); }
			}
		} );

		// Modal close.
		$( 'sbms-sale-modal' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-close-sale' ) ) { closeSale(); }
		} );
		$( 'sbms-detail-modal' ).addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-close-detail' ) ) { closeDetail(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeSale(); closeDetail(); }
		} );

		// Pagination.
		$( 'sbms-sales-prev' ).addEventListener( 'click', function () { if ( state.page > 1 ) { state.page--; loadSales(); } } );
		$( 'sbms-sales-next' ).addEventListener( 'click', function () { if ( state.page < state.totalPages ) { state.page++; loadSales(); } } );

		loadSales();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
