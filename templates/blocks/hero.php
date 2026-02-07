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

$eyebrow = '';
$offer_label = '';
$offer_items = [];
if ($variant === 'b') {
	$eyebrow = __('Trusted local specialists', 'leadsforward-core');
} elseif ($variant === 'c') {
	$eyebrow = __('Limited appointments available', 'leadsforward-core');
	$offer_label = __('Offer highlights', 'leadsforward-core');
	$offer_items = [
		__('Fast, no-obligation estimate', 'leadsforward-core'),
		__('Clear next steps in one call', 'leadsforward-core'),
		__('Priority scheduling window', 'leadsforward-core'),
	];
}

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
$use_phone_link = $cta_type === 'call' && $cta_phone && $cta_text;
$secondary_text = $cta_resolved_for_type['secondary_text'] ?? '';
$review_count = 0;
if (function_exists('wp_count_posts')) {
	$counts = wp_count_posts('lf_testimonial');
	$review_count = isset($counts->publish) ? (int) $counts->publish : 0;
}
$show_trust_strip = $review_count > 0;
?>
<section class="lf-block lf-block-hero lf-block-hero--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-hero__bg" aria-hidden="true"></div>
	<div class="lf-block-hero__inner">
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
					<?php if ($use_phone_link) : ?>
						<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-hero__cta-link lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></a>
					<?php else : ?>
						<span class="lf-block-hero__cta-text lf-btn lf-btn--primary"><?php echo esc_html($cta_text); ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($secondary_text !== '') : ?>
					<span class="lf-block-hero__cta-secondary lf-btn lf-btn--secondary"><?php echo esc_html($secondary_text); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ($variant === 'c' && !empty($offer_items)) : ?>
			<div class="lf-block-hero__offer" role="note" aria-label="<?php esc_attr_e('Offer highlights', 'leadsforward-core'); ?>">
				<span class="lf-block-hero__offer-label"><?php echo esc_html($offer_label); ?></span>
				<ul class="lf-block-hero__offer-list" role="list">
					<?php foreach ($offer_items as $item) : ?>
						<li class="lf-block-hero__offer-item"><?php echo esc_html($item); ?></li>
					<?php endforeach; ?>
				</ul>
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
				<span class="lf-block-hero__badge"><?php esc_html_e('Licensed & Insured', 'leadsforward-core'); ?></span>
			<?php endif; ?>
		</div>
		<?php if ($cta_phone && $cta_phone !== $cta_text) : ?>
			<p class="lf-block-hero__phone-wrap">
				<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-hero__phone"><?php echo esc_html($cta_phone); ?></a>
			</p>
		<?php endif; ?>
	</div>
</section>
