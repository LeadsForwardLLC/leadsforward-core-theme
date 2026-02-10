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
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_intro', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_intro', 'left', 'lf-heading-icon') : '';
$card_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_intro', 'list', 'lf-block-service-intro__icon') : '';
$columns = (int) ($section['service_intro_columns'] ?? 3);
$columns = max(3, min(6, $columns));
$max_items = (int) ($section['service_intro_max_items'] ?? 6);
$max_items = $max_items > 0 ? $max_items : 6;
$show_images = (string) ($section['service_intro_show_images'] ?? '1') !== '0';

$query = new WP_Query([
	'post_type'      => 'lf_service',
	'posts_per_page' => $max_items,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-service-intro <?php echo esc_attr($bg_class); ?> lf-block-service-intro--<?php echo esc_attr($variant); ?> lf-block-service-intro--cols-<?php echo esc_attr((string) $columns); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-service-intro__inner">
		<header class="lf-block-service-intro__header">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<h2 class="lf-block-service-intro__title"><?php echo esc_html($heading); ?></h2>
				</div>
			<?php else : ?>
				<h2 class="lf-block-service-intro__title"><?php echo esc_html($heading); ?></h2>
			<?php endif; ?>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-intro__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<div class="lf-block-service-intro__grid">
				<?php while ($query->have_posts()) : $query->the_post();
					$support = wp_strip_all_tags(get_the_excerpt());
					$desc = wp_trim_words(wp_strip_all_tags(get_the_content(null, false)), 28);
					if ($support === '') {
						$support = $desc;
						$desc = '';
					}
					$image_id = $show_images ? (int) get_post_thumbnail_id(get_the_ID()) : 0;
					$image_html = $image_id ? wp_get_attachment_image($image_id, 'medium', false, [
						'class' => 'lf-block-service-intro__image',
						'loading' => 'lazy',
						'decoding' => 'async',
					]) : '';
				?>
					<article class="lf-block-service-intro__card lf-card lf-card--interactive">
						<div class="lf-block-service-intro__card-head">
							<?php if ($card_icon) : ?><span class="lf-block-service-intro__icon"><?php echo $card_icon; ?></span><?php endif; ?>
							<h3 class="lf-block-service-intro__card-title"><?php the_title(); ?></h3>
						</div>
						<?php if ($image_html) : ?>
							<div class="lf-block-service-intro__media"><?php echo $image_html; ?></div>
						<?php endif; ?>
						<?php if ($support !== '') : ?>
							<p class="lf-block-service-intro__support"><?php echo esc_html($support); ?></p>
						<?php endif; ?>
						<?php if ($desc !== '') : ?>
							<p class="lf-block-service-intro__desc"><?php echo esc_html($desc); ?></p>
						<?php endif; ?>
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
