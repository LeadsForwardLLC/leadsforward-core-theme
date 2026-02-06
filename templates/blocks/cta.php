<?php
/**
 * Block: CTA. Uses global CTAs or block/context override. Optional GHL form embed.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$variant = $block['variant'] ?? 'default';
$primary   = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';
$secondary = function_exists('lf_get_option') ? lf_get_option('lf_cta_secondary_text', 'option') : '';
$ghl_embed = function_exists('lf_get_option') ? lf_get_option('lf_cta_ghl_embed', 'option') : '';
?>
<section class="lf-block lf-block-cta lf-block-cta--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>">
	<div class="lf-block-cta__inner">
		<?php if ($primary) : ?>
			<p class="lf-block-cta__primary"><?php echo esc_html($primary); ?></p>
		<?php endif; ?>
		<?php if ($secondary) : ?>
			<p class="lf-block-cta__secondary"><?php echo esc_html($secondary); ?></p>
		<?php endif; ?>
		<?php if ($ghl_embed) : ?>
			<div class="lf-block-cta__embed">
				<?php echo wp_kses_post($ghl_embed); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
