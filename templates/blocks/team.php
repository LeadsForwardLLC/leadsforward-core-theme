<?php
/**
 * Block: Team.
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
$people_raw = (string) ($section['team_members'] ?? '');
$rows = preg_split('/\r?\n+/', trim($people_raw));
$rows = array_values(array_filter(array_map('trim', is_array($rows) ? $rows : [])));
$people = [];
foreach ($rows as $row) {
	// Format: Name || Role || Bio (bio optional)
	$parts = array_map('trim', explode('||', $row));
	$name = $parts[0] ?? '';
	$role = $parts[1] ?? '';
	$bio = $parts[2] ?? '';
	if ($name !== '') {
		$people[] = ['name' => $name, 'role' => $role, 'bio' => $bio];
	}
}
$columns = isset($section['team_columns']) ? (int) $section['team_columns'] : 3;
$columns = max(2, min(4, $columns));
?>
<section class="lf-block lf-block-team <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-team--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-team__inner">
		<?php if ($heading !== '' || $intro !== '') : ?>
			<header class="lf-block-team__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
				<?php if ($heading !== '') : ?><<?php echo esc_html($section_heading_tag); ?> class="lf-block-team__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>><?php endif; ?>
				<?php if ($intro !== '') : ?><p class="lf-block-team__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
			</header>
		<?php endif; ?>
		<div class="lf-block-team__grid" style="<?php echo esc_attr('--lf-team-cols:' . $columns . ';'); ?>">
			<?php foreach ($people as $p) : ?>
				<article class="lf-block-team__card">
					<div class="lf-block-team__avatar" aria-hidden="true"></div>
					<h3 class="lf-block-team__name"><?php echo esc_html($p['name']); ?></h3>
					<?php if ($p['role'] !== '') : ?><p class="lf-block-team__role"><?php echo esc_html($p['role']); ?></p><?php endif; ?>
					<?php if ($p['bio'] !== '') : ?><div class="lf-block-team__bio lf-prose"><?php echo wp_kses_post(wpautop($p['bio'])); ?></div><?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

