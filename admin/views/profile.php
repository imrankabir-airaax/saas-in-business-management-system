<?php
/**
 * Profile view.
 *
 * Shell only — the current user + business profile are loaded client-side from
 * GET /profile and saved via PUT /profile.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbms-page" id="sbms-profile-app">

	<div class="sbms-header">
		<div class="sbms-header__titles">
			<h1><?php esc_html_e( 'Profile', 'saas-business-management' ); ?></h1>
			<p class="sbms-header__sub"><?php esc_html_e( 'Your account and business details', 'saas-business-management' ); ?></p>
		</div>
	</div>

	<div class="sbms-notice" id="sbms-profile-notice" role="alert" aria-live="polite" hidden></div>

	<div class="sbms-state" id="sbms-profile-loading"><span class="sbms-spinner"></span> <?php esc_html_e( 'Loading…', 'saas-business-management' ); ?></div>
	<div class="sbms-state sbms-state--error" id="sbms-profile-error" hidden>
		<p id="sbms-profile-error-msg"></p>
		<button type="button" class="sbms-btn" id="sbms-profile-retry"><?php esc_html_e( 'Retry', 'saas-business-management' ); ?></button>
	</div>

	<form id="sbms-profile-form" hidden>
		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Account', 'saas-business-management' ); ?></h2></div>
			<div class="sbms-form-row">
				<div class="sbms-field">
					<label><?php esc_html_e( 'Username', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-username" readonly />
					<span class="sbms-field__hint"><?php esc_html_e( 'Usernames cannot be changed.', 'saas-business-management' ); ?></span>
				</div>
				<div class="sbms-field">
					<label for="sbms-pf-display_name"><?php esc_html_e( 'Display name', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-display_name" maxlength="191" />
				</div>
				<div class="sbms-field">
					<label for="sbms-pf-email"><?php esc_html_e( 'Account email', 'saas-business-management' ); ?></label>
					<input type="email" id="sbms-pf-email" />
				</div>
			</div>
		</div>

		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Business', 'saas-business-management' ); ?></h2></div>
			<div class="sbms-form-row">
				<div class="sbms-field">
					<label for="sbms-pf-business_name"><?php esc_html_e( 'Business name', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-business_name" maxlength="191" />
				</div>
				<div class="sbms-field">
					<label for="sbms-pf-business_email"><?php esc_html_e( 'Business email', 'saas-business-management' ); ?></label>
					<input type="email" id="sbms-pf-business_email" />
				</div>
				<div class="sbms-field">
					<label for="sbms-pf-business_phone"><?php esc_html_e( 'Business phone', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-business_phone" maxlength="60" />
				</div>
			</div>
			<div class="sbms-form-row">
				<div class="sbms-field">
					<label for="sbms-pf-currency"><?php esc_html_e( 'Currency', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-currency" maxlength="10" />
				</div>
				<div class="sbms-field">
					<label for="sbms-pf-tax_number"><?php esc_html_e( 'Tax number', 'saas-business-management' ); ?></label>
					<input type="text" id="sbms-pf-tax_number" maxlength="60" />
				</div>
			</div>
			<div class="sbms-field">
				<label for="sbms-pf-address"><?php esc_html_e( 'Address', 'saas-business-management' ); ?></label>
				<textarea id="sbms-pf-address" rows="2"></textarea>
			</div>
		</div>

		<div class="sbms-form-error" id="sbms-profile-form-error" hidden></div>

		<p>
			<button type="submit" class="sbms-btn sbms-btn--primary" id="sbms-profile-save"><?php esc_html_e( 'Save changes', 'saas-business-management' ); ?></button>
		</p>
	</form>

</div>
