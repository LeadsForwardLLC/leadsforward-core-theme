<?php
/**
 * Icon pack registry and pack resolution.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_icon_pack_registry(): array {
	return [
		'general' => [
			'label' => __('General', 'leadsforward-core'),
			'icons' => ['check', 'shield', 'star', 'clock', 'map-pin', 'home', 'calendar', 'phone', 'leaf', 'tree', 'sun'],
			'sections' => [
				'hero' => 'home',
				'trust_bar' => 'star',
				'trust_reviews' => 'star',
				'benefits' => ['check', 'shield', 'star'],
				'cta' => 'check',
				'map_nap' => 'map-pin',
			],
		],
		'remodeling' => [
			'label' => __('Remodeling', 'leadsforward-core'),
			'icons' => ['hammer', 'ruler', 'paintbrush', 'home', 'check', 'star'],
			'sections' => [
				'hero' => 'home',
				'trust_bar' => 'star',
				'trust_reviews' => 'star',
				'benefits' => ['hammer', 'ruler', 'paintbrush'],
				'cta' => 'check',
			],
		],
		'roofing' => [
			'label' => __('Roofing', 'leadsforward-core'),
			'icons' => ['home', 'shield', 'cloud-rain', 'wind', 'check', 'star'],
			'sections' => [
				'hero' => 'home',
				'trust_bar' => 'star',
				'trust_reviews' => 'star',
				'benefits' => ['shield', 'cloud-rain', 'wind'],
				'cta' => 'check',
			],
		],
		'hvac' => [
			'label' => __('HVAC', 'leadsforward-core'),
			'icons' => ['snowflake', 'flame', 'fan', 'thermometer', 'wind', 'shield'],
			'sections' => [
				'hero' => 'snowflake',
				'trust_bar' => 'shield',
				'trust_reviews' => 'shield',
				'benefits' => ['snowflake', 'flame', 'fan'],
				'cta' => 'shield',
			],
		],
		'plumbing' => [
			'label' => __('Plumbing', 'leadsforward-core'),
			'icons' => ['droplet', 'wrench', 'pipe', 'shield', 'check', 'clock'],
			'sections' => [
				'hero' => 'droplet',
				'trust_bar' => 'shield',
				'trust_reviews' => 'shield',
				'benefits' => ['droplet', 'wrench', 'pipe'],
				'cta' => 'check',
			],
		],
		'electrical' => [
			'label' => __('Electrical', 'leadsforward-core'),
			'icons' => ['zap', 'plug', 'battery', 'lightbulb', 'shield', 'check'],
			'sections' => [
				'hero' => 'zap',
				'trust_bar' => 'shield',
				'trust_reviews' => 'shield',
				'benefits' => ['zap', 'plug', 'lightbulb'],
				'cta' => 'check',
			],
		],
	];
}

function lf_icon_pack_slug_for_niche(string $niche_slug): string {
	$slug = sanitize_title($niche_slug);
	if ($slug === '') {
		return 'general';
	}
	if (in_array($slug, ['remodeling', 'kitchen-remodeling', 'bathroom-remodeling', 'basement-remodeling', 'home-builder'], true)) {
		return 'remodeling';
	}
	if (strpos($slug, 'remodel') !== false || strpos($slug, 'builder') !== false || strpos($slug, 'construction') !== false) {
		return 'remodeling';
	}
	if ($slug === 'roofing' || strpos($slug, 'roof') !== false) {
		return 'roofing';
	}
	if ($slug === 'hvac' || strpos($slug, 'hvac') !== false || strpos($slug, 'heating') !== false || strpos($slug, 'cool') !== false) {
		return 'hvac';
	}
	if ($slug === 'plumbing' || strpos($slug, 'plumb') !== false) {
		return 'plumbing';
	}
	if ($slug === 'electrical' || $slug === 'electrician' || strpos($slug, 'electric') !== false) {
		return 'electrical';
	}
	return 'general';
}

function lf_icon_active_pack(): string {
	$registry = lf_icon_pack_registry();
	$stored = (string) get_option('lf_active_icon_pack', '');
	$normalized = lf_icon_pack_slug_for_niche($stored);
	if (isset($registry[$stored])) {
		return $stored;
	}
	if (isset($registry[$normalized])) {
		return $normalized;
	}
	$fallback = lf_icon_pack_slug_for_niche((string) get_option('lf_homepage_niche_slug', 'general'));
	return isset($registry[$fallback]) ? $fallback : 'general';
}

function lf_icon_pack_pool(string $pack): array {
	$registry = lf_icon_pack_registry();
	$entry = $registry[$pack] ?? null;
	if (!is_array($entry)) {
		return [];
	}
	$icons = is_array($entry['icons'] ?? null) ? $entry['icons'] : [];
	return array_values(array_filter(array_unique($icons)));
}

function lf_icon_pack_section_icons(string $section_id, string $pack): array {
	$registry = lf_icon_pack_registry();
	$entry = $registry[$pack] ?? null;
	if (!is_array($entry)) {
		return [];
	}
	$sections = is_array($entry['sections'] ?? null) ? $entry['sections'] : [];
	if (!array_key_exists($section_id, $sections)) {
		return [];
	}
	$icons = $sections[$section_id];
	if (is_string($icons)) {
		return [$icons];
	}
	if (is_array($icons)) {
		return array_values(array_filter(array_unique($icons)));
	}
	return [];
}

function lf_icon_pack_default_for_section(string $section_id, string $pack): string {
	$icons = lf_icon_pack_section_icons($section_id, $pack);
	if (empty($icons)) {
		return '';
	}
	return $icons[0];
}

function lf_icon_pack_all_icons(): array {
	$registry = lf_icon_pack_registry();
	$icons = [];
	foreach ($registry as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$pack_icons = is_array($entry['icons'] ?? null) ? $entry['icons'] : [];
		$icons = array_merge($icons, $pack_icons);
		$sections = is_array($entry['sections'] ?? null) ? $entry['sections'] : [];
		foreach ($sections as $section_icons) {
			if (is_string($section_icons)) {
				$icons[] = $section_icons;
			} elseif (is_array($section_icons)) {
				$icons = array_merge($icons, $section_icons);
			}
		}
	}
	return array_values(array_filter(array_unique($icons)));
}
