<?php
/**
 * LeadsForward → Homepage admin UI. Toggles, variants, copy fields. No layout reorder.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_homepage_admin_save');
add_action('admin_enqueue_scripts', 'lf_homepage_admin_assets');

/**
 * Show notice on front page edit screen: content is controlled by LeadsForward.
 */
add_action('admin_notices', 'lf_homepage_editor_notice');

function lf_homepage_editor_notice(): void {
	$screen = get_current_screen();
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'page') {
		return;
	}
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if ($post_id === 0) {
		return;
	}
	if ((int) get_option('page_on_front') !== $post_id) {
		return;
	}
	$url = admin_url('admin.php?page=lf-homepage-settings');
	echo '<div class="notice notice-info"><p><strong>' . esc_html__('LeadsForward:', 'leadsforward-core') . '</strong> ';
	echo wp_kses(
		sprintf(
			/* translators: %s: link to LeadsForward Homepage settings */
			__('This page is controlled by LeadsForward settings. Edit sections and copy at <a href="%s">LeadsForward → Homepage</a>.', 'leadsforward-core'),
			esc_url($url)
		),
		['a' => ['href' => true]]
	);
	echo '</p></div>';
}

function lf_homepage_admin_save(): void {
	if (!isset($_POST['lf_homepage_settings_nonce']) || !current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_homepage_settings_nonce'], 'lf_homepage_settings')) {
		return;
	}

	/* Save Business Info globally (same storage as Theme Options → Business Info: lf-business-info) */
	if (function_exists('lf_update_business_info_value')) {
		$allowed_embed = [
			'iframe' => [
				'src' => true,
				'width' => true,
				'height' => true,
				'style' => true,
				'loading' => true,
				'referrerpolicy' => true,
				'allowfullscreen' => true,
				'title' => true,
			],
		];
		lf_update_business_info_value('lf_business_name', isset($_POST['lf_business_name']) ? sanitize_text_field(wp_unslash($_POST['lf_business_name'])) : '');
		lf_update_business_info_value('lf_business_phone', isset($_POST['lf_business_phone']) ? sanitize_text_field(wp_unslash($_POST['lf_business_phone'])) : '');
		lf_update_business_info_value('lf_business_email', isset($_POST['lf_business_email']) ? sanitize_email(wp_unslash($_POST['lf_business_email'])) : '');
		lf_update_business_info_value('lf_business_address', isset($_POST['lf_business_address']) ? sanitize_textarea_field(wp_unslash($_POST['lf_business_address'])) : '');
		lf_update_business_info_value('lf_business_place_id', isset($_POST['lf_business_place_id']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_id'])) : '');
		lf_update_business_info_value('lf_business_place_name', isset($_POST['lf_business_place_name']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_name'])) : '');
		lf_update_business_info_value('lf_business_place_address', isset($_POST['lf_business_place_address']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_address'])) : '');
		$embed = isset($_POST['lf_business_map_embed']) ? wp_kses(wp_unslash($_POST['lf_business_map_embed']), $allowed_embed) : '';
		lf_update_business_info_value('lf_business_map_embed', $embed);
	}
	// Maps API key is managed in LeadsForward → Setup.

	$order = lf_homepage_controller_order();
	$config = lf_get_homepage_section_config();
	$allowed_variants = ['default', 'a', 'b', 'c'];
	$order_input = isset($_POST['lf_hp_order']) ? array_map('sanitize_text_field', (array) $_POST['lf_hp_order']) : [];
	$order = function_exists('lf_homepage_sanitize_order') ? lf_homepage_sanitize_order($order_input) : $order;
	update_option(LF_HOMEPAGE_ORDER_OPTION, $order, true);
	update_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, true, true);
	foreach ($order as $type) {
		$key_enabled = 'lf_hp_enabled_' . $type;
		$key_variant = 'lf_hp_variant_' . $type;
		$config[$type]['enabled'] = !empty($_POST[$key_enabled]);
		$v = isset($_POST[$key_variant]) && in_array($_POST[$key_variant], $allowed_variants, true) ? $_POST[$key_variant] : 'default';
		$config[$type]['variant'] = $v;
		$bg_value = isset($_POST['lf_hp_bg_' . $type]) ? sanitize_text_field($_POST['lf_hp_bg_' . $type]) : 'light';
		$config[$type]['section_background'] = in_array($bg_value, ['light', 'soft', 'dark', 'card'], true) ? $bg_value : 'light';
		if ($type === 'hero') {
			$config[$type]['hero_headline'] = isset($_POST['lf_hp_hero_headline']) ? sanitize_text_field($_POST['lf_hp_hero_headline']) : '';
			$config[$type]['hero_subheadline'] = isset($_POST['lf_hp_hero_subheadline']) ? sanitize_text_field($_POST['lf_hp_hero_subheadline']) : '';
			$config[$type]['hero_cta_override'] = isset($_POST['lf_hp_hero_cta_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_override']) : '';
			$config[$type]['hero_cta_secondary_override'] = isset($_POST['lf_hp_hero_cta_secondary_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_secondary_override']) : '';
			$hero_action = isset($_POST['lf_hp_hero_cta_action']) ? sanitize_text_field($_POST['lf_hp_hero_cta_action']) : '';
			$config[$type]['hero_cta_action'] = in_array($hero_action, ['link', 'quote', 'call'], true) ? $hero_action : '';
			$config[$type]['hero_cta_url'] = isset($_POST['lf_hp_hero_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_hero_cta_url'])) : '';
			$hero_secondary_action = isset($_POST['lf_hp_hero_cta_secondary_action']) ? sanitize_text_field($_POST['lf_hp_hero_cta_secondary_action']) : '';
			$config[$type]['hero_cta_secondary_action'] = in_array($hero_secondary_action, ['link', 'quote', 'call'], true) ? $hero_secondary_action : '';
			$config[$type]['hero_cta_secondary_url'] = isset($_POST['lf_hp_hero_cta_secondary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_hero_cta_secondary_url'])) : '';
		}
		if ($type === 'trust_bar') {
			$config[$type]['trust_heading'] = isset($_POST['lf_hp_trust_heading']) ? sanitize_text_field($_POST['lf_hp_trust_heading']) : '';
			$config[$type]['trust_badges'] = isset($_POST['lf_hp_trust_badges']) ? sanitize_textarea_field($_POST['lf_hp_trust_badges']) : '';
			$config[$type]['trust_rating'] = isset($_POST['lf_hp_trust_rating']) ? sanitize_text_field($_POST['lf_hp_trust_rating']) : '';
			$config[$type]['trust_review_count'] = isset($_POST['lf_hp_trust_review_count']) ? sanitize_text_field($_POST['lf_hp_trust_review_count']) : '';
		}
		if ($type === 'benefits') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_benefits_heading']) ? sanitize_text_field($_POST['lf_hp_benefits_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_benefits_intro']) ? sanitize_textarea_field($_POST['lf_hp_benefits_intro']) : '';
			$config[$type]['benefits_items'] = isset($_POST['lf_hp_benefits_items']) ? sanitize_textarea_field($_POST['lf_hp_benefits_items']) : '';
		}
		if ($type === 'service_details') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_details_heading']) ? sanitize_text_field($_POST['lf_hp_details_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_details_intro']) ? sanitize_textarea_field($_POST['lf_hp_details_intro']) : '';
			$config[$type]['service_details_body'] = isset($_POST['lf_hp_details_body']) ? sanitize_textarea_field($_POST['lf_hp_details_body']) : '';
			$config[$type]['service_details_checklist'] = isset($_POST['lf_hp_details_checklist']) ? sanitize_textarea_field($_POST['lf_hp_details_checklist']) : '';
		}
		if ($type === 'process') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_process_heading']) ? sanitize_text_field($_POST['lf_hp_process_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_process_intro']) ? sanitize_textarea_field($_POST['lf_hp_process_intro']) : '';
			$config[$type]['process_steps'] = isset($_POST['lf_hp_process_steps']) ? sanitize_textarea_field($_POST['lf_hp_process_steps']) : '';
		}
		if ($type === 'related_links') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_related_heading']) ? sanitize_text_field($_POST['lf_hp_related_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_related_intro']) ? sanitize_textarea_field($_POST['lf_hp_related_intro']) : '';
			$config[$type]['related_links_mode'] = isset($_POST['lf_hp_related_mode']) ? sanitize_text_field($_POST['lf_hp_related_mode']) : 'both';
		}
		if ($type === 'map_nap') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_map_heading']) ? sanitize_text_field($_POST['lf_hp_map_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_map_intro']) ? sanitize_textarea_field($_POST['lf_hp_map_intro']) : '';
		}
		if ($type === 'faq_accordion') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_faq_heading']) ? sanitize_text_field($_POST['lf_hp_faq_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_faq_intro']) ? sanitize_textarea_field($_POST['lf_hp_faq_intro']) : '';
			$config[$type]['faq_max_items'] = isset($_POST['lf_hp_faq_max']) ? sanitize_text_field($_POST['lf_hp_faq_max']) : '';
		}
		if ($type === 'cta') {
			$config[$type]['cta_headline'] = isset($_POST['lf_hp_cta_headline']) ? sanitize_text_field($_POST['lf_hp_cta_headline']) : '';
			$config[$type]['cta_subheadline'] = isset($_POST['lf_hp_cta_subheadline']) ? sanitize_textarea_field($_POST['lf_hp_cta_subheadline']) : '';
			$config[$type]['cta_primary_override'] = isset($_POST['lf_hp_cta_primary']) ? sanitize_text_field($_POST['lf_hp_cta_primary']) : '';
			$config[$type]['cta_secondary_override'] = isset($_POST['lf_hp_cta_secondary']) ? sanitize_text_field($_POST['lf_hp_cta_secondary']) : '';
			$config[$type]['cta_ghl_override'] = isset($_POST['lf_hp_cta_ghl']) ? wp_kses_post($_POST['lf_hp_cta_ghl']) : '';
			$cta_action = isset($_POST['lf_hp_cta_primary_action']) ? sanitize_text_field($_POST['lf_hp_cta_primary_action']) : '';
			$config[$type]['cta_primary_action'] = in_array($cta_action, ['link', 'quote', 'call'], true) ? $cta_action : '';
			$config[$type]['cta_primary_url'] = isset($_POST['lf_hp_cta_primary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_cta_primary_url'])) : '';
			$cta_secondary_action = isset($_POST['lf_hp_cta_secondary_action']) ? sanitize_text_field($_POST['lf_hp_cta_secondary_action']) : '';
			$config[$type]['cta_secondary_action'] = in_array($cta_secondary_action, ['link', 'quote', 'call'], true) ? $cta_secondary_action : '';
			$config[$type]['cta_secondary_url'] = isset($_POST['lf_hp_cta_secondary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_cta_secondary_url'])) : '';
		}
	}
	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	wp_redirect(admin_url('admin.php?page=lf-homepage-settings&saved=1'));
	exit;
}

