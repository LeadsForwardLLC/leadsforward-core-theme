<?php
/**
 * Block: Logo strip (certifications / partners).
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
$logos_raw = (string) ($section['logo_strip_logos'] ?? '');
$logo_ids = preg_split('/[\r\n,]+/', $logos_raw);
$logo_ids = array_values(array_filter(array_map(static function ($v): int {
	return (int) trim((string) $v);
}, is_array($logo_ids) ? $logo_ids : []), static function (int $id): bool {
	return $id > 0;
}));
$max = isset($section['logo_strip_max']) ? (int) $section['logo_strip_max'] : 10;
$max = max(3, min(24, $max));
if (count($logo_ids) > $max) {
	$logo_ids = array_slice($logo_ids, 0, $max);
}
?>
<section class="lf-block lf-block-logo-strip <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-logo-strip--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-logo-strip__inner">
		<?php if ($heading !== '' || $intro !== '') : ?>
			<header class="lf-block-logo-strip__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
				<?php if ($heading !== '') : ?><h2 class="lf-block-logo-strip__title"><?php echo esc_html($heading); ?></h2><?php endif; ?>
				<?php if ($intro !== '') : ?><p class="lf-block-logo-strip__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
			</header>
		<?php endif; ?>
		<div class="lf-block-logo-strip__logos" role="list">
			<?php foreach ($logo_ids as $id) : ?>
				<div class="lf-block-logo-strip__logo" role="listitem">
					<?php
					echo wp_get_attachment_image(
						$id,
						'medium',
						false,
						[
							'class' => 'lf-block-logo-strip__img',
							'loading' => 'lazy',
							'decoding' => 'async',
						]
					);
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

