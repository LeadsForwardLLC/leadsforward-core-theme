<?php
/**
 * Block: Packages / Comparison.
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
$cards_raw = preg_split('/\r?\n+/', trim((string) ($section['package_cards'] ?? '')));
$cards_raw = array_values(array_filter(array_map('trim', is_array($cards_raw) ? $cards_raw : [])));
$cards = [];
foreach ($cards_raw as $row) {
	// Format: Name || Best for || 3 bullets (comma-separated) || Highlight (optional: 1/0)
	$parts = array_map('trim', explode('||', $row));
	$name = $parts[0] ?? '';
	$best_for = $parts[1] ?? '';
	$bullets = [];
	if (!empty($parts[2])) {
		$bullets = preg_split('/\s*,\s*/', (string) $parts[2]);
		$bullets = array_values(array_filter(array_map('trim', is_array($bullets) ? $bullets : [])));
	}
	$highlight = (string) ($parts[3] ?? '') === '1';
	if ($name !== '') {
		$cards[] = ['name' => $name, 'best_for' => $best_for, 'bullets' => $bullets, 'highlight' => $highlight];
	}
}
?>
<section class="lf-block lf-block-packages <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-packages--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-packages__inner">
		<?php if ($heading !== '' || $intro !== '') : ?>
			<header class="lf-block-packages__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
				<?php if ($heading !== '') : ?><h2 class="lf-block-packages__title"><?php echo esc_html($heading); ?></h2><?php endif; ?>
				<?php if ($intro !== '') : ?><p class="lf-block-packages__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
			</header>
		<?php endif; ?>
		<div class="lf-block-packages__grid">
			<?php foreach ($cards as $card) : ?>
				<article class="lf-block-packages__card<?php echo !empty($card['highlight']) ? ' is-highlight' : ''; ?>">
					<?php if (!empty($card['highlight'])) : ?>
						<span class="lf-block-packages__badge"><?php esc_html_e('Recommended', 'leadsforward-core'); ?></span>
					<?php endif; ?>
					<h3 class="lf-block-packages__name"><?php echo esc_html($card['name']); ?></h3>
					<?php if (!empty($card['best_for'])) : ?><p class="lf-block-packages__bestfor"><?php echo esc_html($card['best_for']); ?></p><?php endif; ?>
					<?php if (!empty($card['bullets'])) : ?>
						<ul class="lf-block-packages__bullets" role="list">
							<?php foreach ($card['bullets'] as $b) : ?>
								<li class="lf-block-packages__bullet"><?php echo esc_html($b); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

