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
$source = (string) ($section['team_members_source'] ?? 'cpt');
if (!in_array($source, ['cpt', 'manual'], true)) {
	$source = 'cpt';
}
$people = [];
if ($source === 'cpt' && function_exists('lf_team_members_query_for_section')) {
	$people = lf_team_members_query_for_section($section);
}
if (empty($people)) {
	$people_raw = (string) ($section['team_members'] ?? '');
	$rows = preg_split('/\r?\n+/', trim($people_raw));
	$rows = array_values(array_filter(array_map('trim', is_array($rows) ? $rows : [])));
	foreach ($rows as $row) {
		// Format: Name || Role || Bio || Image attachment ID (optional).
		$parts = array_map('trim', explode('||', $row));
		$name = $parts[0] ?? '';
		$role = $parts[1] ?? '';
		$bio = $parts[2] ?? '';
		$img_raw = $parts[3] ?? '';
		$image_id = ( $img_raw !== '' && ctype_digit(preg_replace('/\s+/', '', $img_raw)) )
			? absint(preg_replace('/\s+/', '', $img_raw))
			: 0;
		if ($name !== '') {
			$people[] = [
				'name' => $name,
				'role' => $role,
				'bio' => $bio,
				'image_id' => $image_id,
			];
		}
	}
}
$columns = isset($section['team_columns']) ? (int) $section['team_columns'] : 3;
$columns = max(2, min(4, $columns));
$avatar_shape = isset($section['team_avatar_shape']) ? sanitize_key((string) $section['team_avatar_shape']) : 'circle';
if (!in_array($avatar_shape, ['circle', 'rounded', 'square'], true)) {
	$avatar_shape = 'circle';
}
$shape_class = 'lf-block-team--avatar-' . $avatar_shape;
?>
<section class="lf-block lf-block-team <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> <?php echo esc_attr($shape_class); ?> lf-block-team--cols-<?php echo esc_attr((string) $columns); ?> lf-block-team--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-team__inner">
		<?php if ($heading !== '' || $intro !== '') : ?>
			<header class="lf-block-team__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
				<?php if ($heading !== '') : ?><<?php echo esc_html($section_heading_tag); ?> class="lf-block-team__title lf-section__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>><?php endif; ?>
				<?php if ($intro !== '') : ?><p class="lf-block-team__intro lf-section__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
			</header>
		<?php endif; ?>
		<div class="lf-block-team__grid" style="<?php echo esc_attr('--lf-team-cols:' . $columns . ';'); ?>">
			<?php if (empty($people)) : ?>
				<p class="lf-block-team__empty"><?php esc_html_e('No team members yet. Add people under Team in the WordPress admin, or switch this section to “Manual list” and paste rows here.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
			<?php foreach ($people as $p) : ?>
				<?php
				$pid = (int) ( $p['image_id'] ?? 0 );
				$pname = (string) ( $p['name'] ?? '' );
				$img_html = '';
				if ($pid > 0 && wp_attachment_is_image($pid)) {
					$alt = sprintf(
						/* translators: %s: person name */
						__('Photo of %s', 'leadsforward-core'),
						$pname
					);
					$img_html = wp_get_attachment_image(
						$pid,
						'medium_large',
						false,
						[
							'class' => 'lf-block-team__photo-img',
							'loading' => 'lazy',
							'decoding' => 'async',
							'alt' => $alt,
						]
					);
				}
				$initials = function_exists('lf_team_member_initials') ? lf_team_member_initials($pname) : '?';
				?>
				<article class="lf-block-team__card">
					<div class="lf-block-team__media">
						<?php if ($img_html !== '') : ?>
							<div class="lf-block-team__photo">
								<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php else : ?>
							<div class="lf-block-team__placeholder" aria-hidden="true">
								<span class="lf-block-team__placeholder-text"><?php echo esc_html($initials); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="lf-block-team__body">
						<h3 class="lf-block-team__name"><?php echo esc_html($pname); ?></h3>
						<?php if ($p['role'] !== '') : ?><p class="lf-block-team__role"><?php echo esc_html($p['role']); ?></p><?php endif; ?>
						<?php if ($p['bio'] !== '') : ?><div class="lf-block-team__bio lf-prose"><?php echo wp_kses_post(wpautop($p['bio'])); ?></div><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
