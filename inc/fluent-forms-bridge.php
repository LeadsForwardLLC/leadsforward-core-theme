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

/**
 * Fluent Form ID used for the quote takeover (0 = disabled).
 */
function lf_fluent_quote_form_id(): int {
	$id = (int) get_option(LF_FLUENT_QUOTE_FORM_OPTION, 0);
	$id = (int) apply_filters('lf_fluent_quote_form_id', $id);

	return max(0, $id);
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

	$css = LF_THEME_URI . '/assets/css/fluent-forms-bridge.css';
	$js = LF_THEME_URI . '/assets/js/fluent-forms-bridge.js';
	$deps = wp_style_is('lf-design-system', 'enqueued') ? ['lf-design-system'] : [];

	wp_enqueue_style('lf-fluent-forms-bridge', $css, $deps, LF_THEME_VERSION);
	wp_enqueue_script('lf-fluent-forms-bridge', $js, [], LF_THEME_VERSION, true);
	wp_localize_script('lf-fluent-forms-bridge', 'lfFluentQuote', [
		'formId' => lf_fluent_quote_form_id(),
	]);
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
 * Persist Fluent Form ID (called from Quote Builder integrations save).
 */
function lf_fluent_forms_bridge_save_form_id_from_request(): void {
	if (!isset($_POST['lf_fluent_quote_form_id'])) {
		return;
	}
	$form_id = absint(wp_unslash((string) $_POST['lf_fluent_quote_form_id']));
	update_option(LF_FLUENT_QUOTE_FORM_OPTION, $form_id, true);
}

/**
 * Admin field HTML for the Fluent Form ID.
 */
function lf_fluent_forms_bridge_render_admin_field(): void {
	$form_id = lf_fluent_quote_form_id();
	$enabled = lf_fluent_quote_takeover_enabled();
	?>
	<tr>
		<th scope="row"><label for="lf_fluent_quote_form_id"><?php esc_html_e('Fluent Forms quote takeover', 'leadsforward-core'); ?></label></th>
		<td>
			<input type="number" min="0" step="1" class="small-text" id="lf_fluent_quote_form_id" name="lf_fluent_quote_form_id" value="<?php echo esc_attr((string) $form_id); ?>" />
			<p class="description">
				<?php
				esc_html_e('Enter the Fluent Form ID to open that form full-screen from every Free Inspection / quote CTA. Set to 0 to use the native Quote Builder.', 'leadsforward-core');
				?>
			</p>
			<?php if ($form_id > 0 && !$enabled) : ?>
				<p class="description" style="color:#b32d2e;">
					<?php esc_html_e('Fluent Forms does not appear to be active. Install/activate Fluent Forms for the takeover to work.', 'leadsforward-core'); ?>
				</p>
			<?php elseif ($enabled) : ?>
				<p class="description" style="color:#007017;">
					<?php esc_html_e('Takeover active: header and quote CTAs open this Fluent Form full-screen.', 'leadsforward-core'); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}
