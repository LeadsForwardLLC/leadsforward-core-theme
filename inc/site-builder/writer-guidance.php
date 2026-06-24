<?php
/**
 * Writer guidance placeholders for Site Builder template path.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Allowed HTML for writer guidance callouts.
 *
 * @return array<string, array<string, bool>>
 */
function lf_site_builder_guidance_allowed_html(): array {
	return [
		'div' => ['class' => true],
		'p' => ['class' => true],
		'strong' => [],
		'em' => [],
		'ul' => ['class' => true],
		'li' => [],
	];
}

/**
 * Whether stored copy is internal writer scaffolding (not for public UI).
 */
function lf_site_builder_is_writer_placeholder(string $text): bool {
	$text = trim(wp_strip_all_tags($text));
	if ($text === '') {
		return false;
	}
	if (strpos($text, '[Writer]') === 0 || stripos($text, '[writer]') === 0) {
		return true;
	}
	if (strpos($text, 'lf-writer-guidance') !== false) {
		return true;
	}
	if (preg_match('/^Write the .+ for the .+ section\./i', $text) === 1) {
		return true;
	}
	if (preg_match('/—\s*line\s*\d+/iu', $text) === 1) {
		return true;
	}
	return false;
}

/**
 * Return safe public copy, replacing writer scaffolding with a fallback.
 */
function lf_site_builder_public_text(string $text, string $fallback = ''): string {
	$text = trim(wp_strip_all_tags($text));
	if ($text === '' || lf_site_builder_is_writer_placeholder($text)) {
		return $fallback;
	}
	return $text;
}

/**
 * Structural fields should never receive writer placeholder copy.
 */
function lf_site_builder_is_structural_field(string $field_key): bool {
	if ($field_key === '') {
		return true;
	}
	if (preg_match('/_(ids|id|url|action|style|tone|enabled|layout|columns|max_items|show_images|position|tag|type)$/i', $field_key) === 1) {
		return true;
	}
	return in_array(
		$field_key,
		[
			'service_intro_service_ids',
			'logo_strip_logos',
			'process_selected_ids',
			'benefits_selected_ids',
			'hero_background_mode',
			'hero_background_image_id',
			'hero_background_video_id',
			'hero_trust_strip_enabled',
			'cta_primary_enabled',
			'cta_secondary_enabled',
			'image_id',
			'section_background',
			'section_background_custom',
			'section_header_align',
			'section_actions_align',
			'icon_enabled',
			'icon_slug',
			'icon_position',
			'icon_size',
			'icon_color',
			'service_details_micro_sections',
			'service_details_proof_badges',
			'service_details_proof_label',
			'section_intent',
			'section_purpose',
		],
		true
	);
}

/**
 * Example customer-facing copy for empty fields (never writer instructions).
 */
