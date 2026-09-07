<?php
/**
 * Inventory admin view.
 *
 * A static shell only. All product data is loaded client-side from the REST
 * API (no data is rendered server-side, so there are no dummy products). JS in
 * admin/js/inventory.js populates the table and drives the forms.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sbms-inv" id="sbms-inventory-app">

	<h1 class="sbms-inv__title">
		<?php esc_html_e( 'Inventory', 'saas-business-management' ); ?>
		<button type="button" class="page-title-action" id="sbms-inv-add">
			<?php esc_html_e( 'Add Product', 'saas-business-management' ); ?>
		</button>
	</h1>

	<div class="sbms-inv__notice" id="sbms-inv-notice" role="alert" aria-live="polite" hidden></div>

	<div class="sbms-inv__toolbar">
		<input
			type="search"
			id="sbms-inv-search"
			class="sbms-inv__search"
			placeholder="<?php esc_attr_e( 'Search name, SKU, category…', 'saas-business-management' ); ?>"
			autocomplete="off"
		/>
		<label class="sbms-inv__filter">
			<input type="checkbox" id="sbms-inv-lowstock" />
			<?php esc_html_e( 'Low stock only', 'saas-business-management' ); ?>
		</label>
	</div>

	<div class="sbms-inv__table-wrap">
		<table class="widefat striped sbms-inv__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'SKU', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-inv__num"><?php esc_html_e( 'Price', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-inv__num"><?php esc_html_e( 'Cost', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-inv__num"><?php esc_html_e( 'Stock', 'saas-business-management' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'saas-business-management' ); ?></th>
					<th scope="col" class="sbms-inv__actions-col"><?php esc_html_e( 'Actions', 'saas-business-management' ); ?></th>
				</tr>
			</thead>
			<tbody id="sbms-inv-rows"></tbody>
		</table>

		<div class="sbms-inv__state sbms-inv__loading" id="sbms-inv-loading" hidden>
			<span class="spinner is-active"></span>
			<?php esc_html_e( 'Loading products…', 'saas-business-management' ); ?>
		</div>

		<div class="sbms-inv__state sbms-inv__empty" id="sbms-inv-empty" hidden>
			<p><?php esc_html_e( 'No products yet.', 'saas-business-management' ); ?></p>
			<button type="button" class="button button-primary" id="sbms-inv-empty-add">
				<?php esc_html_e( 'Add your first product', 'saas-business-management' ); ?>
			</button>
		</div>

		<div class="sbms-inv__state sbms-inv__error" id="sbms-inv-error" hidden>
			<p id="sbms-inv-error-msg"></p>
			<button type="button" class="button" id="sbms-inv-retry">
				<?php esc_html_e( 'Retry', 'saas-business-management' ); ?>
			</button>
		</div>
	</div>

	<div class="sbms-inv__pagination" id="sbms-inv-pagination" hidden>
		<button type="button" class="button" id="sbms-inv-prev"><?php esc_html_e( 'Previous', 'saas-business-management' ); ?></button>
		<span class="sbms-inv__pageinfo" id="sbms-inv-pageinfo"></span>
		<button type="button" class="button" id="sbms-inv-next"><?php esc_html_e( 'Next', 'saas-business-management' ); ?></button>
	</div>

	<!-- Add / Edit modal -->
	<div class="sbms-inv__modal" id="sbms-inv-modal" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-inv-modal-title">
		<div class="sbms-inv__modal-backdrop" data-close="1"></div>
		<div class="sbms-inv__modal-box">
			<h2 id="sbms-inv-modal-title"><?php esc_html_e( 'Add Product', 'saas-business-management' ); ?></h2>
			<form id="sbms-inv-form">
				<input type="hidden" id="sbms-f-id" value="" />

				<p class="sbms-inv__field">
					<label for="sbms-f-name"><?php esc_html_e( 'Name', 'saas-business-management' ); ?> <span class="sbms-req">*</span></label>
					<input type="text" id="sbms-f-name" required maxlength="191" />
				</p>

				<div class="sbms-inv__row">
					<p class="sbms-inv__field">
						<label for="sbms-f-sku"><?php esc_html_e( 'SKU', 'saas-business-management' ); ?></label>
						<input type="text" id="sbms-f-sku" maxlength="100" />
					</p>
					<p class="sbms-inv__field">
						<label for="sbms-f-category"><?php esc_html_e( 'Category', 'saas-business-management' ); ?></label>
						<input type="text" id="sbms-f-category" maxlength="191" />
					</p>
				</div>

				<div class="sbms-inv__row">
					<p class="sbms-inv__field">
						<label for="sbms-f-price"><?php esc_html_e( 'Selling price', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-f-price" min="0" step="0.01" />
					</p>
					<p class="sbms-inv__field">
						<label for="sbms-f-cost"><?php esc_html_e( 'Cost price', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-f-cost" min="0" step="0.01" />
					</p>
				</div>

				<div class="sbms-inv__row">
					<p class="sbms-inv__field">
						<label for="sbms-f-stock"><?php esc_html_e( 'Stock quantity', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-f-stock" min="0" step="1" />
					</p>
					<p class="sbms-inv__field">
						<label for="sbms-f-threshold"><?php esc_html_e( 'Low-stock threshold', 'saas-business-management' ); ?></label>
						<input type="number" id="sbms-f-threshold" min="0" step="1" />
					</p>
				</div>

				<div class="sbms-inv__row">
					<p class="sbms-inv__field">
						<label for="sbms-f-status"><?php esc_html_e( 'Status', 'saas-business-management' ); ?></label>
						<select id="sbms-f-status">
							<option value="active"><?php esc_html_e( 'Active', 'saas-business-management' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'saas-business-management' ); ?></option>
						</select>
					</p>
				</div>

				<p class="sbms-inv__field">
					<label for="sbms-f-description"><?php esc_html_e( 'Description', 'saas-business-management' ); ?></label>
					<textarea id="sbms-f-description" rows="3"></textarea>
				</p>

				<div class="sbms-inv__form-error" id="sbms-inv-form-error" hidden></div>

				<p class="sbms-inv__modal-actions">
					<button type="submit" class="button button-primary" id="sbms-inv-save"><?php esc_html_e( 'Save', 'saas-business-management' ); ?></button>
					<button type="button" class="button" data-close="1"><?php esc_html_e( 'Cancel', 'saas-business-management' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<!-- Delete confirmation -->
	<div class="sbms-inv__modal" id="sbms-inv-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="sbms-inv-confirm-title">
		<div class="sbms-inv__modal-backdrop" data-close-confirm="1"></div>
		<div class="sbms-inv__modal-box sbms-inv__modal-box--sm">
			<h2 id="sbms-inv-confirm-title"><?php esc_html_e( 'Delete product?', 'saas-business-management' ); ?></h2>
			<p id="sbms-inv-confirm-text"></p>
			<p class="sbms-inv__modal-actions">
				<button type="button" class="button button-link-delete" id="sbms-inv-confirm-yes"><?php esc_html_e( 'Delete', 'saas-business-management' ); ?></button>
				<button type="button" class="button" data-close-confirm="1"><?php esc_html_e( 'Cancel', 'saas-business-management' ); ?></button>
			</p>
		</div>
	</div>

</div>
