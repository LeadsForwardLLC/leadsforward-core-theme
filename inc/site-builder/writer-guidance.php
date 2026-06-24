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
 * Build guidance copy for a section field.
 */
function lf_site_builder_guidance_for_field(
	string $section_type,
	string $field_key,
	string $field_type,
	string $field_label,
	string $section_label,
	string $page_context = ''
): string {
	$length_targets = function_exists('lf_ai_studio_section_length_targets')
		? lf_ai_studio_section_length_targets($section_type, $page_context)
		: [];
	$length_hint = '';
	if (!empty($length_targets)) {
		$parts = [];
		foreach ($length_targets as $key => $rule) {
			if (!is_array($rule)) {
				continue;
			}
			if (isset($rule['min'], $rule['max'])) {
				$parts[] = sprintf('%s: %d–%d', str_replace('_', ' ', (string) $key), (int) $rule['min'], (int) $rule['max']);
			} elseif (isset($rule['max'])) {
				$parts[] = sprintf('%s: max %d', str_replace('_', ' ', (string) $key), (int) $rule['max']);
			} elseif (isset($rule['min'])) {
				$parts[] = sprintf('%s: min %d', str_replace('_', ' ', (string) $key), (int) $rule['min']);
			}
		}
		if ($parts !== []) {
			$length_hint = implode('; ', $parts);
		}
	}

	$purpose = sprintf(
		/* translators: 1: section label, 2: field label */
		__('Write the %2$s for the %1$s section. Use real client voice — no fake reviews, guarantees, or filler.', 'leadsforward-core'),
		$section_label,
		$field_label !== '' ? $field_label : $field_key
	);

	$seo_bits = [];
	if (strpos($field_key, 'headline') !== false || strpos($field_key, 'heading') !== false) {
		$seo_bits[] = __('Place the primary keyword naturally in the headline when it fits.', 'leadsforward-core');
	}
	if (strpos($field_key, 'body') !== false || strpos($field_key, 'intro') !== false || strpos($field_key, 'content') !== false || $field_type === 'richtext') {
		$seo_bits[] = __('Use the primary keyword in the first paragraph when natural.', 'leadsforward-core');
		$seo_bits[] = __('Add at least one internal link to a related service or area page.', 'leadsforward-core');
	}
	if ($field_type === 'list') {
		$seo_bits[] = __('One idea per line; keep bullets scannable.', 'leadsforward-core');
	}
	if (strpos($field_key, 'cta') !== false) {
		$seo_bits[] = __('Action-oriented label; match the page intent.', 'leadsforward-core');
	}

	if ($field_type === 'list') {
		$lines = [
			sprintf('[Writer] %s — line 1', $field_label !== '' ? $field_label : $field_key),
			sprintf('[Writer] %s — line 2', $field_label !== '' ? $field_label : $field_key),
			sprintf('[Writer] %s — line 3', $field_label !== '' ? $field_label : $field_key),
		];
		return implode("\n", $lines);
	}

	if ($field_type === 'text') {
		return sprintf('[Writer] %s — %s', $section_label, $purpose);
	}

	if ($field_type === 'textarea') {
		$bits = [$purpose];
		if ($length_hint !== '') {
			$bits[] = sprintf(__('Length: %s', 'leadsforward-core'), $length_hint);
		}
		if ($seo_bits !== []) {
			$bits[] = implode(' ', $seo_bits);
		}
		return sprintf('[Writer] %s — %s', $section_label, implode(' ', $bits));
	}

	$seo_html = '';
	if ($seo_bits !== []) {
		$seo_html = '<p><strong>' . esc_html__('SEO', 'leadsforward-core') . ':</strong> ' . esc_html(implode(' ', $seo_bits)) . '</p>';
	}
	$length_html = $length_hint !== ''
		? '<p><strong>' . esc_html__('Length', 'leadsforward-core') . ':</strong> ' . esc_html($length_hint) . '</p>'
		: '';

	return wp_kses(
		'<div class="lf-writer-guidance">'
		. '<p><strong>' . esc_html(sprintf(__('Writer note — %s', 'leadsforward-core'), $section_label)) . '</strong></p>'
		. '<p>' . esc_html($purpose) . '</p>'
		. $length_html
		. $seo_html
		. '</div>',
		lf_site_builder_guidance_allowed_html()
	);
}

/**
 * Whether a field value should be replaced with writer guidance.
 */
function lf_site_builder_should_replace_field_value(string $text, string $field_type): bool {
	if ($field_type === 'image' || $field_type === 'select' || $field_type === 'url') {
		return false;
	}
	$text = trim(wp_strip_all_tags($text));
	if ($text === '') {
		return true;
	}
	if (function_exists('lf_ai_studio_is_generic_copy') && lf_ai_studio_is_generic_copy($text)) {
		return true;
	}
	if (strpos($text, 'lf-writer-guidance') !== false || strpos($text, '[Writer]') === 0) {
		return false;
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
 * Apply writer guidance to homepage section config.
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
	$section_label = (string) ($registry_entry['label'] ?? $section_type);
	$allowed = function_exists('lf_ai_studio_homepage_allowed_field_keys')
		? lf_ai_studio_homepage_allowed_field_keys($section_type, $registry_entry)
		: array_keys($settings);
	$filled = 0;
	foreach ($allowed as $field_key) {
		if (!is_string($field_key) || $field_key === '') {
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
		if (!lf_site_builder_should_replace_field_value($text, $field_type)) {
			continue;
		}
		$field_label = lf_site_builder_field_label($registry_entry, $field_key);
		$guidance = lf_site_builder_guidance_for_field($section_type, $field_key, $field_type, $field_label, $section_label, $page_context);
		if ($guidance === '') {
			continue;
		}
		$settings[$field_key] = function_exists('lf_sections_sanitize_settings')
			? lf_sections_sanitize_settings($section_type, [$field_key => $guidance])[$field_key] ?? $guidance
			: $guidance;
		$filled++;
	}
	return [$settings, $filled];
}

/**
 * Fill writer guidance across homepage + page builder sections.
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
