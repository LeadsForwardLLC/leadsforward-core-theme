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
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'soft') : '';
$heading_tag = $context['heading_tag'] ?? 'h1';

$eyebrow = __('Licensed • Insured • Local', 'leadsforward-core');

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
	if ($post_type === 'lf_service') {
		$excerpt = get_the_excerpt();
		$subheading = $excerpt !== '' ? $excerpt : wp_trim_words(wp_strip_all_tags(get_the_content(null, false)), 22);
	}
	if ($post_type === 'lf_service_area' && function_exists('get_field')) {
		$state = get_field('lf_service_area_state');
		if ($state) {
			$subheading = sprintf(__('Serving %1$s, %2$s', 'leadsforward-core'), get_the_title(), $state);
		}
	}
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
$review_count = 0;
if (function_exists('wp_count_posts')) {
	$counts = wp_count_posts('lf_testimonial');
	$review_count = isset($counts->publish) ? (int) $counts->publish : 0;
}
$show_trust_strip = $review_count > 0;
$services = get_posts([
	'post_type'      => 'lf_service',
	'posts_per_page' => 3,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
$latest_testimonial = null;
if (post_type_exists('lf_testimonial')) {
	$testimonials = get_posts([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	if (!empty($testimonials)) {
		$latest_testimonial = $testimonials[0];
	}
}
$show_form_in_hero = $cta_type === 'form' && !empty($cta_resolved_for_type['ghl_embed']);

if (!$primary_enabled) {
	$cta_text = '';
}
if (!$secondary_enabled) {
	$secondary_text = '';
}
$show_cta_group = ($cta_text !== '' || $secondary_text !== '');
$placeholder_id = function_exists('lf_get_placeholder_image_id') ? lf_get_placeholder_image_id() : 0;
$placeholder_alt = $business_name ? $business_name : __('Trusted local service', 'leadsforward-core');
?>
<section class="lf-block lf-block-hero <?php echo esc_attr($bg_class ?: 'lf-surface-soft'); ?> lf-block-hero--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-hero__bg" aria-hidden="true"></div>
	<div class="lf-block-hero__inner">
		<?php if ($variant === 'a') : ?>
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
					<p class="lf-hero-stack__subtitle"><?php echo esc_html($subheading); ?></p>
				<?php endif; ?>
				<div class="lf-hero-stack__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
					<?php if ($show_trust_strip) : ?>
						<span class="lf-block-hero__stars" aria-hidden="true">
							<?php for ($i = 0; $i < 5; $i++) : ?>
								<svg class="lf-block-hero__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							<?php endfor; ?>
						</span>
						<span class="lf-block-hero__badge"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'leadsforward-core'), $review_count)); ?></span>
					<?php else : ?>
						<span class="lf-block-hero__badge"><?php esc_html_e('Trusted local service', 'leadsforward-core'); ?></span>
					<?php endif; ?>
				</div>
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
				<?php if ($show_cta_group && $cta_phone && $cta_phone !== $cta_text) : ?>
					<p class="lf-hero-stack__phone">
						<a href="tel:<?php echo esc_attr($cta_phone); ?>"><?php echo esc_html($cta_phone); ?></a>
					</p>
				<?php endif; ?>
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
						<p class="lf-hero-form__subtitle"><?php echo esc_html($subheading); ?></p>
					<?php endif; ?>
					<div class="lf-hero-form__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
						<?php if ($show_trust_strip) : ?>
							<span class="lf-block-hero__stars" aria-hidden="true">
								<?php for ($i = 0; $i < 5; $i++) : ?>
									<svg class="lf-block-hero__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</span>
							<span class="lf-block-hero__badge"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'leadsforward-core'), $review_count)); ?></span>
						<?php else : ?>
							<span class="lf-block-hero__badge"><?php esc_html_e('Trusted local service', 'leadsforward-core'); ?></span>
						<?php endif; ?>
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
						<?php if ($show_cta_group && $cta_phone && $cta_phone !== $cta_text) : ?>
							<p class="lf-hero-form__panel-note"><?php echo esc_html($cta_phone); ?></p>
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
						<p class="lf-hero-visual__subtitle"><?php echo esc_html($subheading); ?></p>
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
						<?php if ($show_trust_strip) : ?>
							<span class="lf-block-hero__stars" aria-hidden="true">
								<?php for ($i = 0; $i < 5; $i++) : ?>
									<svg class="lf-block-hero__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</span>
							<span class="lf-block-hero__badge"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'leadsforward-core'), $review_count)); ?></span>
						<?php else : ?>
							<span class="lf-block-hero__badge"><?php esc_html_e('Trusted local service', 'leadsforward-core'); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="lf-hero-visual__media">
					<div class="lf-hero-visual__image">
						<?php if ($placeholder_id) : ?>
							<?php echo wp_get_attachment_image($placeholder_id, 'large', false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr($placeholder_alt)]); ?>
						<?php elseif ($latest_testimonial) : ?>
							<div class="lf-block-hero__quote">
								<p class="lf-block-hero__quote-text"><?php echo esc_html(get_the_excerpt($latest_testimonial)); ?></p>
								<p class="lf-block-hero__quote-meta"><?php echo esc_html(get_the_title($latest_testimonial)); ?></p>
							</div>
						<?php else : ?>
							<div class="lf-block-hero__card">
								<div class="lf-block-hero__card-title"><?php esc_html_e('Why homeowners choose us', 'leadsforward-core'); ?></div>
								<ul class="lf-block-hero__card-list" role="list">
									<li><?php esc_html_e('Fast response and clear pricing', 'leadsforward-core'); ?></li>
									<li><?php esc_html_e('Licensed, insured, and local', 'leadsforward-core'); ?></li>
									<li><?php esc_html_e('Clean work backed by warranty', 'leadsforward-core'); ?></li>
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
						<p class="lf-hero-split__subtitle"><?php echo esc_html($subheading); ?></p>
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
						<?php if ($show_trust_strip) : ?>
							<span class="lf-block-hero__stars" aria-hidden="true">
								<?php for ($i = 0; $i < 5; $i++) : ?>
									<svg class="lf-block-hero__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</span>
							<span class="lf-block-hero__badge"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'leadsforward-core'), $review_count)); ?></span>
						<?php else : ?>
							<span class="lf-block-hero__badge"><?php esc_html_e('Trusted local service', 'leadsforward-core'); ?></span>
						<?php endif; ?>
					</div>
					<?php if ($cta_phone && $cta_phone !== $cta_text) : ?>
						<p class="lf-hero-split__phone">
							<a href="tel:<?php echo esc_attr($cta_phone); ?>"><?php echo esc_html($cta_phone); ?></a>
						</p>
					<?php endif; ?>
				</div>
				<div class="lf-hero-split__proof">
					<div class="lf-block-hero__card">
						<div class="lf-block-hero__card-title"><?php esc_html_e('Why homeowners choose us', 'leadsforward-core'); ?></div>
						<ul class="lf-block-hero__card-list" role="list">
							<li><?php esc_html_e('Fast response and clear pricing', 'leadsforward-core'); ?></li>
							<li><?php esc_html_e('Licensed, insured, and local', 'leadsforward-core'); ?></li>
							<li><?php esc_html_e('Clean work backed by warranty', 'leadsforward-core'); ?></li>
						</ul>
						<?php if (!empty($services)) : ?>
							<div class="lf-block-hero__card-subtitle"><?php esc_html_e('Popular services', 'leadsforward-core'); ?></div>
							<ul class="lf-block-hero__card-services" role="list">
								<?php foreach ($services as $svc) : ?>
									<li><a href="<?php echo esc_url(get_permalink($svc)); ?>"><?php echo esc_html(get_the_title($svc)); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
