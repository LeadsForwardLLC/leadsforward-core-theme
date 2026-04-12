<?php
/**
 * Section picker previews: layout schematics aligned to real block structures (not generic wireframes).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * SVG thumbnail for the add-section / Structure library (fixed viewBox for consistent hover UI).
 */
function lf_ai_section_library_preview_svg(string $section_id): string {
	$w = 200;
	$h = 120;
	$bg = '#f1f5f9';
	$stroke = '#e2e8f0';
	$ink = '#1e293b';
	$muted = '#94a3b8';
	$light = '#cbd5e1';
	$brand = '#2563eb';
	$brand2 = '#0ea5e9';

	$wrap = static function (string $inner) use ($w, $h, $bg, $stroke): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $w . '" height="' . (int) $h . '" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" fill="none" aria-hidden="true">' .
			'<rect width="100%" height="100%" rx="10" fill="' . $bg . '" stroke="' . $stroke . '"/>' .
			$inner .
			'</svg>';
	};

	$base = $section_id;
	if (preg_match('/^(.+)__\d+$/', $section_id, $m)) {
		$base = (string) $m[1];
	}

	switch ($base) {
		case 'hero':
			return $wrap(
				'<rect x="0" y="0" width="200" height="120" fill="#0f172a" opacity="0.88" rx="10"/>' .
				'<rect x="48" y="22" width="104" height="10" rx="3" fill="#f8fafc"/>' .
				'<rect x="32" y="40" width="136" height="5" rx="2" fill="#cbd5e1"/>' .
				'<rect x="40" y="50" width="120" height="5" rx="2" fill="#94a3b8"/>' .
				'<rect x="44" y="72" width="52" height="16" rx="8" fill="' . $brand . '"/>' .
				'<rect x="104" y="72" width="52" height="16" rx="8" stroke="#f8fafc" stroke-width="1.5" fill="none"/>'
			);

		case 'trust_bar':
			return $wrap(
				'<rect x="12" y="44" width="176" height="32" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<circle cx="32" cy="60" r="8" fill="' . $light . '"/>' .
				'<rect x="48" y="54" width="44" height="6" rx="2" fill="' . $muted . '"/>' .
				'<rect x="100" y="56" width="36" height="8" rx="2" fill="' . $brand2 . '" opacity="0.35"/>' .
				'<rect x="144" y="54" width="32" height="10" rx="3" fill="' . $light . '"/>'
			);

		case 'benefits':
			return $wrap(
				'<rect x="50" y="14" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="40" y="28" width="120" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="14" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="26" y="56" width="28" height="22" rx="6" fill="' . $brand2 . '" opacity="0.25"/>' .
				'<rect x="22" y="84" width="36" height="5" rx="2" fill="' . $ink . '"/>' .
				'<rect x="74" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="86" y="56" width="28" height="22" rx="6" fill="#22c55e" opacity="0.22"/>' .
				'<rect x="134" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="146" y="56" width="28" height="22" rx="6" fill="#f97316" opacity="0.22"/>'
			);

		case 'service_details':
			return $wrap(
				'<rect x="12" y="14" width="72" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="12" y="28" width="88" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="12" y="38" width="80" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="12" y="52" width="8" height="8" rx="2" fill="' . $brand . '"/>' .
				'<rect x="24" y="54" width="64" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="12" y="66" width="8" height="8" rx="2" fill="' . $brand . '"/>' .
				'<rect x="24" y="68" width="58" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="108" y="18" width="80" height="84" rx="10" fill="' . $light . '" stroke="' . $stroke . '"/>'
			);

		case 'content_image':
		case 'content_image_a':
		case 'content_image_c':
			return $wrap(
				'<rect x="12" y="18" width="78" height="84" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="18" y="26" width="56" height="6" rx="2" fill="' . $ink . '"/>' .
				'<rect x="18" y="38" width="66" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="18" y="46" width="60" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="18" y="58" width="44" height="12" rx="6" fill="' . $brand . '"/>' .
				'<rect x="100" y="18" width="88" height="84" rx="10" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<circle cx="144" cy="52" r="18" fill="' . $brand2 . '" opacity="0.35"/>'
			);

		case 'image_content':
		case 'image_content_b':
			return $wrap(
				'<rect x="12" y="18" width="88" height="84" rx="10" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="110" y="18" width="78" height="84" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="118" y="28" width="56" height="6" rx="2" fill="' . $ink . '"/>' .
				'<rect x="118" y="40" width="62" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="118" y="52" width="40" height="12" rx="6" fill="' . $brand . '"/>'
			);

		case 'process':
			return $wrap(
				'<rect x="60" y="14" width="80" height="8" rx="3" fill="' . $ink . '"/>' .
				'<circle cx="38" cy="58" r="14" stroke="' . $brand . '" stroke-width="2.5" fill="#fff"/>' .
				'<circle cx="100" cy="58" r="14" stroke="' . $brand . '" stroke-width="2.5" fill="#fff"/>' .
				'<circle cx="162" cy="58" r="14" stroke="' . $brand . '" stroke-width="2.5" fill="#fff"/>' .
				'<rect x="52" y="56" width="40" height="3" rx="1" fill="' . $light . '"/>' .
				'<rect x="114" y="56" width="40" height="3" rx="1" fill="' . $light . '"/>'
			);

		case 'faq_accordion':
			return $wrap(
				'<rect x="50" y="12" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="16" y="32" width="168" height="18" rx="6" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="24" y="40" width="100" height="4" rx="2" fill="' . $muted . '"/>' .
				'<path d="M172 38 l4 4 l4-4" stroke="' . $ink . '" stroke-width="1.5" fill="none"/>' .
				'<rect x="16" y="56" width="168" height="18" rx="6" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="24" y="64" width="92" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="16" y="80" width="168" height="18" rx="6" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="24" y="88" width="88" height="4" rx="2" fill="' . $muted . '"/>'
			);

		case 'cta':
			return $wrap(
				'<rect x="8" y="8" width="184" height="104" rx="10" fill="#0f172a"/>' .
				'<rect x="40" y="28" width="120" height="9" rx="3" fill="#f8fafc"/>' .
				'<rect x="32" y="44" width="136" height="5" rx="2" fill="#94a3b8"/>' .
				'<rect x="48" y="64" width="48" height="16" rx="8" fill="' . $brand . '"/>' .
				'<rect x="104" y="64" width="48" height="16" rx="8" stroke="#e2e8f0" stroke-width="1.5" fill="none"/>'
			);

		case 'trust_reviews':
			return $wrap(
				'<rect x="60" y="12" width="80" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="10" y="32" width="56" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="72" y="32" width="56" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="134" y="32" width="56" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="18" y="40" width="40" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="80" y="40" width="40" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="142" y="40" width="40" height="4" rx="2" fill="' . $light . '"/>'
			);

		case 'service_intro':
			return $wrap(
				'<rect x="40" y="12" width="120" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="24" y="28" width="152" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="14" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="22" y="56" width="36" height="24" rx="6" fill="' . $light . '"/>' .
				'<rect x="74" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="134" y="48" width="52" height="62" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="22" y="86" width="36" height="4" rx="2" fill="' . $ink . '"/>'
			);

		case 'service_grid':
			return $wrap(
				'<rect x="14" y="14" width="82" height="46" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="104" y="14" width="82" height="46" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="14" y="68" width="82" height="46" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="104" y="68" width="82" height="46" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="22" y="88" width="50" height="5" rx="2" fill="#fff" opacity="0.9"/>'
			);

		case 'service_areas':
			return $wrap(
				'<rect x="50" y="14" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="14" y="36" width="100" height="70" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="122" y="36" width="64" height="70" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="130" y="48" width="48" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="130" y="58" width="44" height="4" rx="2" fill="' . $light . '"/>'
			);

		case 'logo_strip':
			return $wrap(
				'<rect x="50" y="14" width="100" height="7" rx="3" fill="' . $ink . '"/>' .
				'<rect x="20" y="40" width="28" height="18" rx="4" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="54" y="40" width="28" height="18" rx="4" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="88" y="40" width="28" height="18" rx="4" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="122" y="40" width="28" height="18" rx="4" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="156" y="40" width="24" height="18" rx="4" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="26" y="46" width="16" height="6" rx="2" fill="' . $muted . '"/>' .
				'<rect x="60" y="46" width="16" height="6" rx="2" fill="' . $muted . '"/>' .
				'<rect x="94" y="46" width="16" height="6" rx="2" fill="' . $muted . '"/>'
			);

		case 'team':
			return $wrap(
				'<rect x="55" y="12" width="90" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="22" y="36" width="44" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<circle cx="44" cy="54" r="12" fill="' . $light . '"/>' .
				'<rect x="30" y="72" width="28" height="5" rx="2" fill="' . $ink . '"/>' .
				'<rect x="32" y="82" width="24" height="3" rx="2" fill="' . $muted . '"/>' .
				'<rect x="78" y="36" width="44" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<circle cx="100" cy="54" r="12" fill="' . $light . '"/>' .
				'<rect x="134" y="36" width="44" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<circle cx="156" cy="54" r="12" fill="' . $light . '"/>'
			);

		case 'pricing':
			return $wrap(
				'<rect x="50" y="12" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="14" y="32" width="92" height="74" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="22" y="42" width="8" height="8" rx="2" fill="' . $brand . '"/>' .
				'<rect x="34" y="44" width="64" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="22" y="56" width="8" height="8" rx="2" fill="' . $brand . '"/>' .
				'<rect x="34" y="58" width="58" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="114" y="32" width="72" height="74" rx="10" fill="#eff6ff" stroke="' . $brand . '" stroke-width="1.5"/>' .
				'<rect x="126" y="48" width="48" height="14" rx="7" fill="' . $brand . '"/>'
			);

		case 'packages':
			return $wrap(
				'<rect x="45" y="10" width="110" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="12" y="32" width="52" height="78" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="74" y="26" width="52" height="84" rx="8" fill="#eff6ff" stroke="' . $brand . '" stroke-width="2"/>' .
				'<rect x="136" y="32" width="52" height="78" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="22" y="44" width="32" height="5" rx="2" fill="' . $ink . '"/>' .
				'<rect x="84" y="40" width="32" height="5" rx="2" fill="' . $brand . '"/>' .
				'<rect x="146" y="44" width="32" height="5" rx="2" fill="' . $ink . '"/>'
			);

		case 'map_nap':
			return $wrap(
				'<rect x="50" y="12" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="14" y="32" width="108" height="74" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="48" y="58" width="40" height="28" rx="6" fill="' . $brand2 . '" opacity="0.4"/>' .
				'<rect x="130" y="32" width="56" height="74" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="138" y="44" width="40" height="4" rx="2" fill="' . $ink . '"/>' .
				'<rect x="138" y="54" width="36" height="3" rx="2" fill="' . $muted . '"/>'
			);

		case 'related_links':
			return $wrap(
				'<rect x="40" y="14" width="120" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="24" y="38" width="48" height="14" rx="7" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="80" y="38" width="48" height="14" rx="7" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="136" y="38" width="40" height="14" rx="7" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="32" y="60" width="52" height="14" rx="7" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="92" y="60" width="52" height="14" rx="7" fill="#fff" stroke="' . $stroke . '"/>'
			);

		case 'project_gallery':
			return $wrap(
				'<rect x="50" y="12" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="12" y="32" width="56" height="40" rx="6" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="72" y="32" width="56" height="52" rx="6" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="132" y="32" width="56" height="36" rx="6" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="12" y="78" width="56" height="34" rx="6" fill="' . $light . '" stroke="' . $stroke . '"/>' .
				'<rect x="72" y="90" width="116" height="22" rx="6" fill="' . $light . '" stroke="' . $stroke . '"/>'
			);

		case 'blog_posts':
			return $wrap(
				'<rect x="50" y="12" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="14" y="34" width="52" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="74" y="34" width="52" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="134" y="34" width="52" height="72" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="18" y="40" width="44" height="22" rx="4" fill="' . $light . '"/>' .
				'<rect x="78" y="40" width="44" height="22" rx="4" fill="' . $light . '"/>'
			);

		case 'content':
		case 'content_centered':
			return $wrap(
				'<rect x="40" y="24" width="120" height="10" rx="3" fill="' . $ink . '"/>' .
				'<rect x="24" y="44" width="152" height="5" rx="2" fill="' . $muted . '"/>' .
				'<rect x="24" y="54" width="140" height="5" rx="2" fill="' . $light . '"/>' .
				'<rect x="24" y="66" width="148" height="5" rx="2" fill="' . $light . '"/>' .
				'<rect x="24" y="78" width="100" height="5" rx="2" fill="' . $light . '"/>'
			);

		case 'rich_content':
			return $wrap(
				'<rect x="32" y="14" width="100" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="32" y="28" width="136" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="32" y="36" width="120" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="32" y="48" width="72" height="6" rx="2" fill="' . $ink . '"/>' .
				'<circle cx="38" cy="62" r="2" fill="' . $brand . '"/>' .
				'<rect x="44" y="60" width="110" height="4" rx="2" fill="' . $light . '"/>' .
				'<circle cx="38" cy="72" r="2" fill="' . $brand . '"/>' .
				'<rect x="44" y="70" width="96" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="32" y="84" width="48" height="12" rx="6" fill="' . $brand . '"/>'
			);

		case 'sitemap_links':
			return $wrap(
				'<rect x="40" y="18" width="120" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="30" y="40" width="140" height="60" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="42" y="52" width="60" height="3" rx="2" fill="' . $brand . '"/>' .
				'<rect x="42" y="62" width="52" height="3" rx="2" fill="' . $brand . '"/>' .
				'<rect x="42" y="72" width="56" height="3" rx="2" fill="' . $brand . '"/>'
			);

		case 'services_offered_here':
		case 'nearby_areas':
			return $wrap(
				'<rect x="40" y="16" width="120" height="8" rx="3" fill="' . $ink . '"/>' .
				'<rect x="24" y="36" width="152" height="70" rx="8" fill="#fff" stroke="' . $stroke . '"/>' .
				'<rect x="36" y="48" width="100" height="4" rx="2" fill="' . $muted . '"/>' .
				'<rect x="36" y="60" width="88" height="4" rx="2" fill="' . $light . '"/>' .
				'<rect x="36" y="72" width="92" height="4" rx="2" fill="' . $light . '"/>'
			);

		default:
			return $wrap(
				'<rect x="14" y="18" width="100" height="9" rx="3" fill="' . $ink . '"/>' .
				'<rect x="14" y="34" width="172" height="5" rx="2" fill="' . $muted . '"/>' .
				'<rect x="14" y="44" width="150" height="5" rx="2" fill="' . $light . '"/>' .
				'<rect x="14" y="60" width="56" height="16" rx="8" fill="' . $brand . '"/>' .
				'<rect x="120" y="70" width="66" height="36" rx="8" fill="' . $light . '" stroke="' . $stroke . '"/>'
			);
	}
}
