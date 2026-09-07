<?php
/**
 * Settings view.
 *
 * A server-rendered form that posts to admin-post.php (nonce + capability
 * checked in SBMS_Admin::handle_settings_save). The Gemini API key lives in the
 * sbms_settings option on the server: it is NEVER printed back into the page and
 * NEVER passed to JavaScript. The key field is always empty on load; we only
 * show whether a key is configured.
 *
 * Expects: $settings (array), $has_key (bool), $saved (bool).
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $settings ) && is_array( $settings ) ? $settings : array();
$has_key  = ! empty( $has_key );
$saved    = ! empty( $saved );
?>
<div class="wrap sbms-page" id="sbms-settings-app">

	<div class="sbms-header">
		<div class="sbms-header__titles">
			<h1><?php esc_html_e( 'Settings', 'saas-business-management' ); ?></h1>
			<p class="sbms-header__sub"><?php esc_html_e( 'Configure your workspace and AI integration', 'saas-business-management' ); ?></p>
		</div>
	</div>

	<?php if ( $saved ) : ?>
		<div class="sbms-notice sbms-notice--success"><?php esc_html_e( 'Settings saved.', 'saas-business-management' ); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sbms_save_settings" />
		<?php wp_nonce_field( 'sbms_save_settings' ); ?>

		<div class="sbms-card">
			<div class="sbms-card__head"><h2><?php esc_html_e( 'General', 'saas-business-management' ); ?></h2></div>
			<div class="sbms-field">
				<label for="sbms-set-currency"><?php esc_html_e( 'Currency symbol', 'saas-business-management' ); ?></label>
				<input type="text" id="sbms-set-currency" name="currency_symbol" maxlength="8"
					value="<?php echo esc_attr( isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$' ); ?>" />
				<span class="sbms-field__hint"><?php esc_html_e( 'Shown before money values across the app (e.g. $).', 'saas-business-management' ); ?></span>
			</div>
		</div>

		<div class="sbms-card">
			<div class="sbms-card__head">
				<h2><?php esc_html_e( 'AI (Gemini)', 'saas-business-management' ); ?></h2>
				<?php if ( $has_key ) : ?>
					<span class="sbms-badge sbms-badge--good"><?php esc_html_e( 'Key configured', 'saas-business-management' ); ?></span>
				<?php else : ?>
					<span class="sbms-badge sbms-badge--warn"><?php esc_html_e( 'Not configured', 'saas-business-management' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="sbms-field">
				<label>
					<input type="checkbox" name="enable_ai" value="1" <?php checked( ! empty( $settings['enable_ai'] ) ); ?> />
					<?php esc_html_e( 'Enable AI insights', 'saas-business-management' ); ?>
				</label>
			</div>

			<div class="sbms-field">
				<label for="sbms-set-model"><?php esc_html_e( 'Model', 'saas-business-management' ); ?></label>
				<input type="text" id="sbms-set-model" name="gemini_model" maxlength="60" list="sbms-model-options"
					value="<?php echo esc_attr( isset( $settings['gemini_model'] ) ? $settings['gemini_model'] : 'gemini-3.6-flash' ); ?>" />
				<datalist id="sbms-model-options">
					<option value="gemini-3.6-flash"><?php esc_html_e( 'Recommended: latest stable Flash model', 'saas-business-management' ); ?></option>
					<option value="gemini-3.5-flash"><?php esc_html_e( 'Previous generation Flash model', 'saas-business-management' ); ?></option>
					<option value="gemini-3.5-flash-lite"><?php esc_html_e( 'Fast and low cost', 'saas-business-management' ); ?></option>
					<option value="gemini-3.1-flash-lite"><?php esc_html_e( 'Lowest cost', 'saas-business-management' ); ?></option>
				</datalist>
				<span class="sbms-field__hint"><?php esc_html_e( 'Recommended: gemini-3.6-flash. If Google ever retires the configured model, the plugin automatically falls back to a supported one.', 'saas-business-management' ); ?></span>
			</div>

			<div class="sbms-field">
				<label for="sbms-set-key"><?php esc_html_e( 'Gemini API key', 'saas-business-management' ); ?></label>
				<input type="password" id="sbms-set-key" name="gemini_api_key" autocomplete="new-password"
					placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (leave blank to keep current key)', 'saas-business-management' ) : esc_attr__( 'Paste your Gemini API key', 'saas-business-management' ); ?>" />
				<span class="sbms-field__hint">
					<?php esc_html_e( 'Stored securely on the server. It is never shown again or sent to your browser.', 'saas-business-management' ); ?>
				</span>
			</div>

			<?php if ( $has_key ) : ?>
				<div class="sbms-field">
					<label>
						<input type="checkbox" name="remove_gemini_api_key" value="1" />
						<?php esc_html_e( 'Remove the stored key', 'saas-business-management' ); ?>
					</label>
				</div>
			<?php endif; ?>
		</div>

		<p>
			<button type="submit" class="sbms-btn sbms-btn--primary"><?php esc_html_e( 'Save settings', 'saas-business-management' ); ?></button>
		</p>
	</form>

</div>
