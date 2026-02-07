<?php
/**
 * Block: Trust / Reviews. Outputs testimonials from lf_testimonial CPT.
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
$max = 3;
if (!empty($section['trust_max_items'])) {
	$max = (int) $section['trust_max_items'];
} elseif (isset($block['attributes']['max_items'])) {
	$max = (int) $block['attributes']['max_items'];
}
$max = max(1, min(10, $max));
$heading = !empty($section['trust_heading']) ? $section['trust_heading'] : __('What Our Customers Say', 'leadsforward-core');

$query = new WP_Query([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => $max,
	'no_found_rows'  => true,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => 'publish',
]);
?>
<section class="lf-block lf-block-trust-reviews lf-surface-soft lf-block-trust-reviews--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-trust-reviews__inner">
		<header class="lf-block-trust-reviews__header">
			<h2 class="lf-block-trust-reviews__title"><?php echo esc_html($heading); ?></h2>
		</header>
		<?php if ($query->have_posts()) : ?>
			<ul class="lf-block-trust-reviews__list" role="list">
				<?php while ($query->have_posts()) : $query->the_post();
					$name = function_exists('get_field') ? get_field('lf_testimonial_reviewer_name') : '';
					$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating') : 5;
					$text = function_exists('get_field') ? get_field('lf_testimonial_review_text') : get_the_excerpt();
					$source = function_exists('get_field') ? get_field('lf_testimonial_source') : '';
					if (!$name) {
						$name = get_the_title();
					}
					if (!$text) {
						$text = get_the_content();
					}
				?>
					<li class="lf-block-trust-reviews__item">
						<blockquote class="lf-block-trust-reviews__quote">
							<p class="lf-block-trust-reviews__text"><?php echo esc_html($text); ?></p>
							<footer class="lf-block-trust-reviews__cite">
								<?php if ($rating) : ?>
									<span class="lf-block-trust-reviews__stars" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $rating)); ?>">
										<?php for ($s = 1; $s <= 5; $s++) : ?>
											<svg class="lf-block-trust-reviews__star<?php echo $s <= $rating ? ' lf-block-trust-reviews__star--filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
										<?php endfor; ?>
									</span>
								<?php endif; ?>
								<cite><?php echo esc_html($name); ?></cite>
								<?php if ($source) : ?>
									<span class="lf-block-trust-reviews__source"><?php echo esc_html($source); ?></span>
								<?php endif; ?>
							</footer>
						</blockquote>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="lf-block-trust-reviews__empty" role="status">
				<p class="lf-block-trust-reviews__empty-text"><?php esc_html_e('No reviews yet.', 'leadsforward-core'); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
