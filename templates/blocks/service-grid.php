<?php
/**
 * Block: Service Grid. Links to lf_service posts.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$variant = $block['variant'] ?? 'default';
$query = new WP_Query([
	'post_type'      => 'lf_service',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-service-grid lf-block-service-grid--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-service-grid__inner">
		<?php if ($query->have_posts()) : ?>
			<ul class="lf-block-service-grid__list">
				<?php while ($query->have_posts()) : $query->the_post(); ?>
					<li class="lf-block-service-grid__item">
						<a href="<?php the_permalink(); ?>" class="lf-block-service-grid__link"><?php the_title(); ?></a>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-service-grid__empty"><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
