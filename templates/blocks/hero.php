<?php
/**
 * Block: Hero. Section-level overrides from context (homepage); CTA from resolved stack.
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

$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$variant = $block['variant'] ?? 'default';

$heading = get_the_title() ?: __('Welcome', 'leadsforward-core');
$subheading = '';
$cta_text = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';

if (!empty($context['homepage']) && !empty($section)) {
	if (!empty($section['hero_headline'])) {
		$heading = $section['hero_headline'];
	} else {
		$default_heading = lf_get_option('lf_business_name', 'option') ?: get_bloginfo('name') ?: __('Welcome', 'leadsforward-core');
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

$cta_resolved_for_type = function_exists('lf_get_resolved_cta') ? lf_get_resolved_cta($context) : [];
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$cta_type = $cta_resolved_for_type['primary_type'] ?? 'text';
$use_phone_link = $cta_type === 'call' && $cta_phone && $cta_text;
?>
<section class="lf-block lf-block-hero lf-block-hero--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-hero__inner">
		<h1 class="lf-block-hero__title"><?php echo esc_html($heading); ?></h1>
		<?php if ($subheading !== '') : ?>
			<p class="lf-block-hero__subtitle"><?php echo esc_html($subheading); ?></p>
		<?php endif; ?>
		<?php if ($cta_text) : ?>
			<div class="lf-block-hero__cta">
				<?php if ($use_phone_link) : ?>
					<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-hero__cta-link"><?php echo esc_html($cta_text); ?></a>
				<?php else : ?>
					<span class="lf-block-hero__cta-text"><?php echo esc_html($cta_text); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
