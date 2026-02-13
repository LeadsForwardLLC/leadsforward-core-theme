<?php
/**
 * Icon system: Lucide sprite, packs, and section defaults.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

require_once LF_THEME_DIR . '/inc/icons/icon-render.php';
require_once LF_THEME_DIR . '/inc/icons/icon-packs.php';

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

function lf_icon_aliases(): array {
	return [
		'lightning' => 'zap',
		'water-drop' => 'droplet',
		'roof' => 'home',
		'social-facebook' => 'facebook',
		'social-instagram' => 'instagram',
		'social-youtube' => 'youtube',
		'social-linkedin' => 'linkedin',
		'social-tiktok' => 'video',
		'social-x' => 'twitter',
	];
}

function lf_icon_extra_icons(): array {
	return ['facebook', 'instagram', 'youtube', 'linkedin', 'twitter', 'video', 'mail'];
}

function lf_icon_normalize_slug(string $slug): string {
	$slug = sanitize_title($slug);
	$aliases = lf_icon_aliases();
	if (isset($aliases[$slug])) {
		return $aliases[$slug];
	}
	return $slug;
}

function lf_icon_list(): array {
	$slugs = function_exists('lf_icon_pack_all_icons') ? lf_icon_pack_all_icons() : [];
	$slugs = array_merge($slugs, lf_icon_extra_icons());
	$slugs = array_filter(array_map('sanitize_title', $slugs));
	$slugs = array_values(array_filter($slugs, function ($slug) {
		return strpos((string) $slug, 'social-') !== 0;
	}));
	$slugs = array_values(array_unique($slugs));
	sort($slugs);
	return $slugs;
}

function lf_icon_options(): array {
	$options = [];
	foreach (lf_icon_list() as $slug) {
		$options[$slug] = ucwords(str_replace(['-', '_'], ' ', $slug));
	}
	return $options;
}

function lf_icon_niche_pool(string $niche_slug): array {
	$pack = function_exists('lf_icon_pack_slug_for_niche') ? lf_icon_pack_slug_for_niche($niche_slug) : 'general';
	return function_exists('lf_icon_pack_pool') ? lf_icon_pack_pool($pack) : [];
}

function lf_icon_keyword_map(): array {
	return [
		'fast' => 'zap',
		'quick' => 'zap',
		'response' => 'clock',
		'schedule' => 'calendar',
		'appointment' => 'calendar',
		'licensed' => 'shield',
		'insured' => 'shield',
		'warranty' => 'shield',
		'safe' => 'shield',
		'price' => 'check',
		'pricing' => 'check',
		'upfront' => 'check',
		'transparent' => 'check',
		'quality' => 'star',
		'craft' => 'hammer',
		'expertise' => 'hammer',
		'management' => 'check',
		'local' => 'map-pin',
		'nearby' => 'map-pin',
		'home' => 'home',
		'communication' => 'phone',
		'support' => 'phone',
		'repair' => 'wrench',
		'maintenance' => 'wrench',
		'water' => 'droplet',
		'leak' => 'pipe',
		'plumbing' => 'pipe',
		'roof' => 'home',
		'cool' => 'snowflake',
		'heat' => 'flame',
		'air' => 'fan',
		'landscape' => 'leaf',
		'tree' => 'tree',
		'outdoor' => 'sun',
		'electric' => 'zap',
		'power' => 'plug',
		'light' => 'lightbulb',
	];
}

function lf_icon_slug_for_text(string $text, array $fallback_pool = []): string {
	$text = strtolower(trim($text));
	if ($text === '') {
		return '';
	}
	$available = lf_icon_list();
	foreach (lf_icon_keyword_map() as $keyword => $slug) {
		$slug = lf_icon_normalize_slug($slug);
		if (strpos($text, $keyword) !== false && in_array($slug, $available, true)) {
			return $slug;
		}
	}
	$fallback_pool = array_values(array_filter(array_unique($fallback_pool)));
	if (empty($fallback_pool)) {
		return '';
	}
	foreach ($fallback_pool as $slug) {
		$slug = lf_icon_normalize_slug($slug);
		if (in_array($slug, $available, true)) {
			return $slug;
		}
	}
	return '';
}

function lf_icon_default_for_section(string $section_id, string $niche_slug = ''): string {
	$pack = $niche_slug !== '' && function_exists('lf_icon_pack_slug_for_niche')
		? lf_icon_pack_slug_for_niche($niche_slug)
		: (function_exists('lf_icon_active_pack') ? lf_icon_active_pack() : 'general');
	$icons = function_exists('lf_icon_pack_section_icons') ? lf_icon_pack_section_icons($section_id, $pack) : [];
	$pool = function_exists('lf_icon_pack_pool') ? lf_icon_pack_pool($pack) : [];
	if (!empty($icons)) {
		$pool = $icons;
	}
	if (empty($pool)) {
		return '';
	}
	$seed = lf_icon_seed();
	$hash = crc32($seed . '|' . $pack . '|' . $section_id);
	$index = (int) (abs($hash) % count($pool));
	$slug = $pool[$index] ?? '';
	$slug = lf_icon_normalize_slug($slug);
	return in_array($slug, lf_icon_list(), true) ? $slug : '';
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
	$slug = lf_icon_normalize_slug($slug);
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
