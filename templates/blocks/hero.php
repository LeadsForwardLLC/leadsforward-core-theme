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

$eyebrow = __('Licensed • Insured • Local', 'leadsforward-core');

$heading = get_the_title() ?: __('Quality Local Service', 'leadsforward-core');
$subheading = '';
$cta_text = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';
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
	$cta_resolved = lf_get_resolved_cta($context);
	$cta_text = !empty($section['hero_cta_override']) ? $section['hero_cta_override'] : $cta_resolved['primary_text'];
	if ($cta_text && function_exists('lf_copy_template')) {
		$cta_text = lf_copy_template('cta_microcopy', $cta_text, []);
	}
	if ($cta_text === '') {
		$cta_text = $cta_resolved['primary_text'] ?? '';
	}
} elseif (function_exists('lf_get_resolved_cta')) {
	$cta_resolved = lf_get_resolved_cta([]);
	$cta_text = $cta_resolved['primary_text'];
}

if ($business_name !== '') {
	$heading = str_replace(['[Your Business]', '[Your Business Name]', '{business_name}'], $business_name, $heading);
}

$cta_resolved_for_type = function_exists('lf_get_resolved_cta') ? lf_get_resolved_cta($context) : [];
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$cta_type = $cta_resolved_for_type['primary_type'] ?? 'text';
$cta_action = $cta_resolved_for_type['primary_action'] ?? 'link';
$cta_url = $cta_resolved_for_type['primary_url'] ?? '';
$use_phone_link = $cta_type === 'call' && $cta_phone && $cta_text;
$secondary_text = $cta_resolved_for_type['secondary_text'] ?? '';
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
?>
<section class="lf-block lf-block-hero lf-block-hero--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-hero__bg" aria-hidden="true"></div>
	<div class="lf-block-hero__inner">
		<div class="lf-block-hero__grid">
			<div class="lf-block-hero__content">
				<?php if ($eyebrow !== '') : ?>
					<p class="lf-block-hero__eyebrow"><?php echo esc_html($eyebrow); ?></p>
				<?php endif; ?>
				<h1 class="lf-block-hero__title"><?php echo esc_html($heading); ?></h1>
				<?php if ($subheading !== '') : ?>
					<p class="lf-block-hero__subtitle"><?php echo esc_html($subheading); ?></p>
				<?php endif; ?>
				<?php if ($cta_text || $secondary_text !== '') : ?>
					<div class="lf-block-hero__cta">
						<?php if ($cta_text) : ?>
							<?php if ($cta_action === 'quote') : ?>
								<button type="button" class="lf-block-hero__cta-text lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="hero"><?php echo esc_html($cta_text); ?></button>
							<?php elseif ($use_phone_link) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-hero__cta-link lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
							<?php elseif ($cta_url !== '') : ?>
								<a href="<?php echo esc_url($cta_url); ?>" class="lf-block-hero__cta-link lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
							<?php else : ?>
								<span class="lf-block-hero__cta-text lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></span>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($secondary_text !== '') : ?>
							<span class="lf-block-hero__cta-secondary lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="lf-block-hero__trust" role="group" aria-label="<?php esc_attr_e('Trust', 'leadsforward-core'); ?>">
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
					<p class="lf-block-hero__phone-wrap">
						<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-hero__phone"><?php echo esc_html($cta_phone); ?></a>
					</p>
				<?php endif; ?>
			</div>
			<div class="lf-block-hero__proof">
				<?php if ($variant === 'b' && $show_form_in_hero) : ?>
					<div class="lf-block-hero__form">
						<div class="lf-block-hero__form-head"><?php esc_html_e('Get a fast response', 'leadsforward-core'); ?></div>
						<?php echo wp_kses_post($cta_resolved_for_type['ghl_embed']); ?>
					</div>
				<?php elseif ($variant === 'c' && $latest_testimonial) : ?>
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
						<?php if (!empty($services)) : ?>
							<div class="lf-block-hero__card-subtitle"><?php esc_html_e('Popular services', 'leadsforward-core'); ?></div>
							<ul class="lf-block-hero__card-services" role="list">
								<?php foreach ($services as $svc) : ?>
									<li><a href="<?php echo esc_url(get_permalink($svc)); ?>"><?php echo esc_html(get_the_title($svc)); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