function lf_site_builder_example_for_field(
	string $section_type,
	string $field_key,
	string $field_type,
	array $registry_entry
): string {
	if (lf_site_builder_is_structural_field($field_key)) {
		return '';
	}

	foreach ($registry_entry['fields'] ?? [] as $field) {
		if (!is_array($field) || ($field['key'] ?? '') !== $field_key) {
			continue;
		}
		$default = trim((string) ($field['default'] ?? ''));
		if ($default !== '' && !lf_site_builder_is_writer_placeholder($default)) {
			return $default;
		}
		break;
	}

	if (strpos($field_key, 'cta') !== false) {
		if (strpos($field_key, 'secondary') !== false) {
			return __('Call Now', 'leadsforward-core');
		}
		if ($field_key === 'cta_headline') {
			return __('Ready to get started?', 'leadsforward-core');
		}
		if (strpos($field_key, 'subheadline') !== false || $field_key === 'cta_subheadline') {
			return __('Get a fast response with clear pricing and next steps.', 'leadsforward-core');
		}
		if (strpos($field_key, 'url') !== false || strpos($field_key, 'action') !== false) {
			return '';
		}
		return __('Get a Free Estimate', 'leadsforward-core');
	}

	if ($field_key === 'hero_chip_bullets') {
		return implode(
			"\n",
			[
				__('Licensed & Insured', 'leadsforward-core'),
				__('5-Star Rated', 'leadsforward-core'),
				__('Fast Response', 'leadsforward-core'),
			]
		);
	}

	if ($field_key === 'hero_proof_bullets') {
		return implode(
			"\n",
			[
				__('Fast response and clear pricing', 'leadsforward-core'),
				__('Licensed, insured, and local', 'leadsforward-core'),
				__('Quality work backed by warranty', 'leadsforward-core'),
			]
		);
	}

	if ($field_key === 'trust_badges' || $field_key === 'cta_bullets' || $field_key === 'cta_trust_badges') {
		return implode(
			"\n",
			[
				__('Licensed & Insured', 'leadsforward-core'),
				__('Free Estimates', 'leadsforward-core'),
				__('Local Team', 'leadsforward-core'),
			]
		);
	}

	if ($field_type === 'list') {
		return '';
	}

	if (strpos($field_key, 'headline') !== false || strpos($field_key, 'heading') !== false) {
		return __('Quality local service you can trust', 'leadsforward-core');
	}

	if (strpos($field_key, 'intro') !== false || strpos($field_key, 'subheadline') !== false) {
		return __('Clear communication, reliable scheduling, and workmanship you can count on.', 'leadsforward-core');
	}

	if ($field_type === 'textarea' || $field_type === 'richtext') {
		return __('We help homeowners solve problems quickly with upfront expectations and professional results.', 'leadsforward-core');
	}

	return __('Learn more', 'leadsforward-core');
}

/**
 * Build example copy for a section field (public-facing only).
 */
function lf_site_builder_guidance_for_field(
	string $section_type,
	string $field_key,
	string $field_type,
	string $field_label,
	string $section_label,
	string $page_context = ''
): string {
	unset($field_label, $section_label, $page_context, $section_type);
	return lf_site_builder_example_for_field($section_type, $field_key, $field_type, ['fields' => []]);
}

/**
 * Whether a field value should be replaced with example copy.
 */
function lf_site_builder_should_replace_field_value(string $text, string $field_type, string $field_key = ''): bool {
	if ($field_type === 'image' || $field_type === 'select' || $field_type === 'url' || $field_type === 'number') {
		return false;
	}
	if ($field_key !== '' && lf_site_builder_is_structural_field($field_key)) {
		return false;
	}
	$text = trim(wp_strip_all_tags($text));
	if ($text === '') {
		return true;
	}
	if (function_exists('lf_ai_studio_is_generic_copy') && lf_ai_studio_is_generic_copy($text)) {
		return true;
	}
	if (lf_site_builder_is_writer_placeholder($text)) {
		return true;
	}
	return false;
}

/**
 * Field label from section registry.
 */
function lf_site_builder_field_label(array $registry_entry, string $field_key): string {
	foreach ($registry_entry['fields'] ?? [] as $field) {
		if (($field['key'] ?? '') === $field_key) {
			return (string) ($field['label'] ?? $field_key);
		}
	}
	return $field_key;
}

/**
 * Apply example copy to homepage section config.
 *
 * @param array<string, mixed> $settings
 * @return array{0: array<string, mixed>, 1: int}
 */
