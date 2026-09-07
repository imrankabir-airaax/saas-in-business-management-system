/**
 * Dashboard behaviour (vanilla JS).
 *
 * Fetches GET /dashboard and renders headline stats, a Sales-vs-Expenses bar,
 * expenses-by-category bars, the inventory summary, and recent sales/expenses
 * plus the low-stock list. Every value comes from the server — nothing here is
 * hardcoded. Uses the wp_rest cookie nonce for authentication.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_Dashboard || { dashboardUrl: '', restNonce: '', currency: '$' };

	function $( id ) { return document.getElementById( id ); }

	function esc( v ) {
		return String( v == null ? '' : v ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function money( n ) {
		var v = Number( n );
		if ( isNaN( v ) ) { v = 0; }
		return cfg.currency + v.toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	function fmtDate( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( String( s ).replace( ' ', 'T' ) );
		return isNaN( d.getTime() ) ? esc( s ) : d.toLocaleDateString();
	}

	function call( url ) {
		return fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.restNonce, Accept: 'application/json' }
		} ).then( function ( res ) {
			return res.json().catch( function () { return null; } ).then( function ( json ) {
				if ( ! res.ok ) {
					throw new Error( ( json && json.message ) ? json.message : ( 'Request failed (' + res.status + ')' ) );
				}
				return json;
			} );
		} );
	}

	function setText( id, text ) {
		var n = $( id );
		if ( n ) { n.textContent = text; n.classList.remove( 'sbms-skeleton' ); }
	}

	function emptyRow( colspan, msg ) {
		return '<div class="sbms-state">' + esc( msg ) + '</div>';
	}

	function bar( label, value, max, cls ) {
		var pct = max > 0 ? Math.round( ( value / max ) * 100 ) : 0;
		return '<div class="sbms-bar">' +
			'<span class="sbms-bar__label">' + esc( label ) + '</span>' +
			'<span class="sbms-bar__track"><span class="sbms-bar__fill' + ( cls ? ' ' + cls : '' ) + '" style="width:' + pct + '%"></span></span>' +
			'<span class="sbms-bar__val">' + esc( money( value ) ) + '</span>' +
			'</div>';
	}

	function render( d ) {
		var t = d.totals || {};
		setText( 'sbms-m-sales', money( t.total_sales ) );
		setText( 'sbms-m-expenses', money( t.total_expenses ) );
		setText( 'sbms-m-products', String( t.total_products != null ? t.total_products : 0 ) );
		setText( 'sbms-m-lowstock', String( t.low_stock_products != null ? t.low_stock_products : 0 ) );

		var profit = $( 'sbms-m-profit' );
		if ( profit ) {
			profit.textContent = money( t.net_profit );
			profit.classList.remove( 'sbms-skeleton' );
			profit.classList.toggle( 'sbms-stat__value--good', Number( t.net_profit ) >= 0 );
			profit.classList.toggle( 'sbms-stat__value--bad', Number( t.net_profit ) < 0 );
		}

		// Sales vs Expenses bars.
		var ss = d.sales_summary || {}, es = d.expense_summary || {};
		var maxMoney = Math.max( Number( t.total_sales ) || 0, Number( t.total_expenses ) || 0, 1 );
		$( 'sbms-dash-money' ).innerHTML =
			'<div class="sbms-bars">' +
				bar( 'Sales (' + ( ss.count || 0 ) + ')', Number( t.total_sales ) || 0, maxMoney, 'sbms-bar__fill--good' ) +
				bar( 'Expenses (' + ( es.count || 0 ) + ')', Number( t.total_expenses ) || 0, maxMoney, 'sbms-bar__fill--bad' ) +
			'</div>' +
			'<p class="sbms-header__sub" style="margin-top:12px">' +
				'Avg sale ' + esc( money( ss.average ) ) + ' · Avg expense ' + esc( money( es.average ) ) +
			'</p>';

		// Expenses by category.
		var cats = ( d.expense_summary && d.expense_summary.by_category ) || [];
		if ( ! cats.length ) {
			$( 'sbms-dash-categories' ).innerHTML = emptyRow( 1, 'No expenses recorded yet.' );
		} else {
			var maxCat = cats.reduce( function ( m, c ) { return Math.max( m, Number( c.total ) || 0 ); }, 1 );
			$( 'sbms-dash-categories' ).innerHTML = '<div class="sbms-bars">' +
				cats.map( function ( c ) { return bar( c.category, Number( c.total ) || 0, maxCat ); } ).join( '' ) +
				'</div>';
		}

		// Inventory summary.
		var inv = d.inventory_summary || {};
		$( 'sbms-dash-inventory' ).innerHTML =
			'<div class="sbms-stats" style="margin-bottom:0">' +
				invCell( 'Products', inv.total_products || 0 ) +
				invCell( 'Units in stock', inv.total_units || 0 ) +
				invCell( 'Retail value', money( inv.retail_value ) ) +
				invCell( 'Cost value', money( inv.cost_value ) ) +
				invCell( 'Low stock', inv.low_stock || 0 ) +
				invCell( 'Out of stock', inv.out_of_stock || 0 ) +
			'</div>';

		// Recent sales.
		$( 'sbms-dash-recent-sales' ).innerHTML = recentSales( d.recent_sales || [] );
		// Recent expenses.
		$( 'sbms-dash-recent-expenses' ).innerHTML = recentExpenses( d.recent_expenses || [] );
		// Low stock list.
		$( 'sbms-dash-lowstock-list' ).innerHTML = lowStock( d.low_stock_list || [] );
	}

	function invCell( label, value ) {
		return '<div class="sbms-stat"><p class="sbms-stat__label">' + esc( label ) + '</p>' +
			'<p class="sbms-stat__value" style="font-size:20px">' + esc( value ) + '</p></div>';
	}

	function recentSales( rows ) {
		if ( ! rows.length ) { return emptyRow( 1, 'No sales yet.' ); }
		var body = rows.map( function ( s ) {
			return '<tr><td>' + esc( s.invoice_number ) + '</td>' +
				'<td>' + esc( fmtDate( s.sale_date ) ) + '</td>' +
				'<td>' + esc( s.customer_name || '—' ) + '</td>' +
				'<td class="sbms-num">' + esc( money( s.total_amount ) ) + '</td></tr>';
		} ).join( '' );
		return '<table class="sbms-table"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="sbms-num">Total</th></tr></thead><tbody>' + body + '</tbody></table>';
	}

	function recentExpenses( rows ) {
		if ( ! rows.length ) { return emptyRow( 1, 'No expenses yet.' ); }
		var body = rows.map( function ( e ) {
			return '<tr><td>' + esc( e.category || '—' ) + '</td>' +
				'<td>' + esc( fmtDate( e.expense_date ) ) + '</td>' +
				'<td>' + esc( e.vendor || '—' ) + '</td>' +
				'<td class="sbms-num">' + esc( money( e.amount ) ) + '</td></tr>';
		} ).join( '' );
		return '<table class="sbms-table"><thead><tr><th>Category</th><th>Date</th><th>Vendor</th><th class="sbms-num">Amount</th></tr></thead><tbody>' + body + '</tbody></table>';
	}

	function lowStock( rows ) {
		if ( ! rows.length ) { return emptyRow( 1, 'Nothing is low on stock. 🎉' ); }
		var body = rows.map( function ( p ) {
			return '<tr class="sbms-row-low"><td>' + esc( p.name ) + '</td>' +
				'<td>' + esc( p.sku || '—' ) + '</td>' +
				'<td class="sbms-num">' + esc( p.stock_quantity ) + '</td>' +
				'<td class="sbms-num">' + esc( p.low_stock_threshold ) + '</td>' +
				'<td><span class="sbms-badge sbms-badge--warn">Low</span></td></tr>';
		} ).join( '' );
		return '<table class="sbms-table"><thead><tr><th>Product</th><th>SKU</th><th class="sbms-num">Stock</th><th class="sbms-num">Threshold</th><th></th></tr></thead><tbody>' + body + '</tbody></table>';
	}

	function load( announce ) {
		$( 'sbms-dash-error' ).hidden = true;
		call( cfg.dashboardUrl ).then( function ( json ) {
			render( ( json && json.data ) ? json.data : {} );
			if ( announce === true ) { notify( 'Dashboard updated.' ); }
		} ).catch( function ( err ) {
			$( 'sbms-dash-error-msg' ).textContent = err.message;
			$( 'sbms-dash-error' ).hidden = false;
			failStates();
		} );
	}

	function notify( msg ) {
		var n = $( 'sbms-dash-success' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.hidden = false;
		clearTimeout( notify._t );
		notify._t = setTimeout( function () { n.hidden = true; }, 3000 );
	}

	// Stop every loading indicator on error so the page never looks stuck.
	function failStates() {
		[ 'sbms-m-sales', 'sbms-m-expenses', 'sbms-m-profit', 'sbms-m-products', 'sbms-m-lowstock' ].forEach( function ( id ) {
			var n = $( id );
			if ( n ) { n.textContent = '—'; n.classList.remove( 'sbms-skeleton' ); }
		} );
		[ 'sbms-dash-money', 'sbms-dash-categories', 'sbms-dash-inventory', 'sbms-dash-recent-sales', 'sbms-dash-recent-expenses', 'sbms-dash-lowstock-list' ].forEach( function ( id ) {
			var n = $( id );
			if ( n ) { n.innerHTML = '<div class="sbms-state">' + esc( 'Could not load.' ) + '</div>'; }
		} );
	}

	function init() {
		if ( ! $( 'sbms-dashboard-app' ) ) { return; }
		var refresh = $( 'sbms-dash-refresh' );
		if ( refresh ) { refresh.addEventListener( 'click', function () { load( true ); } ); }
		load();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
