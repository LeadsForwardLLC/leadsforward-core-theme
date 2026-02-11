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
$render_id = $block_id ?: 'block-' . uniqid();
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'soft') : '';
$max = 3;
if (!empty($section['trust_max_items'])) {
	$max = (int) $section['trust_max_items'];
} elseif (isset($block['attributes']['max_items'])) {
	$max = (int) $block['attributes']['max_items'];
}
$max = max(1, min(10, $max));
$heading = !empty($section['trust_heading']) ? $section['trust_heading'] : __('What Our Customers Say', 'leadsforward-core');
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'trust_reviews', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'trust_reviews', 'left', 'lf-heading-icon') : '';

$query = new WP_Query([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => $max,
	'no_found_rows'  => true,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => 'publish',
]);
$review_total = is_array($query->posts) ? count($query->posts) : 0;
$ratings_total = 0;
$ratings_count = 0;
if ($review_total > 0) {
	foreach ($query->posts as $post) {
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $post->ID) : 5;
		if ($rating > 0) {
			$ratings_total += $rating;
			$ratings_count++;
		}
	}
}
$avg_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 0;
$is_slider = $review_total > 3;
$section_classes = 'lf-block lf-block-trust-reviews ' . $bg_class . ' lf-block-trust-reviews--' . $variant;
if ($is_slider) {
	$section_classes .= ' lf-block-trust-reviews--slider';
}
?>
<section class="<?php echo esc_attr(trim($section_classes)); ?>" id="<?php echo esc_attr($render_id); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-trust-reviews__inner">
		<header class="lf-block-trust-reviews__header">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<h2 class="lf-block-trust-reviews__title"><?php echo esc_html($heading); ?></h2>
				</div>
			<?php else : ?>
				<h2 class="lf-block-trust-reviews__title"><?php echo esc_html($heading); ?></h2>
			<?php endif; ?>
			<?php if ($variant === 'a' && $review_total > 0) : ?>
				<div class="lf-block-trust-reviews__summary" role="note" aria-label="<?php esc_attr_e('Review summary', 'leadsforward-core'); ?>">
					<?php if ($avg_rating > 0) : ?>
						<div class="lf-block-trust-reviews__summary-score">
							<span class="lf-block-trust-reviews__summary-rating"><?php echo esc_html(number_format($avg_rating, 1)); ?></span>
							<span class="lf-block-trust-reviews__summary-max">/5</span>
						</div>
					<?php endif; ?>
					<div class="lf-block-trust-reviews__summary-meta">
						<span class="lf-block-trust-reviews__summary-count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_total, 'leadsforward-core'), $review_total)); ?></span>
						<span class="lf-block-trust-reviews__summary-label"><?php esc_html_e('Verified feedback', 'leadsforward-core'); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<div class="lf-block-trust-reviews__carousel" data-slider="<?php echo $is_slider ? '1' : '0'; ?>">
				<?php if ($is_slider) : ?>
					<button type="button" class="lf-block-trust-reviews__nav lf-block-trust-reviews__nav--prev" aria-label="<?php esc_attr_e('Previous reviews', 'leadsforward-core'); ?>">‹</button>
					<button type="button" class="lf-block-trust-reviews__nav lf-block-trust-reviews__nav--next" aria-label="<?php esc_attr_e('Next reviews', 'leadsforward-core'); ?>">›</button>
				<?php endif; ?>
				<ul class="lf-block-trust-reviews__list" role="list">
					<?php while ($query->have_posts()) : $query->the_post();
						$name = function_exists('get_field') ? get_field('lf_testimonial_reviewer_name') : '';
						$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating') : 5;
						$text = function_exists('get_field') ? get_field('lf_testimonial_review_text') : '';
					$source = function_exists('get_field') ? get_field('lf_testimonial_source') : '';
					$source_url = function_exists('get_field') ? get_field('lf_testimonial_source_url') : '';
					$avatar_id = function_exists('get_field') ? (int) get_field('lf_testimonial_reviewer_avatar') : 0;
					if (!$avatar_id) {
						$avatar_id = get_post_thumbnail_id();
					}
						if (!$name) {
							$name = get_the_title();
						}
					$initials = '';
					if ($name) {
						$parts = preg_split('/\s+/', trim((string) $name));
						$initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
					}
					?>
					<li class="lf-block-trust-reviews__item" <?php echo $source ? 'data-source="' . esc_attr(sanitize_title((string) $source)) . '"' : ''; ?>>
						<figure class="lf-block-trust-reviews__quote">
							<span class="lf-block-trust-reviews__quote-icon" aria-hidden="true">“</span>
							<blockquote class="lf-block-trust-reviews__text"><?php echo esc_html($text); ?></blockquote>
							<figcaption class="lf-block-trust-reviews__cite">
								<span class="lf-block-trust-reviews__avatar" aria-hidden="true">
									<?php
									if ($avatar_id) {
										echo wp_get_attachment_image($avatar_id, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'decoding' => 'async']);
									} else {
										echo esc_html($initials ?: '★');
									}
									?>
								</span>
								<span class="lf-block-trust-reviews__author">
									<cite class="lf-block-trust-reviews__name"><?php echo esc_html($name); ?></cite>
									<?php if ($source) : ?>
										<?php if ($source_url) : ?>
											<a class="lf-block-trust-reviews__source" href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($source); ?></a>
										<?php else : ?>
											<span class="lf-block-trust-reviews__source"><?php echo esc_html($source); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</span>
								<?php if ($rating) : ?>
									<span class="lf-block-trust-reviews__stars" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $rating)); ?>">
										<?php for ($s = 1; $s <= 5; $s++) : ?>
											<svg class="lf-block-trust-reviews__star<?php echo $s <= $rating ? ' lf-block-trust-reviews__star--filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
										<?php endfor; ?>
									</span>
								<?php endif; ?>
							</figcaption>
						</figure>
					</li>
					<?php endwhile; ?>
				</ul>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="lf-block-trust-reviews__empty" role="status">
				<p class="lf-block-trust-reviews__empty-text"><?php esc_html_e('No reviews yet.', 'leadsforward-core'); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php if ($is_slider) : ?>
	<?php static $slider_script_loaded = false; ?>
	<?php if (!$slider_script_loaded) : ?>
		<?php $slider_script_loaded = true; ?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.lf-block-trust-reviews--slider').forEach(function (section) {
					var list = section.querySelector('.lf-block-trust-reviews__list');
					var prev = section.querySelector('.lf-block-trust-reviews__nav--prev');
					var next = section.querySelector('.lf-block-trust-reviews__nav--next');
					if (!list || !prev || !next) {
						return;
					}
					function getStep() {
						var item = list.querySelector('.lf-block-trust-reviews__item');
						if (!item) {
							return list.clientWidth || 0;
						}
						var styles = window.getComputedStyle(list);
						var gap = parseFloat(styles.columnGap || styles.gap || 0);
						return item.getBoundingClientRect().width + (isNaN(gap) ? 0 : gap);
					}
					prev.addEventListener('click', function () {
						list.scrollBy({ left: -getStep(), behavior: 'smooth' });
					});
					next.addEventListener('click', function () {
						list.scrollBy({ left: getStep(), behavior: 'smooth' });
					});
				});
			});
		</script>
	<?php endif; ?>
<?php endif; ?>
