<?php
/**
 * Block: Service Areas. Grid of lf_service_area links (homepage section).
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Areas We Serve', 'leadsforward-core');
$intro   = !empty($section['section_intro']) ? $section['section_intro'] : '';

$query = new WP_Query([
	'post_type'      => 'lf_service_area',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-service-areas lf-surface-soft lf-block-service-areas--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-service-areas__inner">
		<header class="lf-block-service-areas__header">
			<h2 class="lf-block-service-areas__title"><?php echo esc_html($heading); ?></h2>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-areas__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<ul class="lf-block-service-areas__list" role="list">
				<?php while ($query->have_posts()) : $query->the_post(); ?>
					<li class="lf-block-service-areas__item">
						<a href="<?php the_permalink(); ?>" class="lf-block-service-areas__link">
							<span class="lf-block-service-areas__card-title"><?php the_title(); ?></span>
							<span class="lf-block-service-areas__card-action" aria-hidden="true"><?php esc_html_e('View area', 'leadsforward-core'); ?></span>
						</a>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-service-areas__empty"><?php esc_html_e('No service areas yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
