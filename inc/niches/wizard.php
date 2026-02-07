<?php
/**
 * Admin setup wizard UI. One-time flow: niche → NAP → services/areas → profile → generate.
 * Persists completion flag; never shows again after success.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_wizard_handle_post');
add_action('admin_notices', 'lf_wizard_admin_notice');
add_action('after_switch_theme', 'lf_wizard_on_activation');

function lf_wizard_on_activation(): void {
	// Do not reset completion on re-activation. Only first install has no option.
	if (get_option('lf_setup_wizard_complete', null) === null) {
		update_option('lf_setup_wizard_complete', false);
	}
}

function lf_wizard_admin_notice(): void {
	if (get_option('lf_setup_wizard_complete', false)) {
		return;
	}
	$screen = get_current_screen();
	// Hide notice on any LeadsForward admin page (Setup lives under LeadsForward menu)
	if ($screen && strpos($screen->id, 'lf-ops') !== false) {
		return;
	}
	echo '<div class="notice notice-info"><p>' . sprintf(
		/* translators: %s: link to setup wizard */
		esc_html__('LeadsForward: Complete your site setup in one go. %s', 'leadsforward-core'),
		'<a href="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">' . esc_html__('Run setup wizard', 'leadsforward-core') . '</a>'
	) . '</p></div>';
}

function lf_wizard_handle_post(): void {
	if (!isset($_POST['lf_wizard_step']) || !current_user_can('edit_theme_options')) {
		return;
	}
	$step = (int) $_POST['lf_wizard_step'];
	if ($step === 5 && isset($_POST['lf_wizard_generate']) && check_admin_referer('lf_wizard_generate', 'lf_wizard_nonce')) {
		$data = [
			'niche_slug'                 => sanitize_text_field($_POST['lf_niche'] ?? ''),
			'business_name'              => sanitize_text_field($_POST['lf_business_name'] ?? ''),
			'business_phone'             => sanitize_text_field($_POST['lf_business_phone'] ?? ''),
			'business_email'             => sanitize_email($_POST['lf_business_email'] ?? ''),
			'business_address'           => sanitize_textarea_field($_POST['lf_business_address'] ?? ''),
			'business_hours'             => sanitize_textarea_field($_POST['lf_business_hours'] ?? ''),
			'service_areas'              => lf_wizard_sanitize_areas($_POST['lf_service_areas'] ?? ''),
			'variation_profile_override' => sanitize_text_field($_POST['lf_variation_profile'] ?? ''),
		];
		$result = lf_run_setup($data);
		if (!empty($result['success'])) {
			// Ensure business info is saved where LeadsForward → Homepage (Business Info) reads from
			$business_slug = defined('LF_OPTIONS_PAGE_BUSINESS') ? LF_OPTIONS_PAGE_BUSINESS : 'lf-business-info';
			if (function_exists('update_field')) {
				update_field('lf_business_name', $data['business_name'] ?? '', $business_slug);
				update_field('lf_business_phone', $data['business_phone'] ?? '', $business_slug);
				update_field('lf_business_email', $data['business_email'] ?? '', $business_slug);
				update_field('lf_business_address', $data['business_address'] ?? '', $business_slug);
				if (array_key_exists('business_hours', $data) && $data['business_hours'] !== '') {
					update_field('lf_business_hours', $data['business_hours'], $business_slug);
				}
			}
			update_option('lf_setup_wizard_complete', true);
			if (!empty($result['ids']) && is_array($result['ids'])) {
				update_option('lf_wizard_created_ids', $result['ids']);
			}
			wp_redirect(admin_url('admin.php?page=lf-ops&done=1'));
			exit;
		}
		wp_redirect(admin_url('admin.php?page=lf-ops&step=5&errors=1&msg=' . urlencode(implode('; ', $result['errors']))));
		exit;
	}
}

function lf_wizard_sanitize_areas($input): array {
	if (is_array($input)) {
		return array_filter(array_map('sanitize_text_field', $input));
	}
	$lines = array_filter(array_map('trim', explode("\n", (string) $input)));
	return array_map('sanitize_text_field', $lines);
}