function lf_site_builder_apply_guidance_to_settings(
	array $settings,
	string $section_id,
	array $registry,
	string $page_context = 'homepage'
): array {
	$registry_entry = $registry[$section_id] ?? [];
	if (!is_array($registry_entry)) {
		$base_type = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type($section_id) : $section_id;
		$registry_entry = $registry[$base_type] ?? [];
		$section_type = $base_type !== '' ? $base_type : $section_id;
	} else {
		$section_type = $section_id;
	}
	if (!is_array($registry_entry) || $registry_entry === []) {
		return [$settings, 0];
	}
	$allowed = function_exists('lf_ai_studio_homepage_allowed_field_keys')
		? lf_ai_studio_homepage_allowed_field_keys($section_type, $registry_entry)
		: array_keys($settings);
	$filled = 0;
	foreach ($allowed as $field_key) {
		if (!is_string($field_key) || $field_key === '') {
			continue;
		}
		if (function_exists('lf_sections_is_hidden_non_editable_field') && lf_sections_is_hidden_non_editable_field($field_key)) {
			continue;
		}
		$value = $settings[$field_key] ?? '';
		if (is_array($value)) {
			continue;
		}
		$field_type = function_exists('lf_ai_studio_registry_field_type')
			? lf_ai_studio_registry_field_type($registry, $section_type, $field_key)
			: 'text';
		if ($field_type === '' && isset($registry[$section_id])) {
			$field_type = lf_ai_studio_registry_field_type($registry, $section_id, $field_key);
		}
		$text = is_scalar($value) ? (string) $value : '';
		if (!lf_site_builder_should_replace_field_value($text, $field_type, $field_key)) {
			continue;
		}
		$example = lf_site_builder_example_for_field($section_type, $field_key, $field_type, $registry_entry);
		if ($example === '') {
			continue;
		}
		$settings[$field_key] = function_exists('lf_sections_sanitize_settings')
			? lf_sections_sanitize_settings($section_type, [$field_key => $example])[$field_key] ?? $example
			: $example;
		$filled++;
	}
	unset($page_context);
	return [$settings, $filled];
}

/**
 * Replace any remaining [Writer] scaffolding across homepage + page builder sections.
 */
function lf_site_builder_strip_writer_placeholders(): array {
	$stats = ['homepage_fields' => 0, 'post_fields' => 0, 'posts_updated' => 0];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if ($registry === []) {
		return $stats;
	}

	$scrub_settings = static function (array $settings, string $section_type, array $registry_entry) use (&$stats, $registry): array {
		$changed = 0;
		if (function_exists('lf_sections_purge_hidden_non_editable_fields_from_settings')) {
			$before = wp_json_encode($settings);
			$settings = lf_sections_purge_hidden_non_editable_fields_from_settings($settings);
			if ($before !== wp_json_encode($settings)) {
				$changed++;
			}
		}
		foreach ($settings as $field_key => $value) {
			if (!is_string($field_key) || is_array($value)) {
				continue;
			}
			$field_type = function_exists('lf_ai_studio_registry_field_type')
				? lf_ai_studio_registry_field_type($registry, $section_type, $field_key)
				: 'text';
			if ($field_type === 'list' && function_exists('lf_sections_scrub_writer_list_lines')) {
				$scrubbed = lf_sections_scrub_writer_list_lines((string) $value);
				if ($scrubbed !== (string) $value) {
					$settings[$field_key] = $scrubbed;
					$changed++;
				}
				continue;
			}
			$text = (string) $value;
			if (!lf_site_builder_is_writer_placeholder($text)) {
				continue;
			}
			if (lf_site_builder_is_structural_field($field_key)) {
				$settings[$field_key] = '';
			} else {
				$example = lf_site_builder_example_for_field($section_type, $field_key, $field_type, $registry_entry);
				$settings[$field_key] = $example;
			}
			$changed++;
		}
		return [$settings, $changed];
	};

	if (function_exists('lf_get_homepage_section_config') && defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		$home_config = lf_get_homepage_section_config();
		if (is_array($home_config) && $home_config !== []) {
			$home_changed = false;
			foreach ($home_config as $section_id => $settings) {
				if (!is_array($settings)) {
					continue;
				}
				$base = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type((string) $section_id) : (string) $section_id;
				$registry_entry = $registry[$base] ?? $registry[(string) $section_id] ?? [];
				[$next, $n] = $scrub_settings($settings, (string) $base, is_array($registry_entry) ? $registry_entry : []);
				if ($n > 0) {
					$home_config[$section_id] = $next;
					$stats['homepage_fields'] += $n;
					$home_changed = true;
				}
			}
			if ($home_changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $home_config, true);
			}
		}
	}

	if (!function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config') || !defined('LF_PB_META_KEY')) {
		return $stats;
	}

	$post_ids = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach (array_map('intval', $post_ids) as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$context = lf_pb_get_context_for_post($post);
		if ($context === '') {
			continue;
		}
		$config = lf_pb_get_post_config($post_id, $context);
		$order = is_array($config['order'] ?? null) ? $config['order'] : [];
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		if ($order === [] || $sections === []) {
			continue;
		}
		$changed = false;
		foreach ($order as $instance_id) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section) || empty($section['enabled'])) {
				continue;
			}
			$type = (string) ($section['type'] ?? '');
			$registry_entry = ($type !== '' && isset($registry[$type])) ? $registry[$type] : [];
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			[$next, $n] = $scrub_settings($settings, $type, is_array($registry_entry) ? $registry_entry : []);
			if ($n > 0) {
				$sections[$instance_id]['settings'] = function_exists('lf_sections_sanitize_settings')
					? lf_sections_sanitize_settings($type, $next)
					: $next;
				$stats['post_fields'] += $n;
				$changed = true;
			}
		}
		if ($changed) {
			update_post_meta($post_id, LF_PB_META_KEY, [
				'order' => $order,
				'sections' => $sections,
				'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
			]);
			$stats['posts_updated']++;
		}
	}

	return $stats;
}

