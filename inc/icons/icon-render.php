<?php
/**
 * Icon renderer (Lucide sprite).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_icon_sprite_path(): string {
	return LF_THEME_DIR . '/assets/icons/sprite.svg';
}

function lf_icon_sprite_markup(): string {
	static $sprite = null;
	if ($sprite !== null) {
		return $sprite;
	}
	$path = lf_icon_sprite_path();
	$sprite = is_readable($path) ? (string) file_get_contents($path) : '';
	return $sprite;
}

function lf_icon_sprite(): void {
	static $rendered = false;
	if ($rendered) {
		return;
	}
	$rendered = true;
	$sprite = lf_icon_sprite_markup();
	if ($sprite === '') {
		return;
	}
	echo $sprite;
}

add_action('wp_footer', 'lf_icon_sprite', 1);
add_action('admin_footer', 'lf_icon_sprite', 1);

function lf_icon_exists(string $slug): bool {
	if (function_exists('lf_icon_list')) {
		return in_array($slug, lf_icon_list(), true);
	}
	return $slug !== '';
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
	$style_attr = $style !== '' ? ' style="' . esc_attr($style) . '"' : '';
	$title_markup = $title !== '' ? '<title>' . esc_html($title) . '</title>' : '';
	$symbol_id = 'lf-icon-' . $slug;

	return sprintf(
		'<svg class="%s" role="%s" aria-hidden="%s"%s%s focusable="false"><use href="#%s" xlink:href="#%s"></use>%s</svg>',
		esc_attr($class),
		esc_attr($role),
		esc_attr($aria_hidden),
		$aria_label !== '' ? ' aria-label="' . esc_attr($aria_label) . '"' : '',
		$style_attr,
		esc_attr($symbol_id),
		esc_attr($symbol_id),
		$title_markup
	);
}
