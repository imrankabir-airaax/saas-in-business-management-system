<?php
/**
 * Expenses view.
 *
 * Shell only — expense data is loaded client-side from the REST API. No dummy
 * data is rendered server-side.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbms-page" id="sbms-expenses-app">

	<div class="sbms-header">
		<div class="sbms-header__titles">
			<h1><?php esc_html_e( 'Expenses', 'saas-business-management' ); ?></h1>
			<p class="sbms-header__sub"><?php esc_html_e( 'Track what your business spends', 'saas-business-management' ); ?></p>
		</div>
		<div class="sbms-header__actions">
			<button type="button" class="sbms-btn sbms-btn--primary" id="sbms-exp-add"><?php esc_html_e( 'Add expense', 'saas-business-management' ); ?></button>
		</div>
	</div>

	<div class="sbms-notice" id="sbms-exp-notice" role="alert" aria-live="polite" hidden></div>

	<div class="sbms-toolbar">
		<input type="search" id="sbms-exp-search" placeholder="<?php esc_attr_e( 'Search category, vendor, description…', 'saas-business-management' ); ?>" autocomplete="off" />
	</div>

	<div class="sbms-card">
		<table class="sbms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'saas-business-management' ); ?></th>
					<th><?php esc_html_e( 'Category', 'saas-business-management' ); ?></th>
					<th><?php esc_html_e( 'Vendor', 'saas-business-management' ); ?></th>
					<th><?php esc_html_e( 'Description', 'saas-business-management' ); ?></th>
					<th class="sbms-num"><?php esc_html_e( 'Amount', 'saas-business-management' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="sbms-exp-rows"></tbody>
		</table>

		<div class="sbms-state" id="sbms-exp-loading" hidden><span class="sbms-spinner"></span> <?php esc_html_e( 'Loading…', 'saas-business-management' ); ?></div>
		<div class="sbms-state" id="sbms-exp-empty" hidden>
			<p><?php esc_html_e( 'No expenses recorded yet.', 'saas-business-management' ); ?></p>
			<button type="button" class="sbms-btn sbms-btn--primary" id="sbms-exp-empty-add"><?php esc_html_e( 'Add your first expense', 'saas-business-management' ); ?></button>
		</div>
		<div class="sbms-state sbms-state--error" id="sbms-exp-error" hidden>
			<p id="sbms-exp-error-msg"></p>
			<button type="button" class="sbms-btn" id="sbms-exp-retry"><?php esc_html_e( 'Retry', 'saas-business-management' ); ?></button>
		</div>

		<div class="sbms-pagination" id="sbms-exp-pagination" hidden>
			<button type="button" class="sbms-btn" id="sbms-exp-prev"><?php esc_html_e( 'Previous', 'saas-business-management' ); ?></button>
			<span class="sbms-pagination__info" id="sbms-exp-pageinfo"></span>
			<button type="button" class="sbms-btn" id="sbms-exp-next"><?php esc_html_e( 'Next', 'saas-business-management' ); ?></button>
		</div>
	</div>

	<!-- Add / edit modal -->
	<div class="sbms-modal" id="sbms-exp-modal" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-exp-modal-title">
		<div class="sbms-modal__backdrop" data-close="1"></div>
		<div class="sbms-modal__box">
			<h2 id="sbms-exp-modal-title"><?php esc_html_e( 'Add expense', 'saas-business-management' ); ?></h2>
			<form id="sbms-exp-form">
				<input type="hidden" id="sbms-ef-id" value="" />
				<div class="sbms-form-row">
					<div class="sbms-field">
						<label for="sbms-ef-amount"><?php esc_html_e( 'Amount', 'saas-business-management' ); ?> *</label>
						<input type="number" id="sbms-ef-amount" min="0" step="0.01" required />
					</div>
					<div class="sbms-field">
						<label for="sbms-ef-date"><?php esc_html_e( 'Date', 'saas-business-management' ); ?></label>
						<input type="datetime-local" id="sbms-ef-date" />
					</div>
				</div>
				<div class="sbms-form-row">
					<div class="sbms-field">
						<label for="sbms-ef-category"><?php esc_html_e( 'Category', 'saas-business-management' ); ?></label>
						<input type="text" id="sbms-ef-category" maxlength="191" />
					</div>
					<div class="sbms-field">
						<label for="sbms-ef-vendor"><?php esc_html_e( 'Vendor', 'saas-business-management' ); ?></label>
						<input type="text" id="sbms-ef-vendor" maxlength="191" />
					</div>
					<div class="sbms-field">
						<label for="sbms-ef-payment"><?php esc_html_e( 'Payment', 'saas-business-management' ); ?></label>
						<select id="sbms-ef-payment">
							<option value=""><?php esc_html_e( '—', 'saas-business-management' ); ?></option>
							<option value="cash"><?php esc_html_e( 'Cash', 'saas-business-management' ); ?></option>
							<option value="card"><?php esc_html_e( 'Card', 'saas-business-management' ); ?></option>
							<option value="transfer"><?php esc_html_e( 'Transfer', 'saas-business-management' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', 'saas-business-management' ); ?></option>
						</select>
					</div>
				</div>
				<div class="sbms-field">
					<label for="sbms-ef-description"><?php esc_html_e( 'Description', 'saas-business-management' ); ?></label>
					<textarea id="sbms-ef-description" rows="2"></textarea>
				</div>
				<div class="sbms-form-error" id="sbms-exp-form-error" hidden></div>
				<div class="sbms-modal__actions">
					<button type="submit" class="sbms-btn sbms-btn--primary" id="sbms-exp-save"><?php esc_html_e( 'Save', 'saas-business-management' ); ?></button>
					<button type="button" class="sbms-btn" data-close="1"><?php esc_html_e( 'Cancel', 'saas-business-management' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Delete confirm -->
	<div class="sbms-modal" id="sbms-exp-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-exp-confirm-title">
		<div class="sbms-modal__backdrop" data-close-confirm="1"></div>
		<div class="sbms-modal__box sbms-modal__box--sm">
			<h2 id="sbms-exp-confirm-title"><?php esc_html_e( 'Delete expense?', 'saas-business-management' ); ?></h2>
			<p id="sbms-exp-confirm-text"></p>
			<div class="sbms-modal__actions">
				<button type="button" class="sbms-btn sbms-btn--danger" id="sbms-exp-confirm-yes"><?php esc_html_e( 'Delete', 'saas-business-management' ); ?></button>
				<button type="button" class="sbms-btn" data-close-confirm="1"><?php esc_html_e( 'Cancel', 'saas-business-management' ); ?></button>
			</div>
		</div>
	</div>

</div>
