<?php
/**
 * Branding tokens: CSS variables sourced from Theme Options > Branding.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_design_font_choices(): array {
	return [
		'plus-jakarta' => __('Plus Jakarta Sans', 'leadsforward-core'),
		'outfit' => __('Outfit', 'leadsforward-core'),
		'manrope' => __('Manrope', 'leadsforward-core'),
		'inter' => __('Inter', 'leadsforward-core'),
		'dm-sans' => __('DM Sans', 'leadsforward-core'),
		'space-grotesk' => __('Space Grotesk', 'leadsforward-core'),
		'oswald' => __('Oswald', 'leadsforward-core'),
		'playfair' => __('Playfair Display', 'leadsforward-core'),
	];
}

function lf_design_font_family_map(): array {
	return [
		'plus-jakarta' => "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif",
		'outfit' => "'Outfit', -apple-system, BlinkMacSystemFont, sans-serif",
		'manrope' => "'Manrope', -apple-system, BlinkMacSystemFont, sans-serif",
		'inter' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
		'dm-sans' => "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif",
		'space-grotesk' => "'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif",
		'oswald' => "'Oswald', -apple-system, BlinkMacSystemFont, sans-serif",
		'playfair' => "'Playfair Display', -apple-system, BlinkMacSystemFont, serif",
	];
}

function lf_design_heading_weight_choices(): array {
	return [
		'600' => __('600 (Semi-bold)', 'leadsforward-core'),
		'700' => __('700 (Bold)', 'leadsforward-core'),
		'800' => __('800 (Extra bold)', 'leadsforward-core'),
	];
}

function lf_design_button_radius_choices(): array {
	return [
		'sharp' => __('Sharp', 'leadsforward-core'),
		'soft' => __('Soft', 'leadsforward-core'),
		'pill' => __('Pill', 'leadsforward-core'),
	];
}

function lf_design_card_radius_choices(): array {
	return [
		'tight' => __('Tight', 'leadsforward-core'),
		'medium' => __('Medium', 'leadsforward-core'),
		'round' => __('Round', 'leadsforward-core'),
	];
}

function lf_design_card_shadow_choices(): array {
	return [
		'none' => __('None', 'leadsforward-core'),
		'soft' => __('Soft', 'leadsforward-core'),
		'bold' => __('Bold', 'leadsforward-core'),
	];
}

function lf_design_section_spacing_choices(): array {
	return [
		'compact' => __('Compact', 'leadsforward-core'),
		'normal' => __('Normal', 'leadsforward-core'),
		'airy' => __('Airy', 'leadsforward-core'),
	];
}

function lf_branding_get_value(string $key, string $default): string {
	$val = '';
	if (function_exists('get_field')) {
		foreach (['lf-branding', 'options_lf_branding', 'options_lf-branding', 'option', 'options'] as $post_id) {
			$tmp = get_field($key, $post_id);
			if (is_string($tmp) && $tmp !== '') {
				$val = $tmp;
				break;
			}
		}
	}
	if ($val === '') {
		$opt = get_option('options_' . $key, '');
		if (is_string($opt) && $opt !== '') {
			$val = $opt;
		}
	}
	$val = is_string($val) ? $val : '';
	$val = $val !== '' ? $val : $default;
	$val = function_exists('sanitize_hex_color') ? (sanitize_hex_color($val) ?: $default) : $val;
	return $val;
}

function lf_branding_css(): string {
	$primary   = lf_branding_get_value('lf_brand_primary', '#2563eb');
	$secondary = lf_branding_get_value('lf_brand_secondary', '#0ea5e9');
	$tertiary  = lf_branding_get_value('lf_brand_tertiary', '#f97316');
	$light     = lf_branding_get_value('lf_surface_light', '#ffffff');
	$soft      = lf_branding_get_value('lf_surface_soft', '#f8fafc');
	$dark      = lf_branding_get_value('lf_surface_dark', '#0f172a');
	$card      = lf_branding_get_value('lf_surface_card', '#ffffff');
	$text      = lf_branding_get_value('lf_text_primary', '#0f172a');
	$muted     = lf_branding_get_value('lf_text_muted', '#64748b');
	$inverse   = lf_branding_get_value('lf_text_inverse', '#ffffff');

	return ':root{'
		. '--lf-color-primary:' . $primary . ';'
		. '--lf-color-secondary:' . $secondary . ';'
		. '--lf-color-tertiary:' . $tertiary . ';'
		. '--lf-surface-light:' . $light . ';'
		. '--lf-surface-soft:' . $soft . ';'
		. '--lf-surface-dark:' . $dark . ';'
		. '--lf-surface-card:' . $card . ';'
		. '--lf-text-primary:' . $text . ';'
		. '--lf-text-muted:' . $muted . ';'
		. '--lf-text-inverse:' . $inverse . ';'
		. '--lf-primary:var(--lf-color-primary);'
		. '--lf-secondary:var(--lf-color-secondary);'
		. '--lf-tertiary:var(--lf-color-tertiary);'
		. '--lf-muted:var(--lf-text-muted);'
		. '--lf-body-bg:var(--lf-surface-light);'
		. '}';
}

function lf_enqueue_branding_tokens(): void {
	$css = lf_branding_css();
	if ($css === '') {
		return;
	}
	if (wp_style_is('lf-design-system', 'enqueued')) {
		wp_add_inline_style('lf-design-system', $css);
		return;
	}
	wp_register_style('lf-branding-tokens', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-branding-tokens');
	wp_add_inline_style('lf-branding-tokens', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_branding_tokens', 6);
add_action('enqueue_block_editor_assets', 'lf_enqueue_branding_tokens', 6);

function lf_design_overrides_css(): string {
	$enabled = get_option('lf_design_overrides_enabled', '0') === '1';
	if (!$enabled) {
		return '';
	}
	$font_map = lf_design_font_family_map();
	$heading_font = (string) get_option('lf_design_heading_font', '');
	$body_font = (string) get_option('lf_design_body_font', '');
	$heading_weight = (string) get_option('lf_design_heading_weight', '');
	$button_radius = (string) get_option('lf_design_button_radius', '');
	$card_radius = (string) get_option('lf_design_card_radius', '');
	$card_shadow = (string) get_option('lf_design_card_shadow', '');
	$section_spacing = (string) get_option('lf_design_section_spacing', '');

	$css = ':root, body[class*="lf-design-"], .editor-styles-wrapper{';
	if ($heading_font !== '' && isset($font_map[$heading_font])) {
		$css .= '--lf-font-heading:' . $font_map[$heading_font] . ';';
	}
	if ($body_font !== '' && isset($font_map[$body_font])) {
		$css .= '--lf-font-system:' . $font_map[$body_font] . ';';
	}
	if (in_array($heading_weight, ['600', '700', '800'], true)) {
		$css .= '--lf-heading-weight:' . $heading_weight . ';';
	}
	switch ($button_radius) {
		case 'sharp':
			$css .= '--lf-button-radius:0.25rem;';
			break;
		case 'soft':
			$css .= '--lf-button-radius:0.75rem;';
			break;
		case 'pill':
			$css .= '--lf-button-radius:999px;';
			break;
	}
	switch ($card_radius) {
		case 'tight':
			$css .= '--lf-card-radius:0.4rem;';
			break;
		case 'medium':
			$css .= '--lf-card-radius:0.9rem;';
			break;
		case 'round':
			$css .= '--lf-card-radius:1.4rem;';
			break;
	}
	switch ($card_shadow) {
		case 'none':
			$css .= '--lf-card-shadow:none;--lf-card-shadow-hover:none;--lf-card-shadow-spotlight:none;';
			break;
		case 'soft':
			$css .= '--lf-card-shadow:0 8px 18px rgba(15,23,42,0.08);';
			$css .= '--lf-card-shadow-hover:0 14px 28px rgba(15,23,42,0.12);';
			$css .= '--lf-card-shadow-spotlight:0 8px 24px rgba(15,23,42,0.12);';
			break;
		case 'bold':
			$css .= '--lf-card-shadow:0 18px 36px rgba(15,23,42,0.16);';
			$css .= '--lf-card-shadow-hover:0 26px 52px rgba(15,23,42,0.2);';
			$css .= '--lf-card-shadow-spotlight:0 16px 32px rgba(15,23,42,0.2);';
			break;
	}
	switch ($section_spacing) {
		case 'compact':
			$css .= '--lf-space-section:1.6rem;--lf-space-section-sm:1rem;--lf-space-section-lg:2.1rem;';
			break;
		case 'normal':
			$css .= '--lf-space-section:2rem;--lf-space-section-sm:1.5rem;--lf-space-section-lg:2.5rem;';
			break;
		case 'airy':
			$css .= '--lf-space-section:2.9rem;--lf-space-section-sm:1.7rem;--lf-space-section-lg:3.4rem;';
			break;
	}
	$css .= '}';
	return $css;
}

function lf_enqueue_design_overrides(): void {
	$css = lf_design_overrides_css();
	if ($css === '') {
		return;
	}
	if (wp_style_is('lf-design-presets', 'enqueued')) {
		wp_add_inline_style('lf-design-presets', $css);
		return;
	}
	if (wp_style_is('lf-design-system', 'enqueued')) {
		wp_add_inline_style('lf-design-system', $css);
		return;
	}
	wp_register_style('lf-design-overrides', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-design-overrides');
	wp_add_inline_style('lf-design-overrides', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_design_overrides', 7);
add_action('enqueue_block_editor_assets', 'lf_enqueue_design_overrides', 7);

/**
 * Auto-generate branding colors from an uploaded logo.
 */
