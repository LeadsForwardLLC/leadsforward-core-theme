<?php
/**
 * LeadsForward parent menu and submenu registration. Admin only.
 * Order: Setup → Homepage → Export/Import/Bulk/Audit → Reset (dev).
 * Site Health is added by inc/site-health/dashboard.php at priority 11.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_ops_register_menu', 10);
add_action('admin_menu', 'lf_ops_remove_theme_options_menu', 999);
add_action('admin_init', 'lf_ops_handle_global_settings_save');
add_action('admin_init', 'lf_ops_handle_branding_save');
add_action('admin_enqueue_scripts', 'lf_ops_settings_assets');

function lf_ops_register_menu(): void {
	// Parent: slug lf-ops so first submenu with same slug (Setup) is the default — no redirect, avoids "headers already sent"
	add_menu_page(
		__('LeadsForward', 'leadsforward-core'),
		__('LeadsForward', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_wizard_render_page',
		'dashicons-admin-generic',
		59
	);

	// 1. Setup — same slug as parent so clicking "LeadsForward" shows this; only this item highlights when on page=lf-ops
	add_submenu_page(
		'lf-ops',
		__('Setup', 'leadsforward-core'),
		__('Setup', 'leadsforward-core'),
		'edit_theme_options',
		'lf-ops',
		'lf_wizard_render_page'
	);
	// 2. Homepage (sections, business info)
	add_submenu_page(
		'lf-ops',
		__('Homepage', 'leadsforward-core'),
		__('Homepage', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-homepage-settings',
		'lf_homepage_admin_render'
	);
	$has_acf_options = function_exists('acf_options_page_html');
	// Global Settings + Branding (custom UI fallback if ACF options pages are unavailable).
	add_submenu_page(
		'lf-ops',
		__('Global Settings', 'leadsforward-core'),
		__('Global Settings', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-global',
		$has_acf_options ? 'lf_ops_render_acf_options_page' : 'lf_ops_render_global_settings_page'
	);
	add_submenu_page(
		'lf-ops',
		__('Branding', 'leadsforward-core'),
		__('Branding', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-branding',
		$has_acf_options ? 'lf_ops_render_acf_options_page' : 'lf_ops_render_branding_page'
	);
	// Remaining ACF option pages (only render if ACF options pages exist).
	if ($has_acf_options) {
		add_submenu_page('lf-ops', __('CTAs', 'leadsforward-core'), __('CTAs', 'leadsforward-core'), LF_OPS_CAP, 'lf-ctas', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Schema', 'leadsforward-core'), __('Schema', 'leadsforward-core'), LF_OPS_CAP, 'lf-schema', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Variation', 'leadsforward-core'), __('Variation', 'leadsforward-core'), LF_OPS_CAP, 'lf-variation', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Homepage Options', 'leadsforward-core'), __('Homepage Options', 'leadsforward-core'), LF_OPS_CAP, 'lf-homepage', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Business Info', 'leadsforward-core'), __('Business Info', 'leadsforward-core'), LF_OPS_CAP, 'lf-business-info', 'lf_ops_render_acf_options_page');
	}
	// 3. Export Config (own slug so it doesn’t highlight when parent is clicked)
	add_submenu_page(
		'lf-ops',
		__('Export Config', 'leadsforward-core'),
		__('Export Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-export',
		'lf_ops_export_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Import Config', 'leadsforward-core'),
		__('Import Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-import',
		'lf_ops_import_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Bulk Actions', 'leadsforward-core'),
		__('Bulk Actions', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-bulk',
		'lf_ops_bulk_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Audit Log', 'leadsforward-core'),
		__('Audit Log', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-audit',
		'lf_ops_audit_render'
	);
	// 4. Reset site (dev only) — last, gated by lf_dev_reset_allowed()
	if (function_exists('lf_dev_reset_allowed') && lf_dev_reset_allowed()) {
		add_submenu_page(
			'lf-ops',
			__('Reset site (dev)', 'leadsforward-core'),
			__('Reset site (dev)', 'leadsforward-core'),
			'manage_options',
			'lf-dev-reset',
			'lf_dev_reset_render_page'
		);
	}
}

function lf_ops_render_acf_options_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (function_exists('acf_options_page_html')) {
		acf_options_page_html();
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Settings', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('This page requires Advanced Custom Fields (ACF).', 'leadsforward-core') . '</p></div>';
}

function lf_ops_settings_assets(string $hook): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!in_array($hook, ['leadsforward_page_lf-global', 'leadsforward_page_lf-branding'], true)) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style('wp-color-picker');
	wp_enqueue_script('wp-color-picker');
}

function lf_ops_handle_global_settings_save(): void {
	if (!isset($_POST['lf_global_settings_nonce'])) {
		return;
	}
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_global_settings_nonce'], 'lf_global_settings')) {
		return;
	}
	$logo_id = isset($_POST['lf_global_logo']) ? (int) $_POST['lf_global_logo'] : 0;
	update_option('options_lf_global_logo', $logo_id);
	update_option('options_lf_header_cta_label', isset($_POST['lf_header_cta_label']) ? sanitize_text_field(wp_unslash($_POST['lf_header_cta_label'])) : '');
	update_option('options_lf_header_cta_url', isset($_POST['lf_header_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_header_cta_url'])) : '');
	wp_safe_redirect(admin_url('admin.php?page=lf-global&saved=1'));
	exit;
}

function lf_ops_handle_branding_save(): void {
	if (!isset($_POST['lf_branding_settings_nonce'])) {
		return;
	}
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_branding_settings_nonce'], 'lf_branding_settings')) {
		return;
	}
	$keys = [
		'lf_brand_primary',
		'lf_brand_secondary',
		'lf_brand_tertiary',
		'lf_surface_light',
		'lf_surface_soft',
		'lf_surface_dark',
		'lf_surface_card',
		'lf_text_primary',
		'lf_text_muted',
		'lf_text_inverse',
	];
	foreach ($keys as $key) {
		$val = isset($_POST[$key]) ? sanitize_hex_color(wp_unslash($_POST[$key])) : '';
		update_option('options_' . $key, $val ?: '');
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-branding&saved=1'));
	exit;
}

function lf_ops_render_global_settings_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$logo_id = (int) get_option('options_lf_global_logo', 0);
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
	$cta_label = (string) get_option('options_lf_header_cta_label', '');
	$cta_url = (string) get_option('options_lf_header_cta_url', '');
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field('lf_global_settings', 'lf_global_settings_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Logo', 'leadsforward-core'); ?></th>
					<td>
						<div style="display:flex;align-items:center;gap:1rem;">
							<div>
								<img id="lf-global-logo-preview" src="<?php echo esc_url($logo_url); ?>" style="max-height:60px;<?php echo $logo_url ? '' : 'display:none;'; ?>" alt="" />
							</div>
							<input type="hidden" name="lf_global_logo" id="lf_global_logo" value="<?php echo esc_attr((string) $logo_id); ?>" />
							<button type="button" class="button" id="lf-global-logo-select"><?php esc_html_e('Select Logo', 'leadsforward-core'); ?></button>
							<button type="button" class="button" id="lf-global-logo-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_header_cta_label"><?php esc_html_e('Header CTA label', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="regular-text" id="lf_header_cta_label" name="lf_header_cta_label" value="<?php echo esc_attr($cta_label); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_header_cta_url"><?php esc_html_e('Header CTA URL', 'leadsforward-core'); ?></label></th>
					<td><input type="url" class="large-text" id="lf_header_cta_url" name="lf_header_cta_url" value="<?php echo esc_attr($cta_url); ?>" /></td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Global Settings', 'leadsforward-core'); ?></button></p>
		</form>
	</div>
	<script>
		(function () {
			var frame;
			var selectBtn = document.getElementById('lf-global-logo-select');
			var clearBtn = document.getElementById('lf-global-logo-clear');
			var input = document.getElementById('lf_global_logo');
			var preview = document.getElementById('lf-global-logo-preview');
			if (selectBtn) {
				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select Logo', button: { text: 'Use logo' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (input) input.value = attachment.id;
						if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
					});
					frame.open();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (input) input.value = '';
					if (preview) { preview.src = ''; preview.style.display = 'none'; }
				});
			}
		})();
	</script>
	<?php
}

function lf_ops_render_branding_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$get = function (string $key, string $default): string {
		$val = get_option('options_' . $key, $default);
		return is_string($val) && $val !== '' ? $val : $default;
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Branding', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field('lf_branding_settings', 'lf_branding_settings_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e('Primary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_primary" value="<?php echo esc_attr($get('lf_brand_primary', '#2563eb')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Secondary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_secondary" value="<?php echo esc_attr($get('lf_brand_secondary', '#0ea5e9')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Tertiary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_tertiary" value="<?php echo esc_attr($get('lf_brand_tertiary', '#f97316')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Light background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_light" value="<?php echo esc_attr($get('lf_surface_light', '#ffffff')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Soft background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_soft" value="<?php echo esc_attr($get('lf_surface_soft', '#f8fafc')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Dark background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_dark" value="<?php echo esc_attr($get('lf_surface_dark', '#0f172a')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Card background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_card" value="<?php echo esc_attr($get('lf_surface_card', '#ffffff')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Primary text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_primary" value="<?php echo esc_attr($get('lf_text_primary', '#0f172a')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Muted text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_muted" value="<?php echo esc_attr($get('lf_text_muted', '#64748b')); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e('Inverse text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_inverse" value="<?php echo esc_attr($get('lf_text_inverse', '#ffffff')); ?>" /></td></tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Branding', 'leadsforward-core'); ?></button></p>
		</form>
	</div>
	<script>
		jQuery(function ($) {
			if ($.fn.wpColorPicker) {
				$('.lf-color').wpColorPicker();
			}
		});
	</script>
	<?php
}

function lf_ops_remove_theme_options_menu(): void {
	// Remove Theme Options submenus under Appearance to keep everything under LeadsForward.
	foreach (['lf-theme-options', 'lf-global', 'lf-branding', 'lf-ctas', 'lf-schema', 'lf-homepage', 'lf-variation', 'lf-business-info'] as $slug) {
		remove_submenu_page('themes.php', $slug);
	}
}
