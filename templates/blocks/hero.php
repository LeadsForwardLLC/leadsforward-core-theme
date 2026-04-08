<?php
/**
 * Block: Hero. Section-level overrides from context (homepage); CTA from resolved stack.
 * Layout: H1 → Subheadline → Primary + secondary CTA buttons → Trust row (stars + count).
 * Any image added here must use loading="lazy".
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
$variant  = $block['variant'] ?? 'default';
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
	? sprintf(__('Homeowners in %1$s, %2$s', 'leadsforward-core'), $business_city, $business_state)
	: ($business_city !== '' ? sprintf(__('Homeowners in %s', 'leadsforward-core'), $business_city) : __('Licensed • Insured • Local', 'leadsforward-core'));
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
$hero_media = $section['hero_media'] ?? 'none';
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
			<img src="<?php echo esc_url(LF_THEME_URI . '/assets/images/customers.png'); ?>" alt="<?php esc_attr_e('Customers', 'leadsforward-core'); ?>" width="50" height="50" loading="lazy" decoding="async" />
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
// Services list removed from hero card.
$latest_testimonial = null;
$latest_testimonial_text = '';
if (post_type_exists('lf_testimonial')) {
	$testimonials = get_posts([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => 6,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	foreach ($testimonials as $testimonial) {
		$rating_value = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $testimonial->ID) : 5;
		if ($rating_value <= 1) {
			continue;
		}
		$latest_testimonial = $testimonial;
		if ($latest_testimonial && function_exists('get_field')) {
			$latest_testimonial_text = (string) get_field('lf_testimonial_review_text', $latest_testimonial->ID);
		}
		break;
	}
}
$show_form_in_hero = $cta_type === 'form' && !empty($cta_resolved_for_type['ghl_embed']);
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
// Empty stored lines (including legacy "" after a bad save) should not blank the Authority Split card;
// fall back to defaults. Non-empty lists always win.
if ($proof_items === []) {
	$proof_items = $proof_default_items;
}
// Left pills (`hero_chip_bullets`) are separate from proof card lines (`hero_proof_bullets`).
// If `hero_chip_bullets` was never saved, mirror proof items for backward compatibility.
if (array_key_exists('hero_chip_bullets', $section)) {
	$chip_raw = (string) ($section['hero_chip_bullets'] ?? '');
	$chip_items = function_exists('lf_sections_parse_lines')
		? lf_sections_parse_lines($chip_raw)
		: preg_split('/\r\n|\r|\n/', $chip_raw);
	$chip_items = array_values(array_filter(array_map('trim', is_array($chip_items) ? $chip_items : [])));
} else {
	$chip_items = $proof_items;
}
$show_hero_chips = $variant !== 'internal' && !empty($chip_items);
$hero_chips_html = '';
if ($show_hero_chips) {
	ob_start();
	?>
	<ul class="lf-hero-chips" role="list">
		<?php foreach ($chip_items as $chip) : ?>
			<li class="lf-hero-chip"><?php echo esc_html($chip); ?></li>
		<?php endforeach; ?>
	</ul>
	<?php
	$hero_chips_html = (string) ob_get_clean();
}

if (!$primary_enabled) {
	$cta_text = '';
}
if (!$secondary_enabled) {
	$secondary_text = '';
}
$show_cta_group = ($cta_text !== '' || $secondary_text !== '');
$placeholder_id = function_exists('lf_get_placeholder_image_id') ? lf_get_placeholder_image_id() : 0;
$placeholder_alt = $business_name ? $business_name : __('Trusted local service', 'leadsforward-core');
$hero_bg_mode = (string) ($section['hero_background_mode'] ?? 'image');
if (!in_array($hero_bg_mode, ['color', 'image', 'video'], true)) {
	$hero_bg_mode = 'image';
}
$hero_bg_stored_image_id = isset($section['hero_background_image_id']) ? (int) $section['hero_background_image_id'] : 0;
$hero_bg_stored_video_id = isset($section['hero_background_video_id']) ? (int) $section['hero_background_video_id'] : 0;
$hero_bg_id = 0;
if ($hero_bg_mode === 'image' && $variant !== 'c') {
	$hero_bg_id = $hero_bg_stored_image_id;
	if ($hero_bg_id === 0) {
		$hero_bg_id = (int) get_post_thumbnail_id(get_queried_object_id());
	}
	if ($hero_bg_id === 0 && $placeholder_id) {
		$hero_bg_id = (int) $placeholder_id;
	}
}
$hero_bg_url = $hero_bg_id ? wp_get_attachment_image_url($hero_bg_id, 'full') : '';
$hero_bg_class = '';
$hero_bg_style = '';
if ($hero_bg_url && $hero_bg_mode === 'image' && $variant !== 'c') {
	$hero_bg_overlay = $variant === 'a' ? '0.45' : '0.82';
	$hero_bg_class = ' lf-block-hero--has-bg';
	$hero_bg_style = sprintf(
		'--lf-hero-bg-image: url(\'%s\'); --lf-hero-bg-overlay-opacity: %s;',
		esc_url($hero_bg_url),
		$hero_bg_overlay
	);
}
$hero_video_url = '';
$hero_video_mime = 'video/mp4';
if ($hero_bg_mode === 'video' && $variant !== 'c' && $hero_bg_stored_video_id > 0) {
	$vurl = wp_get_attachment_url($hero_bg_stored_video_id);
	$hero_video_url = is_string($vurl) ? $vurl : '';
	$vm = get_post_mime_type($hero_bg_stored_video_id);
	if (is_string($vm) && $vm !== '') {
		$hero_video_mime = $vm;
	}
}
$hero_video_class = ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant !== 'c') ? ' lf-block-hero--has-video' : '';
$hero_video_overlay_css = '';
if ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant !== 'c') {
	$hero_video_overlay_css = sprintf('--lf-hero-bg-overlay-opacity: %s;', $variant === 'a' ? '0.45' : '0.55');
}
$hero_outer_class = trim('lf-block lf-block-hero ' . ($surface['class'] ?? '') . ' lf-block-hero--' . $variant . $hero_bg_class . $hero_video_class);
$hero_combined_style = trim(
	($surface['style'] ?? '')
	. ($hero_bg_style !== '' ? ' ' . $hero_bg_style : '')
	. ($hero_video_overlay_css !== '' ? ' ' . $hero_video_overlay_css : '')
);
?>
<section class="<?php echo esc_attr($hero_outer_class); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-lf-hero-bg-mode="<?php echo esc_attr($hero_bg_mode); ?>" data-lf-hero-bg-image-id="<?php echo esc_attr((string) $hero_bg_stored_image_id); ?>" data-lf-hero-bg-video-id="<?php echo esc_attr((string) $hero_bg_stored_video_id); ?>" data-lf-hero-trust-strip-setting="<?php echo esc_attr($hero_trust_strip_enabled ? '1' : '0'); ?>"<?php echo $hero_combined_style !== '' ? ' style="' . esc_attr($hero_combined_style) . '"' : ''; ?>>
	<div class="lf-block-hero__bg" aria-hidden="true">
		<?php if ($hero_video_url !== '' && $hero_bg_mode === 'video' && $variant !== 'c') : ?>
			<video class="lf-block-hero__video" autoplay muted loop playsinline>
				<source src="<?php echo esc_url($hero_video_url); ?>" type="<?php echo esc_attr($hero_video_mime); ?>" />
			</video>
		<?php endif; ?>
	</div>
	<div class="lf-block-hero__inner">
		<?php if ($variant === 'internal') : ?>
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
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php elseif ($cta_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="hero-basic"><?php echo esc_html($cta_text); ?></button>
								<?php elseif ($cta_url !== '') : ?>
									<a href="<?php echo esc_url($cta_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($secondary_text !== '') : ?>
								<?php if ($secondary_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="hero-basic-secondary"><?php echo esc_html($secondary_text); ?></button>
								<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
									<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php if ($show_hero_image) : ?>
					<div class="lf-hero-basic__media">
						<div class="lf-hero-basic__image">
							<?php echo wp_get_attachment_image($hero_image_id, 'large', false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr($hero_image_alt)]); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php elseif ($variant === 'a') : ?>
			<div class="lf-hero-stack">
				<?php if ($eyebrow !== '') : ?>
					<p class="lf-hero-stack__eyebrow"><?php echo esc_html($eyebrow); ?></p>
				<?php endif; ?>
				<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
				<?php if ($icon_left) : ?>
					<div class="lf-heading-row">
						<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-stack__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
					</div>
				<?php else : ?>
					<<?php echo esc_html($heading_tag); ?> class="lf-hero-stack__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
				<?php endif; ?>
				<?php if ($subheading !== '') : ?>
					<p class="lf-hero-stack__subtitle"><?php echo $subheading_html; ?></p>
				<?php endif; ?>
				<?php if ($hero_chips_html !== '') : ?>
					<div class="lf-hero-stack__chips"><?php echo $hero_chips_html; ?></div>
				<?php endif; ?>
				<?php if ($show_cta_group) : ?>
					<div class="lf-hero-stack__actions">
						<?php if ($cta_text) : ?>
							<?php if ($use_phone_link) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--primary lf-hero-stack__primary"><?php echo esc_html($cta_text); ?></a>
							<?php elseif ($cta_action === 'quote') : ?>
								<button type="button" class="lf-btn lf-btn--primary lf-hero-stack__primary" data-lf-quote-trigger="1" data-lf-quote-source="hero-stack"><?php echo esc_html($cta_text); ?></button>
							<?php elseif ($cta_url !== '') : ?>
								<a href="<?php echo esc_url($cta_url); ?>" class="lf-btn lf-btn--primary lf-hero-stack__primary"><?php echo esc_html($cta_text); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($secondary_text !== '') : ?>
							<?php if ($secondary_action === 'quote') : ?>
								<button type="button" class="lf-btn lf-btn--secondary lf-hero-stack__secondary" data-lf-quote-trigger="1" data-lf-quote-source="hero-stack-secondary"><?php echo esc_html($secondary_text); ?></button>
							<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--secondary lf-hero-stack__secondary"><?php echo esc_html($secondary_text); ?></a>
							<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
								<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary lf-hero-stack__secondary"><?php echo esc_html($secondary_text); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="lf-hero-stack__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
					<?php echo $trust_strip_html; ?>
				</div>
			</div>
		<?php elseif ($variant === 'b') : ?>
			<div class="lf-hero-form">
				<div class="lf-hero-form__content">
					<?php if ($eyebrow !== '') : ?>
						<p class="lf-hero-form__eyebrow"><?php echo esc_html($eyebrow); ?></p>
					<?php endif; ?>
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<<?php echo esc_html($heading_tag); ?> class="lf-hero-form__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
						</div>
					<?php else : ?>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-form__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
					<?php endif; ?>
					<?php if ($subheading !== '') : ?>
						<p class="lf-hero-form__subtitle"><?php echo $subheading_html; ?></p>
					<?php endif; ?>
					<?php if ($hero_chips_html !== '') : ?>
						<div class="lf-hero-form__chips"><?php echo $hero_chips_html; ?></div>
					<?php endif; ?>
					<div class="lf-hero-form__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
						<?php echo $trust_strip_html; ?>
					</div>
				</div>
				<div class="lf-hero-form__panel">
					<div class="lf-hero-form__panel-inner">
						<p class="lf-hero-form__panel-title"><?php esc_html_e('Start your free estimate', 'leadsforward-core'); ?></p>
						<p class="lf-hero-form__panel-text"><?php esc_html_e('Get a fast response with clear pricing and next steps.', 'leadsforward-core'); ?></p>
						<?php if ($cta_text) : ?>
							<button type="button" class="lf-btn lf-btn--primary lf-hero-form__panel-button" data-lf-quote-trigger="1" data-lf-quote-source="hero-form"><?php echo esc_html($cta_text); ?></button>
						<?php endif; ?>
						<?php if ($secondary_text !== '') : ?>
							<?php if ($secondary_action === 'call' && $cta_phone) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--secondary lf-hero-form__panel-secondary"><?php echo esc_html($secondary_text); ?></a>
							<?php elseif ($secondary_action === 'quote') : ?>
								<button type="button" class="lf-btn lf-btn--secondary lf-hero-form__panel-secondary" data-lf-quote-trigger="1" data-lf-quote-source="hero-form-secondary"><?php echo esc_html($secondary_text); ?></button>
							<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
								<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary lf-hero-form__panel-secondary"><?php echo esc_html($secondary_text); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php elseif ($variant === 'c') : ?>
			<div class="lf-hero-visual">
				<div class="lf-hero-visual__content">
					<?php if ($eyebrow !== '') : ?>
						<p class="lf-hero-visual__eyebrow"><?php echo esc_html($eyebrow); ?></p>
					<?php endif; ?>
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<<?php echo esc_html($heading_tag); ?> class="lf-hero-visual__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
						</div>
					<?php else : ?>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-visual__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
					<?php endif; ?>
					<?php if ($subheading !== '') : ?>
						<p class="lf-hero-visual__subtitle"><?php echo $subheading_html; ?></p>
					<?php endif; ?>
					<?php if ($hero_chips_html !== '') : ?>
						<div class="lf-hero-visual__chips"><?php echo $hero_chips_html; ?></div>
					<?php endif; ?>
					<?php if ($show_cta_group) : ?>
						<div class="lf-hero-visual__actions">
							<?php if ($cta_text) : ?>
								<?php if ($use_phone_link) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php elseif ($cta_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="hero-visual"><?php echo esc_html($cta_text); ?></button>
								<?php elseif ($cta_url !== '') : ?>
									<a href="<?php echo esc_url($cta_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($secondary_text !== '') : ?>
								<?php if ($secondary_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="hero-visual-secondary"><?php echo esc_html($secondary_text); ?></button>
								<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
									<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<div class="lf-hero-visual__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
						<?php echo $trust_strip_html; ?>
					</div>
				</div>
				<div class="lf-hero-visual__media">
					<div class="lf-hero-visual__image">
						<?php if ($show_hero_image) : ?>
							<?php echo wp_get_attachment_image($hero_image_id, 'large', false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr($hero_image_alt)]); ?>
						<?php elseif ($placeholder_id) : ?>
							<?php echo wp_get_attachment_image($placeholder_id, 'large', false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr($placeholder_alt)]); ?>
						<?php elseif ($latest_testimonial) : ?>
							<div class="lf-block-hero__quote">
								<p class="lf-block-hero__quote-text"><?php echo esc_html($latest_testimonial_text); ?></p>
								<p class="lf-block-hero__quote-meta"><?php echo esc_html(get_the_title($latest_testimonial)); ?></p>
							</div>
						<?php else : ?>
							<div class="lf-block-hero__card">
								<div class="lf-block-hero__card-title"><?php echo esc_html($proof_title ?: $proof_default_title); ?></div>
								<ul class="lf-block-hero__card-list" role="list">
									<?php foreach ($proof_items as $item) : ?>
										<li><span class="lf-block-hero__card-item-text"><?php echo wp_kses( (string) $item, function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></span></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
					<?php if ($review_count > 0) : ?>
						<div class="lf-hero-visual__overlay">
							<span class="lf-block-hero__stars" aria-hidden="true">
								<?php for ($i = 0; $i < 5; $i++) : ?>
									<svg class="lf-block-hero__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</span>
							<span class="lf-hero-visual__overlay-text"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'leadsforward-core'), $review_count)); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="lf-hero-split">
				<div class="lf-hero-split__content">
					<?php if ($eyebrow !== '') : ?>
						<p class="lf-hero-split__eyebrow"><?php echo esc_html($eyebrow); ?></p>
					<?php endif; ?>
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<<?php echo esc_html($heading_tag); ?> class="lf-hero-split__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
						</div>
					<?php else : ?>
						<<?php echo esc_html($heading_tag); ?> class="lf-hero-split__title"><?php echo esc_html($heading); ?></<?php echo esc_html($heading_tag); ?>>
					<?php endif; ?>
					<?php if ($subheading !== '') : ?>
						<p class="lf-hero-split__subtitle"><?php echo $subheading_html; ?></p>
					<?php endif; ?>
					<?php if ($hero_chips_html !== '') : ?>
						<div class="lf-hero-split__chips"><?php echo $hero_chips_html; ?></div>
					<?php endif; ?>
					<?php if ($show_cta_group) : ?>
						<div class="lf-hero-split__actions">
							<?php if ($cta_text) : ?>
								<?php if ($use_phone_link) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php elseif ($cta_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="hero-split"><?php echo esc_html($cta_text); ?></button>
								<?php elseif ($cta_url !== '') : ?>
									<a href="<?php echo esc_url($cta_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($secondary_text !== '') : ?>
								<?php if ($secondary_action === 'quote') : ?>
									<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="hero-split-secondary"><?php echo esc_html($secondary_text); ?></button>
								<?php elseif ($secondary_action === 'call' && $cta_phone) : ?>
									<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php elseif ($secondary_action === 'link' && $secondary_url !== '') : ?>
									<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<div class="lf-hero-split__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
						<?php echo $trust_strip_html; ?>
					</div>
				</div>
				<div class="lf-hero-split__proof">
					<div class="lf-block-hero__card">
						<div class="lf-block-hero__card-title"><?php echo esc_html($proof_title ?: $proof_default_title); ?></div>
						<ul class="lf-block-hero__card-list" role="list">
							<?php foreach ($proof_items as $item) : ?>
								<li><span class="lf-block-hero__card-item-text"><?php echo wp_kses( (string) $item, function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
