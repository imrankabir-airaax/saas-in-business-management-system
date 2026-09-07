/**
 * Profile behaviour (vanilla JS).
 *
 * Loads the current user + business profile from GET /profile and saves changes
 * via PUT /profile, using the wp_rest cookie nonce. No hardcoded data.
 */
( function () {
	'use strict';

	var cfg = window.SBMS_Profile || { restBase: '', restNonce: '' };

	function $( id ) { return document.getElementById( id ); }
	function show( id, on ) { var n = $( id ); if ( n ) { n.hidden = ! on; } }
	function val( id ) { var n = $( id ); return n ? n.value : ''; }
	function setVal( id, v ) { var n = $( id ); if ( n ) { n.value = ( v == null ? '' : v ); } }

	function call( opts ) {
		opts = opts || {};
		var headers = { 'X-WP-Nonce': cfg.restNonce, Accept: 'application/json' };
		if ( opts.body ) { headers['Content-Type'] = 'application/json'; }
		return fetch( cfg.restBase, {
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
		var n = $( 'sbms-profile-notice' );
		if ( ! n ) { return; }
		n.textContent = msg;
		n.className = 'sbms-notice sbms-notice--' + ( type || 'success' );
		n.hidden = false;
		if ( type !== 'error' ) { clearTimeout( notify._t ); notify._t = setTimeout( function () { n.hidden = true; }, 4000 ); }
	}

	function fill( data ) {
		var p = data.profile || {};
		setVal( 'sbms-pf-username', data.username );
		setVal( 'sbms-pf-display_name', data.display_name );
		setVal( 'sbms-pf-email', data.email );
		setVal( 'sbms-pf-business_name', p.business_name );
		setVal( 'sbms-pf-business_email', p.business_email );
		setVal( 'sbms-pf-business_phone', p.business_phone );
		setVal( 'sbms-pf-currency', p.currency );
		setVal( 'sbms-pf-tax_number', p.tax_number );
		setVal( 'sbms-pf-address', p.address );
	}

	function load() {
		show( 'sbms-profile-error', false );
		show( 'sbms-profile-loading', true );
		show( 'sbms-profile-form', false );
		call().then( function ( json ) {
			show( 'sbms-profile-loading', false );
			fill( ( json && json.data ) ? json.data : {} );
			show( 'sbms-profile-form', true );
		} ).catch( function ( err ) {
			show( 'sbms-profile-loading', false );
			$( 'sbms-profile-error-msg' ).textContent = err.message;
			show( 'sbms-profile-error', true );
		} );
	}

	function save( e ) {
		e.preventDefault();
		var err = $( 'sbms-profile-form-error' );
		err.hidden = true; err.textContent = '';

		var payload = {
			display_name: val( 'sbms-pf-display_name' ),
			email: val( 'sbms-pf-email' ),
			business_name: val( 'sbms-pf-business_name' ),
			business_email: val( 'sbms-pf-business_email' ),
			business_phone: val( 'sbms-pf-business_phone' ),
			address: val( 'sbms-pf-address' ),
			currency: val( 'sbms-pf-currency' ),
			tax_number: val( 'sbms-pf-tax_number' )
		};

		// Client-side validation (the server re-validates authoritatively).
		var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if ( payload.email.trim() === '' ) {
			err.textContent = 'Account email is required.'; err.hidden = false; return;
		}
		if ( ! emailRe.test( payload.email.trim() ) ) {
			err.textContent = 'Please enter a valid account email address.'; err.hidden = false; return;
		}
		if ( payload.business_email.trim() !== '' && ! emailRe.test( payload.business_email.trim() ) ) {
			err.textContent = 'Please enter a valid business email address, or leave it blank.'; err.hidden = false; return;
		}

		var btn = $( 'sbms-profile-save' );
		btn.disabled = true;
		call( { method: 'PUT', body: payload } ).then( function ( json ) {
			btn.disabled = false;
			if ( json && json.data ) { fill( json.data ); }
			notify( 'Profile saved.', 'success' );
		} ).catch( function ( e2 ) {
			btn.disabled = false;
			err.textContent = e2.message; err.hidden = false;
		} );
	}

	function init() {
		if ( ! $( 'sbms-profile-app' ) ) { return; }
		$( 'sbms-profile-retry' ).addEventListener( 'click', load );
		$( 'sbms-profile-form' ).addEventListener( 'submit', save );
		load();
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