function lf_homepage_admin_section_labels(): array {
	return [
		'hero'           => __('Hero', 'leadsforward-core'),
		'trust_bar'      => __('Trust Bar', 'leadsforward-core'),
		'benefits'       => __('Benefits / Why Choose Us', 'leadsforward-core'),
		'service_details' => __('Service Details', 'leadsforward-core'),
		'process'        => __('Process', 'leadsforward-core'),
		'faq_accordion'  => __('FAQ', 'leadsforward-core'),
		'cta'            => __('CTA Band', 'leadsforward-core'),
		'related_links'  => __('Related Links', 'leadsforward-core'),
		'map_nap'        => __('Service Areas + Map', 'leadsforward-core'),
	];
}

function lf_homepage_admin_assets(): void {
	if (!isset($_GET['page']) || $_GET['page'] !== 'lf-homepage-settings') {
		return;
	}
	wp_enqueue_script('jquery-ui-sortable');
	$script = <<<'JS'
jQuery(function ($) {
	var $table = $('.lf-homepage-sections tbody');
	if ($table.length && $table.sortable) {
		$table.sortable({
			items: 'tr',
			handle: '.lf-homepage-drag',
			axis: 'y',
			cancel: 'input,select,textarea,button,label,a',
			helper: function (e, tr) {
				var $originals = tr.children();
				var $helper = tr.clone();
				$helper.children().each(function (index) {
					$(this).width($originals.eq(index).width());
				});
				return $helper;
			}
		});
	}

	var storageKey = 'lf_homepage_collapsed';
	var collapsed = {};
	try {
		collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
	} catch (e) {
		collapsed = {};
	}

	function applyCollapse(type) {
		var isCollapsed = !!collapsed[type];
		var $rows = $('.lf-homepage-section-fields[data-parent="' + type + '"]');
		$rows.toggleClass('lf-homepage-fields--collapsed', isCollapsed);
		var $header = $('.lf-homepage-section-row[data-section="' + type + '"], .lf-homepage-panel[data-section="' + type + '"]');
		$header.toggleClass('lf-homepage-row--collapsed', isCollapsed);
		var $toggle = $header.find('.lf-homepage-toggle');
		$toggle.attr('aria-expanded', (!isCollapsed).toString());
		$toggle.find('.lf-homepage-toggle-icon').text(isCollapsed ? '▸' : '▾');
		$toggle.find('.lf-homepage-toggle-label').text(isCollapsed ? 'Expand' : 'Collapse');
	}

	$('.lf-homepage-toggle').each(function () {
		var type = $(this).data('target');
		if (type) {
			applyCollapse(type);
		}
	});

	$(document).on('click', '.lf-homepage-toggle', function () {
		var type = $(this).data('target');
		if (!type) {
			return;
		}
		collapsed[type] = !collapsed[type];
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(collapsed));
		} catch (e) {}
		applyCollapse(type);
	});

	$('.lf-homepage-expand-all').on('click', function () {
	$('.lf-homepage-section-row, .lf-homepage-panel').each(function () {
			var type = $(this).data('section');
			if (type) {
				collapsed[type] = false;
				applyCollapse(type);
			}
		});
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(collapsed));
		} catch (e) {}
	});

	$('.lf-homepage-collapse-all').on('click', function () {
	$('.lf-homepage-section-row, .lf-homepage-panel').each(function () {
			var type = $(this).data('section');
			if (type) {
				collapsed[type] = true;
				applyCollapse(type);
			}
		});
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(collapsed));
		} catch (e) {}
	});

	function loadPlacesApi(key, callback) {
		var status = document.getElementById('lf_maps_status');
		if (window.google && window.google.maps && window.google.maps.places) {
			callback();
			return;
		}
		if (!key) {
			if (status) {
				status.textContent = 'Add your Google Maps API key in LeadsForward → Setup to enable search.';
			}
			return;
		}
		var scriptId = 'lf-maps-places';
		if (document.getElementById(scriptId)) {
			return;
		}
		var script = document.createElement('script');
		script.id = scriptId;
		script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=places';
		script.async = true;
		script.onerror = function () {
			if (status) {
				status.textContent = 'Failed to load Google Maps. Check API key restrictions and billing.';
			}
		};
		script.onload = callback;
		document.head.appendChild(script);
	}

	function initPlacesSearch() {
		var input = document.getElementById('lf_business_place_search');
		var keyInput = null;
		var placeId = document.getElementById('lf_business_place_id');
		var placeName = document.getElementById('lf_business_place_name');
		var placeAddress = document.getElementById('lf_business_place_address');
		var businessName = document.getElementById('lf_business_name');
		var businessAddress = document.getElementById('lf_business_address');
		var selected = document.getElementById('lf_place_selected');
		var status = document.getElementById('lf_maps_status');
		if (!input) {
			return;
		}
		var form = input.closest('form');
		var key = form ? (form.getAttribute('data-maps-key') || '') : '';
		key = key.trim();
		if (!key) {
			if (selected) {
				selected.textContent = 'Add your Google Maps API key in LeadsForward → Setup to enable search.';
			}
			if (status) {
				status.textContent = '';
			}
			return;
		}
		if (status) {
			status.textContent = 'Loading Google Maps…';
		}
		window.gm_authFailure = function () {
			if (status) {
				status.textContent = 'Google Maps auth failed. Check key restrictions and billing.';
			}
		};
		loadPlacesApi(key, function () {
			if (!window.google || !google.maps || !google.maps.places) {
				if (status) {
					status.textContent = 'Google Maps loaded without Places library. Check API settings.';
				}
				return;
			}
			if (status) {
				status.textContent = '';
			}
			var ac = new google.maps.places.Autocomplete(input, {
				fields: ['place_id', 'name', 'formatted_address']
			});
			ac.addListener('place_changed', function () {
				var place = ac.getPlace();
				if (!place || !place.place_id) {
					return;
				}
				if (placeId) placeId.value = place.place_id || '';
				if (placeName) placeName.value = place.name || '';
				if (placeAddress) placeAddress.value = place.formatted_address || '';
				if (businessName && !businessName.value) businessName.value = place.name || '';
				if (businessAddress && !businessAddress.value) businessAddress.value = place.formatted_address || '';
				if (selected) {
					selected.textContent = 'Selected: ' + (place.name || '') + (place.formatted_address ? ' (' + place.formatted_address + ')' : '');
				}
			});
		});
	}

	initPlacesSearch();
	// Maps API key changes are handled in LeadsForward → Setup.
});
JS;
	wp_add_inline_script('jquery-ui-sortable', $script);
}

