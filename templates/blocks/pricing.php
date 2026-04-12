<?php
/**
 * Block: Pricing / Financing.
 *
 * @var array $block
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$style_attr = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';

$heading = (string) ($section['section_heading'] ?? '');
$intro = (string) ($section['section_intro'] ?? '');
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$factors = preg_split('/\r?\n+/', (string) ($section['pricing_factors'] ?? ''));
$factors = array_values(array_filter(array_map('trim', is_array($factors) ? $factors : [])));
$financing_enabled = (string) ($section['financing_enabled'] ?? '0') !== '0';
$financing_text = (string) ($section['financing_text'] ?? '');
$cta_text = (string) ($section['pricing_cta_text'] ?? '');
$cta_action = strtolower(trim((string) ($section['pricing_cta_action'] ?? 'quote')));
if (!in_array($cta_action, ['quote', 'call', 'link'], true)) {
	$cta_action = 'quote';
}
$cta_url = trim((string) ($section['pricing_cta_url'] ?? ''));
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
if ($cta_action === 'call' && $cta_phone === '') {
	$cta_action = 'quote';
}
if ($cta_action === 'link' && $cta_url === '') {
	$cta_action = 'quote';
}
if (function_exists('lf_sections_pricing_cta_button_classes') && function_exists('lf_sections_pricing_cta_data_attrs')) {
	$pricing_cta_cls = lf_sections_pricing_cta_button_classes($section);
	$pricing_cta_attr = lf_sections_pricing_cta_data_attrs($section);
} else {
	$pricing_cta_cls = 'lf-btn lf-btn--primary';
	$pricing_cta_attr = ' data-lf-cta-slot="primary" data-lf-btn-style="solid" data-lf-btn-tone="primary"';
}
?>
<section class="lf-block lf-block-pricing <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-pricing--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-pricing__inner">
		<?php if ($heading !== '' || $intro !== '') : ?>
			<header class="lf-block-pricing__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
				<?php if ($heading !== '') : ?><<?php echo esc_html($section_heading_tag); ?> class="lf-block-pricing__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>><?php endif; ?>
				<?php if ($intro !== '') : ?><p class="lf-block-pricing__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if (!empty($factors)) : ?>
			<ul class="lf-block-pricing__factors" role="list">
				<?php foreach ($factors as $factor) : ?>
					<li class="lf-block-pricing__factor"><?php echo esc_html($factor); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ($financing_enabled && $financing_text !== '') : ?>
			<div class="lf-block-pricing__financing" role="note">
				<strong class="lf-block-pricing__financing-label"><?php esc_html_e('Financing', 'leadsforward-core'); ?></strong>
				<p class="lf-block-pricing__financing-text"><?php echo esc_html($financing_text); ?></p>
			</div>
		<?php endif; ?>

		<?php if ($cta_text !== '') : ?>
			<div class="lf-block-pricing__actions">
				<?php if ($cta_action === 'link') : ?>
					<a class="<?php echo esc_attr($pricing_cta_cls); ?>" href="<?php echo esc_url($cta_url); ?>"<?php echo $pricing_cta_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></a>
				<?php elseif ($cta_action === 'call' && $cta_phone !== '') : ?>
					<a class="<?php echo esc_attr($pricing_cta_cls); ?>" href="<?php echo esc_url('tel:' . $cta_phone); ?>"<?php echo $pricing_cta_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($pricing_cta_cls); ?>" data-lf-quote-trigger="1" data-lf-quote-source="pricing"<?php echo $pricing_cta_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

