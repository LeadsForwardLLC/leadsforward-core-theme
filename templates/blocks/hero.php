<?php
/**
 * Block: Hero — single conversion layout for marketing pages; compact page hero for interiors.
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context'] optional homepage section overrides
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$context  = $block['context'] ?? [];
$section  = $context['section'] ?? [];
$raw_variant = (string) ($block['variant'] ?? 'conversion');
$variant = function_exists('lf_sections_normalize_hero_variant')
	? lf_sections_normalize_hero_variant($raw_variant, !empty($context['homepage']))
	: (in_array($raw_variant, ['page', 'internal'], true) ? 'page' : 'conversion');
$block_id = $block['id'] ?? '';
$bg_fallback = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'soft') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_fallback, 'style' => ''];
$heading_tag = $context['heading_tag'] ?? 'h1';

$eyebrow_enabled = (string) (($section['hero_eyebrow_enabled'] ?? '1')) !== '0';
$business_city = function_exists('lf_get_option') ? (string) lf_get_option('lf_business_address_city', 'option') : '';
$business_state = function_exists('lf_get_option') ? (string) lf_get_option('lf_business_address_state', 'option') : '';
$business_city = sanitize_text_field($business_city);
$business_state = sanitize_text_field($business_state);
$eyebrow_default = ($business_city !== '' && $business_state !== '')
	? sprintf(__('Licensed & insured in %1$s, %2$s', 'leadsforward-core'), $business_city, $business_state)
	: ($business_city !== '' ? sprintf(__('Licensed & insured in %s', 'leadsforward-core'), $business_city) : __('Licensed • Insured • Local', 'leadsforward-core'));
$eyebrow = isset($section['hero_eyebrow_text']) && $section['hero_eyebrow_text'] !== '' ? $section['hero_eyebrow_text'] : $eyebrow_default;
if (!$eyebrow_enabled) {
	$eyebrow = '';
}

$heading = get_the_title() ?: __('Quality Local Service', 'leadsforward-core');
$subheading = '';
$primary_enabled = (string) (($section['cta_primary_enabled'] ?? '1')) !== '0';
$secondary_enabled = (string) (($section['cta_secondary_enabled'] ?? '1')) !== '0';
$business_name = function_exists('lf_get_option') ? lf_get_option('lf_business_name', 'option') : '';
if (!is_string($business_name) || $business_name === '') {
	$business_name = get_bloginfo('name') ?: '';
}

if (!empty($context['homepage']) && !empty($section)) {
	if (!empty($section['hero_headline'])) {
		$heading = $section['hero_headline'];
	} else {
		$default_heading = lf_get_option('lf_business_name', 'option') ?: get_bloginfo('name') ?: __('Quality Local Service', 'leadsforward-core');
		$heading = function_exists('lf_copy_template') ? lf_copy_template('hero_headline', $default_heading, [
			'business_name' => lf_get_option('lf_business_name', 'option'),
			'service'       => '',
			'city'          => '',
			'area'          => '',
		]) : $default_heading;
		if ($heading === '') {
			$heading = $default_heading;
		}
	}
	$subheading = $section['hero_subheadline'] ?? '';
} elseif (!empty($section['hero_headline'])) {
	$heading = $section['hero_headline'];
	$subheading = $section['hero_subheadline'] ?? '';
}

if ($subheading === '' && empty($context['homepage'])) {
	$post_type = get_post_type();
	if ($post_type === 'lf_service_area' && function_exists('get_field')) {
		$state = get_field('lf_service_area_state');
		if ($state) {
			$subheading = sprintf(__('Serving %1$s, %2$s', 'leadsforward-core'), get_the_title(), $state);
		}
	}
}

$subheading_html = '';
if ($subheading !== '') {
	$subheading_html = function_exists('lf_ai_inline_link_allowed_kses')
		? wp_kses((string) $subheading, lf_ai_inline_link_allowed_kses())
		: esc_html((string) $subheading);
}

if ($business_name !== '') {
	$heading = str_replace(['[Your Business]', '[Your Business Name]', '{business_name}'], $business_name, $heading);
}

if (function_exists('lf_strip_airtable_record_id_prefix')) {
	$heading = lf_strip_airtable_record_id_prefix((string) $heading);
	if ($subheading !== '') {
		$subheading = lf_strip_airtable_record_id_prefix((string) $subheading);
	}
}

$cta_resolved_for_type = function_exists('lf_resolve_cta') ? lf_resolve_cta($context, $section, []) : [];
$cta_text = $cta_resolved_for_type['primary_text'] ?? '';
if (!empty($context['homepage']) && $cta_text && function_exists('lf_copy_template')) {
	$cta_text = lf_copy_template('cta_microcopy', $cta_text, []);
	if ($cta_text === '') {
		$cta_text = $cta_resolved_for_type['primary_text'] ?? '';
	}
}
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$cta_type = $cta_resolved_for_type['primary_type'] ?? 'text';
$cta_action = $cta_resolved_for_type['primary_action'] ?? 'link';
$cta_url = $cta_resolved_for_type['primary_url'] ?? '';
$use_phone_link = $cta_type === 'call' && $cta_phone && $cta_text;
$secondary_text = $cta_resolved_for_type['secondary_text'] ?? '';
$secondary_action = $cta_resolved_for_type['secondary_action'] ?? 'call';
$secondary_url = $cta_resolved_for_type['secondary_url'] ?? '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'hero', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'hero', 'left', 'lf-heading-icon') : '';
$hero_media = $section['hero_media'] ?? 'image';
$hero_image_id = isset($section['hero_image_id']) ? (int) $section['hero_image_id'] : 0;
if ($hero_image_id === 0 && is_singular()) {
	$hero_image_id = (int) get_post_thumbnail_id(get_the_ID());
}
$show_hero_image = $hero_media === 'image' && $hero_image_id > 0;
$hero_image_alt = '';
if ($show_hero_image) {
	$hero_image_alt = (string) get_post_meta($hero_image_id, '_wp_attachment_image_alt', true);
	if ($hero_image_alt === '') {
		$hero_image_alt = $heading !== '' ? $heading : ($business_name ?: __('Trusted local service', 'leadsforward-core'));
	}
}
$review_count = 0;
$review_rating = 0.0;
if (post_type_exists('lf_testimonial')) {
	$rating_posts = get_posts([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	$ratings_total = 0;
	$ratings_count = 0;
	foreach ($rating_posts as $rating_post_id) {
		$rating_value = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', (int) $rating_post_id) : 5;
		if ($rating_value <= 1) {
			continue;
		}
		$review_count++;
		$ratings_total += $rating_value;
		$ratings_count++;
	}
	$review_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 5.0;
}
$hero_trust_strip_enabled = (string) ($section['hero_trust_strip_enabled'] ?? '1') !== '0';
$show_trust_strip = $hero_trust_strip_enabled && $review_count > 0;
$homeowner_count = $review_count > 0 ? $review_count : 200;
$homeowner_display = number_format_i18n($homeowner_count);
$homeowner_label = sprintf(__('Trusted by %s homeowners', 'leadsforward-core'), $homeowner_display);
if ($review_rating <= 0) {
	$review_rating = 5.0;
}
$trust_strip_html = '';
$reviews_display = number_format_i18n($review_count);
$rating_display = number_format_i18n($review_rating, 1);
if ($show_trust_strip) {
	ob_start();
	?>
	<div class="lf-hero-trust">
		<span class="lf-hero-trust__icon" aria-hidden="true">
			<img src="<?php echo esc_url(LF_THEME_URI . '/assets/images/customers.png'); ?>" alt="<?php esc_attr_e('Customers', 'leadsforward-core'); ?>" width="50" height="50" decoding="async" />
		</span>
		<span class="lf-hero-trust__badge">
			<span class="lf-block-hero__stars" aria-hidden="true">
				<?php for ($i = 0; $i < 5; $i++) : ?>
					<svg class="lf-block-hero__star" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php endfor; ?>
			</span>
			<span class="lf-hero-trust__rating"><?php echo esc_html($rating_display); ?></span>
		</span>
		<span class="lf-hero-trust__stat lf-hero-trust__stat--emphasis">
			<span class="lf-hero-trust__label-only"><?php echo esc_html($homeowner_label); ?></span>
		</span>
	</div>
	<?php
	$trust_strip_html = (string) ob_get_clean();
}

$proof_default_title = __('Why homeowners choose us', 'leadsforward-core');
$proof_default_items = [
	__('Fast response and clear pricing', 'leadsforward-core'),
	__('Licensed, insured, and local', 'leadsforward-core'),
	__('Clean work backed by warranty', 'leadsforward-core'),
];
$proof_title = $section['hero_proof_title'] ?? $proof_default_title;
$proof_bullets_raw = $section['hero_proof_bullets'] ?? '';
$proof_items = function_exists('lf_sections_parse_lines')
	? lf_sections_parse_lines((string) $proof_bullets_raw)
	: preg_split('/\r\n|\r|\n/', (string) $proof_bullets_raw);
$proof_items = array_values(array_filter(array_map('trim', is_array($proof_items) ? $proof_items : [])));
if ($proof_items === []) {
	$proof_items = $proof_default_items;
}
if (array_key_exists('hero_chip_bullets', $section)) {
	$chip_raw = (string) ($section['hero_chip_bullets'] ?? '');
	$chip_lines = function_exists('lf_sections_parse_chip_lines')
		? lf_sections_parse_chip_lines($chip_raw)
		: [];
} else {
	$chip_lines = [];
}
$chip_items = array_column($chip_lines, 'label');
if ($chip_items === []) {
	$chip_raw = (string) ($section['hero_chip_bullets'] ?? '');
	$chip_items = function_exists('lf_sections_parse_lines')
		? lf_sections_parse_lines($chip_raw)
		: preg_split('/\r\n|\r|\n/', $chip_raw);
	$chip_items = array_values(array_filter(array_map('trim', is_array($chip_items) ? $chip_items : [])));
	if ($chip_items === []) {
		$chip_items = $proof_items;
	}
	$chip_lines = array_map(static function (string $label, int $i): array {
		$icons = ['shield-check', 'star', 'calendar', 'certificate'];
		return [
			'label' => $label,
			'icon'  => $icons[$i % count($icons)] ?? 'check',
		];
	}, $chip_items, array_keys($chip_items));
}
$check_items = $variant === 'page' ? [] : array_slice($chip_lines, 0, 5);

if (!$primary_enabled) {
	$cta_text = '';
}
if (!$secondary_enabled) {
	$secondary_text = '';
}
$show_cta_group = ($cta_text !== '' || $secondary_text !== '');
$lead_cta_label = $cta_text !== '' ? $cta_text : __('Get a Free Estimate', 'leadsforward-core');

if (function_exists('lf_sections_hero_cta_button_classes') && function_exists('lf_sections_hero_cta_data_attrs')) {
	$h_pri = lf_sections_hero_cta_button_classes($section, 'primary', 'lf-hero-conversion__cta-primary');
	$h_sec = lf_sections_hero_cta_button_classes($section, 'secondary', 'lf-hero-conversion__cta-secondary');
	$h_pri_page = lf_sections_hero_cta_button_classes($section, 'primary', '');
	$h_sec_page = lf_sections_hero_cta_button_classes($section, 'secondary', '');
	$h_pri_lead = lf_sections_hero_cta_button_classes($section, 'primary', 'lf-hero-conversion__lead-button');
	$h_sec_lead = lf_sections_hero_cta_button_classes($section, 'secondary', 'lf-hero-conversion__lead-call');
	$h_pri_attr = lf_sections_hero_cta_data_attrs($section, 'primary');
	$h_sec_attr = lf_sections_hero_cta_data_attrs($section, 'secondary');
} else {
	$h_pri = 'lf-btn lf-btn--primary lf-hero-conversion__cta-primary';
	$h_sec = 'lf-btn lf-btn--secondary lf-hero-conversion__cta-secondary';
	$h_pri_page = 'lf-btn lf-btn--primary';
	$h_sec_page = 'lf-btn lf-btn--secondary';
	$h_pri_lead = 'lf-btn lf-btn--primary lf-hero-conversion__lead-button';
	$h_sec_lead = 'lf-btn lf-btn--secondary lf-hero-conversion__lead-call';
	$h_pri_attr = ' data-lf-cta-slot="primary" data-lf-btn-style="solid" data-lf-btn-tone="primary"';
	$h_sec_attr = ' data-lf-cta-slot="secondary" data-lf-btn-style="outline" data-lf-btn-tone="primary"';
}

$placeholder_id = function_exists('lf_get_placeholder_image_id') ? lf_get_placeholder_image_id() : 0;
$placeholder_alt = $business_name ? $business_name : __('Trusted local service', 'leadsforward-core');
$hero_bg_mode = (string) ($section['hero_background_mode'] ?? 'image');
if (!in_array($hero_bg_mode, ['color', 'image', 'video'], true)) {
	$hero_bg_mode = 'image';
}
$hero_bg_stored_image_id = isset($section['hero_background_image_id']) ? (int) $section['hero_background_image_id'] : 0;
$hero_bg_stored_video_id = isset($section['hero_background_video_id']) ? (int) $section['hero_background_video_id'] : 0;
$hero_bg_id = 0;
if ($hero_bg_mode === 'image' && $variant === 'conversion') {
	$hero_bg_id = $hero_bg_stored_image_id;
	if ($hero_bg_id === 0) {
		$hero_bg_id = (int) get_post_thumbnail_id(get_queried_object_id());
	}
	if ($hero_bg_id === 0 && $placeholder_id) {
		$hero_bg_id = (int) $placeholder_id;
	}
}
$hero_bg_url = $hero_bg_id ? wp_get_attachment_image_url($hero_bg_id, function_exists('lf_hero_background_image_size') ? lf_hero_background_image_size() : 'large') : '';
$hero_bg_class = '';
$hero_bg_style = '';
if ($hero_bg_url && $hero_bg_mode === 'image' && $variant === 'conversion') {
	$hero_bg_overlay = '0.68';
	$hero_bg_class = ' lf-block-hero--has-bg';
	$hero_bg_style = sprintf(
		'--lf-hero-bg-image: url(\'%s\'); --lf-hero-bg-overlay-opacity: %s;',
		esc_url($hero_bg_url),
		$hero_bg_overlay
	);
}
$hero_video_url = '';
$hero_video_mime = 'video/mp4';
if ($hero_bg_mode === 'video' && $variant === 'conversion' && $hero_bg_stored_video_id > 0) {
	$vurl = wp_get_attachment_url($hero_bg_stored_video_id);
	$hero_video_url = is_string($vurl) ? $vurl : '';
	$vm = get_post_mime_type($hero_bg_stored_video_id);
	if (is_string($vm) && $vm !== '') {
		$hero_video_mime = $vm;
	}
}
$hero_video_class = ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant === 'conversion') ? ' lf-block-hero--has-video' : '';
$hero_video_overlay_css = '';
if ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant === 'conversion') {
	$hero_video_overlay_css = '--lf-hero-bg-overlay-opacity: 0.55;';
}
$eyebrow_html = $eyebrow !== '' && function_exists('lf_sections_render_accent_text')
	? lf_sections_render_accent_text((string) $eyebrow)
	: esc_html((string) $eyebrow);
$heading_html = function_exists('lf_sections_render_accent_text')
	? lf_sections_render_accent_text((string) $heading)
	: esc_html((string) $heading);

$hero_outer_class = trim('lf-block lf-block-hero ' . ($surface['class'] ?? '') . ' lf-block-hero--' . $variant . $hero_bg_class . $hero_video_class);
$hero_combined_style = trim(
	($surface['style'] ?? '')
	. ($hero_bg_style !== '' ? ' ' . $hero_bg_style : '')
	. ($hero_video_overlay_css !== '' ? ' ' . $hero_video_overlay_css : '')
);
$hero_bg_tone = (function_exists('lf_sections_hero_background_is_dark') && lf_sections_hero_background_is_dark($section)) ? 'dark' : 'light';
$hero_img_attrs = function_exists('lf_image_lcp_attrs')
	? lf_image_lcp_attrs(['alt' => esc_attr($hero_image_alt)])
	: ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'alt' => esc_attr($hero_image_alt)];
$placeholder_attrs = function_exists('lf_image_lcp_attrs')
	? lf_image_lcp_attrs(['alt' => esc_attr($placeholder_alt)])
	: ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'alt' => esc_attr($placeholder_alt)];
?>
<section class="<?php echo esc_attr($hero_outer_class); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-lf-hero-bg-tone="<?php echo esc_attr($hero_bg_tone); ?>" data-lf-hero-bg-mode="<?php echo esc_attr($hero_bg_mode); ?>" data-lf-hero-bg-image-id="<?php echo esc_attr((string) $hero_bg_stored_image_id); ?>" data-lf-hero-bg-video-id="<?php echo esc_attr((string) $hero_bg_stored_video_id); ?>" data-lf-hero-trust-strip-setting="<?php echo esc_attr($hero_trust_strip_enabled ? '1' : '0'); ?>"<?php echo $hero_combined_style !== '' ? ' style="' . esc_attr($hero_combined_style) . '"' : ''; ?>>
	<div class="lf-block-hero__bg" aria-hidden="true">
		<?php if ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant === 'conversion') : ?>
			<video class="lf-block-hero__video" autoplay muted loop playsinline preload="none">
				<source src="<?php echo esc_url($hero_video_url); ?>" type="<?php echo esc_attr($hero_video_mime); ?>" />
			</video>
		<?php endif; ?>
	</div>
	<div class="lf-block-hero__inner">
		<?php if ($variant === 'page') : ?>
			<div class="lf-hero-basic<?php echo $show_hero_image ? ' lf-hero-basic--media' : ''; ?>">
				<div class="lf-hero-basic__content">
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<<?php echo esc_html($heading_tag); ?> class="lf-hero-basic__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
						</div>
					<?php else : ?>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-basic__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
					<?php endif; ?>
					<?php if ($subheading !== '') : ?>
						<p class="lf-hero-basic__subtitle"><?php echo $subheading_html; ?></p>
					<?php endif; ?>
					<?php if ($show_cta_group) : ?>
						<div class="lf-hero-basic__actions">
							<?php if ($cta_text) : ?>
								<?php if ($use_phone_link) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="<?php echo esc_attr($h_pri_page); ?>"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></a>
								<?php elseif ($cta_action === 'quote') : ?>
									<button type="button" class="<?php echo esc_attr($h_pri_page); ?>" data-lf-quote-trigger="1" data-lf-quote-source="hero-page"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></button>
								<?php elseif ($cta_url !== '') : ?>
									<a href="<?php echo esc_url($cta_url); ?>" class="<?php echo esc_attr($h_pri_page); ?>"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($secondary_text !== '') : ?>
								<?php if ($secondary_action === 'quote') : ?>
									<button type="button" class="<?php echo esc_attr($h_sec_page); ?>" data-lf-quote-trigger="1" data-lf-quote-source="hero-page-secondary"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></button>
								<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="<?php echo esc_attr($h_sec_page); ?>"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></a>
								<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
									<a href="<?php echo esc_url($secondary_url); ?>" class="<?php echo esc_attr($h_sec_page); ?>"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php if ($show_hero_image) : ?>
					<div class="lf-hero-basic__media">
						<div class="lf-hero-basic__image">
							<?php echo wp_get_attachment_image($hero_image_id, 'large', false, $hero_img_attrs); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php
			$badge_items = array_slice($chip_lines, 0, 4);
			$primary_label = $cta_text !== '' ? $cta_text : __('Get a Free Inspection', 'leadsforward-core');
			$mobile_trust_bits = [];
			if ($review_count > 0) {
				$mobile_trust_bits[] = sprintf(__('%s Google Rating', 'leadsforward-core'), $rating_display);
			}
			if ($eyebrow !== '') {
				$mobile_trust_bits[] = __('Licensed & Insured', 'leadsforward-core');
			}
			$mobile_trust_bits[] = __('Free Inspection', 'leadsforward-core');
			?>
			<div class="lf-hero-conversion">
				<div class="lf-hero-conversion__content">
					<?php if ($eyebrow !== '') : ?>
						<p class="lf-hero-conversion__eyebrow"><?php echo $eyebrow_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php endif; ?>
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<<?php echo esc_html($heading_tag); ?> class="lf-hero-conversion__title"><?php echo $heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($heading_tag); ?>>
						</div>
					<?php else : ?>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-conversion__title"><?php echo $heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html($heading_tag); ?>>
					<?php endif; ?>
					<?php if ($subheading !== '') : ?>
						<p class="lf-hero-conversion__subtitle"><?php echo $subheading_html; ?></p>
					<?php endif; ?>
					<?php if (!empty($badge_items)) : ?>
						<ul class="lf-hero-conversion__badges lf-hero-chips" role="list">
							<?php foreach ($badge_items as $chip_row) : ?>
								<?php
								$chip_label = is_array($chip_row) ? (string) ($chip_row['label'] ?? '') : (string) $chip_row;
								$chip_icon = is_array($chip_row) ? (string) ($chip_row['icon'] ?? 'check') : 'check';
								if ($chip_label === '') {
									continue;
								}
								$chip_icon_html = function_exists('lf_icon')
									? lf_icon($chip_icon, ['class' => 'lf-hero-conversion__badge-icon-svg'])
									: '';
								?>
								<li class="lf-hero-conversion__badge lf-hero-chip" data-lf-chip-icon="<?php echo esc_attr($chip_icon); ?>">
									<span class="lf-hero-conversion__badge-icon" aria-hidden="true">
										<?php if ($chip_icon_html !== '') : ?>
											<?php echo $chip_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php else : ?>
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										<?php endif; ?>
									</span>
									<span class="lf-hero-conversion__badge-text" data-lf-hero-pill-text="1"><?php echo esc_html($chip_label); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ($show_cta_group || $primary_label !== '') : ?>
						<div class="lf-hero-conversion__actions">
							<?php if ($use_phone_link) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="<?php echo esc_attr($h_pri); ?>"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($primary_label); ?></a>
							<?php elseif ($cta_action === 'quote' || $cta_text !== '') : ?>
								<button type="button" class="<?php echo esc_attr($h_pri); ?>" data-lf-hero-form-focus="1" data-lf-quote-trigger="1" data-lf-quote-source="hero-primary"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($primary_label); ?></button>
							<?php elseif ($cta_url !== '') : ?>
								<a href="<?php echo esc_url($cta_url); ?>" class="<?php echo esc_attr($h_pri); ?>"<?php echo $h_pri_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($primary_label); ?></a>
							<?php endif; ?>
							<?php if ($secondary_text !== '') : ?>
								<?php if ($secondary_action === 'quote') : ?>
									<button type="button" class="<?php echo esc_attr($h_sec); ?>" data-lf-quote-trigger="1" data-lf-quote-source="hero-secondary"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></button>
								<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="<?php echo esc_attr($h_sec); ?>"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></a>
								<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
									<a href="<?php echo esc_url($secondary_url); ?>" class="<?php echo esc_attr($h_sec); ?>"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($secondary_text); ?></a>
								<?php endif; ?>
							<?php elseif ($cta_phone && !$use_phone_link) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="<?php echo esc_attr($h_sec); ?> lf-hero-conversion__cta-call"<?php echo $h_sec_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e('Call Now', 'leadsforward-core'); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ($trust_strip_html !== '') : ?>
						<div class="lf-hero-conversion__trust lf-hero-conversion__trust--desktop" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
							<?php echo $trust_strip_html; ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($mobile_trust_bits)) : ?>
						<p class="lf-hero-conversion__trust-mobile"><?php echo esc_html(implode(' · ', $mobile_trust_bits)); ?></p>
					<?php endif; ?>
				</div>
				<div class="lf-hero-conversion__aside lf-hero-conversion__aside--form">
					<?php if (function_exists('lf_quote_builder_render_inline_form_card')) : ?>
						<?php
						$inline_form_args = function_exists('lf_niche_homepage_inline_form_args')
							? lf_niche_homepage_inline_form_args()
							: ['form_id' => 'lf-hero-inline-form'];
						lf_quote_builder_render_inline_form_card($inline_form_args);
						?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