function lf_wizard_render_page(): void {
	$complete = (bool) get_option('lf_setup_wizard_complete', false);
	if ($complete && !isset($_GET['done'])) {
		echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
		echo '<p>' . esc_html__('Setup is already complete. Your site has the required pages, menus, and structure.', 'leadsforward-core') . '</p>';
		echo '<p><a href="' . esc_url(admin_url('admin.php?page=lf-ops&reset=1')) . '" class="button">' . esc_html__('Show wizard again', 'leadsforward-core') . '</a>';
		if (function_exists('lf_dev_reset_allowed') && lf_dev_reset_allowed() && current_user_can('manage_options')) {
			echo ' <a href="' . esc_url(admin_url('admin.php?page=lf-dev-reset')) . '" class="button" style="background:#b32d2e;border-color:#b32d2e;color:#fff;">' . esc_html__('RESET SITE (DEV ONLY)', 'leadsforward-core') . '</a>';
		}
		echo '</p></div>';
		return;
	}
	if (isset($_GET['reset']) && current_user_can('edit_theme_options')) {
		delete_option('lf_setup_wizard_complete');
		wp_redirect(admin_url('admin.php?page=lf-ops'));
		exit;
	}
	if (isset($_GET['done'])) {
		echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
		echo '<p class="notice notice-success">' . esc_html__('Site setup complete. You can now customize Theme Options and edit pages.', 'leadsforward-core') . '</p>';
		echo '<p><a href="' . esc_url(get_permalink(get_option('page_on_front'))) . '" class="button button-primary">' . esc_html__('View site', 'leadsforward-core') . '</a> ';
		echo '<a href="' . esc_url(admin_url('admin.php?page=lf-theme-options')) . '" class="button">' . esc_html__('Theme Options', 'leadsforward-core') . '</a></p></div>';
		return;
	}

	$step = isset($_GET['step']) ? max(1, min(5, (int) $_GET['step'])) : 1;
	$errors = isset($_GET['errors']) ? sanitize_text_field($_GET['msg'] ?? '') : '';

	echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
	if ($errors) {
		echo '<div class="notice notice-error"><p>' . esc_html($errors) . '</p></div>';
	}
	echo '<p>' . esc_html__('Step', 'leadsforward-core') . ' ' . $step . ' / 5</p>';

	$method = 'post';
	$action = admin_url('admin.php?page=lf-ops');
	$niche = isset($_GET['niche']) ? sanitize_text_field($_GET['niche']) : '';
	$profiles = ['a' => __('Clean + Minimal', 'leadsforward-core'), 'b' => __('Bold + High Contrast', 'leadsforward-core'), 'c' => __('Trust Heavy', 'leadsforward-core'), 'd' => __('Service Heavy', 'leadsforward-core'), 'e' => __('Offer/Promo Heavy', 'leadsforward-core')];

	if ($step === 1) {
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="2" />';
		echo '<table class="form-table"><tr><th scope="row">' . esc_html__('Niche', 'leadsforward-core') . '</th><td><select name="niche" required>';
		foreach (lf_get_niche_registry() as $slug => $n) {
			echo '<option value="' . esc_attr($slug) . '"' . selected($niche, $slug, false) . '>' . esc_html($n['name']) . '</option>';
		}
		echo '</select></td></tr></table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
	} elseif ($step === 2) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		// Defaults: schema-friendly format examples (pre-fill when empty so user sees how to format)
		$default_name    = __('Quality Roofing of Sarasota', 'leadsforward-core');
		$default_phone   = __('(941) 555-0123', 'leadsforward-core');
		$default_email   = __('contact@yourbusiness.com', 'leadsforward-core');
		$default_address = __("123 Main Street\nSarasota, FL 34232", 'leadsforward-core');
		$default_hours   = __("Mon–Fri 8am–6pm\nSat 9am–2pm", 'leadsforward-core');
		$bn = sanitize_text_field($_GET['lf_business_name'] ?? '');
		$bp = sanitize_text_field($_GET['lf_business_phone'] ?? '');
		$be = sanitize_email($_GET['lf_business_email'] ?? '');
		$ba = sanitize_textarea_field($_GET['lf_business_address'] ?? '');
		$bh = sanitize_textarea_field($_GET['lf_business_hours'] ?? '');
		if ($bn === '') { $bn = $default_name; }
		if ($bp === '') { $bp = $default_phone; }
		if ($be === '') { $be = $default_email; }
		if ($ba === '') { $ba = $default_address; }
		if ($bh === '') { $bh = $default_hours; }
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="3" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		echo '<p class="description">' . esc_html__('Used for schema, footer, and Map + NAP. Replace with your real business info—these show the recommended format.', 'leadsforward-core') . '</p>';
		echo '<table class="form-table">';
		echo '<tr><th scope="row"><label for="lf_business_name">' . esc_html__('Business name', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_name" name="lf_business_name" class="regular-text" value="' . esc_attr($bn) . '" required placeholder="' . esc_attr($default_name) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_phone">' . esc_html__('Phone', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_phone" name="lf_business_phone" class="regular-text" value="' . esc_attr($bp) . '" required placeholder="(XXX) XXX-XXXX" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_email">' . esc_html__('Email', 'leadsforward-core') . '</label></th><td><input type="email" id="lf_business_email" name="lf_business_email" class="regular-text" value="' . esc_attr($be) . '" placeholder="contact@yourbusiness.com" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_address">' . esc_html__('Address (NAP)', 'leadsforward-core') . '</label></th><td><textarea id="lf_business_address" name="lf_business_address" rows="3" class="large-text" placeholder="' . esc_attr(preg_replace('/\s+/', ' ', $default_address)) . '">' . esc_textarea($ba) . '</textarea><br /><span class="description">' . esc_html__('Street, then city/state/zip on following lines. Used in schema and Map + NAP.', 'leadsforward-core') . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_hours">' . esc_html__('Opening hours', 'leadsforward-core') . '</label></th><td><textarea id="lf_business_hours" name="lf_business_hours" rows="3" class="large-text" placeholder="Mon–Fri 8am–6pm">' . esc_textarea($bh) . '</textarea><br /><span class="description">' . esc_html__('Human-readable hours for schema (e.g. Mon–Fri 8am–6pm). One line per rule.', 'leadsforward-core') . '</span></td></tr>';
		echo '</table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
	} elseif ($step === 3) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		$n = lf_get_niche($niche);
		$services_list = $n ? implode(', ', $n['services']) : '';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="4" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		echo '<input type="hidden" name="lf_business_name" value="' . esc_attr(sanitize_text_field($_GET['lf_business_name'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_phone" value="' . esc_attr(sanitize_text_field($_GET['lf_business_phone'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_email" value="' . esc_attr(sanitize_email($_GET['lf_business_email'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_address" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_address'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_hours" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_hours'] ?? '')) . '" />';
		echo '<p>' . esc_html__('Services to create:', 'leadsforward-core') . ' ' . esc_html($services_list) . '</p>';
		$areas_value = isset($_GET['lf_service_areas']) ? implode("\n", lf_wizard_sanitize_areas($_GET['lf_service_areas'])) : '';
		echo '<table class="form-table"><tr><th scope="row"><label for="lf_service_areas">' . esc_html__('Service areas (one per line; optional "City, ST")', 'leadsforward-core') . '</label></th><td><textarea id="lf_service_areas" name="lf_service_areas" rows="5" class="large-text" placeholder="City One&#10;City Two, CA">' . esc_textarea($areas_value) . '</textarea></td></tr></table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
	} elseif ($step === 4) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		$n = lf_get_niche($niche);
		$rec = $n['variation_profile'] ?? 'a';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="5" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		foreach (['lf_business_name','lf_business_phone','lf_business_email'] as $k) {
			echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr(sanitize_text_field($_GET[$k] ?? '')) . '" />';
		}
		echo '<input type="hidden" name="lf_business_address" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_address'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_hours" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_hours'] ?? '')) . '" />';
		$areas = isset($_GET['lf_service_areas']) ? lf_wizard_sanitize_areas($_GET['lf_service_areas']) : [];
		echo '<input type="hidden" name="lf_service_areas_raw" value="' . esc_attr(implode("\n", $areas)) . '" />';
		echo '<table class="form-table"><tr><th scope="row">' . esc_html__('Variation profile', 'leadsforward-core') . '</th><td><select name="lf_variation_profile">';
		foreach ($profiles as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected($rec, $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr></table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
	} else {
		$step = 5;
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		wp_nonce_field('lf_wizard_generate', 'lf_wizard_nonce');
		echo '<input type="hidden" name="lf_wizard_step" value="5" />';
		echo '<input type="hidden" name="lf_wizard_generate" value="1" />';
		echo '<input type="hidden" name="lf_niche" value="' . esc_attr($niche) . '" />';
		foreach (['lf_business_name','lf_business_phone','lf_business_email','lf_variation_profile'] as $k) {
			echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr(sanitize_text_field($_GET[$k] ?? '')) . '" />';
		}
		echo '<input type="hidden" name="lf_business_address" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_address'] ?? '')) . '" />';
		echo '<input type="hidden" name="lf_business_hours" value="' . esc_attr(sanitize_textarea_field($_GET['lf_business_hours'] ?? '')) . '" />';
		$areas_raw = isset($_GET['lf_service_areas_raw']) ? $_GET['lf_service_areas_raw'] : (isset($_GET['lf_service_areas']) ? $_GET['lf_service_areas'] : '');
		$areas_str = is_string($areas_raw) ? $areas_raw : implode("\n", (array) $areas_raw);
		echo '<input type="hidden" name="lf_service_areas" value="' . esc_attr($areas_str) . '" />';
		echo '<p>' . esc_html__('Click Generate to create pages, services, service areas, menus, and set Theme Options. This will not duplicate existing pages.', 'leadsforward-core') . '</p>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Generate site', 'leadsforward-core') . '" /></p></form>';
	}
	echo '</div>';
}
