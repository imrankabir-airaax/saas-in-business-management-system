<?php
/**
 * Sales admin view.
 *
 * Static shell only — the sales list, the product options for the create form,
 * and each sale's details are all loaded client-side from the REST API. Totals
 * shown in the form are a live preview; the authoritative totals are computed
 * on the server when the sale is submitted.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbms-sales" id="sbms-sales-app">

	<h1 class="sbms-sales__title">
		<?php esc_html_e( 'Sales', 'saas-business-management' ); ?>
		<button type="button" class="page-title-action" id="sbms-sales-new">
			<?php esc_html_e( 'New Sale', 'saas-business-management' ); ?>
		</button>
	</h1>

	<div class="sbms-sales__notice" id="sbms-sales-notice" role="alert" aria-live="polite" hidden></div>

	<div class="sbms-sales__table-wrap">
		<table class="widefat striped sbms-sales__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Invoice', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Customer', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-sales__num"><?php esc_html_e( 'Total', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-sales__actions-col"><?php esc_html_e( 'Details', 'saas-business-management' ); ?></th>
				</tr>
			</thead>
			<tbody id="sbms-sales-rows"></tbody>
		</table>

		<div class="sbms-sales__state" id="sbms-sales-loading" hidden>
			<span class="spinner is-active"></span>
			<?php esc_html_e( 'Loading sales…', 'saas-business-management' ); ?>
		</div>

		<div class="sbms-sales__state" id="sbms-sales-empty" hidden>
			<p><?php esc_html_e( 'No sales recorded yet.', 'saas-business-management' ); ?></p>
			<button type="button" class="button button-primary" id="sbms-sales-empty-new">
				<?php esc_html_e( 'Record your first sale', 'saas-business-management' ); ?>
			</button>
		</div>

		<div class="sbms-sales__state sbms-sales__error" id="sbms-sales-error" hidden>
			<p id="sbms-sales-error-msg"></p>
			<button type="button" class="button" id="sbms-sales-retry"><?php esc_html_e( 'Retry', 'saas-business-management' ); ?></button>
		</div>
	</div>

	<div class="sbms-sales__pagination" id="sbms-sales-pagination" hidden>
		<button type="button" class="button" id="sbms-sales-prev"><?php esc_html_e( 'Previous', 'saas-business-management' ); ?></button>
		<span class="sbms-sales__pageinfo" id="sbms-sales-pageinfo"></span>
		<button type="button" class="button" id="sbms-sales-next"><?php esc_html_e( 'Next', 'saas-business-management' ); ?></button>
	</div>

	<!-- Create sale modal -->
	<div class="sbms-sales__modal" id="sbms-sale-modal" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-sale-modal-title">
		<div class="sbms-sales__modal-backdrop" data-close-sale="1"></div>
		<div class="sbms-sales__modal-box">
			<h2 id="sbms-sale-modal-title"><?php esc_html_e( 'New Sale', 'saas-business-management' ); ?></h2>

			<form id="sbms-sale-form">
				<div class="sbms-sales__row">
					<p class="sbms-sales__field">
						<label for="sbms-sf-customer"><?php esc_html_e( 'Customer', 'saas-business-management' ); ?></label>
						<input type="text" id="sbms-sf-customer" maxlength="191" />
					</p>
					<p class="sbms-sales__field">
						<label for="sbms-sf-date"><?php esc_html_e( 'Sale date', 'saas-business-management' ); ?></label>
						<input type="datetime-local" id="sbms-sf-date" />
					</p>
					<p class="sbms-sales__field">
						<label for="sbms-sf-payment"><?php esc_html_e( 'Payment', 'saas-business-management' ); ?></label>
						<select id="sbms-sf-payment">
							<option value="cash"><?php esc_html_e( 'Cash', 'saas-business-management' ); ?></option>
							<option value="card"><?php esc_html_e( 'Card', 'saas-business-management' ); ?></option>
							<option value="transfer"><?php esc_html_e( 'Transfer', 'saas-business-management' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', 'saas-business-management' ); ?></option>
						</select>
					</p>
				</div>

				<h3 class="sbms-sales__subhead"><?php esc_html_e( 'Items', 'saas-business-management' ); ?></h3>

				<table class="sbms-sales__lines">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'saas-business-management' ); ?></th>
							<th class="sbms-sales__num"><?php esc_html_e( 'In stock', 'saas-business-management' ); ?></th>
							<th class="sbms-sales__num"><?php esc_html_e( 'Unit price', 'saas-business-management' ); ?></th>
							<th class="sbms-sales__num"><?php esc_html_e( 'Qty', 'saas-business-management' ); ?></th>
							<th class="sbms-sales__num"><?php esc_html_e( 'Subtotal', 'saas-business-management' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="sbms-sale-lines"></tbody>
				</table>

				<p>
					<button type="button" class="button" id="sbms-sale-add-line"><?php esc_html_e( '+ Add item', 'saas-business-management' ); ?></button>
				</p>

				<div class="sbms-sales__totals">
					<div class="sbms-sales__totrow">
						<span><?php esc_html_e( 'Subtotal', 'saas-business-management' ); ?></span>
						<span id="sbms-sale-subtotal">—</span>
					</div>
					<div class="sbms-sales__totrow">
						<label for="sbms-sf-tax"><?php esc_html_e( 'Tax', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-sf-tax" min="0" step="0.01" value="0" />
					</div>
					<div class="sbms-sales__totrow">
						<label for="sbms-sf-discount"><?php esc_html_e( 'Discount', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-sf-discount" min="0" step="0.01" value="0" />
					</div>
					<div class="sbms-sales__totrow sbms-sales__totrow--grand">
						<span><?php esc_html_e( 'Total', 'saas-business-management' ); ?></span>
						<span id="sbms-sale-total">—</span>
					</div>
				</div>

				<p class="sbms-sales__field">
					<label for="sbms-sf-note"><?php esc_html_e( 'Note', 'saas-business-management' ); ?></label>
					<textarea id="sbms-sf-note" rows="2"></textarea>
				</p>

				<div class="sbms-sales__form-error" id="sbms-sale-form-error" hidden></div>

				<p class="sbms-sales__modal-actions">
					<button type="submit" class="button button-primary" id="sbms-sale-save"><?php esc_html_e( 'Complete sale', 'saas-business-management' ); ?></button>
					<button type="button" class="button" data-close-sale="1"><?php esc_html_e( 'Cancel', 'saas-business-management' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<!-- Sale details modal -->
	<div class="sbms-sales__modal" id="sbms-detail-modal" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-detail-title">
		<div class="sbms-sales__modal-backdrop" data-close-detail="1"></div>
		<div class="sbms-sales__modal-box">
			<h2 id="sbms-detail-title"><?php esc_html_e( 'Sale details', 'saas-business-management' ); ?></h2>
			<div id="sbms-detail-body"></div>
			<p class="sbms-sales__modal-actions">
				<button type="button" class="button" data-close-detail="1"><?php esc_html_e( 'Close', 'saas-business-management' ); ?></button>
			</p>
		</div>
	</div>

</div>
