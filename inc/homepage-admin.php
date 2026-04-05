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

	// Business Entity is managed in LeadsForward → Global Settings.

	$order = lf_homepage_controller_order();
	$config = lf_get_homepage_section_config();
	$allowed_variants = array_keys(function_exists('lf_sections_hero_variant_options') ? lf_sections_hero_variant_options() : ['default' => 'default', 'a' => 'a', 'b' => 'b', 'c' => 'c']);
	$icon_positions = ['above', 'left', 'list'];
	$icon_sizes = ['sm', 'md', 'lg'];
	$icon_colors = ['inherit', 'primary', 'secondary', 'muted'];
	$cta_action_keys = array_keys(function_exists('lf_sections_cta_action_options') ? lf_sections_cta_action_options(true) : ['' => '', 'quote' => '', 'call' => '', 'link' => '']);
	$bg_keys = function_exists('lf_sections_bg_options') ? array_keys(lf_sections_bg_options()) : ['light', 'soft', 'dark', 'card'];
	$icon_slugs = function_exists('lf_icon_options') ? array_keys(lf_icon_options()) : [];
	$details_prefixes = [
		'service_details' => 'lf_hp_details_',
		'content_image' => 'lf_hp_details_ci_',
		'content_image_a' => 'lf_hp_details_a_',
		'content_image_c' => 'lf_hp_details_c_',
		'image_content' => 'lf_hp_details_ic_',
		'image_content_b' => 'lf_hp_details_b_',
	];
	$details_layout_defaults = [
		'service_details' => 'content_media',
		'content_image' => 'content_media',
		'content_image_a' => 'content_media',
		'content_image_c' => 'content_media',
		'image_content' => 'media_content',
		'image_content_b' => 'media_content',
	];
	$details_media_defaults = [
		'service_details' => 'video',
		'content_image' => 'image',
		'content_image_a' => 'image',
		'content_image_c' => 'image',
		'image_content' => 'image',
		'image_content_b' => 'image',
	];
	$order_input = isset($_POST['lf_hp_order']) ? array_map('sanitize_text_field', (array) $_POST['lf_hp_order']) : [];
	$order = function_exists('lf_homepage_sanitize_order') ? lf_homepage_sanitize_order($order_input) : $order;
	update_option(LF_HOMEPAGE_ORDER_OPTION, $order, true);
	update_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, true, true);
	foreach ($order as $type) {
		$key_enabled = 'lf_hp_enabled_' . $type;
		$key_variant = 'lf_hp_variant_' . $type;
		$config[$type]['enabled'] = !empty($_POST[$key_enabled]);
		if ($type === 'hero') {
			$v = isset($_POST[$key_variant]) && in_array($_POST[$key_variant], $allowed_variants, true) ? $_POST[$key_variant] : 'default';
			$config[$type]['variant'] = $v;
		} else {
			$config[$type]['variant'] = 'default';
		}
		$bg_value = isset($_POST['lf_hp_bg_' . $type]) ? sanitize_text_field($_POST['lf_hp_bg_' . $type]) : 'light';
		$config[$type]['section_background'] = in_array($bg_value, $bg_keys, true) ? $bg_value : 'light';
		if ($type !== 'service_intro') {
			$icon_enabled = isset($_POST['lf_hp_icon_enabled_' . $type]) ? sanitize_text_field($_POST['lf_hp_icon_enabled_' . $type]) : '0';
			$config[$type]['icon_enabled'] = $icon_enabled === '1' ? '1' : '0';
			$icon_slug = isset($_POST['lf_hp_icon_slug_' . $type]) ? sanitize_text_field($_POST['lf_hp_icon_slug_' . $type]) : '';
			$config[$type]['icon_slug'] = in_array($icon_slug, $icon_slugs, true) ? $icon_slug : '';
			$icon_position = isset($_POST['lf_hp_icon_position_' . $type]) ? sanitize_text_field($_POST['lf_hp_icon_position_' . $type]) : 'left';
			$config[$type]['icon_position'] = in_array($icon_position, $icon_positions, true) ? $icon_position : 'left';
			$icon_size = isset($_POST['lf_hp_icon_size_' . $type]) ? sanitize_text_field($_POST['lf_hp_icon_size_' . $type]) : 'md';
			$config[$type]['icon_size'] = in_array($icon_size, $icon_sizes, true) ? $icon_size : 'md';
			$icon_color = isset($_POST['lf_hp_icon_color_' . $type]) ? sanitize_text_field($_POST['lf_hp_icon_color_' . $type]) : 'primary';
			$config[$type]['icon_color'] = in_array($icon_color, $icon_colors, true) ? $icon_color : 'primary';
		} else {
			$config[$type]['icon_enabled'] = '1';
			$config[$type]['icon_slug'] = '';
			$config[$type]['icon_position'] = 'list';
			$config[$type]['icon_size'] = 'md';
			$config[$type]['icon_color'] = 'primary';
		}
		if ($type === 'hero') {
			$config[$type]['hero_headline'] = isset($_POST['lf_hp_hero_headline']) ? sanitize_text_field($_POST['lf_hp_hero_headline']) : '';
			$config[$type]['hero_subheadline'] = isset($_POST['lf_hp_hero_subheadline']) ? sanitize_text_field($_POST['lf_hp_hero_subheadline']) : '';
			$config[$type]['hero_proof_title'] = isset($_POST['lf_hp_hero_proof_title']) ? sanitize_text_field($_POST['lf_hp_hero_proof_title']) : '';
			$config[$type]['hero_proof_bullets'] = isset($_POST['lf_hp_hero_proof_bullets']) ? sanitize_textarea_field(wp_unslash($_POST['lf_hp_hero_proof_bullets'])) : '';
			$hero_bg_mode = isset($_POST['lf_hp_hero_bg_mode']) ? sanitize_text_field($_POST['lf_hp_hero_bg_mode']) : 'image';
			$config[$type]['hero_background_mode'] = in_array($hero_bg_mode, ['color', 'image'], true) ? $hero_bg_mode : 'image';
			$config[$type]['hero_background_image_id'] = isset($_POST['lf_hp_hero_bg_image_id']) ? absint($_POST['lf_hp_hero_bg_image_id']) : 0;
			$eyebrow_enabled = isset($_POST['lf_hp_hero_eyebrow_enabled']) ? sanitize_text_field($_POST['lf_hp_hero_eyebrow_enabled']) : '1';
			$config[$type]['hero_eyebrow_enabled'] = $eyebrow_enabled === '0' ? '0' : '1';
			$config[$type]['hero_eyebrow_text'] = isset($_POST['lf_hp_hero_eyebrow_text']) ? sanitize_text_field($_POST['lf_hp_hero_eyebrow_text']) : '';
			$config[$type]['cta_primary_override'] = isset($_POST['lf_hp_hero_cta_primary_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_primary_override']) : '';
			$config[$type]['cta_secondary_override'] = isset($_POST['lf_hp_hero_cta_secondary_override']) ? sanitize_text_field($_POST['lf_hp_hero_cta_secondary_override']) : '';
			$hero_primary_enabled = isset($_POST['lf_hp_hero_cta_primary_enabled']) ? sanitize_text_field($_POST['lf_hp_hero_cta_primary_enabled']) : '1';
			$hero_secondary_enabled = isset($_POST['lf_hp_hero_cta_secondary_enabled']) ? sanitize_text_field($_POST['lf_hp_hero_cta_secondary_enabled']) : '1';
			$config[$type]['cta_primary_enabled'] = $hero_primary_enabled === '0' ? '0' : '1';
			$config[$type]['cta_secondary_enabled'] = $hero_secondary_enabled === '0' ? '0' : '1';
			$hero_action = isset($_POST['lf_hp_hero_cta_primary_action']) ? sanitize_text_field($_POST['lf_hp_hero_cta_primary_action']) : '';
			$config[$type]['cta_primary_action'] = in_array($hero_action, $cta_action_keys, true) ? $hero_action : '';
			$config[$type]['cta_primary_url'] = isset($_POST['lf_hp_hero_cta_primary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_hero_cta_primary_url'])) : '';
			$hero_secondary_action = isset($_POST['lf_hp_hero_cta_secondary_action']) ? sanitize_text_field($_POST['lf_hp_hero_cta_secondary_action']) : '';
			$config[$type]['cta_secondary_action'] = in_array($hero_secondary_action, $cta_action_keys, true) ? $hero_secondary_action : '';
			$config[$type]['cta_secondary_url'] = isset($_POST['lf_hp_hero_cta_secondary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_hero_cta_secondary_url'])) : '';
		}
		if ($type === 'trust_bar') {
			$config[$type]['trust_heading'] = isset($_POST['lf_hp_trust_heading']) ? sanitize_text_field($_POST['lf_hp_trust_heading']) : '';
			$config[$type]['trust_badges'] = isset($_POST['lf_hp_trust_badges']) ? sanitize_textarea_field($_POST['lf_hp_trust_badges']) : '';
			$config[$type]['trust_rating'] = isset($_POST['lf_hp_trust_rating']) ? sanitize_text_field($_POST['lf_hp_trust_rating']) : '';
			$config[$type]['trust_review_count'] = isset($_POST['lf_hp_trust_review_count']) ? sanitize_text_field($_POST['lf_hp_trust_review_count']) : '';
		}
		if ($type === 'trust_reviews') {
			$config[$type]['trust_heading'] = isset($_POST['lf_hp_reviews_heading']) ? sanitize_text_field($_POST['lf_hp_reviews_heading']) : '';
			$layout = isset($_POST['lf_hp_reviews_layout']) ? sanitize_text_field($_POST['lf_hp_reviews_layout']) : 'grid';
			$config[$type]['trust_layout'] = in_array($layout, ['grid', 'slider', 'masonry'], true) ? $layout : 'grid';
			$cols = isset($_POST['lf_hp_reviews_columns']) ? sanitize_text_field($_POST['lf_hp_reviews_columns']) : '3';
			$config[$type]['trust_columns'] = in_array($cols, ['2', '3', '4'], true) ? $cols : '3';
			$config[$type]['trust_max_items'] = isset($_POST['lf_hp_reviews_max']) ? absint($_POST['lf_hp_reviews_max']) : 6;
			$config[$type]['trust_show_summary'] = isset($_POST['lf_hp_reviews_summary']) && $_POST['lf_hp_reviews_summary'] === '0' ? '0' : '1';
			$config[$type]['trust_show_stars'] = isset($_POST['lf_hp_reviews_stars']) && $_POST['lf_hp_reviews_stars'] === '0' ? '0' : '1';
			$config[$type]['trust_show_source'] = isset($_POST['lf_hp_reviews_source']) && $_POST['lf_hp_reviews_source'] === '0' ? '0' : '1';
			$config[$type]['trust_show_avatars'] = isset($_POST['lf_hp_reviews_avatars']) && $_POST['lf_hp_reviews_avatars'] === '0' ? '0' : '1';
			$config[$type]['trust_show_quote_icon'] = isset($_POST['lf_hp_reviews_quote']) && $_POST['lf_hp_reviews_quote'] === '0' ? '0' : '1';
			$config[$type]['trust_slider_items_per_slide'] = isset($_POST['lf_hp_reviews_items_per_slide']) ? absint($_POST['lf_hp_reviews_items_per_slide']) : 3;
			$config[$type]['trust_slider_autoplay'] = isset($_POST['lf_hp_reviews_autoplay']) && $_POST['lf_hp_reviews_autoplay'] === '0' ? '0' : '1';
			$config[$type]['trust_slider_autoplay_delay'] = isset($_POST['lf_hp_reviews_autoplay_delay']) ? absint($_POST['lf_hp_reviews_autoplay_delay']) : 5;
			$config[$type]['trust_slider_show_dots'] = isset($_POST['lf_hp_reviews_show_dots']) && $_POST['lf_hp_reviews_show_dots'] === '0' ? '0' : '1';
			$config[$type]['trust_slider_show_arrows'] = isset($_POST['lf_hp_reviews_show_arrows']) && $_POST['lf_hp_reviews_show_arrows'] === '0' ? '0' : '1';
		}
		if ($type === 'benefits') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_benefits_heading']) ? sanitize_text_field($_POST['lf_hp_benefits_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_benefits_intro']) ? sanitize_textarea_field($_POST['lf_hp_benefits_intro']) : '';
			$config[$type]['benefits_items'] = isset($_POST['lf_hp_benefits_items']) ? sanitize_textarea_field($_POST['lf_hp_benefits_items']) : '';
		}
		if ($type === 'service_intro') {
			$config[$type]['section_heading'] = isset($_POST['lf_hp_service_intro_heading']) ? sanitize_text_field($_POST['lf_hp_service_intro_heading']) : '';
			$config[$type]['section_intro'] = isset($_POST['lf_hp_service_intro_intro']) ? sanitize_textarea_field($_POST['lf_hp_service_intro_intro']) : '';
			$cols = isset($_POST['lf_hp_service_intro_columns']) ? sanitize_text_field($_POST['lf_hp_service_intro_columns']) : '3';
			$config[$type]['service_intro_columns'] = in_array($cols, ['3', '4', '5', '6'], true) ? $cols : '3';
			$config[$type]['service_intro_max_items'] = isset($_POST['lf_hp_service_intro_max']) ? absint($_POST['lf_hp_service_intro_max']) : 6;
			$show_images = isset($_POST['lf_hp_service_intro_images']) ? sanitize_text_field($_POST['lf_hp_service_intro_images']) : '1';
			$config[$type]['service_intro_show_images'] = $show_images === '0' ? '0' : '1';
		}
		if (isset($details_prefixes[$type])) {
			$prefix = $details_prefixes[$type];
			$layout_default = $details_layout_defaults[$type] ?? 'content_media';
			$media_default = $details_media_defaults[$type] ?? 'video';
			$config[$type]['section_heading'] = isset($_POST[$prefix . 'heading']) ? sanitize_text_field($_POST[$prefix . 'heading']) : '';
			$config[$type]['section_intro'] = isset($_POST[$prefix . 'intro']) ? sanitize_textarea_field($_POST[$prefix . 'intro']) : '';
			$config[$type]['service_details_body'] = isset($_POST[$prefix . 'body']) ? sanitize_textarea_field($_POST[$prefix . 'body']) : '';
			$config[$type]['service_details_checklist'] = isset($_POST[$prefix . 'checklist']) ? sanitize_textarea_field($_POST[$prefix . 'checklist']) : '';
			$details_layout = isset($_POST[$prefix . 'layout']) ? sanitize_text_field($_POST[$prefix . 'layout']) : $layout_default;
			$config[$type]['service_details_layout'] = in_array($details_layout, ['content_media', 'media_content'], true) ? $details_layout : $layout_default;
			$details_media_mode = isset($_POST[$prefix . 'media_mode']) ? sanitize_text_field($_POST[$prefix . 'media_mode']) : $media_default;
			$config[$type]['service_details_media_mode'] = in_array($details_media_mode, ['video', 'image', 'none'], true) ? $details_media_mode : $media_default;
			$config[$type]['service_details_media_embed'] = isset($_POST[$prefix . 'media_embed'])
				? wp_kses_post(wp_unslash($_POST[$prefix . 'media_embed']))
				: '';
			$config[$type]['service_details_media_video_url'] = isset($_POST[$prefix . 'media_video_url'])
				? esc_url_raw(wp_unslash($_POST[$prefix . 'media_video_url']))
				: '';
			$config[$type]['service_details_media_image_id'] = isset($_POST[$prefix . 'media_image_id'])
				? absint($_POST[$prefix . 'media_image_id'])
				: 0;
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
			$config[$type]['cta_primary_action'] = in_array($cta_action, $cta_action_keys, true) ? $cta_action : '';
			$config[$type]['cta_primary_url'] = isset($_POST['lf_hp_cta_primary_url']) ? esc_url_raw(wp_unslash($_POST['lf_hp_cta_primary_url'])) : '';
			$cta_secondary_action = isset($_POST['lf_hp_cta_secondary_action']) ? sanitize_text_field($_POST['lf_hp_cta_secondary_action']) : '';
			$config[$type]['cta_secondary_action'] = in_array($cta_secondary_action, $cta_action_keys, true) ? $cta_secondary_action : '';
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
		'trust_reviews'  => __('Reviews', 'leadsforward-core'),
		'benefits'       => __('Benefits / Why Choose Us', 'leadsforward-core'),
		'service_intro'  => __('Service Intro Boxes', 'leadsforward-core'),
		'content_image_a' => __('Service Details (A)', 'leadsforward-core'),
		'image_content_b' => __('Service Details (B)', 'leadsforward-core'),
		'content_image_c' => __('Service Details (C)', 'leadsforward-core'),
		'service_details' => __('Service Details', 'leadsforward-core'),
		'content_image'  => __('Service Details (Alt)', 'leadsforward-core'),
		'image_content'  => __('Service Details (Alt • Media Left)', 'leadsforward-core'),
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
	wp_enqueue_script('jquery-ui-draggable');
	wp_enqueue_script('jquery-ui-droppable');
	wp_enqueue_media();
	wp_enqueue_script(
		'lf-section-sortable',
		LF_THEME_URI . '/assets/js/lf-section-sortable.js',
		['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'],
		LF_THEME_VERSION,
		true
	);
	$script = <<<'JS'
jQuery(function ($) {
	var $list = $('.lf-hp-sections');
	if (window.LFSectionSortable) {
		window.LFSectionSortable.initSortable($list, {
			items: '> li.lf-hp-section',
			handle: '.lf-homepage-drag',
			placeholder: 'lf-hp-placeholder'
		});
	}

	var storageKey = 'lf_homepage_collapsed';
	var collapsed = {};
	try {
		collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
	} catch (e) {
		collapsed = {};
	}

	function setStorage() {
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(collapsed));
		} catch (e) {}
	}

	function applyCollapse(type) {
		var isCollapsed = !!collapsed[type];
		var $section = $('.lf-hp-section[data-section="' + type + '"]');
		if ($section.length) {
			$section.toggleClass('lf-hp-section--collapsed', isCollapsed);
			var $toggle = $section.find('.lf-homepage-toggle');
			$toggle.attr('aria-expanded', (!isCollapsed).toString());
			$toggle.find('.lf-homepage-toggle-icon').text(isCollapsed ? '▸' : '▾');
			$toggle.find('.lf-homepage-toggle-label').text(isCollapsed ? 'Expand' : 'Collapse');
		}
		var $panel = $('.lf-homepage-panel[data-section="' + type + '"]');
		if ($panel.length) {
			$panel.toggleClass('lf-homepage-row--collapsed', isCollapsed);
			var $rows = $('.lf-homepage-section-fields[data-parent="' + type + '"]');
			$rows.toggleClass('lf-homepage-fields--collapsed', isCollapsed);
			var $panelToggle = $panel.find('.lf-homepage-toggle');
			$panelToggle.attr('aria-expanded', (!isCollapsed).toString());
			$panelToggle.find('.lf-homepage-toggle-icon').text(isCollapsed ? '▸' : '▾');
			$panelToggle.find('.lf-homepage-toggle-label').text(isCollapsed ? 'Expand' : 'Collapse');
		}
	}

	function getAllTypes() {
		var types = {};
		$('[data-section]').each(function () {
			var type = $(this).data('section');
			if (type) {
				types[type] = true;
			}
		});
		return Object.keys(types);
	}

	function activateSection(type, doScroll) {
		var $section = $('.lf-hp-section[data-section="' + type + '"]');
		if (!$section.length) {
			return;
		}
		var $checkbox = $section.find('input[type="checkbox"][name="lf_hp_enabled_' + type + '"]');
		if ($checkbox.length) {
			$checkbox.prop('checked', true);
		}
		collapsed[type] = false;
		applyCollapse(type);
		setStorage();
		if (doScroll) {
			$section[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function insertAtDrop($item, e) {
		var el = document.elementFromPoint(e.clientX, e.clientY);
		var $target = $(el).closest('.lf-hp-section');
		if ($target.length) {
			var midpoint = $target.offset().top + ($target.outerHeight() / 2);
			if (e.pageY > midpoint) {
				$target.after($item);
			} else {
				$target.before($item);
			}
		} else {
			$list.append($item);
		}
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
		setStorage();
		applyCollapse(type);
	});

	$('.lf-homepage-expand-all').on('click', function () {
		getAllTypes().forEach(function (type) {
			collapsed[type] = false;
			applyCollapse(type);
		});
		setStorage();
	});

	$('.lf-homepage-collapse-all').on('click', function () {
		getAllTypes().forEach(function (type) {
			collapsed[type] = true;
			applyCollapse(type);
		});
		setStorage();
	});

	var mediaFrame = null;
	function openMediaFrame($field) {
		if (!window.wp || !wp.media) {
			return;
		}
		if (mediaFrame) {
			mediaFrame.off('select');
		}
		mediaFrame = wp.media({
			title: 'Select image',
			button: { text: 'Use image' },
			library: { type: 'image' },
			multiple: false
		});
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first();
			if (!attachment) return;
			var data = attachment.toJSON();
			$field.find('.lf-media-id').val(data.id || '');
			var url = (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url;
			var html = url ? '<img src="' + url + '" alt="" />' : '';
			$field.find('.lf-media-preview').html(html || '<div class="lf-media-preview__empty">No image selected</div>');
		});
		mediaFrame.open();
	}

	$(document).on('click', '.lf-media-upload', function () {
		var $field = $(this).closest('.lf-media-field');
		openMediaFrame($field);
	});

	$(document).on('click', '.lf-media-remove', function () {
		var $field = $(this).closest('.lf-media-field');
		$field.find('.lf-media-id').val('');
		$field.find('.lf-media-preview').html('<div class="lf-media-preview__empty">No image selected</div>');
	});


	if (window.LFSectionSortable) {
		window.LFSectionSortable.initLibraryDrag($('.lf-hp-library__item'), $list, {
			helper: 'clone',
			appendTo: 'body',
			revert: 'invalid',
			zIndex: 9999,
			cancel: '.lf-hp-library__add',
			accept: '.lf-hp-library__item',
			tolerance: 'pointer',
			onDrop: function (e, ui) {
				var type = ui.draggable.data('sectionType');
				if (!type) return;
				var $section = $('.lf-hp-section[data-section="' + type + '"]');
				if (!$section.length) return;
				insertAtDrop($section, e);
				activateSection(type, false);
			}
		});
	}

	$(document).on('click', '.lf-hp-library__add', function () {
		var type = $(this).closest('.lf-hp-library__item').data('sectionType');
		if (!type) {
			return;
		}
		activateSection(type, true);
	});

	// Maps search now lives in Global Settings → Business Entity.
});
JS;
	wp_add_inline_script('lf-section-sortable', $script);
}

