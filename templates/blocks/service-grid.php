<?php
/**
 * Block: Service Grid. Section heading + intro, links to lf_service posts (decision elements).
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context']['section'] homepage section overrides (section_heading, section_intro)
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Our Services', 'leadsforward-core');
$intro   = !empty($section['section_intro']) ? $section['section_intro'] : '';

$query = new WP_Query([
	'post_type'      => 'lf_service',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-service-grid lf-surface-white lf-block-service-grid--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-service-grid__inner">
		<header class="lf-block-service-grid__header">
			<h2 class="lf-block-service-grid__title"><?php echo esc_html($heading); ?></h2>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-grid__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<ul class="lf-block-service-grid__list" role="list">
				<?php $index = 0; ?>
				<?php while ($query->have_posts()) : $query->the_post();
					$index++;
					$excerpt = '';
					if ($variant === 'a') {
						$excerpt = wp_strip_all_tags(get_the_excerpt());
					}
				?>
					<li class="lf-block-service-grid__item">
						<a href="<?php the_permalink(); ?>" class="lf-block-service-grid__link">
							<?php if ($variant === 'a') : ?>
								<span class="lf-block-service-grid__card-index"><?php echo esc_html(str_pad((string) $index, 2, '0', STR_PAD_LEFT)); ?></span>
							<?php endif; ?>
							<span class="lf-block-service-grid__card-title"><?php the_title(); ?></span>
							<?php if ($variant === 'a' && $excerpt !== '') : ?>
								<span class="lf-block-service-grid__card-desc"><?php echo esc_html($excerpt); ?></span>
							<?php endif; ?>
							<span class="lf-block-service-grid__card-action" aria-hidden="true"><?php esc_html_e('View', 'leadsforward-core'); ?></span>
						</a>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-service-grid__empty"><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
