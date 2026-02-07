<?php
/**
 * Block: CTA. Resolved CTA (section > homepage > global). Single GHL source; phone link when type=call.
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context'] optional homepage section overrides
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$variant = $block['variant'] ?? 'default';
$headline = !empty($section['cta_headline']) ? $section['cta_headline'] : '';
$eyebrow = '';
$offer_label = '';
$offer_items = [];
if ($variant === 'b') {
	$eyebrow = __('Get a fast response', 'leadsforward-core');
} elseif ($variant === 'c') {
	$eyebrow = __('Limited availability', 'leadsforward-core');
	$offer_label = __('Offer includes', 'leadsforward-core');
	$offer_items = [
		__('Fast, no-obligation estimate', 'leadsforward-core'),
		__('Priority scheduling window', 'leadsforward-core'),
		__('Clear next steps in one call', 'leadsforward-core'),
	];
}

$cta = function_exists('lf_get_resolved_cta') ? lf_get_resolved_cta($context) : [
	'primary_text'   => lf_get_option('lf_cta_primary_text', 'option'),
	'secondary_text' => lf_get_option('lf_cta_secondary_text', 'option'),
	'ghl_embed'     => lf_get_option('lf_cta_ghl_embed', 'option'),
	'primary_type'   => 'text',
];
$primary   = $cta['primary_text'] ?? '';
$secondary = $cta['secondary_text'] ?? '';
if (!empty($context['homepage']) && function_exists('lf_copy_template')) {
	$primary = lf_copy_template('cta_microcopy', $primary, []);
	if ($primary === '') {
		$primary = $cta['primary_text'] ?? '';
	}
}
$ghl_embed = $cta['ghl_embed'] ?? '';
$cta_type  = $cta['primary_type'] ?? 'text';
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$use_phone_link = $cta_type === 'call' && $cta_phone && $primary;
$show_form = ($cta_type === 'form' && $ghl_embed) || ($cta_type !== 'call' && $ghl_embed);
?>
<section class="lf-block lf-block-cta lf-surface-dark lf-block-cta--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" aria-label="<?php esc_attr_e('Call to action', 'leadsforward-core'); ?>">
	<div class="lf-block-cta__inner">
		<div class="lf-block-cta__content">
			<?php if ($eyebrow !== '') : ?>
				<p class="lf-block-cta__eyebrow"><?php echo esc_html($eyebrow); ?></p>
			<?php endif; ?>
			<?php if ($headline !== '') : ?>
				<h2 class="lf-block-cta__headline"><?php echo esc_html($headline); ?></h2>
			<?php endif; ?>
			<?php if ($primary) : ?>
				<p class="lf-block-cta__primary">
					<?php if ($use_phone_link) : ?>
						<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-cta__primary-link"><?php echo esc_html($primary); ?></a>
					<?php else : ?>
						<span class="lf-block-cta__primary-text"><?php echo esc_html($primary); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ($secondary) : ?>
				<p class="lf-block-cta__secondary"><?php echo esc_html($secondary); ?></p>
			<?php endif; ?>
			<?php if ($variant === 'c' && !empty($offer_items)) : ?>
				<div class="lf-block-cta__offer" role="note" aria-label="<?php esc_attr_e('Offer details', 'leadsforward-core'); ?>">
					<span class="lf-block-cta__offer-label"><?php echo esc_html($offer_label); ?></span>
					<ul class="lf-block-cta__offer-list" role="list">
						<?php foreach ($offer_items as $item) : ?>
							<li class="lf-block-cta__offer-item"><?php echo esc_html($item); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php if ($show_form) : ?>
			<div class="lf-block-cta__form">
				<div class="lf-block-cta__embed">
					<?php echo wp_kses_post($ghl_embed); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