function lf_homepage_admin_render(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$config = lf_get_homepage_section_config();
	$order = lf_homepage_controller_order();
	$labels = lf_homepage_admin_section_labels();
	$maps_api_key = get_option('lf_maps_api_key', '');
	$maps_api_key = is_string($maps_api_key) ? $maps_api_key : '';
	$variants = [
		'default' => __('Authority Split (Recommended)', 'leadsforward-core'),
		'a'       => __('Conversion Stack', 'leadsforward-core'),
		'b'       => __('Form First', 'leadsforward-core'),
		'c'       => __('Visual Proof', 'leadsforward-core'),
	];
	$bg_options = [
		'light' => __('Light', 'leadsforward-core'),
		'soft'  => __('Soft', 'leadsforward-core'),
		'dark'  => __('Dark', 'leadsforward-core'),
		'card'  => __('Card', 'leadsforward-core'),
	];
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Homepage', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e('Drag and drop sections to reorder. A recommended default order is provided, but you control the layout. Turn sections on or off and edit copy below.', 'leadsforward-core'); ?></p>
		<style>
			.lf-homepage-panel-controls { display: flex; gap: 0.5rem; margin: 1rem 0 1.25rem; }
			.lf-homepage-panel-controls .button { font-size: 12px; }
			.lf-homepage-sections { border-collapse: separate; border-spacing: 0 0.75rem; }
			.lf-homepage-sections tr { background: #fff; }
			.lf-homepage-sections tr.ui-sortable-helper { box-shadow: 0 16px 30px rgba(0,0,0,0.18); border-radius: 12px; }
			.lf-homepage-section-row { box-shadow: 0 10px 24px rgba(15,23,42,0.08); border-radius: 14px; overflow: hidden; }
			.lf-homepage-section-row th,
			.lf-homepage-section-row td { padding: 0.85rem 1rem; }
			.lf-homepage-section-head { display: flex; align-items: center; gap: 0.6rem; }
			.lf-homepage-panel { background: #fff; border-radius: 14px; box-shadow: 0 10px 24px rgba(15,23,42,0.08); padding: 1rem; margin: 1rem 0 1.5rem; }
			.lf-homepage-panel-header { display: flex; align-items: center; gap: 0.75rem; }
			.lf-homepage-panel-header h2 { margin: 0; }
			.lf-homepage-panel-body { margin-top: 0.75rem; }
			.lf-homepage-drag { cursor: grab; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #f1f5f9; color: #64748b; }
			.lf-homepage-drag:active { cursor: grabbing; }
			.lf-homepage-section-row:hover .lf-homepage-drag { color: #0f172a; }
			.lf-homepage-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
			.lf-homepage-toggle:hover { background: #e2e8f0; }
			.lf-homepage-toggle .lf-homepage-toggle-icon { margin-right: 4px; }
			.lf-homepage-row--collapsed .lf-homepage-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
			.lf-homepage-fields--collapsed { display: none; }
			.lf-homepage-section-fields td { background: #f8fafc; }
			.lf-homepage-section-fields th { background: #f8fafc; }
		</style>
		<div class="lf-homepage-panel-controls">
			<button type="button" class="button lf-homepage-expand-all"><?php esc_html_e('Expand all', 'leadsforward-core'); ?></button>
			<button type="button" class="button lf-homepage-collapse-all"><?php esc_html_e('Collapse all', 'leadsforward-core'); ?></button>
		</div>

		<form method="post" action="" data-maps-key="<?php echo esc_attr($maps_api_key); ?>">
			<?php wp_nonce_field('lf_homepage_settings', 'lf_homepage_settings_nonce'); ?>
		<?php
		$get_business = function (string $key) {
			if (function_exists('lf_get_business_info_value')) {
				return lf_get_business_info_value($key, '');
			}
			return '';
		};
		$business_name    = $get_business('lf_business_name');
		$business_phone   = $get_business('lf_business_phone');
		$business_email   = $get_business('lf_business_email');
		$business_address = $get_business('lf_business_address');
		$place_id         = $get_business('lf_business_place_id');
		$place_name       = $get_business('lf_business_place_name');
		$place_address    = $get_business('lf_business_place_address');
		$map_embed        = $get_business('lf_business_map_embed');
		$business_name    = is_string($business_name) ? $business_name : '';
		$business_phone   = is_string($business_phone) ? $business_phone : '';
		$business_email   = is_string($business_email) ? $business_email : '';
		$business_address = is_string($business_address) ? $business_address : '';
		$place_id         = is_string($place_id) ? $place_id : '';
		$place_name       = is_string($place_name) ? $place_name : '';
		$place_address    = is_string($place_address) ? $place_address : '';
		$map_embed        = is_string($map_embed) ? $map_embed : '';
		?>
		<div class="lf-homepage-panel lf-homepage-panel--business" data-section="business_info">
			<div class="lf-homepage-panel-header">
				<h2 id="lf-business-info" class="title"><?php esc_html_e('Business Info', 'leadsforward-core'); ?></h2>
				<button type="button" class="lf-homepage-toggle" data-target="business_info" aria-expanded="true">
					<span class="lf-homepage-toggle-icon">▾</span>
					<span class="lf-homepage-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
				</button>
			</div>
			<div class="lf-homepage-panel-body lf-homepage-section-fields" data-parent="business_info">
				<p class="description"><?php esc_html_e('Used site-wide: footer, Map + NAP section, schema, and CTAs. Same data as the startup wizard—edit here anytime.', 'leadsforward-core'); ?> <?php esc_html_e('Kept consistent for local SEO: NAP (name, address, phone) is output in one format everywhere and in LocalBusiness schema.', 'leadsforward-core'); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="lf_business_name"><?php esc_html_e('Business name', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="large-text" name="lf_business_name" id="lf_business_name" value="<?php echo esc_attr($business_name); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_phone"><?php esc_html_e('Phone', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" name="lf_business_phone" id="lf_business_phone" value="<?php echo esc_attr($business_phone); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_email"><?php esc_html_e('Email', 'leadsforward-core'); ?></label></th>
							<td><input type="email" class="regular-text" name="lf_business_email" id="lf_business_email" value="<?php echo esc_attr($business_email); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_address"><?php esc_html_e('Address (NAP)', 'leadsforward-core'); ?></label></th>
							<td><textarea class="large-text" name="lf_business_address" id="lf_business_address" rows="3"><?php echo esc_textarea($business_address); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Google Maps API key', 'leadsforward-core'); ?></th>
							<td>
								<?php if ($maps_api_key) : ?>
									<p class="description"><?php esc_html_e('Key is set in LeadsForward → Setup.', 'leadsforward-core'); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e('Add your Google Maps API key in LeadsForward → Setup to enable place search + embeds.', 'leadsforward-core'); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_place_search"><?php esc_html_e('Search business on Google Maps', 'leadsforward-core'); ?></label></th>
							<td>
								<input type="text" class="large-text" id="lf_business_place_search" placeholder="<?php esc_attr_e('Start typing your business name...', 'leadsforward-core'); ?>" value="<?php echo esc_attr($place_name); ?>" />
								<input type="hidden" name="lf_business_place_id" id="lf_business_place_id" value="<?php echo esc_attr($place_id); ?>" />
								<input type="hidden" name="lf_business_place_name" id="lf_business_place_name" value="<?php echo esc_attr($place_name); ?>" />
								<input type="hidden" name="lf_business_place_address" id="lf_business_place_address" value="<?php echo esc_attr($place_address); ?>" />
								<p class="description" id="lf_place_selected">
									<?php echo $place_name !== '' ? esc_html(sprintf(__('Selected: %1$s (%2$s)', 'leadsforward-core'), $place_name, $place_address)) : esc_html__('No place selected yet.', 'leadsforward-core'); ?>
								</p>
								<p class="description" id="lf_maps_status" style="color:#b45309;"></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_map_embed"><?php esc_html_e('Map embed override (optional)', 'leadsforward-core'); ?></label></th>
							<td>
								<textarea class="large-text" name="lf_business_map_embed" id="lf_business_map_embed" rows="3"><?php echo esc_textarea($map_embed); ?></textarea>
								<p class="description"><?php esc_html_e('Paste a custom iframe embed if you prefer. If empty, the selected Google Maps place will be used.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<h2 class="title"><?php esc_html_e('Homepage sections', 'leadsforward-core'); ?></h2>
			<table class="form-table lf-homepage-sections" role="presentation">
				<tbody>
				<?php foreach ($order as $type) :
					$sec = $config[$type] ?? [];
					$enabled = !empty($sec['enabled']);
					$variant = $sec['variant'] ?? 'default';
					$label = $labels[$type] ?? $type;
				?>
					<tr class="lf-homepage-section-row" data-section="<?php echo esc_attr($type); ?>">
						<th scope="row">
							<div class="lf-homepage-section-head">
								<span class="lf-homepage-drag" aria-hidden="true">⋮⋮</span>
								<label for="lf_hp_enabled_<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></label>
								<button type="button" class="lf-homepage-toggle" data-target="<?php echo esc_attr($type); ?>" aria-expanded="true">
									<span class="lf-homepage-toggle-icon">▾</span>
									<span class="lf-homepage-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
								</button>
							</div>
						</th>
						<td>
							<input type="hidden" name="lf_hp_order[]" value="<?php echo esc_attr($type); ?>" />
							<label><input type="checkbox" name="lf_hp_enabled_<?php echo esc_attr($type); ?>" id="lf_hp_enabled_<?php echo esc_attr($type); ?>" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Show this section', 'leadsforward-core'); ?></label>
							&nbsp;&nbsp;
							<label><?php esc_html_e('Variant', 'leadsforward-core'); ?>
								<select name="lf_hp_variant_<?php echo esc_attr($type); ?>">
									<?php foreach ($variants as $v => $vlabel) : ?>
										<option value="<?php echo esc_attr($v); ?>" <?php selected($variant, $v); ?>><?php echo esc_html($vlabel); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<?php if ($type === 'map_nap') : ?>
								<p class="description" style="margin: 0.5em 0 0;"><?php esc_html_e('Service areas and map come from Business Info + Service Areas. Select a place above to show the map.', 'leadsforward-core'); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="<?php echo esc_attr($type); ?>">
						<th scope="row"><label for="lf_hp_bg_<?php echo esc_attr($type); ?>"><?php esc_html_e('Background', 'leadsforward-core'); ?></label></th>
						<td>
							<?php $bg_val = $sec['section_background'] ?? 'light'; ?>
							<select name="lf_hp_bg_<?php echo esc_attr($type); ?>" id="lf_hp_bg_<?php echo esc_attr($type); ?>">
								<?php foreach ($bg_options as $bg_key => $bg_label) : ?>
									<option value="<?php echo esc_attr($bg_key); ?>" <?php selected($bg_val, $bg_key); ?>><?php echo esc_html($bg_label); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php if ($type === 'hero') : ?>
					<tr class="lf-homepage-section-fields lf-homepage-hero-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_headline"><?php esc_html_e('Hero headline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_hero_headline" id="lf_hp_hero_headline" value="<?php echo esc_attr($sec['hero_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Quality Roofing in [City]', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_subheadline"><?php esc_html_e('Hero subheadline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_hero_subheadline" id="lf_hp_hero_subheadline" value="<?php echo esc_attr($sec['hero_subheadline'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_override"><?php esc_html_e('Hero CTA override', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_hero_cta_override" id="lf_hp_hero_cta_override" value="<?php echo esc_attr($sec['hero_cta_override'] ?? ''); ?>" /> <span class="description"><?php esc_html_e('Leave blank to use homepage CTA.', 'leadsforward-core'); ?></span></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_secondary_override"><?php esc_html_e('Hero secondary CTA', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_hero_cta_secondary_override" id="lf_hp_hero_cta_secondary_override" value="<?php echo esc_attr($sec['hero_cta_secondary_override'] ?? ''); ?>" /> <span class="description"><?php esc_html_e('Defaults to global secondary CTA if empty.', 'leadsforward-core'); ?></span></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_action"><?php esc_html_e('Hero CTA action', 'leadsforward-core'); ?></label></th>
						<td>
							<select name="lf_hp_hero_cta_action" id="lf_hp_hero_cta_action">
								<option value=""><?php esc_html_e('Use global/homepage setting', 'leadsforward-core'); ?></option>
								<option value="quote" <?php selected(($sec['hero_cta_action'] ?? ''), 'quote'); ?>><?php esc_html_e('Open Quote Builder', 'leadsforward-core'); ?></option>
								<option value="call" <?php selected(($sec['hero_cta_action'] ?? ''), 'call'); ?>><?php esc_html_e('Call now', 'leadsforward-core'); ?></option>
								<option value="link" <?php selected(($sec['hero_cta_action'] ?? ''), 'link'); ?>><?php esc_html_e('Link', 'leadsforward-core'); ?></option>
							</select>
							<span class="description"><?php esc_html_e('Controls whether this CTA opens the Quote Builder modal.', 'leadsforward-core'); ?></span>
						</td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_url"><?php esc_html_e('Hero CTA URL', 'leadsforward-core'); ?></label></th>
						<td><input type="url" class="large-text" name="lf_hp_hero_cta_url" id="lf_hp_hero_cta_url" value="<?php echo esc_attr($sec['hero_cta_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_secondary_action"><?php esc_html_e('Hero secondary CTA action', 'leadsforward-core'); ?></label></th>
						<td>
							<select name="lf_hp_hero_cta_secondary_action" id="lf_hp_hero_cta_secondary_action">
								<option value=""><?php esc_html_e('Use global/homepage setting', 'leadsforward-core'); ?></option>
								<option value="call" <?php selected(($sec['hero_cta_secondary_action'] ?? ''), 'call'); ?>><?php esc_html_e('Call now', 'leadsforward-core'); ?></option>
								<option value="quote" <?php selected(($sec['hero_cta_secondary_action'] ?? ''), 'quote'); ?>><?php esc_html_e('Open Quote Builder', 'leadsforward-core'); ?></option>
								<option value="link" <?php selected(($sec['hero_cta_secondary_action'] ?? ''), 'link'); ?>><?php esc_html_e('Link', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="hero">
						<th scope="row"><label for="lf_hp_hero_cta_secondary_url"><?php esc_html_e('Hero secondary CTA URL', 'leadsforward-core'); ?></label></th>
						<td><input type="url" class="large-text" name="lf_hp_hero_cta_secondary_url" id="lf_hp_hero_cta_secondary_url" value="<?php echo esc_attr($sec['hero_cta_secondary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'trust_bar') : ?>
					<tr class="lf-homepage-section-fields" data-parent="trust_bar">
						<th scope="row"><label for="lf_hp_trust_heading"><?php esc_html_e('Trust bar heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_trust_heading" id="lf_hp_trust_heading" value="<?php echo esc_attr($sec['trust_heading'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="trust_bar">
						<th scope="row"><label for="lf_hp_trust_badges"><?php esc_html_e('Badges (one per line)', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_trust_badges" id="lf_hp_trust_badges" rows="3"><?php echo esc_textarea($sec['trust_badges'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="trust_bar">
						<th scope="row"><label for="lf_hp_trust_rating"><?php esc_html_e('Rating override (optional)', 'leadsforward-core'); ?></label></th>
						<td><input type="number" step="0.1" name="lf_hp_trust_rating" id="lf_hp_trust_rating" value="<?php echo esc_attr((string) ($sec['trust_rating'] ?? '')); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="trust_bar">
						<th scope="row"><label for="lf_hp_trust_review_count"><?php esc_html_e('Review count override (optional)', 'leadsforward-core'); ?></label></th>
						<td><input type="number" name="lf_hp_trust_review_count" id="lf_hp_trust_review_count" value="<?php echo esc_attr((string) ($sec['trust_review_count'] ?? '')); ?>" /></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'benefits') : ?>
					<tr class="lf-homepage-section-fields" data-parent="benefits">
						<th scope="row"><label for="lf_hp_benefits_heading"><?php esc_html_e('Benefits heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_benefits_heading" id="lf_hp_benefits_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="benefits">
						<th scope="row"><label for="lf_hp_benefits_intro"><?php esc_html_e('Benefits intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_benefits_intro" id="lf_hp_benefits_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="benefits">
						<th scope="row"><label for="lf_hp_benefits_items"><?php esc_html_e('Benefits (one per line)', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_benefits_items" id="lf_hp_benefits_items" rows="3"><?php echo esc_textarea($sec['benefits_items'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'service_details') : ?>
					<tr class="lf-homepage-section-fields" data-parent="service_details">
						<th scope="row"><label for="lf_hp_details_heading"><?php esc_html_e('Service details heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_details_heading" id="lf_hp_details_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="service_details">
						<th scope="row"><label for="lf_hp_details_intro"><?php esc_html_e('Service details intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_details_intro" id="lf_hp_details_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="service_details">
						<th scope="row"><label for="lf_hp_details_body"><?php esc_html_e('Service details body', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_details_body" id="lf_hp_details_body" rows="4"><?php echo esc_textarea($sec['service_details_body'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="service_details">
						<th scope="row"><label for="lf_hp_details_checklist"><?php esc_html_e('Checklist (one per line)', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_details_checklist" id="lf_hp_details_checklist" rows="3"><?php echo esc_textarea($sec['service_details_checklist'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'process') : ?>
					<tr class="lf-homepage-section-fields" data-parent="process">
						<th scope="row"><label for="lf_hp_process_heading"><?php esc_html_e('Process heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_process_heading" id="lf_hp_process_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="process">
						<th scope="row"><label for="lf_hp_process_intro"><?php esc_html_e('Process intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_process_intro" id="lf_hp_process_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="process">
						<th scope="row"><label for="lf_hp_process_steps"><?php esc_html_e('Process steps (one per line)', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_process_steps" id="lf_hp_process_steps" rows="3"><?php echo esc_textarea($sec['process_steps'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'related_links') : ?>
					<tr class="lf-homepage-section-fields" data-parent="related_links">
						<th scope="row"><label for="lf_hp_related_heading"><?php esc_html_e('Related links heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_related_heading" id="lf_hp_related_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="related_links">
						<th scope="row"><label for="lf_hp_related_intro"><?php esc_html_e('Related links intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_related_intro" id="lf_hp_related_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="related_links">
						<th scope="row"><label for="lf_hp_related_mode"><?php esc_html_e('Links to show', 'leadsforward-core'); ?></label></th>
						<td>
							<select name="lf_hp_related_mode" id="lf_hp_related_mode">
								<?php $related_mode = $sec['related_links_mode'] ?? 'both'; ?>
								<option value="services" <?php selected($related_mode, 'services'); ?>><?php esc_html_e('Services', 'leadsforward-core'); ?></option>
								<option value="areas" <?php selected($related_mode, 'areas'); ?>><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></option>
								<option value="both" <?php selected($related_mode, 'both'); ?>><?php esc_html_e('Both', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'map_nap') : ?>
					<tr class="lf-homepage-section-fields" data-parent="map_nap">
						<th scope="row"><label for="lf_hp_map_heading"><?php esc_html_e('Map section heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_map_heading" id="lf_hp_map_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Areas We Serve', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="map_nap">
						<th scope="row"><label for="lf_hp_map_intro"><?php esc_html_e('Map section intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_map_intro" id="lf_hp_map_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'faq_accordion') : ?>
					<tr class="lf-homepage-section-fields" data-parent="faq_accordion">
						<th scope="row"><label for="lf_hp_faq_heading"><?php esc_html_e('FAQ section heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_faq_heading" id="lf_hp_faq_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Frequently Asked Questions', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="faq_accordion">
						<th scope="row"><label for="lf_hp_faq_intro"><?php esc_html_e('FAQ intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_faq_intro" id="lf_hp_faq_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="faq_accordion">
						<th scope="row"><label for="lf_hp_faq_max"><?php esc_html_e('Max FAQ items', 'leadsforward-core'); ?></label></th>
						<td><input type="number" name="lf_hp_faq_max" id="lf_hp_faq_max" value="<?php echo esc_attr((string) ($sec['faq_max_items'] ?? '6')); ?>" min="1" max="20" /></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'cta') : ?>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_headline"><?php esc_html_e('CTA headline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_cta_headline" id="lf_hp_cta_headline" value="<?php echo esc_attr($sec['cta_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('Ready to get started?', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_subheadline"><?php esc_html_e('Supporting text', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_cta_subheadline" id="lf_hp_cta_subheadline" rows="2"><?php echo esc_textarea($sec['cta_subheadline'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_primary"><?php esc_html_e('Section primary CTA', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_cta_primary" id="lf_hp_cta_primary" value="<?php echo esc_attr($sec['cta_primary_override'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_secondary"><?php esc_html_e('Section secondary CTA', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" name="lf_hp_cta_secondary" id="lf_hp_cta_secondary" value="<?php echo esc_attr($sec['cta_secondary_override'] ?? ''); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_ghl"><?php esc_html_e('Section GHL embed override', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text code" name="lf_hp_cta_ghl" id="lf_hp_cta_ghl" rows="4"><?php echo esc_textarea($sec['cta_ghl_override'] ?? ''); ?></textarea></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_primary_action"><?php esc_html_e('Section primary CTA action', 'leadsforward-core'); ?></label></th>
						<td>
							<select name="lf_hp_cta_primary_action" id="lf_hp_cta_primary_action">
								<option value=""><?php esc_html_e('Use global/homepage setting', 'leadsforward-core'); ?></option>
								<option value="quote" <?php selected(($sec['cta_primary_action'] ?? ''), 'quote'); ?>><?php esc_html_e('Open Quote Builder', 'leadsforward-core'); ?></option>
								<option value="call" <?php selected(($sec['cta_primary_action'] ?? ''), 'call'); ?>><?php esc_html_e('Call now', 'leadsforward-core'); ?></option>
								<option value="link" <?php selected(($sec['cta_primary_action'] ?? ''), 'link'); ?>><?php esc_html_e('Link', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_primary_url"><?php esc_html_e('Section primary CTA URL', 'leadsforward-core'); ?></label></th>
						<td><input type="url" class="large-text" name="lf_hp_cta_primary_url" id="lf_hp_cta_primary_url" value="<?php echo esc_attr($sec['cta_primary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_secondary_action"><?php esc_html_e('Section secondary CTA action', 'leadsforward-core'); ?></label></th>
						<td>
							<select name="lf_hp_cta_secondary_action" id="lf_hp_cta_secondary_action">
								<option value=""><?php esc_html_e('Use global/homepage setting', 'leadsforward-core'); ?></option>
								<option value="call" <?php selected(($sec['cta_secondary_action'] ?? ''), 'call'); ?>><?php esc_html_e('Call now', 'leadsforward-core'); ?></option>
								<option value="quote" <?php selected(($sec['cta_secondary_action'] ?? ''), 'quote'); ?>><?php esc_html_e('Open Quote Builder', 'leadsforward-core'); ?></option>
								<option value="link" <?php selected(($sec['cta_secondary_action'] ?? ''), 'link'); ?>><?php esc_html_e('Link', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_secondary_url"><?php esc_html_e('Section secondary CTA URL', 'leadsforward-core'); ?></label></th>
						<td><input type="url" class="large-text" name="lf_hp_cta_secondary_url" id="lf_hp_cta_secondary_url" value="<?php echo esc_attr($sec['cta_secondary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e('Save Homepage Settings', 'leadsforward-core'); ?></button>
			</p>
		</form>
		<p class="description"><?php esc_html_e('Homepage CTA overrides (primary/secondary text, type) are in LeadsForward → Homepage Options.', 'leadsforward-core'); ?></p>
	</div>
	<?php
}
