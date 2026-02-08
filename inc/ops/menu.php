<?php
/**
 * LeadsForward parent menu and submenu registration. Admin only.
 * Order: Setup → Global Settings → Homepage → Ops (bulk/audit/config).
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
	// 2. Global Settings (includes Branding).
	add_submenu_page(
		'lf-ops',
		__('Global Settings', 'leadsforward-core'),
		__('Global Settings', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-global',
		'lf_ops_render_global_settings_page'
	);
	// 3. Homepage (sections, business info)
	add_submenu_page(
		'lf-ops',
		__('Homepage', 'leadsforward-core'),
		__('Homepage', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-homepage-settings',
		'lf_homepage_admin_render'
	);
	// 4. Quote Builder
	add_submenu_page(
		'lf-ops',
		__('Quote Builder', 'leadsforward-core'),
		__('Quote Builder', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-quote-builder',
		'lf_quote_builder_render_admin'
	);
	add_submenu_page(
		'lf-ops',
		__('Quote Builder Integrations', 'leadsforward-core'),
		__('Quote Builder — Integrations', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-quote-builder-integrations',
		'lf_quote_builder_render_integrations'
	);
	add_submenu_page(
		'lf-ops',
		__('Quote Builder Analytics', 'leadsforward-core'),
		__('Quote Builder — Analytics', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-quote-builder-analytics',
		'lf_quote_builder_render_analytics'
	);
	$has_acf_options = function_exists('acf_options_page_html');
	// Remaining ACF option pages (only render if ACF options pages exist).
	if ($has_acf_options) {
		add_submenu_page('lf-ops', __('CTAs', 'leadsforward-core'), __('CTAs', 'leadsforward-core'), LF_OPS_CAP, 'lf-ctas', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Schema', 'leadsforward-core'), __('Schema', 'leadsforward-core'), LF_OPS_CAP, 'lf-schema', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Variation', 'leadsforward-core'), __('Variation', 'leadsforward-core'), LF_OPS_CAP, 'lf-variation', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Homepage Options', 'leadsforward-core'), __('Homepage Options', 'leadsforward-core'), LF_OPS_CAP, 'lf-homepage', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Business Info', 'leadsforward-core'), __('Business Info', 'leadsforward-core'), LF_OPS_CAP, 'lf-business-info', 'lf_ops_render_acf_options_page');
	}
	// Ops utilities.
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
	// Config (Export + Import) — keep at the bottom.
	add_submenu_page(
		'lf-ops',
		__('Config', 'leadsforward-core'),
		__('Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-config',
		'lf_ops_config_render'
	);
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
	if (!in_array($hook, ['leadsforward_page_lf-global'], true)) {
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
	$prev_logo_id = (int) lf_get_global_option('lf_global_logo', 0);
	$logo_id = isset($_POST['lf_global_logo']) ? (int) $_POST['lf_global_logo'] : 0;
	update_option('options_lf_global_logo', $logo_id);
	update_option('options_lf_header_cta_label', isset($_POST['lf_header_cta_label']) ? sanitize_text_field(wp_unslash($_POST['lf_header_cta_label'])) : '');
	update_option('options_lf_header_cta_url', isset($_POST['lf_header_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_header_cta_url'])) : '');
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
	if (function_exists('update_field')) {
		$global_post_ids = ['lf-global', 'options_lf_global', 'options_lf-global', 'option', 'options'];
		foreach ($global_post_ids as $post_id) {
			update_field('lf_global_logo', $logo_id, $post_id);
			update_field('lf_header_cta_label', isset($_POST['lf_header_cta_label']) ? sanitize_text_field(wp_unslash($_POST['lf_header_cta_label'])) : '', $post_id);
			update_field('lf_header_cta_url', isset($_POST['lf_header_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_header_cta_url'])) : '', $post_id);
		}
		foreach ($keys as $key) {
			$val = isset($_POST[$key]) ? sanitize_hex_color(wp_unslash($_POST[$key])) : '';
			if ($val) {
				foreach (['lf-branding', 'options_lf_branding', 'options_lf-branding', 'option', 'options'] as $post_id) {
					update_field($key, $val, $post_id);
				}
			}
		}
	}
	if ($logo_id > 0 && $logo_id !== $prev_logo_id && function_exists('lf_branding_auto_from_logo')) {
		lf_branding_auto_from_logo($logo_id);
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-global&saved=1'));
	exit;
}

function lf_ops_render_global_settings_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$logo_id = (int) lf_get_global_option('lf_global_logo', 0);
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
	$cta_label = (string) lf_get_global_option('lf_header_cta_label', '');
	$cta_url = (string) lf_get_global_option('lf_header_cta_url', '');
	$get_brand = function (string $key, string $default): string {
		if (function_exists('lf_branding_get_value')) {
			return lf_branding_get_value($key, $default);
		}
		$val = get_option('options_' . $key, $default);
		return is_string($val) && $val !== '' ? $val : $default;
	};
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<style>
			.lf-settings-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin: 1.25rem 0; }
			.lf-settings-panel-header { display: flex; align-items: center; gap: 0.75rem; }
			.lf-settings-panel-header h2 { margin: 0; }
			.lf-settings-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
			.lf-settings-toggle:hover { background: #e2e8f0; }
			.lf-settings-fields--collapsed { display: none; }
			.lf-settings-panel--collapsed .lf-settings-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
		</style>
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
			<div class="lf-settings-panel" data-section="branding">
				<div class="lf-settings-panel-header">
					<h2><?php esc_html_e('Branding', 'leadsforward-core'); ?></h2>
					<button type="button" class="lf-settings-toggle" data-target="branding" aria-expanded="true">
						<span class="lf-settings-toggle-icon">▾</span>
						<span class="lf-settings-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
					</button>
				</div>
				<div class="lf-settings-panel-body" data-parent="branding">
					<p class="description"><?php esc_html_e('When you upload a logo, the primary colors sync automatically. You can adjust these anytime.', 'leadsforward-core'); ?></p>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e('Primary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_primary" value="<?php echo esc_attr($get_brand('lf_brand_primary', '#2563eb')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Secondary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_secondary" value="<?php echo esc_attr($get_brand('lf_brand_secondary', '#0ea5e9')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Tertiary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_tertiary" value="<?php echo esc_attr($get_brand('lf_brand_tertiary', '#f97316')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Light background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_light" value="<?php echo esc_attr($get_brand('lf_surface_light', '#ffffff')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Soft background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_soft" value="<?php echo esc_attr($get_brand('lf_surface_soft', '#f8fafc')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Dark background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_dark" value="<?php echo esc_attr($get_brand('lf_surface_dark', '#0f172a')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Card background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_card" value="<?php echo esc_attr($get_brand('lf_surface_card', '#ffffff')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Primary text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_primary" value="<?php echo esc_attr($get_brand('lf_text_primary', '#0f172a')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Muted text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_muted" value="<?php echo esc_attr($get_brand('lf_text_muted', '#64748b')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Inverse text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_inverse" value="<?php echo esc_attr($get_brand('lf_text_inverse', '#ffffff')); ?>" /></td></tr>
					</table>
				</div>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Global Settings', 'leadsforward-core'); ?></button></p>
		</form>
	</div>
	<script>
		(function () {
			var storageKey = 'lf_global_settings_collapsed';
			var collapsed = {};
			try {
				collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
			} catch (e) {
				collapsed = {};
			}
			function applyCollapse(type) {
				var isCollapsed = !!collapsed[type];
				var panel = document.querySelector('.lf-settings-panel[data-section="' + type + '"]');
				var body = document.querySelector('.lf-settings-panel-body[data-parent="' + type + '"]');
				if (panel && body) {
					panel.classList.toggle('lf-settings-panel--collapsed', isCollapsed);
					body.classList.toggle('lf-settings-fields--collapsed', isCollapsed);
					var toggle = panel.querySelector('.lf-settings-toggle');
					if (toggle) {
						toggle.setAttribute('aria-expanded', (!isCollapsed).toString());
						var icon = toggle.querySelector('.lf-settings-toggle-icon');
						var label = toggle.querySelector('.lf-settings-toggle-label');
						if (icon) icon.textContent = isCollapsed ? '▸' : '▾';
						if (label) label.textContent = isCollapsed ? 'Expand' : 'Collapse';
					}
				}
			}
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
			var toggle = document.querySelector('.lf-settings-toggle');
			if (toggle) {
				var type = toggle.getAttribute('data-target');
				applyCollapse(type);
				toggle.addEventListener('click', function () {
					collapsed[type] = !collapsed[type];
					try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
					applyCollapse(type);
				});
			}
		})();
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
	foreach (['lf-theme-options', 'lf-global', 'lf-ctas', 'lf-schema', 'lf-homepage', 'lf-variation', 'lf-business-info'] as $slug) {
		remove_submenu_page('themes.php', $slug);
	}
}