/**
 * Fill example copy across homepage + page builder sections.
 *
 * @return array{homepage_sections: int, post_sections: int, posts_updated: int}
 */
function lf_site_builder_fill_writer_guidance(): array {
	$stats = ['homepage_sections' => 0, 'post_sections' => 0, 'posts_updated' => 0];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if ($registry === []) {
		return $stats;
	}

	if (function_exists('lf_get_homepage_section_config') && defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		$home_config = lf_get_homepage_section_config();
		if (is_array($home_config) && $home_config !== []) {
			$home_changed = false;
			foreach ($home_config as $section_id => $settings) {
				if (!is_array($settings)) {
					continue;
				}
				[$filled_settings, $count] = lf_site_builder_apply_guidance_to_settings($settings, (string) $section_id, $registry, 'homepage');
				if ($count > 0) {
					$home_config[$section_id] = $filled_settings;
					$stats['homepage_sections'] += $count;
					$home_changed = true;
				}
			}
			if ($home_changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $home_config, true);
			}
		}
	}

	if (!function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config') || !defined('LF_PB_META_KEY')) {
		return $stats;
	}

	$post_ids = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach (array_map('intval', $post_ids) as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$context = lf_pb_get_context_for_post($post);
		if ($context === '') {
			continue;
		}
		$config = lf_pb_get_post_config($post_id, $context);
		$order = is_array($config['order'] ?? null) ? $config['order'] : [];
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		if ($order === [] || $sections === []) {
			continue;
		}
		$changed = false;
		foreach ($order as $instance_id) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section) || empty($section['enabled'])) {
				continue;
			}
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			$type = (string) ($section['type'] ?? '');
			$registry_entry = ($type !== '' && isset($registry[$type])) ? $registry[$type] : [];
			if (!is_array($registry_entry)) {
				continue;
			}
			[$filled_settings, $count] = lf_site_builder_apply_guidance_to_settings($settings, $type, $registry, $context);
			if ($count > 0) {
				$sections[$instance_id]['settings'] = function_exists('lf_sections_sanitize_settings')
					? lf_sections_sanitize_settings($type, $filled_settings)
					: $filled_settings;
				$stats['post_sections'] += $count;
				$changed = true;
			}
		}
		if ($changed) {
			update_post_meta($post_id, LF_PB_META_KEY, [
				'order' => $order,
				'sections' => $sections,
				'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
			]);
			$stats['posts_updated']++;
		}
	}

	return $stats;
}
