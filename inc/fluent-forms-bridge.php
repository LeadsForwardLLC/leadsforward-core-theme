<?php
/**
 * Fluent Forms bridge — full-screen quote takeover + theme-token styling.
 *
 * When a Fluent Form ID is set, existing [data-lf-quote-trigger] CTAs open that
 * form in a theme-owned full-screen modal instead of the native quote builder.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_FLUENT_QUOTE_FORM_OPTION = 'lf_fluent_quote_form_id';
const LF_FLUENT_QUOTE_CSS_BRIDGE_OPTION = 'lf_fluent_quote_css_bridge';

/**
 * Fluent Form ID used for the quote takeover (0 = disabled).
 */
function lf_fluent_quote_form_id(): int {
	$id = (int) get_option(LF_FLUENT_QUOTE_FORM_OPTION, 0);
	$id = (int) apply_filters('lf_fluent_quote_form_id', $id);

	return max(0, $id);
}

/**
 * Whether theme CSS should restyle Fluent Forms with design-system tokens.
 * Default on so brand changes cascade without Fluent Custom CSS.
 */
function lf_fluent_quote_css_bridge_enabled(): bool {
	$raw = get_option(LF_FLUENT_QUOTE_CSS_BRIDGE_OPTION, '1');
	$enabled = $raw === '1' || $raw === 1 || $raw === true;

	return (bool) apply_filters('lf_fluent_quote_css_bridge_enabled', $enabled);
}

/**
 * Whether the Fluent quote takeover should replace the native quote modal.
 */
function lf_fluent_quote_takeover_enabled(): bool {
	if (lf_fluent_quote_form_id() <= 0) {
		return false;
	}
	if (!function_exists('wpFluentForm') && !shortcode_exists('fluentform')) {
		return false;
	}

	return (bool) apply_filters('lf_fluent_quote_takeover_enabled', true);
}

add_action('wp_enqueue_scripts', 'lf_fluent_forms_bridge_enqueue_assets', 30);
add_action('wp_footer', 'lf_fluent_forms_bridge_render_modal', 18);

/**
 * Enqueue bridge CSS/JS when takeover is active.
 */
function lf_fluent_forms_bridge_enqueue_assets(): void {
	if (is_admin() || !lf_fluent_quote_takeover_enabled()) {
		return;
	}

	$js = LF_THEME_URI . '/assets/js/fluent-forms-bridge.js';
	wp_enqueue_script('lf-fluent-forms-bridge', $js, [], LF_THEME_VERSION, true);
	wp_localize_script('lf-fluent-forms-bridge', 'lfFluentQuote', [
		'formId' => lf_fluent_quote_form_id(),
		'cssBridge' => lf_fluent_quote_css_bridge_enabled(),
	]);

	if (!lf_fluent_quote_css_bridge_enabled()) {
		return;
	}

	$css = LF_THEME_URI . '/assets/css/fluent-forms-bridge.css';
	$deps = wp_style_is('lf-design-system', 'enqueued') ? ['lf-design-system'] : [];
	wp_enqueue_style('lf-fluent-forms-bridge', $css, $deps, LF_THEME_VERSION);
}

/**
 * Full-screen shell that mounts the Fluent Form shortcode.
 */