function lf_branding_auto_from_logo(int $attachment_id): bool {
	$palette = lf_branding_extract_logo_palette($attachment_id);
	if (empty($palette)) {
		return false;
	}
	$primary = $palette[0] ?? '#2563eb';
	$secondary = $palette[1] ?? lf_branding_shift_color($primary, 0.18);
	$tertiary = $palette[2] ?? lf_branding_shift_color($primary, -0.12);
	$updates = [
		'lf_brand_primary'   => $primary,
		'lf_brand_secondary' => $secondary,
		'lf_brand_tertiary'  => $tertiary,
	];
	foreach ($updates as $key => $value) {
		update_option('options_' . $key, $value);
		if (function_exists('update_field')) {
			foreach (['lf-branding', 'options_lf_branding', 'options_lf-branding', 'option', 'options'] as $post_id) {
				update_field($key, $value, $post_id);
			}
		}
	}
	return true;
}

function lf_branding_extract_logo_palette(int $attachment_id): array {
	if (!function_exists('imagecreatefromstring')) {
		return [];
	}
	$path = get_attached_file($attachment_id);
	if (!$path || !is_readable($path)) {
		return [];
	}
	$data = @file_get_contents($path);
	if ($data === false) {
		return [];
	}
	$image = @imagecreatefromstring($data);
	if (!$image) {
		return [];
	}
	$width = imagesx($image);
	$height = imagesy($image);
	if (!$width || !$height) {
		imagedestroy($image);
		return [];
	}
	$max_size = 48;
	$scale = min($max_size / $width, $max_size / $height, 1);
	$sample_w = max(1, (int) round($width * $scale));
	$sample_h = max(1, (int) round($height * $scale));
	$sample = imagecreatetruecolor($sample_w, $sample_h);
	imagealphablending($sample, false);
	imagesavealpha($sample, true);
	imagecopyresampled($sample, $image, 0, 0, 0, 0, $sample_w, $sample_h, $width, $height);
	imagedestroy($image);

	$buckets = [];
	$step = 32;
	for ($y = 0; $y < $sample_h; $y++) {
		for ($x = 0; $x < $sample_w; $x++) {
			$rgba = imagecolorsforindex($sample, imagecolorat($sample, $x, $y));
			if (!is_array($rgba)) {
				continue;
			}
			$alpha = $rgba['alpha'] ?? 0;
			if ($alpha >= 100) {
				continue;
			}
			$r = (int) $rgba['red'];
			$g = (int) $rgba['green'];
			$b = (int) $rgba['blue'];
			$brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
			if ($brightness > 245) {
				continue;
			}
			$r = (int) (round($r / $step) * $step);
			$g = (int) (round($g / $step) * $step);
			$b = (int) (round($b / $step) * $step);
			$r = min(255, max(0, $r));
			$g = min(255, max(0, $g));
			$b = min(255, max(0, $b));
			$key = $r . ',' . $g . ',' . $b;
			$buckets[$key] = ($buckets[$key] ?? 0) + 1;
		}
	}
	imagedestroy($sample);

	if (empty($buckets)) {
		return [];
	}
	arsort($buckets);
	$colors = [];
	foreach ($buckets as $key => $count) {
		[$r, $g, $b] = array_map('intval', explode(',', $key));
		$hex = sprintf('#%02x%02x%02x', $r, $g, $b);
		if (!in_array($hex, $colors, true)) {
			$colors[] = $hex;
		}
		if (count($colors) >= 3) {
			break;
		}
	}
	return $colors;
}

function lf_branding_shift_color(string $hex, float $percent): string {
	$hex = ltrim($hex, '#');
	if (strlen($hex) === 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if (strlen($hex) !== 6) {
		return '#2563eb';
	}
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));
	if ($percent >= 0) {
		$r = (int) round($r + (255 - $r) * $percent);
		$g = (int) round($g + (255 - $g) * $percent);
		$b = (int) round($b + (255 - $b) * $percent);
	} else {
		$r = (int) round($r * (1 + $percent));
		$g = (int) round($g * (1 + $percent));
		$b = (int) round($b * (1 + $percent));
	}
	return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

if (function_exists('add_filter')) {
	add_filter('acf/update_value/name=lf_global_logo', function ($value) {
		$logo_id = (int) $value;
		if ($logo_id > 0) {
			lf_branding_auto_from_logo($logo_id);
		}
		return $value;
	}, 20, 1);
}
