<?php
/**
 * Icon renderer (Tabler SVG files).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_icon_tabler_dir(): string {
	return LF_THEME_DIR . '/assets/icons/tabler/outline';
}

function lf_icon_tabler_path(string $slug): string {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return '';
	}
	return rtrim(lf_icon_tabler_dir(), '/') . '/' . $slug . '.svg';
}

function lf_icon_exists(string $slug): bool {
	$slug = sanitize_title((string) $slug);
	if ($slug === '') {
		return false;
	}
	if (function_exists('lf_icon_list') && !in_array($slug, lf_icon_list(), true)) {
		return false;
	}
	$path = lf_icon_tabler_path($slug);
	return $path !== '' && is_readable($path);
}

function lf_icon(string $name, array $args = []): string {
	$slug = sanitize_title((string) $name);
	if ($slug === '') {
		return '';
	}
	if (function_exists('lf_icon_normalize_slug')) {
		$slug = lf_icon_normalize_slug($slug);
	}
	if (!lf_icon_exists($slug)) {
		return '';
	}

	static $cache = [];
	if (isset($cache[$slug]) && is_string($cache[$slug])) {
		$svg_raw = $cache[$slug];
	} else {
		$path = lf_icon_tabler_path($slug);
		$svg_raw = ($path !== '' && is_readable($path)) ? (string) file_get_contents($path) : '';
		$cache[$slug] = $svg_raw;
	}
	if ($svg_raw === '') {
		return '';
	}
	$class = 'lf-icon';
	if (!empty($args['class'])) {
		$class .= ' ' . trim((string) $args['class']);
	}
	$aria_label = isset($args['aria_label']) ? trim((string) $args['aria_label']) : '';
	$role = $aria_label !== '' ? 'img' : 'presentation';
	$aria_hidden = $aria_label === '' ? 'true' : 'false';
	$title = isset($args['title']) ? trim((string) $args['title']) : '';
	$style = isset($args['style']) ? trim((string) $args['style']) : '';
	$stroke_width = isset($args['stroke_width']) ? (float) $args['stroke_width'] : 0.0;
	if ($stroke_width > 0) {
		$style = trim('--lf-icon-stroke:' . $stroke_width . '; ' . $style);
	}

	// Inject class/aria while keeping Tabler paths intact. Remove width/height so CSS can size via em.
	$attrs = 'class="' . esc_attr($class) . '" role="' . esc_attr($role) . '" aria-hidden="' . esc_attr($aria_hidden) . '" focusable="false"';
	if ($aria_label !== '') {
		$attrs .= ' aria-label="' . esc_attr($aria_label) . '"';
	}
	if ($style !== '') {
		$attrs .= ' style="' . esc_attr($style) . '"';
	}
	$title_markup = $title !== '' ? '<title>' . esc_html($title) . '</title>' : '';

	$svg = preg_replace('/<svg\b[^>]*>/i', '<svg ' . $attrs . '>', $svg_raw, 1);
	if (!is_string($svg) || $svg === '') {
		return '';
	}
	$svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg);
	if ($title_markup !== '') {
		$svg = preg_replace('/<svg\b([^>]*)>/i', '<svg$1>' . $title_markup, $svg, 1);
	}
	return $svg;
}