function lf_fluent_forms_bridge_render_modal(): void {
	if (is_admin() || !lf_fluent_quote_takeover_enabled()) {
		return;
	}

	$form_id = lf_fluent_quote_form_id();
	$title_id = 'lf-fluent-quote-title';
	?>
	<div class="lf-quote-modal lf-fluent-quote-modal" id="lf-fluent-quote" aria-hidden="true">
		<div class="lf-quote-modal__overlay" data-lf-fluent-quote-close></div>
		<div class="lf-quote-modal__dialog lf-fluent-quote-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($title_id); ?>" tabindex="-1">
			<button type="button" class="lf-quote-modal__close" data-lf-fluent-quote-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
			<h2 class="screen-reader-text" id="<?php echo esc_attr($title_id); ?>"><?php esc_html_e('Request a free inspection', 'leadsforward-core'); ?></h2>
			<div class="lf-fluent-quote-modal__scroll">
				<div class="lf-fluent-quote-modal__form">
					<?php echo do_shortcode('[fluentform id="' . absint($form_id) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Persist Fluent bridge options from a settings form POST.
 */
function lf_fluent_forms_bridge_save_from_request(): void {
	if (isset($_POST['lf_fluent_quote_form_id'])) {
		$form_id = absint(wp_unslash((string) $_POST['lf_fluent_quote_form_id']));
		update_option(LF_FLUENT_QUOTE_FORM_OPTION, $form_id, true);
	}
	// Checkbox: absent when unchecked.
	if (isset($_POST['lf_fluent_quote_settings_present'])) {
		$css_on = !empty($_POST['lf_fluent_quote_css_bridge']) ? '1' : '0';
		update_option(LF_FLUENT_QUOTE_CSS_BRIDGE_OPTION, $css_on, true);
	}
}

/**
 * @deprecated Use lf_fluent_forms_bridge_save_from_request().
 */
function lf_fluent_forms_bridge_save_form_id_from_request(): void {
	lf_fluent_forms_bridge_save_from_request();
}

/**
 * Compact fields for Quote Builder → Integrations (same options as Global Settings).
 */
function lf_fluent_forms_bridge_render_admin_field(): void {
	lf_fluent_forms_bridge_render_settings_fields(false);
}

/**
 * Render Fluent Forms bridge settings fields.
 *
 * @param bool $for_global_settings When true, includes section intro copy for Global Settings.
 */
function lf_fluent_forms_bridge_render_settings_fields(bool $for_global_settings = true): void {
	$form_id = lf_fluent_quote_form_id();
	$takeover = lf_fluent_quote_takeover_enabled();
	$css_bridge = lf_fluent_quote_css_bridge_enabled();
	?>
	<input type="hidden" name="lf_fluent_quote_settings_present" value="1" />
	<?php if ($for_global_settings) : ?>
		<p class="description">
			<?php esc_html_e('Optional: open a Fluent Form full-screen from every Free Inspection / quote CTA instead of the native Quote Builder. Leave Form ID at 0 to keep the native builder.', 'leadsforward-core'); ?>
		</p>
	<?php endif; ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lf_fluent_quote_form_id"><?php esc_html_e('Fluent Form ID', 'leadsforward-core'); ?></label></th>
			<td>
				<input type="number" min="0" step="1" class="small-text" id="lf_fluent_quote_form_id" name="lf_fluent_quote_form_id" value="<?php echo esc_attr((string) $form_id); ?>" />
				<p class="description">
					<?php esc_html_e('Find the ID in Fluent Forms → All Forms. Set to 0 to use the native Quote Builder.', 'leadsforward-core'); ?>
				</p>
				<?php if ($form_id > 0 && !$takeover) : ?>
					<p class="description" style="color:#b32d2e;">
						<?php esc_html_e('Fluent Forms does not appear to be active. Install/activate Fluent Forms for the takeover to work.', 'leadsforward-core'); ?>
					</p>
				<?php elseif ($takeover) : ?>
					<p class="description" style="color:#007017;">
						<?php esc_html_e('Takeover active: header and quote CTAs open this Fluent Form full-screen.', 'leadsforward-core'); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('Theme CSS override', 'leadsforward-core'); ?></th>
			<td>
				<label>
					<input type="checkbox" name="lf_fluent_quote_css_bridge" value="1" <?php checked($css_bridge); ?> />
					<?php esc_html_e('Apply LeadsForward design-system styles to this Fluent Form', 'leadsforward-core'); ?>
				</label>
				<p class="description">
					<?php esc_html_e('When on, theme tokens (colors, buttons, choice cards) style the form automatically. Leave Fluent Custom CSS blank. Turn off to use Fluent’s own styling only.', 'leadsforward-core'); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
