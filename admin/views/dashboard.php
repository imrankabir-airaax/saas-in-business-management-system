<?php
/**
 * Dashboard view.
 *
 * Shell only — every number is loaded client-side from GET /dashboard. No data
 * is rendered server-side, so there is no dummy data anywhere on this page.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbms-page" id="sbms-dashboard-app">

	<div class="sbms-header">
		<div class="sbms-header__titles">
			<h1><?php esc_html_e( 'Dashboard', 'saas-business-management' ); ?></h1>
			<p class="sbms-header__sub"><?php esc_html_e( 'Live overview of your business', 'saas-business-management' ); ?></p>
		</div>
		<div class="sbms-header__actions">
			<button type="button" class="sbms-btn" id="sbms-dash-refresh"><?php esc_html_e( 'Refresh', 'saas-business-management' ); ?></button>
		</div>
	</div>

	<div class="sbms-notice sbms-notice--success" id="sbms-dash-success" role="status" aria-live="polite" hidden></div>

	<div class="sbms-notice sbms-notice--error" id="sbms-dash-error" hidden>
		<span id="sbms-dash-error-msg"></span>
	</div>

	<div class="sbms-stats" id="sbms-dash-stats">
		<div class="sbms-stat">
			<p class="sbms-stat__label"><?php esc_html_e( 'Total sales', 'saas-business-management' ); ?></p>
			<p class="sbms-stat__value sbms-skeleton" id="sbms-m-sales">0</p>
		</div>
		<div class="sbms-stat">
			<p class="sbms-stat__label"><?php esc_html_e( 'Total expenses', 'saas-business-management' ); ?></p>
			<p class="sbms-stat__value sbms-skeleton" id="sbms-m-expenses">0</p>
		</div>
		<div class="sbms-stat">
			<p class="sbms-stat__label"><?php esc_html_e( 'Net profit', 'saas-business-management' ); ?></p>
			<p class="sbms-stat__value sbms-skeleton" id="sbms-m-profit">0</p>
		</div>
		<div class="sbms-stat">
			<p class="sbms-stat__label"><?php esc_html_e( 'Products', 'saas-business-management' ); ?></p>
			<p class="sbms-stat__value sbms-skeleton" id="sbms-m-products">0</p>
		</div>
		<div class="sbms-stat">
			<p class="sbms-stat__label"><?php esc_html_e( 'Low-stock products', 'saas-business-management' ); ?></p>
			<p class="sbms-stat__value sbms-skeleton" id="sbms-m-lowstock">0</p>
		</div>
	</div>

	<div class="sbms-grid sbms-grid--2">
		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Sales vs Expenses', 'saas-business-management' ); ?></h2></div>
			<div id="sbms-dash-money"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
		</div>
		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Expenses by category', 'saas-business-management' ); ?></h2></div>
			<div id="sbms-dash-categories"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
		</div>
	</div>

	<div class="sbms-card">
		<div class="sbms-card__head"><h2><?php esc_html_e( 'Inventory summary', 'saas-business-management' ); ?></h2></div>
		<div id="sbms-dash-inventory"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
	</div>

	<div class="sbms-grid sbms-grid--2">
		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Recent sales', 'saas-business-management' ); ?></h2></div>
			<div id="sbms-dash-recent-sales"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
		</div>
		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'Recent expenses', 'saas-business-management' ); ?></h2></div>
			<div id="sbms-dash-recent-expenses"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
		</div>
	</div>

	<div class="sbms-card">
		<div class="sbms-card__head"><h2><?php esc_html_e( 'Low-stock products', 'saas-business-management' ); ?></h2></div>
		<div id="sbms-dash-lowstock-list"><div class="sbms-state"><span class="sbms-spinner"></span></div></div>
	</div>

</div>
