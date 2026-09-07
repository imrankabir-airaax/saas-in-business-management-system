<?php
/**
 * AI Insights view.
 *
 * Shell only — analyses are generated on demand from the current user's REAL
 * business data via POST /ai/analyze and listed from GET /ai/reports. No AI
 * output is rendered server-side and nothing is fabricated. The Gemini key is
 * never present on this page or in its script config.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_url = admin_url( 'admin.php?page=' . SBMS_Admin::SETTINGS_SLUG );
?>
<div class="wrap sbms-page" id="sbms-ai-app">

	<div class="sbms-header">
		<div class="sbms-header__titles">
			<h1><?php esc_html_e( 'AI Insights', 'saas-business-management' ); ?></h1>
			<p class="sbms-header__sub"><?php esc_html_e( 'Gemini-powered analysis of your real business data', 'saas-business-management' ); ?></p>
		</div>
	</div>

	<div class="sbms-notice sbms-notice--error" id="sbms-ai-config-notice" hidden>
		<?php
		printf(
			/* translators: %s: settings page URL */
			wp_kses_post( __( 'AI is not available yet. An administrator can enable it and add a Gemini API key in <a href="%s">Settings</a>.', 'saas-business-management' ) ),
			esc_url( $settings_url )
		);
		?>
	</div>

	<div class="sbms-notice" id="sbms-ai-notice" role="alert" aria-live="polite" hidden></div>

	<div class="sbms-card">
		<div class="sbms-card__head"><h2><?php esc_html_e( 'Generate analysis', 'saas-business-management' ); ?></h2></div>
		<div class="sbms-form-row">
			<div class="sbms-field">
				<label for="sbms-ai-type"><?php esc_html_e( 'Analysis type', 'saas-business-management' ); ?></label>
				<select id="sbms-ai-type">
					<option value="business"><?php esc_html_e( 'Business overview', 'saas-business-management' ); ?></option>
					<option value="sales"><?php esc_html_e( 'Sales analysis', 'saas-business-management' ); ?></option>
					<option value="expense"><?php esc_html_e( 'Expense analysis', 'saas-business-management' ); ?></option>
					<option value="inventory"><?php esc_html_e( 'Inventory analysis', 'saas-business-management' ); ?></option>
				</select>
			</div>
			<div class="sbms-field" style="flex:2">
				<label for="sbms-ai-question"><?php esc_html_e( 'Optional question / focus', 'saas-business-management' ); ?></label>
				<input type="text" id="sbms-ai-question" maxlength="500" placeholder="<?php esc_attr_e( 'e.g. Where am I losing money?', 'saas-business-management' ); ?>" />
			</div>
		</div>
		<div class="sbms-header__actions">
			<button type="button" class="sbms-btn sbms-btn--primary" id="sbms-ai-generate"><?php esc_html_e( 'Generate analysis', 'saas-business-management' ); ?></button>
			<button type="button" class="sbms-btn" id="sbms-ai-recommend"><?php esc_html_e( 'Get recommendations', 'saas-business-management' ); ?></button>
		</div>
	</div>

	<div class="sbms-state" id="sbms-ai-loading" hidden>
		<span class="sbms-spinner"></span>
		<p><?php esc_html_e( 'Analysing your business data…', 'saas-business-management' ); ?></p>
	</div>

	<div class="sbms-card" id="sbms-ai-result" hidden>
		<div class="sbms-card__head">
			<h2 id="sbms-ai-result-title"><?php esc_html_e( 'Analysis', 'saas-business-management' ); ?></h2>
			<span class="sbms-badge" id="sbms-ai-result-meta"></span>
		</div>
		<div id="sbms-ai-result-body"></div>
	</div>

	<div class="sbms-card">
		<div class="sbms-card__head"><h2><?php esc_html_e( 'Previous reports', 'saas-business-management' ); ?></h2></div>
		<div id="sbms-ai-reports">
			<div class="sbms-state"><span class="sbms-spinner"></span></div>
		</div>
	</div>

</div>