function lf_homepage_admin_render(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$config = lf_get_homepage_section_config();
	$order = lf_homepage_controller_order();
	$labels = lf_homepage_admin_section_labels();
	$section_defs = function_exists('lf_sections_get_context_sections') ? lf_sections_get_context_sections('homepage') : [];
	$section_list = array_values(array_filter($section_defs, function ($def) use ($order) {
		$id = is_array($def) && isset($def['id']) ? (string) $def['id'] : '';
		return $id !== '' && in_array($id, $order, true);
	}));
	$variants = function_exists('lf_sections_hero_variant_options') ? lf_sections_hero_variant_options() : [
		'default' => __('Authority Split (Recommended)', 'leadsforward-core'),
		'a'       => __('Conversion Stack', 'leadsforward-core'),
		'b'       => __('Form First', 'leadsforward-core'),
		'c'       => __('Visual Proof', 'leadsforward-core'),
	];
	$bg_options = function_exists('lf_sections_bg_options') ? lf_sections_bg_options() : [
		'white'     => __('White', 'leadsforward-core'),
		'light'     => __('Light', 'leadsforward-core'),
		'soft'      => __('Soft', 'leadsforward-core'),
		'primary'   => __('Primary', 'leadsforward-core'),
		'secondary' => __('Secondary', 'leadsforward-core'),
		'accent'    => __('Accent', 'leadsforward-core'),
		'dark'      => __('Dark', 'leadsforward-core'),
		'black'     => __('Black', 'leadsforward-core'),
		'card'      => __('Card', 'leadsforward-core'),
	];
	$icon_options = function_exists('lf_icon_options') ? lf_icon_options() : [];
	$icon_options = array_merge(['' => __('Auto (niche default)', 'leadsforward-core')], $icon_options);
	$icon_enabled_options = [
		'0' => __('Off', 'leadsforward-core'),
		'1' => __('On', 'leadsforward-core'),
	];
	$icon_positions = [
		'above' => __('Above heading', 'leadsforward-core'),
		'left'  => __('Left of heading', 'leadsforward-core'),
		'list'  => __('Inline with list items', 'leadsforward-core'),
	];
	$icon_sizes = [
		'sm' => __('Small', 'leadsforward-core'),
		'md' => __('Medium', 'leadsforward-core'),
		'lg' => __('Large', 'leadsforward-core'),
	];
	$icon_colors = [
		'inherit' => __('Inherit', 'leadsforward-core'),
		'primary' => __('Primary', 'leadsforward-core'),
		'secondary' => __('Secondary', 'leadsforward-core'),
		'muted' => __('Muted', 'leadsforward-core'),
	];
	$details_prefixes = [
		'service_details' => 'lf_hp_details_',
		'content_image' => 'lf_hp_details_ci_',
		'content_image_a' => 'lf_hp_details_a_',
		'content_image_c' => 'lf_hp_details_c_',
		'image_content' => 'lf_hp_details_ic_',
		'image_content_b' => 'lf_hp_details_b_',
	];
	$details_layout_defaults = [
		'service_details' => 'content_media',
		'content_image' => 'content_media',
		'content_image_a' => 'content_media',
		'content_image_c' => 'content_media',
		'image_content' => 'media_content',
		'image_content_b' => 'media_content',
	];
	$details_media_defaults = [
		'service_details' => 'video',
		'content_image' => 'image',
		'content_image_a' => 'image',
		'content_image_c' => 'image',
		'image_content' => 'image',
		'image_content_b' => 'image',
	];
	$cta_enabled_options = function_exists('lf_sections_toggle_options') ? lf_sections_toggle_options() : [
		'1' => __('On', 'leadsforward-core'),
		'0' => __('Off', 'leadsforward-core'),
	];
	$cta_action_options = function_exists('lf_sections_cta_action_options') ? lf_sections_cta_action_options(true) : [
		''      => __('Use global/homepage setting', 'leadsforward-core'),
		'quote' => __('Open Quote Builder', 'leadsforward-core'),
		'call'  => __('Call now', 'leadsforward-core'),
		'link'  => __('Link', 'leadsforward-core'),
	];
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Homepage', 'leadsforward-core'); ?></h1>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e('Drag to reorder sections. Use the library on the right to jump, re-enable, or move sections into place. A recommended default order is provided, but you control the layout.', 'leadsforward-core'); ?></p>
		<style>
			.lf-homepage-panel-controls { display: flex; gap: 0.5rem; margin: 1rem 0 1.25rem; }
			.lf-homepage-panel-controls .button { font-size: 12px; }
			.lf-homepage-panel { background: #fff; border-radius: 14px; box-shadow: 0 10px 24px rgba(15,23,42,0.08); padding: 1rem; margin: 1rem 0 1.5rem; }
			.lf-homepage-panel-header { display: flex; align-items: center; gap: 0.75rem; }
			.lf-homepage-panel-header h2 { margin: 0; }
			.lf-homepage-panel-body { margin-top: 0.75rem; }
			.lf-homepage-drag { cursor: grab; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #f1f5f9; color: #64748b; }
			.lf-homepage-drag:active { cursor: grabbing; }
			.lf-homepage-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
			.lf-homepage-toggle:hover { background: #e2e8f0; }
			.lf-homepage-toggle .lf-homepage-toggle-icon { margin-right: 4px; }
			.lf-homepage-row--collapsed .lf-homepage-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
			.lf-homepage-fields--collapsed { display: none; }
			.lf-hp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 1.5rem; align-items: start; }
			.lf-hp-main { min-width: 0; }
			.lf-hp-sections { list-style: none; margin: 0; padding: 0; min-height: 80px; }
			.lf-hp-section { list-style: none; margin: 0 0 1rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; }
			.lf-hp-section-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1rem; flex-wrap: wrap; }
			.lf-hp-section-header label { font-size: 12px; }
			.lf-hp-section-body { padding: 0 1rem 1rem; }
			.lf-hp-section--collapsed .lf-hp-section-body { display: none; }
			.lf-hp-section--collapsed .lf-homepage-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
			.lf-hp-placeholder { border: 2px dashed #94a3b8; border-radius: 14px; height: 58px; margin-bottom: 1rem; background: #f8fafc; }
			.lf-hp-library { position: sticky; top: 12px; background: #0f172a; color: #fff; border-radius: 16px; padding: 1rem; }
			.lf-hp-library h4 { margin: 0 0 0.5rem; font-size: 14px; }
			.lf-hp-library p { margin: 0 0 0.75rem; color: #cbd5f5; font-size: 12px; }
			.lf-hp-library__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
			.lf-hp-library__item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.6rem 0.65rem; border-radius: 12px; background: rgba(255,255,255,0.08); cursor: grab; }
			.lf-hp-library__label { font-size: 12px; font-weight: 600; }
			.lf-hp-library__add { font-size: 11px; border-radius: 999px; padding: 0.15rem 0.6rem; border: 1px solid rgba(255,255,255,0.4); background: transparent; color: #fff; cursor: pointer; }
			.lf-hp-library__item:active { cursor: grabbing; }
			.lf-hp-section .form-table { margin-top: 0; }
			.lf-media-field { display: grid; gap: 0.75rem; }
			.lf-media-preview { width: 160px; height: 100px; border: 1px dashed #cbd5e1; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f8fafc; }
			.lf-media-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
			.lf-media-preview__empty { font-size: 12px; color: #64748b; text-align: center; padding: 0 0.5rem; }
			.lf-media-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
		</style>
		<div class="lf-homepage-panel-controls">
			<button type="button" class="button lf-homepage-expand-all"><?php esc_html_e('Expand all', 'leadsforward-core'); ?></button>
			<button type="button" class="button lf-homepage-collapse-all"><?php esc_html_e('Collapse all', 'leadsforward-core'); ?></button>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field('lf_homepage_settings', 'lf_homepage_settings_nonce'); ?>
		
		<h2 class="title"><?php esc_html_e('Homepage sections', 'leadsforward-core'); ?></h2>
		<div class="lf-hp-grid">
			<div class="lf-hp-main">
				<ul class="lf-hp-sections">
				<?php foreach ($order as $type) :
					$sec = $config[$type] ?? [];
					$enabled = !empty($sec['enabled']);
					$variant = $sec['variant'] ?? 'default';
					$label = $labels[$type] ?? $type;
				?>
					<li class="lf-hp-section" data-section="<?php echo esc_attr($type); ?>">
						<div class="lf-hp-section-header">
							<span class="lf-homepage-drag" aria-hidden="true">⋮⋮</span>
							<strong><?php echo esc_html($label); ?></strong>
							<label><input type="checkbox" name="lf_hp_enabled_<?php echo esc_attr($type); ?>" id="lf_hp_enabled_<?php echo esc_attr($type); ?>" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Show this section', 'leadsforward-core'); ?></label>
							<?php if ($type === 'hero') : ?>
								<label><?php esc_html_e('Variant', 'leadsforward-core'); ?>
									<select name="lf_hp_variant_<?php echo esc_attr($type); ?>">
										<?php foreach ($variants as $v => $vlabel) : ?>
											<option value="<?php echo esc_attr($v); ?>" <?php selected($variant, $v); ?>><?php echo esc_html($vlabel); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endif; ?>
							<button type="button" class="lf-homepage-toggle" data-target="<?php echo esc_attr($type); ?>" aria-expanded="true">
								<span class="lf-homepage-toggle-icon">▾</span>
								<span class="lf-homepage-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
							</button>
							<input type="hidden" name="lf_hp_order[]" value="<?php echo esc_attr($type); ?>" />
						</div>
						<div class="lf-hp-section-body lf-homepage-section-fields" data-parent="<?php echo esc_attr($type); ?>">
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
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
									<?php if ($type !== 'service_intro') : ?>
										<?php
											$icon_enabled = (string) ($sec['icon_enabled'] ?? '0');
											$icon_slug = (string) ($sec['icon_slug'] ?? '');
											$icon_position = (string) ($sec['icon_position'] ?? 'left');
											$icon_size = (string) ($sec['icon_size'] ?? 'md');
											$icon_color = (string) ($sec['icon_color'] ?? 'primary');
										?>
										<tr>
											<th scope="row"><label for="lf_hp_icon_enabled_<?php echo esc_attr($type); ?>"><?php esc_html_e('Icon', 'leadsforward-core'); ?></label></th>
											<td>
												<select name="lf_hp_icon_enabled_<?php echo esc_attr($type); ?>" id="lf_hp_icon_enabled_<?php echo esc_attr($type); ?>">
													<?php foreach ($icon_enabled_options as $opt_key => $opt_label) : ?>
														<option value="<?php echo esc_attr($opt_key); ?>" <?php selected($icon_enabled, $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
													<?php endforeach; ?>
												</select>
												<select name="lf_hp_icon_slug_<?php echo esc_attr($type); ?>" id="lf_hp_icon_slug_<?php echo esc_attr($type); ?>">
													<?php foreach ($icon_options as $opt_key => $opt_label) : ?>
														<option value="<?php echo esc_attr($opt_key); ?>" <?php selected($icon_slug, $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="lf_hp_icon_position_<?php echo esc_attr($type); ?>"><?php esc_html_e('Icon position', 'leadsforward-core'); ?></label></th>
											<td>
												<select name="lf_hp_icon_position_<?php echo esc_attr($type); ?>" id="lf_hp_icon_position_<?php echo esc_attr($type); ?>">
													<?php foreach ($icon_positions as $opt_key => $opt_label) : ?>
														<option value="<?php echo esc_attr($opt_key); ?>" <?php selected($icon_position, $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="lf_hp_icon_size_<?php echo esc_attr($type); ?>"><?php esc_html_e('Icon size', 'leadsforward-core'); ?></label></th>
											<td>
												<select name="lf_hp_icon_size_<?php echo esc_attr($type); ?>" id="lf_hp_icon_size_<?php echo esc_attr($type); ?>">
													<?php foreach ($icon_sizes as $opt_key => $opt_label) : ?>
														<option value="<?php echo esc_attr($opt_key); ?>" <?php selected($icon_size, $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="lf_hp_icon_color_<?php echo esc_attr($type); ?>"><?php esc_html_e('Icon color', 'leadsforward-core'); ?></label></th>
											<td>
												<select name="lf_hp_icon_color_<?php echo esc_attr($type); ?>" id="lf_hp_icon_color_<?php echo esc_attr($type); ?>">
													<?php foreach ($icon_colors as $opt_key => $opt_label) : ?>
														<option value="<?php echo esc_attr($opt_key); ?>" <?php selected($icon_color, $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
									<?php endif; ?>
									<?php if ($type === 'map_nap') : ?>
										<tr>
											<td colspan="2">
												<p class="description" style="margin: 0;"><?php esc_html_e('Service areas and map come from Global Settings -> Business Entity.', 'leadsforward-core'); ?></p>
											</td>
										</tr>
									<?php endif; ?>
									<?php if ($type === 'hero') : ?>
									<?php
										$hero_bg_mode = $sec['hero_background_mode'] ?? 'image';
										$hero_bg_image_id = isset($sec['hero_background_image_id']) ? (int) $sec['hero_background_image_id'] : 0;
										$hero_bg_thumb = $hero_bg_image_id ? wp_get_attachment_image_src($hero_bg_image_id, 'thumbnail') : null;
										$hero_bg_html = $hero_bg_thumb ? '<img src="' . esc_url($hero_bg_thumb[0]) . '" alt="" />' : '';
									?>
									<tr>
										<th scope="row"><label for="lf_hp_hero_bg_mode"><?php esc_html_e('Hero background', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_bg_mode" id="lf_hp_hero_bg_mode">
												<option value="color" <?php selected($hero_bg_mode, 'color'); ?>><?php esc_html_e('Background color', 'leadsforward-core'); ?></option>
												<option value="image" <?php selected($hero_bg_mode, 'image'); ?>><?php esc_html_e('Featured image overlay', 'leadsforward-core'); ?></option>
											</select>
											<p class="description" style="margin: 6px 0 0;"><?php esc_html_e('Uses the page featured image when enabled. Background color becomes the overlay color.', 'leadsforward-core'); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e('Hero background image', 'leadsforward-core'); ?></th>
										<td>
											<div class="lf-media-field">
												<div class="lf-media-preview">
													<?php echo $hero_bg_html !== '' ? $hero_bg_html : '<div class="lf-media-preview__empty">' . esc_html__('No image selected', 'leadsforward-core') . '</div>'; ?>
												</div>
												<div class="lf-media-actions">
													<button type="button" class="button lf-media-upload"><?php esc_html_e('Select image', 'leadsforward-core'); ?></button>
													<button type="button" class="button lf-media-remove"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
												</div>
												<input type="hidden" class="lf-media-id" name="lf_hp_hero_bg_image_id" value="<?php echo esc_attr((string) $hero_bg_image_id); ?>" />
											</div>
											<p class="description" style="margin: 6px 0 0;"><?php esc_html_e('Default background image. Falls back to featured image if empty.', 'leadsforward-core'); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_headline"><?php esc_html_e('Hero headline', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_hero_headline" id="lf_hp_hero_headline" value="<?php echo esc_attr($sec['hero_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Quality Roofing in [City]', 'leadsforward-core'); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_subheadline"><?php esc_html_e('Hero subheadline', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_hero_subheadline" id="lf_hp_hero_subheadline" value="<?php echo esc_attr($sec['hero_subheadline'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_proof_title"><?php esc_html_e('Proof card title', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_hero_proof_title" id="lf_hp_hero_proof_title" value="<?php echo esc_attr($sec['hero_proof_title'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_proof_bullets"><?php esc_html_e('Proof card bullets (one per line)', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_hero_proof_bullets" id="lf_hp_hero_proof_bullets" rows="3"><?php echo esc_textarea($sec['hero_proof_bullets'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_eyebrow_enabled"><?php esc_html_e('Trust badge enabled', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_eyebrow_enabled" id="lf_hp_hero_eyebrow_enabled">
												<?php foreach ($cta_enabled_options as $opt_key => $opt_label) : ?>
													<option value="<?php echo esc_attr($opt_key); ?>" <?php selected((string) ($sec['hero_eyebrow_enabled'] ?? '1'), $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_eyebrow_text"><?php esc_html_e('Trust badge text', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_hero_eyebrow_text" id="lf_hp_hero_eyebrow_text" value="<?php echo esc_attr($sec['hero_eyebrow_text'] ?? ''); ?>" placeholder="<?php esc_attr_e('Licensed • Insured • Local', 'leadsforward-core'); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_primary_enabled"><?php esc_html_e('Primary CTA enabled', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_cta_primary_enabled" id="lf_hp_hero_cta_primary_enabled">
												<?php foreach ($cta_enabled_options as $opt_key => $opt_label) : ?>
													<option value="<?php echo esc_attr($opt_key); ?>" <?php selected((string) ($sec['cta_primary_enabled'] ?? '1'), $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_secondary_enabled"><?php esc_html_e('Secondary CTA enabled', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_cta_secondary_enabled" id="lf_hp_hero_cta_secondary_enabled">
												<?php foreach ($cta_enabled_options as $opt_key => $opt_label) : ?>
													<option value="<?php echo esc_attr($opt_key); ?>" <?php selected((string) ($sec['cta_secondary_enabled'] ?? '1'), $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_primary_override"><?php esc_html_e('Primary CTA override', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_hp_hero_cta_primary_override" id="lf_hp_hero_cta_primary_override" value="<?php echo esc_attr($sec['cta_primary_override'] ?? ''); ?>" /> <span class="description"><?php esc_html_e('Leave blank to use homepage CTA.', 'leadsforward-core'); ?></span></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_secondary_override"><?php esc_html_e('Secondary CTA override', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_hp_hero_cta_secondary_override" id="lf_hp_hero_cta_secondary_override" value="<?php echo esc_attr($sec['cta_secondary_override'] ?? ''); ?>" /> <span class="description"><?php esc_html_e('Defaults to global secondary CTA if empty.', 'leadsforward-core'); ?></span></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_primary_action"><?php esc_html_e('Primary CTA action', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_cta_primary_action" id="lf_hp_hero_cta_primary_action">
												<?php foreach ($cta_action_options as $opt_key => $opt_label) : ?>
													<option value="<?php echo esc_attr($opt_key); ?>" <?php selected(($sec['cta_primary_action'] ?? ''), $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
												<?php endforeach; ?>
											</select>
											<span class="description"><?php esc_html_e('Controls whether this CTA opens the Quote Builder modal.', 'leadsforward-core'); ?></span>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_primary_url"><?php esc_html_e('Primary CTA URL', 'leadsforward-core'); ?></label></th>
										<td><input type="url" class="large-text" name="lf_hp_hero_cta_primary_url" id="lf_hp_hero_cta_primary_url" value="<?php echo esc_attr($sec['cta_primary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_secondary_action"><?php esc_html_e('Secondary CTA action', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_hero_cta_secondary_action" id="lf_hp_hero_cta_secondary_action">
												<?php foreach ($cta_action_options as $opt_key => $opt_label) : ?>
													<option value="<?php echo esc_attr($opt_key); ?>" <?php selected(($sec['cta_secondary_action'] ?? ''), $opt_key); ?>><?php echo esc_html($opt_label); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_hero_cta_secondary_url"><?php esc_html_e('Secondary CTA URL', 'leadsforward-core'); ?></label></th>
										<td><input type="url" class="large-text" name="lf_hp_hero_cta_secondary_url" id="lf_hp_hero_cta_secondary_url" value="<?php echo esc_attr($sec['cta_secondary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'trust_bar') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_trust_heading"><?php esc_html_e('Trust bar heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_trust_heading" id="lf_hp_trust_heading" value="<?php echo esc_attr($sec['trust_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_trust_badges"><?php esc_html_e('Badges (one per line)', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_trust_badges" id="lf_hp_trust_badges" rows="3"><?php echo esc_textarea($sec['trust_badges'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_trust_rating"><?php esc_html_e('Rating override (optional)', 'leadsforward-core'); ?></label></th>
										<td><input type="number" step="0.1" name="lf_hp_trust_rating" id="lf_hp_trust_rating" value="<?php echo esc_attr((string) ($sec['trust_rating'] ?? '')); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_trust_review_count"><?php esc_html_e('Review count override (optional)', 'leadsforward-core'); ?></label></th>
										<td><input type="number" name="lf_hp_trust_review_count" id="lf_hp_trust_review_count" value="<?php echo esc_attr((string) ($sec['trust_review_count'] ?? '')); ?>" /></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'trust_reviews') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_heading"><?php esc_html_e('Reviews heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_reviews_heading" id="lf_hp_reviews_heading" value="<?php echo esc_attr($sec['trust_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_layout"><?php esc_html_e('Layout', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_layout" id="lf_hp_reviews_layout">
												<option value="grid" <?php selected(($sec['trust_layout'] ?? 'grid'), 'grid'); ?>><?php esc_html_e('Grid', 'leadsforward-core'); ?></option>
												<option value="slider" <?php selected(($sec['trust_layout'] ?? ''), 'slider'); ?>><?php esc_html_e('Slider', 'leadsforward-core'); ?></option>
												<option value="masonry" <?php selected(($sec['trust_layout'] ?? ''), 'masonry'); ?>><?php esc_html_e('Masonry', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_columns"><?php esc_html_e('Columns', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_columns" id="lf_hp_reviews_columns">
												<option value="2" <?php selected(($sec['trust_columns'] ?? '3'), '2'); ?>><?php esc_html_e('2 columns', 'leadsforward-core'); ?></option>
												<option value="3" <?php selected(($sec['trust_columns'] ?? '3'), '3'); ?>><?php esc_html_e('3 columns', 'leadsforward-core'); ?></option>
												<option value="4" <?php selected(($sec['trust_columns'] ?? '3'), '4'); ?>><?php esc_html_e('4 columns', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_max"><?php esc_html_e('Max items', 'leadsforward-core'); ?></label></th>
										<td><input type="number" class="small-text" name="lf_hp_reviews_max" id="lf_hp_reviews_max" value="<?php echo esc_attr((string) ($sec['trust_max_items'] ?? '6')); ?>" min="1" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_summary"><?php esc_html_e('Show summary', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_summary" id="lf_hp_reviews_summary">
												<option value="1" <?php selected((string) ($sec['trust_show_summary'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_show_summary'] ?? ''), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_stars"><?php esc_html_e('Show stars', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_stars" id="lf_hp_reviews_stars">
												<option value="1" <?php selected((string) ($sec['trust_show_stars'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_show_stars'] ?? ''), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_source"><?php esc_html_e('Show review source', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_source" id="lf_hp_reviews_source">
												<option value="1" <?php selected((string) ($sec['trust_show_source'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_show_source'] ?? ''), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_avatars"><?php esc_html_e('Show avatars', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_avatars" id="lf_hp_reviews_avatars">
												<option value="1" <?php selected((string) ($sec['trust_show_avatars'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_show_avatars'] ?? ''), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_quote"><?php esc_html_e('Show quote icon', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_quote" id="lf_hp_reviews_quote">
												<option value="1" <?php selected((string) ($sec['trust_show_quote_icon'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_show_quote_icon'] ?? ''), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<!-- Slider Controls -->
									<tr>
										<th scope="row"><label for="lf_hp_reviews_items_per_slide"><?php esc_html_e('Reviews Per Slide (Slider)', 'leadsforward-core'); ?></label></th>
										<td><input type="number" class="small-text" name="lf_hp_reviews_items_per_slide" id="lf_hp_reviews_items_per_slide" value="<?php echo esc_attr((string) ($sec['trust_slider_items_per_slide'] ?? '3')); ?>" min="1" max="6" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_autoplay"><?php esc_html_e('Auto-Play Slider', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_autoplay" id="lf_hp_reviews_autoplay">
												<option value="1" <?php selected((string) ($sec['trust_slider_autoplay'] ?? '0'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_slider_autoplay'] ?? '0'), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_autoplay_delay"><?php esc_html_e('Auto-Play Delay (seconds)', 'leadsforward-core'); ?></label></th>
										<td><input type="number" class="small-text" name="lf_hp_reviews_autoplay_delay" id="lf_hp_reviews_autoplay_delay" value="<?php echo esc_attr((string) ($sec['trust_slider_autoplay_delay'] ?? '5')); ?>" min="1" max="30" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_show_dots"><?php esc_html_e('Show Slider Dots', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_show_dots" id="lf_hp_reviews_show_dots">
												<option value="1" <?php selected((string) ($sec['trust_slider_show_dots'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_slider_show_dots'] ?? '1'), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_reviews_show_arrows"><?php esc_html_e('Show Slider Arrows', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_reviews_show_arrows" id="lf_hp_reviews_show_arrows">
												<option value="1" <?php selected((string) ($sec['trust_slider_show_arrows'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['trust_slider_show_arrows'] ?? '1'), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'benefits') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_benefits_heading"><?php esc_html_e('Benefits heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_benefits_heading" id="lf_hp_benefits_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_benefits_intro"><?php esc_html_e('Benefits intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_benefits_intro" id="lf_hp_benefits_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_benefits_items"><?php esc_html_e('Benefits (one per line)', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_benefits_items" id="lf_hp_benefits_items" rows="3"><?php echo esc_textarea($sec['benefits_items'] ?? ''); ?></textarea></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'service_intro') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_service_intro_heading"><?php esc_html_e('Section heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_service_intro_heading" id="lf_hp_service_intro_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_service_intro_intro"><?php esc_html_e('Supporting text', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_service_intro_intro" id="lf_hp_service_intro_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_service_intro_columns"><?php esc_html_e('Columns', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_service_intro_columns" id="lf_hp_service_intro_columns">
												<option value="3" <?php selected(($sec['service_intro_columns'] ?? '3'), '3'); ?>><?php esc_html_e('3 columns', 'leadsforward-core'); ?></option>
												<option value="4" <?php selected(($sec['service_intro_columns'] ?? ''), '4'); ?>><?php esc_html_e('4 columns', 'leadsforward-core'); ?></option>
												<option value="5" <?php selected(($sec['service_intro_columns'] ?? ''), '5'); ?>><?php esc_html_e('5 columns', 'leadsforward-core'); ?></option>
												<option value="6" <?php selected(($sec['service_intro_columns'] ?? ''), '6'); ?>><?php esc_html_e('6 columns', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_service_intro_max"><?php esc_html_e('Max services', 'leadsforward-core'); ?></label></th>
										<td><input type="number" name="lf_hp_service_intro_max" id="lf_hp_service_intro_max" value="<?php echo esc_attr((string) ($sec['service_intro_max_items'] ?? '6')); ?>" min="3" max="12" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_service_intro_images"><?php esc_html_e('Show images', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="lf_hp_service_intro_images" id="lf_hp_service_intro_images">
												<option value="1" <?php selected((string) ($sec['service_intro_show_images'] ?? '1'), '1'); ?>><?php esc_html_e('On', 'leadsforward-core'); ?></option>
												<option value="0" <?php selected((string) ($sec['service_intro_show_images'] ?? '1'), '0'); ?>><?php esc_html_e('Off', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<?php endif; ?>
									<?php if (isset($details_prefixes[$type])) : ?>
									<?php
										$prefix = $details_prefixes[$type];
										$layout_default = $details_layout_defaults[$type] ?? 'content_media';
										$media_default = $details_media_defaults[$type] ?? 'video';
										$details_layout = $sec['service_details_layout'] ?? $layout_default;
										$details_media_mode = $sec['service_details_media_mode'] ?? $media_default;
										$details_media_embed = $sec['service_details_media_embed'] ?? '';
										$details_media_video_url = $sec['service_details_media_video_url'] ?? '';
										$details_media_image_id = isset($sec['service_details_media_image_id']) ? (int) $sec['service_details_media_image_id'] : 0;
										$details_thumb = $details_media_image_id ? wp_get_attachment_image_src($details_media_image_id, 'thumbnail') : null;
										$details_img_html = $details_thumb ? '<img src="' . esc_url($details_thumb[0]) . '" alt="" />' : '';
									?>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>heading"><?php esc_html_e('Service details heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="<?php echo esc_attr($prefix); ?>heading" id="<?php echo esc_attr($prefix); ?>heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>intro"><?php esc_html_e('Service details intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="<?php echo esc_attr($prefix); ?>intro" id="<?php echo esc_attr($prefix); ?>intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>body"><?php esc_html_e('Service details body', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="<?php echo esc_attr($prefix); ?>body" id="<?php echo esc_attr($prefix); ?>body" rows="4"><?php echo esc_textarea($sec['service_details_body'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>checklist"><?php esc_html_e('Checklist (one per line)', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="<?php echo esc_attr($prefix); ?>checklist" id="<?php echo esc_attr($prefix); ?>checklist" rows="3"><?php echo esc_textarea($sec['service_details_checklist'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>layout"><?php esc_html_e('Layout', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="<?php echo esc_attr($prefix); ?>layout" id="<?php echo esc_attr($prefix); ?>layout">
												<option value="content_media" <?php selected($details_layout, 'content_media'); ?>><?php esc_html_e('Content left / Media right', 'leadsforward-core'); ?></option>
												<option value="media_content" <?php selected($details_layout, 'media_content'); ?>><?php esc_html_e('Media left / Content right', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>media_mode"><?php esc_html_e('Media mode', 'leadsforward-core'); ?></label></th>
										<td>
											<select name="<?php echo esc_attr($prefix); ?>media_mode" id="<?php echo esc_attr($prefix); ?>media_mode">
												<option value="video" <?php selected($details_media_mode, 'video'); ?>><?php esc_html_e('Video (embed or URL)', 'leadsforward-core'); ?></option>
												<option value="image" <?php selected($details_media_mode, 'image'); ?>><?php esc_html_e('Image', 'leadsforward-core'); ?></option>
												<option value="none" <?php selected($details_media_mode, 'none'); ?>><?php esc_html_e('None', 'leadsforward-core'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>media_embed"><?php esc_html_e('Video embed code', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="<?php echo esc_attr($prefix); ?>media_embed" id="<?php echo esc_attr($prefix); ?>media_embed" rows="3"><?php echo esc_textarea($details_media_embed); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr($prefix); ?>media_video_url"><?php esc_html_e('Video URL (self-hosted or YouTube)', 'leadsforward-core'); ?></label></th>
										<td><input type="url" class="large-text" name="<?php echo esc_attr($prefix); ?>media_video_url" id="<?php echo esc_attr($prefix); ?>media_video_url" value="<?php echo esc_attr($details_media_video_url); ?>" placeholder="https://..." /></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e('Media image', 'leadsforward-core'); ?></th>
										<td>
											<div class="lf-media-field">
												<div class="lf-media-preview">
													<?php echo $details_img_html !== '' ? $details_img_html : '<div class="lf-media-preview__empty">' . esc_html__('No image selected', 'leadsforward-core') . '</div>'; ?>
												</div>
												<div class="lf-media-actions">
													<button type="button" class="button lf-media-upload"><?php esc_html_e('Select image', 'leadsforward-core'); ?></button>
													<button type="button" class="button lf-media-remove"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
												</div>
												<input type="hidden" class="lf-media-id" name="<?php echo esc_attr($prefix); ?>media_image_id" value="<?php echo esc_attr((string) $details_media_image_id); ?>" />
											</div>
										</td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'process') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_process_heading"><?php esc_html_e('Process heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_process_heading" id="lf_hp_process_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_process_intro"><?php esc_html_e('Process intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_process_intro" id="lf_hp_process_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_process_steps"><?php esc_html_e('Process steps (one per line)', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_process_steps" id="lf_hp_process_steps" rows="3"><?php echo esc_textarea($sec['process_steps'] ?? ''); ?></textarea></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'related_links') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_related_heading"><?php esc_html_e('Related links heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_related_heading" id="lf_hp_related_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_related_intro"><?php esc_html_e('Related links intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_related_intro" id="lf_hp_related_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
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
									<tr>
										<th scope="row"><label for="lf_hp_map_heading"><?php esc_html_e('Map section heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_map_heading" id="lf_hp_map_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Areas We Serve', 'leadsforward-core'); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_map_intro"><?php esc_html_e('Map section intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_map_intro" id="lf_hp_map_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'faq_accordion') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_faq_heading"><?php esc_html_e('FAQ section heading', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_faq_heading" id="lf_hp_faq_heading" value="<?php echo esc_attr($sec['section_heading'] ?? ''); ?>" placeholder="<?php esc_attr_e('Frequently Asked Questions', 'leadsforward-core'); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_faq_intro"><?php esc_html_e('FAQ intro', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_faq_intro" id="lf_hp_faq_intro" rows="2"><?php echo esc_textarea($sec['section_intro'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_faq_max"><?php esc_html_e('Max FAQ items', 'leadsforward-core'); ?></label></th>
										<td><input type="number" name="lf_hp_faq_max" id="lf_hp_faq_max" value="<?php echo esc_attr((string) ($sec['faq_max_items'] ?? '6')); ?>" min="1" max="20" /></td>
									</tr>
									<?php endif; ?>
									<?php if ($type === 'cta') : ?>
									<tr>
										<th scope="row"><label for="lf_hp_cta_headline"><?php esc_html_e('CTA headline', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_hp_cta_headline" id="lf_hp_cta_headline" value="<?php echo esc_attr($sec['cta_headline'] ?? ''); ?>" placeholder="<?php esc_attr_e('Ready to get started?', 'leadsforward-core'); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_cta_subheadline"><?php esc_html_e('Supporting text', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" name="lf_hp_cta_subheadline" id="lf_hp_cta_subheadline" rows="2"><?php echo esc_textarea($sec['cta_subheadline'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_cta_primary"><?php esc_html_e('Section primary CTA', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_hp_cta_primary" id="lf_hp_cta_primary" value="<?php echo esc_attr($sec['cta_primary_override'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_cta_secondary"><?php esc_html_e('Section secondary CTA', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="regular-text" name="lf_hp_cta_secondary" id="lf_hp_cta_secondary" value="<?php echo esc_attr($sec['cta_secondary_override'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label for="lf_hp_cta_ghl"><?php esc_html_e('Section GHL embed override', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text code" name="lf_hp_cta_ghl" id="lf_hp_cta_ghl" rows="4"><?php echo esc_textarea($sec['cta_ghl_override'] ?? ''); ?></textarea></td>
									</tr>
									<tr>
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
									<tr>
										<th scope="row"><label for="lf_hp_cta_primary_url"><?php esc_html_e('Section primary CTA URL', 'leadsforward-core'); ?></label></th>
										<td><input type="url" class="large-text" name="lf_hp_cta_primary_url" id="lf_hp_cta_primary_url" value="<?php echo esc_attr($sec['cta_primary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
									</tr>
									<tr>
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
									<tr>
										<th scope="row"><label for="lf_hp_cta_secondary_url"><?php esc_html_e('Section secondary CTA URL', 'leadsforward-core'); ?></label></th>
										<td><input type="url" class="large-text" name="lf_hp_cta_secondary_url" id="lf_hp_cta_secondary_url" value="<?php echo esc_attr($sec['cta_secondary_url'] ?? ''); ?>" placeholder="https://example.com" /></td>
									</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
			<aside class="lf-hp-library">
				<h4><?php esc_html_e('Section Library', 'leadsforward-core'); ?></h4>
				<p><?php esc_html_e('Drag or click Add to insert a section.', 'leadsforward-core'); ?></p>
				<ul class="lf-hp-library__list">
					<?php foreach ($section_list as $def) :
						$type = $def['id'] ?? '';
						if ($type === '') {
							continue;
						}
						$label = $def['label'] ?? ($labels[$type] ?? $type);
					?>
						<li class="lf-hp-library__item" data-section-type="<?php echo esc_attr($type); ?>">
							<span class="lf-hp-library__label"><?php echo esc_html($label); ?></span>
							<button type="button" class="lf-hp-library__add"><?php esc_html_e('Add', 'leadsforward-core'); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</aside>
		</div>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e('Save Homepage Settings', 'leadsforward-core'); ?></button>
			</p>
		</form>
		<p class="description"><?php esc_html_e('Homepage CTA overrides (primary/secondary text, type) are in LeadsForward → Homepage Options.', 'leadsforward-core'); ?></p>
	</div>
	<?php
}
