<?php
/**
 * Block: Map + NAP. Uses global business info. Map embed can be added via options later.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$variant = $block['variant'] ?? 'default';
$name    = function_exists('lf_get_option') ? lf_get_option('lf_business_name', 'option') : '';
$phone   = function_exists('lf_get_option') ? lf_get_option('lf_business_phone', 'option') : '';
$email   = function_exists('lf_get_option') ? lf_get_option('lf_business_email', 'option') : '';
$address = function_exists('lf_get_option') ? lf_get_option('lf_business_address', 'option') : '';
$geo     = function_exists('lf_get_option') ? lf_get_option('lf_business_geo', 'option') : null;
?>
<section class="lf-block lf-block-map-nap lf-block-map-nap--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>">
	<div class="lf-block-map-nap__inner">
		<address class="lf-block-map-nap__address">
			<?php if ($name) : ?>
				<span class="lf-block-map-nap__name"><?php echo esc_html($name); ?></span>
			<?php endif; ?>
			<?php if ($address) : ?>
				<span class="lf-block-map-nap__street"><?php echo nl2br(esc_html($address)); ?></span>
			<?php endif; ?>
			<?php if ($phone) : ?>
				<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>" class="lf-block-map-nap__phone"><?php echo esc_html($phone); ?></a>
			<?php endif; ?>
			<?php if ($email) : ?>
				<a href="mailto:<?php echo esc_attr($email); ?>" class="lf-block-map-nap__email"><?php echo esc_html($email); ?></a>
			<?php endif; ?>
		</address>
		<?php if ($geo && !empty($geo['lat']) && !empty($geo['lng'])) : ?>
			<div class="lf-block-map-nap__map" data-lat="<?php echo esc_attr((string) $geo['lat']); ?>" data-lng="<?php echo esc_attr((string) $geo['lng']); ?>">
				<p class="lf-block-map-nap__map-fallback"><?php esc_html_e('Map placeholder (embed or iframe can be added via options).', 'leadsforward-core'); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
