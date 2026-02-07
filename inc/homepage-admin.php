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
	update_option('lf_maps_api_key', isset($_POST['lf_maps_api_key']) ? sanitize_text_field(wp_unslash($_POST['lf_maps_api_key'])) : '');

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
		if ($type === 'hero') {
			$config[$type]['hero_headline'] = isset($_POST['lf_hp_hero_headline']) ? sanitize_text_field($_POST['lf_hp_hero_headline']) : '';
			$config[$type]['hero_subheadline'] = isset($_POST['lf_hp_hero_subheadline']) ? sanitize_text_field($_POST['lf_hp_hero_subheadline']) : '';
			$config[$type]['hero_cta_override'] = isset($_POST['lf_hp_hero_cta_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_override']) : '';
		}
		if ($type === 'trust_reviews') {
			$n = isset($_POST['lf_hp_trust_max_items']) ? (int) $_POST['lf_hp_trust_max_items'] : 1;
			$config[$type]['trust_max_items'] = max(1, min(10, $n));
			$config[$type]['trust_heading'] = isset($_POST['lf_hp_trust_heading']) ? sanitize_text_field($_POST['lf_hp_trust_heading']) : '';
		}
		if ($type === 'service_grid') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_services_heading']) ? sanitize_text_field($_POST['lf_hp_services_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_services_intro']) ? sanitize_textarea_field($_POST['lf_hp_services_intro']) : '';
		}
		if ($type === 'service_areas') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_areas_heading']) ? sanitize_text_field($_POST['lf_hp_areas_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_areas_intro']) ? sanitize_textarea_field($_POST['lf_hp_areas_intro']) : '';
		}
		if ($type === 'map_nap') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_map_heading']) ? sanitize_text_field($_POST['lf_hp_map_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_map_intro']) ? sanitize_textarea_field($_POST['lf_hp_map_intro']) : '';
		}
		if ($type === 'faq_accordion') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_faq_heading']) ? sanitize_text_field($_POST['lf_hp_faq_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_faq_intro']) ? sanitize_textarea_field($_POST['lf_hp_faq_intro']) : '';
		}
		if ($type === 'cta') {
			$config[$type]['cta_headline'] = isset($_POST['lf_hp_cta_headline']) ? sanitize_text_field($_POST['lf_hp_cta_headline']) : '';
			$config[$type]['cta_primary_override'] = isset($_POST['lf_hp_cta_primary']) ? sanitize_text_field($_POST['lf_hp_cta_primary']) : '';
			$config[$type]['cta_secondary_override'] = isset($_POST['lf_hp_cta_secondary']) ? sanitize_text_field($_POST['lf_hp_cta_secondary']) : '';
			$config[$type]['cta_ghl_override'] = isset($_POST['lf_hp_cta_ghl']) ? wp_kses_post($_POST['lf_hp_cta_ghl']) : '';
		}
	}
	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	wp_redirect(admin_url('admin.php?page=lf-homepage-settings&saved=1'));
	exit;
}

function lf_homepage_admin_section_labels(): array {
	return [
		'hero'           => __('Hero', 'leadsforward-core'),
		'trust_reviews'  => __('Trust / Reviews', 'leadsforward-core'),
		'service_grid'   => __('Service Grid', 'leadsforward-core'),
		'service_areas'  => __('Service Areas', 'leadsforward-core'),
		'faq_accordion'  => __('FAQ Accordion', 'leadsforward-core'),
		'cta'            => __('Final CTA', 'leadsforward-core'),
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
		if (window.google && window.google.maps && window.google.maps.places) {
			callback();
			return;
		}
		if (!key) {
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
		if (!input) {
			return;
		}
		var form = input.closest('form');
		var key = form ? (form.getAttribute('data-maps-key') || '') : '';
		key = key.trim();
		if (!key) {
			if (selected) {
				selected.textContent = 'Add a Google Maps API key to enable search.';
			}
			return;
		}
		loadPlacesApi(key, function () {
			if (!window.google || !google.maps || !google.maps.places) {
				return;
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
	$('#lf_maps_api_key').on('change', initPlacesSearch);
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
	$variants = [
		'default' => __('Authority Split (Recommended)', 'leadsforward-core'),
		'a'       => __('Conversion Stack', 'leadsforward-core'),
		'b'       => __('Form First', 'leadsforward-core'),
		'c'       => __('Visual Proof', 'leadsforward-core'),
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
		$maps_api_key     = get_option('lf_maps_api_key', '');
		$business_name    = is_string($business_name) ? $business_name : '';
		$business_phone   = is_string($business_phone) ? $business_phone : '';
		$business_email   = is_string($business_email) ? $business_email : '';
		$business_address = is_string($business_address) ? $business_address : '';
		$place_id         = is_string($place_id) ? $place_id : '';
		$place_name       = is_string($place_name) ? $place_name : '';
		$place_address    = is_string($place_address) ? $place_address : '';
		$map_embed        = is_string($map_embed) ? $map_embed : '';
		$maps_api_key     = is_string($maps_api_key) ? $maps_api_key : '';
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
					<?php endif; ?>
					<?php if ($type === 'service_grid') : ?>
					<tr class="lf-homepage-section-fields" data-parent="service_grid">
						<th scope="row"><label for="lf_hp_services_heading"><?php esc_html_e('Services section heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_services_heading" id="lf_hp_services_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Our Services', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="service_grid">
						<th scope="row"><label for="lf_hp_services_intro"><?php esc_html_e('Services intro text', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_services_intro" id="lf_hp_services_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
					</tr>
					<?php endif; ?>
					<?php if ($type === 'service_areas') : ?>
					<tr class="lf-homepage-section-fields" data-parent="service_areas">
						<th scope="row"><label for="lf_hp_areas_heading"><?php esc_html_e('Service areas heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_areas_heading" id="lf_hp_areas_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Service Areas', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="service_areas">
						<th scope="row"><label for="lf_hp_areas_intro"><?php esc_html_e('Service areas intro', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" name="lf_hp_areas_intro" id="lf_hp_areas_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
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
					<?php if ($type === 'trust_reviews') : ?>
					<tr class="lf-homepage-section-fields" data-parent="trust_reviews">
						<th scope="row"><label for="lf_hp_trust_heading"><?php esc_html_e('Social proof heading', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_trust_heading" id="lf_hp_trust_heading" value="<?php echo esc_attr($sec['trust_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('What Our Customers Say', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr class="lf-homepage-section-fields" data-parent="trust_reviews">
						<th scope="row"><label for="lf_hp_trust_max_items"><?php esc_html_e('Max reviews to show', 'leadsforward-core'); ?></label></th>
						<td><input type="number" name="lf_hp_trust_max_items" id="lf_hp_trust_max_items" value="<?php echo esc_attr((string) ($sec['trust_max_items'] ?? 1)); ?>" min="1" max="10" /> (1–10)</td>
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
					<?php endif; ?>
					<?php if ($type === 'cta') : ?>
					<tr class="lf-homepage-section-fields" data-parent="cta">
						<th scope="row"><label for="lf_hp_cta_headline"><?php esc_html_e('CTA headline', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="large-text" name="lf_hp_cta_headline" id="lf_hp_cta_headline" value="<?php echo esc_attr($sec['cta_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('Ready to get started?', 'leadsforward-core'); ?>" /></td>
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
