<?php
/**
 * Block: Service Intro Boxes. Service cards with title, support, description, optional image and icon.
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context']['section'] section overrides
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Service options', 'leadsforward-core');
$intro = !empty($section['section_intro']) ? $section['section_intro'] : '';
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$section_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_intro', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_intro', 'left', 'lf-heading-icon') : '';
$card_icon = '';
if (!empty($section['icon_enabled']) && !empty($section['icon_slug']) && function_exists('lf_section_icon_markup')) {
	$card_icon = lf_section_icon_markup($section, 'service_intro', 'list', 'lf-block-service-intro__icon');
}
$columns = (int) ($section['service_intro_columns'] ?? 3);
$columns = max(2, min(6, $columns));
$max_items = (int) ($section['service_intro_max_items'] ?? 6);
$max_items = $max_items > 0 ? $max_items : 6;
$show_images = (string) ($section['service_intro_show_images'] ?? '1') !== '0';
$header_align = sanitize_key((string) ($section['section_header_align'] ?? 'center'));
if (!in_array($header_align, ['left', 'center', 'right'], true)) {
	$header_align = 'center';
}

$order_ids_raw = trim((string) ($section['service_intro_service_ids'] ?? ''));
$order_ids = [];
if ($order_ids_raw !== '') {
	foreach (preg_split('/[\s,]+/', $order_ids_raw) ?: [] as $pid) {
		$pid = (int) $pid;
		if ($pid > 0) {
			$order_ids[] = $pid;
		}
	}
}
$query_args = [
	'post_type'      => 'lf_service',
	'posts_per_page' => $max_items,
	'post_status'    => 'publish',
	'no_found_rows'  => true,
];
if ($order_ids !== []) {
	$query_args['post__in'] = $order_ids;
	$query_args['orderby'] = 'post__in';
} else {
	$query_args['orderby'] = 'menu_order title';
	$query_args['order'] = 'ASC';
}
$query = new WP_Query($query_args);
?>
<section class="lf-block lf-block-service-intro <?php echo esc_attr($surface['class']); ?> lf-block-service-intro--<?php echo esc_attr($variant); ?> lf-block-service-intro--cols-<?php echo esc_attr((string) $columns); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>"<?php echo $section_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr in $section_surface_style ?>>
	<div class="lf-block-service-intro__inner">
		<header class="lf-block-service-intro__header lf-block-service-intro__header--align-<?php echo esc_attr($header_align); ?> lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-intro__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
				</div>
			<?php else : ?>
				<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-intro__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
			<?php endif; ?>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-intro__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<div class="lf-block-service-intro__grid">
				<?php while ($query->have_posts()) : $query->the_post();
					$short_desc = function_exists('get_field') ? (string) get_field('lf_service_short_desc', get_the_ID()) : '';
					$desc = $short_desc !== '' ? wp_trim_words(wp_strip_all_tags($short_desc), 28) : '';
					if ($desc === '') {
						$excerpt = get_the_excerpt();
						if (is_string($excerpt) && $excerpt !== '') {
							$desc = wp_trim_words(wp_strip_all_tags($excerpt), 28);
						}
					}
					if ($desc === '') {
						$desc = sprintf(__('Short overview of %s and what to expect.', 'leadsforward-core'), get_the_title());
					}
					$image_id = $show_images ? (int) get_post_thumbnail_id(get_the_ID()) : 0;
					if ($show_images && $image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
						$image_id = lf_get_placeholder_image_id();
					}
					$image_html = $image_id ? wp_get_attachment_image($image_id, 'medium', false, [
						'class' => 'lf-block-service-intro__image',
						'loading' => 'lazy',
						'decoding' => 'async',
					]) : '';
				?>
					<article class="lf-block-service-intro__card lf-card lf-card--interactive" data-lf-service-id="<?php echo esc_attr((string) get_the_ID()); ?>">
						<div class="lf-block-service-intro__card-head">
							<?php if ($card_icon) : ?><span class="lf-block-service-intro__icon"><?php echo $card_icon; ?></span><?php endif; ?>
							<h3 class="lf-block-service-intro__card-title"><?php the_title(); ?></h3>
						</div>
						<?php if ($image_html) : ?>
							<div class="lf-block-service-intro__media"><?php echo $image_html; ?></div>
						<?php endif; ?>
						<p class="lf-block-service-intro__desc"><?php echo esc_html($desc); ?></p>
						<a class="lf-block-service-intro__link" href="<?php the_permalink(); ?>"><?php esc_html_e('Learn more', 'leadsforward-core'); ?></a>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-service-intro__empty"><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
