<?php
/**
 * Block: Hero. Semantic section; layout via variant attribute.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$variant = $block['variant'] ?? 'default';
$heading = get_the_title() ?: __('Welcome', 'leadsforward-core');
$subheading = '';
$cta_text = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';
?>
<section class="lf-block lf-block-hero lf-block-hero--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>">
	<div class="lf-block-hero__inner">
		<h1 class="lf-block-hero__title"><?php echo esc_html($heading); ?></h1>
		<?php if (!empty($subheading)) : ?>
			<p class="lf-block-hero__subtitle"><?php echo esc_html($subheading); ?></p>
		<?php endif; ?>
		<?php if ($cta_text) : ?>
			<p class="lf-block-hero__cta"><?php echo esc_html($cta_text); ?></p>
		<?php endif; ?>
	</div>
</section>
