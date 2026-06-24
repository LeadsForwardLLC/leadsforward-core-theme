<?php
/**
 * Service intro / grid card wiring after manifest scaffold.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return list<int>
 */
function lf_site_builder_manifest_service_post_ids(): array {
	$statuses = function_exists('lf_cpt_card_query_post_statuses')
		? lf_cpt_card_query_post_statuses()
		: ['publish', 'future', 'draft', 'pending', 'private'];
	$ids = get_posts([
		'post_type' => 'lf_service',
		'post_status' => $statuses,
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	if (!is_array($ids)) {
		return [];
	}
	$out = [];
	foreach ($ids as $id) {
		$id = (int) $id;
		if ($id > 0) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * Populate service_intro_service_ids on homepage + services overview so cards render and link correctly.
 */
function lf_site_builder_sync_service_card_sections(): int {
	$service_ids = lf_site_builder_manifest_service_post_ids();
	if ($service_ids === []) {
		return 0;
	}
	$id_lines = implode("\n", array_map('strval', $service_ids));
	$count = count($service_ids);
	$max_items = (string) min(24, max(6, $count));
	$touched = 0;

	if (defined('LF_HOMEPAGE_CONFIG_OPTION') && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		if (is_array($config) && $config !== []) {
			$changed = false;
			foreach ($config as $section_id => $settings) {
				if (!is_array($settings)) {
					continue;
				}
				$base = function_exists('lf_homepage_base_section_type')
					? lf_homepage_base_section_type((string) $section_id)
					: (string) $section_id;
				if ($base !== 'service_intro') {
					continue;
				}
				$config[$section_id]['service_intro_service_ids'] = $id_lines;
				$config[$section_id]['service_intro_max_items'] = $max_items;
				$changed = true;
				++$touched;
			}
			if ($changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
		}
	}

	if (!defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config') || !function_exists('lf_pb_get_context_for_post')) {
		return $touched;
	}

	$services_page = get_page_by_path('services');
	if (!$services_page instanceof \WP_Post) {
		$services_page = get_page_by_path('our-services');
	}
	if (!$services_page instanceof \WP_Post) {
		return $touched;
	}

	$context = lf_pb_get_context_for_post($services_page);
	if ($context === '') {
		$context = 'services';
	}
	$config = lf_pb_get_post_config((int) $services_page->ID, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if ($order === [] || $sections === []) {
		return $touched;
	}

	$pb_changed = false;
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		if ((string) ($section['type'] ?? '') !== 'service_intro') {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$settings['service_intro_service_ids'] = $id_lines;
		$settings['service_intro_max_items'] = $max_items;
		$sections[$instance_id]['settings'] = function_exists('lf_sections_sanitize_settings')
			? lf_sections_sanitize_settings('service_intro', $settings)
			: $settings;
		$pb_changed = true;
		++$touched;
	}

	if ($pb_changed) {
		update_post_meta((int) $services_page->ID, LF_PB_META_KEY, [
			'order' => $order,
			'sections' => $sections,
			'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
		]);
	}

	return $touched;
}
