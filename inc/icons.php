<?php
/**
 * Icon system: Heroicons SVGs, inline rendering, and niche defaults.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_icon_seed(): string {
	if (function_exists('lf_internal_link_seed')) {
		return lf_internal_link_seed();
	}
	$seed = (string) get_option('lf_site_seed', '');
	if ($seed === '') {
		$seed = wp_generate_password(12, false, false);
		update_option('lf_site_seed', $seed);
	}
	return $seed;
}

function lf_icon_list(): array {
	$dir = LF_THEME_DIR . '/assets/icons';
	$files = glob($dir . '/*.svg');
	if (!$files) {
		return [];
	}
	$slugs = array_map(function ($file) {
		return basename((string) $file, '.svg');
	}, $files);
	$slugs = array_filter(array_map('sanitize_title', $slugs));
	$slugs = array_values(array_filter($slugs, function ($slug) {
		return strpos((string) $slug, 'social-') !== 0;
	}));
	sort($slugs);
	return array_values($slugs);
}

function lf_icon_options(): array {
	$options = [];
	foreach (lf_icon_list() as $slug) {
		$options[$slug] = ucwords(str_replace(['-', '_'], ' ', $slug));
	}
	return $options;
}

function lf_icon_niche_pool(string $niche_slug): array {
	switch ($niche_slug) {
		case 'roofing':
			return ['roof', 'shield', 'lightning', 'hammer'];
		case 'plumbing':
			return ['water-drop', 'wrench', 'pipe'];
		case 'hvac':
			return ['snowflake', 'flame', 'fan'];
		case 'landscaping':
			return ['leaf', 'tree', 'sun'];
		case 'general':
		default:
			return ['check', 'shield', 'star'];
	}
}

function lf_icon_default_for_section(string $section_id, string $niche_slug = ''): string {
	$pool = lf_icon_niche_pool($niche_slug);
	if (empty($pool)) {
		return '';
	}
	$seed = lf_icon_seed();
	$hash = crc32($seed . '|' . $niche_slug . '|' . $section_id);
	$index = (int) (abs($hash) % count($pool));
	return $pool[$index] ?? '';
}

function lf_icon_default_settings(string $section_id, string $niche_slug = ''): array {
	$enabled_sections = ['benefits'];
	if (!in_array($section_id, $enabled_sections, true)) {
		return [];
	}
	$position = 'list';
	return [
		'icon_enabled' => '1',
		'icon_slug' => '',
		'icon_position' => $position,
		'icon_size' => 'md',
		'icon_color' => 'primary',
	];
}

function lf_section_icon_data(array $settings, string $section_id): array {
	$enabled = !empty($settings['icon_enabled']) && (string) $settings['icon_enabled'] !== '0';
	if (!$enabled) {
		return ['enabled' => false];
	}
	$position = $settings['icon_position'] ?? 'left';
	if (!in_array($position, ['above', 'left', 'list'], true)) {
		$position = 'left';
	}
	$size = $settings['icon_size'] ?? 'md';
	if (!in_array($size, ['sm', 'md', 'lg'], true)) {
		$size = 'md';
	}
	$color = $settings['icon_color'] ?? 'primary';
	if (!in_array($color, ['inherit', 'primary', 'secondary', 'muted'], true)) {
		$color = 'primary';
	}
	$slug = sanitize_title((string) ($settings['icon_slug'] ?? ''));
	$available = lf_icon_list();
	if ($slug !== '' && !in_array($slug, $available, true)) {
		$slug = '';
	}
	if ($slug === '') {
		$niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
		$slug = lf_icon_default_for_section($section_id, $niche_slug);
	}
	if ($slug === '') {
		return ['enabled' => false];
	}
	return [
		'enabled' => true,
		'slug' => $slug,
		'position' => $position,
		'size' => $size,
		'color' => $color,
	];
}

function lf_section_icon_markup(array $settings, string $section_id, string $position, string $extra_class = ''): string {
	if (!function_exists('lf_icon')) {
		return '';
	}
	$data = lf_section_icon_data($settings, $section_id);
	if (empty($data['enabled']) || ($data['position'] ?? '') !== $position) {
		return '';
	}
	$classes = trim($extra_class . ' lf-icon--' . $data['size'] . ' lf-icon--' . $data['color']);
	return lf_icon($data['slug'], ['class' => $classes]);
}

function lf_icon(string $icon_slug, array $args = []): string {
	$slug = strtolower(trim($icon_slug));
	if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
		return '';
	}
	static $cache = [];
	if (!array_key_exists($slug, $cache)) {
		$path = LF_THEME_DIR . '/assets/icons/' . $slug . '.svg';
		$cache[$slug] = is_readable($path) ? file_get_contents($path) : '';
	}
	$svg = $cache[$slug];
	if (!is_string($svg) || $svg === '') {
		return '';
	}
	$class = 'lf-icon';
	if (!empty($args['class'])) {
		$class .= ' ' . trim((string) $args['class']);
	}
	$aria_label = isset($args['aria_label']) ? trim((string) $args['aria_label']) : '';
	$role = $aria_label !== '' ? 'img' : 'presentation';
	$aria_hidden = $aria_label === '' ? 'true' : 'false';
	$attributes = 'class="' . esc_attr($class) . '" role="' . esc_attr($role) . '" aria-hidden="' . esc_attr($aria_hidden) . '"';
	if ($aria_label !== '') {
		$attributes .= ' aria-label="' . esc_attr($aria_label) . '"';
	}
	$attributes .= ' focusable="false"';
	$svg = preg_replace('/<svg\b([^>]*)>/i', '<svg$1 ' . $attributes . '>', $svg, 1);
	return is_string($svg) ? $svg : '';
}
