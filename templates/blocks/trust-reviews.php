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
$query = new WP_Query([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => $max,
	'no_found_rows'  => true,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => 'publish',
]);
?>
<section class="lf-block lf-block-trust-reviews lf-block-trust-reviews--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>">
	<div class="lf-block-trust-reviews__inner">
		<?php if ($query->have_posts()) : ?>
			<ul class="lf-block-trust-reviews__list">
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
								<cite><?php echo esc_html($name); ?></cite>
								<?php if ($rating) : ?>
									<span class="lf-block-trust-reviews__rating" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $rating)); ?>"><?php echo esc_html((string) $rating); ?></span>
								<?php endif; ?>
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
			<p class="lf-block-trust-reviews__empty"><?php esc_html_e('No reviews yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
