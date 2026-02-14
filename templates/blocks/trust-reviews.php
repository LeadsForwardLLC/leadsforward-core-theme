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
$heading = !empty($section['trust_heading']) ? $section['trust_heading'] : __('What Our Customers Say', 'leadsforward-core');
$layout = $section['trust_layout'] ?? 'grid';
$columns = isset($section['trust_columns']) ? (int) $section['trust_columns'] : 3;
$columns = max(2, min(4, $columns));
$is_homepage = function_exists('is_front_page') && is_front_page();
if ($is_homepage && $layout === 'grid') {
	$layout = 'slider';
}
$show_summary = (string) ($section['trust_show_summary'] ?? '1') !== '0';
$show_stars = (string) ($section['trust_show_stars'] ?? '1') !== '0';
$show_source = (string) ($section['trust_show_source'] ?? '1') !== '0';
$show_avatars = (string) ($section['trust_show_avatars'] ?? '1') !== '0';
$show_quote_icon = (string) ($section['trust_show_quote_icon'] ?? '1') !== '0';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'trust_reviews', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'trust_reviews', 'left', 'lf-heading-icon') : '';
$queried = get_queried_object();
$is_reviews_page = $queried instanceof WP_Post
	&& $queried->post_type === 'page'
	&& (strtolower($queried->post_name) === 'reviews' || strtolower((string) $queried->post_title) === 'reviews');
$reviews_page_limit = 15;
if ($is_reviews_page) {
	$layout = 'grid';
	$max = -1;
	$heading = sprintf(
		__('Reviews for %s', 'leadsforward-core'),
		(function_exists('lf_get_option') ? lf_get_option('lf_business_name', 'option') : '') ?: get_bloginfo('name')
	);
} else {
	$max = max(1, min(10, $max));
}

$rating_meta_query = [
	'relation' => 'OR',
	[
		'key' => 'lf_testimonial_rating',
		'value' => 1,
		'compare' => '>',
		'type' => 'NUMERIC',
	],
	[
		'key' => 'lf_testimonial_rating',
		'compare' => 'NOT EXISTS',
	],
];
$query = new WP_Query([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => $max,
	'no_found_rows'  => true,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => 'publish',
	'meta_query'     => $rating_meta_query,
]);
$review_total = is_array($query->posts) ? count($query->posts) : 0;
$all_review_ids = get_posts([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
	'meta_query'     => $rating_meta_query,
]);
$total_reviews = is_array($all_review_ids) ? count($all_review_ids) : 0;
$display_limit = $is_reviews_page ? $reviews_page_limit : $review_total;
$ratings_total = 0;
$ratings_count = 0;
if (!empty($all_review_ids)) {
	foreach ($all_review_ids as $review_id) {
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $review_id) : 5;
		if ($rating <= 1) {
			continue;
		}
		$ratings_total += $rating;
		$ratings_count++;
	}
}
$avg_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 0;
$is_slider = $layout === 'slider';
$section_classes = 'lf-block lf-block-trust-reviews ' . $bg_class . ' lf-block-trust-reviews--' . $variant;
if ($is_slider) {
	$section_classes .= ' lf-block-trust-reviews--slider';
}
$section_classes .= ' lf-block-trust-reviews--' . $layout;
?>
<section class="<?php echo esc_attr(trim($section_classes)); ?>" id="<?php echo esc_attr($render_id); ?>" data-variant="<?php echo esc_attr($variant); ?>" style="--lf-reviews-columns: <?php echo esc_attr((string) $columns); ?>;">
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
			<?php if ($show_summary && $total_reviews > 0) : ?>
				<div class="lf-block-trust-reviews__summary" role="note" aria-label="<?php esc_attr_e('Review summary', 'leadsforward-core'); ?>">
					<?php if ($avg_rating > 0) : ?>
						<div class="lf-block-trust-reviews__summary-score">
							<span class="lf-block-trust-reviews__summary-stars" aria-hidden="true">
								<?php for ($s = 1; $s <= 5; $s++) : ?>
									<svg class="lf-block-trust-reviews__summary-star<?php echo $s <= round($avg_rating) ? ' is-filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
								<?php endfor; ?>
							</span>
							<span class="lf-block-trust-reviews__summary-rating"><?php echo esc_html(number_format($avg_rating, 1)); ?></span>
							<span class="lf-block-trust-reviews__summary-max">/5 rating</span>
						</div>
					<?php endif; ?>
					<span class="lf-block-trust-reviews__summary-divider" aria-hidden="true"></span>
					<div class="lf-block-trust-reviews__summary-meta">
						<span class="lf-block-trust-reviews__summary-count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $total_reviews, 'leadsforward-core'), $total_reviews)); ?></span>
						<span class="lf-block-trust-reviews__summary-label"><?php esc_html_e('Verified feedback', 'leadsforward-core'); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
		<div class="lf-block-trust-reviews__carousel" data-slider="<?php echo $is_slider ? '1' : '0'; ?>"<?php echo $is_reviews_page ? ' data-reviews-step="' . esc_attr((string) $reviews_page_limit) . '"' : ''; ?>>
				<?php if ($is_slider) : ?>
					<div class="lf-slider" data-lf-slider>
						<div class="lf-slider__controls">
							<button type="button" class="lf-slider__nav lf-block-trust-reviews__nav lf-block-trust-reviews__nav--prev" data-lf-slider-prev aria-label="<?php esc_attr_e('Previous reviews', 'leadsforward-core'); ?>">‹</button>
							<button type="button" class="lf-slider__nav lf-block-trust-reviews__nav lf-block-trust-reviews__nav--next" data-lf-slider-next aria-label="<?php esc_attr_e('Next reviews', 'leadsforward-core'); ?>">›</button>
						</div>
						<div class="lf-slider__viewport" data-lf-slider-viewport>
							<ul class="lf-block-trust-reviews__list lf-slider__track" role="list" data-lf-slider-track>
				<?php else : ?>
					<ul class="lf-block-trust-reviews__list" role="list">
				<?php endif; ?>
				<?php
				$review_index = 0;
				while ($query->have_posts()) : $query->the_post();
						$name = function_exists('get_field') ? get_field('lf_testimonial_reviewer_name') : '';
						$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating') : 5;
						$text = function_exists('get_field') ? get_field('lf_testimonial_review_text') : '';
					$source = function_exists('get_field') ? get_field('lf_testimonial_source') : '';
					$source_url = function_exists('get_field') ? get_field('lf_testimonial_source_url') : '';
					$avatar_id = function_exists('get_field') ? (int) get_field('lf_testimonial_reviewer_avatar') : 0;
					if (!$avatar_id) {
						$avatar_id = get_post_thumbnail_id();
					}
						if ($rating <= 1) {
							continue;
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
				<li class="lf-block-trust-reviews__item<?php echo ($is_reviews_page && $review_index >= $display_limit) ? ' is-hidden' : ''; ?>" <?php echo $source ? 'data-source="' . esc_attr(sanitize_title((string) $source)) . '"' : ''; ?>>
						<figure class="lf-block-trust-reviews__quote">
							<?php if ($show_quote_icon) : ?>
								<span class="lf-block-trust-reviews__quote-icon" aria-hidden="true">“</span>
							<?php endif; ?>
							<blockquote class="lf-block-trust-reviews__text"><?php echo esc_html($text); ?></blockquote>
							<figcaption class="lf-block-trust-reviews__cite">
								<?php if ($show_avatars) : ?>
									<span class="lf-block-trust-reviews__avatar" aria-hidden="true">
										<?php
										if ($avatar_id) {
											echo wp_get_attachment_image($avatar_id, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'decoding' => 'async']);
										} else {
											echo esc_html($initials ?: '★');
										}
										?>
									</span>
								<?php endif; ?>
								<span class="lf-block-trust-reviews__author">
									<cite class="lf-block-trust-reviews__name"><?php echo esc_html($name); ?></cite>
									<?php if ($show_source && $source) : ?>
										<?php if ($source_url) : ?>
											<a class="lf-block-trust-reviews__source" href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($source); ?></a>
										<?php else : ?>
											<span class="lf-block-trust-reviews__source"><?php echo esc_html($source); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</span>
								<?php if ($show_stars && $rating) : ?>
									<span class="lf-block-trust-reviews__stars" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $rating)); ?>">
										<?php for ($s = 1; $s <= 5; $s++) : ?>
											<svg class="lf-block-trust-reviews__star<?php echo $s <= $rating ? ' lf-block-trust-reviews__star--filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
										<?php endfor; ?>
									</span>
								<?php endif; ?>
							</figcaption>
						</figure>
					</li>
				<?php
					$review_index++;
				endwhile;
				?>
				</ul>
				<?php if ($is_slider) : ?>
						</div>
				<?php endif; ?>
				<?php if ($is_slider) : ?>
						<div class="lf-slider__dots" data-lf-slider-dots aria-label="<?php esc_attr_e('Review pages', 'leadsforward-core'); ?>"></div>
					</div>
				<?php endif; ?>
			</div>
		<?php if ($is_reviews_page && $total_reviews > $display_limit) : ?>
			<div class="lf-block-trust-reviews__actions">
				<button type="button" class="lf-block-trust-reviews__load-more" data-reviews-load><?php esc_html_e('Load more reviews', 'leadsforward-core'); ?></button>
			</div>
		<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="lf-block-trust-reviews__empty" role="status">
				<p class="lf-block-trust-reviews__empty-text"><?php esc_html_e('No reviews yet.', 'leadsforward-core'); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php if ($is_reviews_page && $total_reviews > $display_limit) : ?>
	<?php static $load_more_script_loaded = false; ?>
	<?php if (!$load_more_script_loaded) : ?>
		<?php $load_more_script_loaded = true; ?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.lf-block-trust-reviews__load-more').forEach(function (button) {
					var section = button.closest('.lf-block-trust-reviews');
					if (!section) {
						return;
					}
					var carousel = section.querySelector('.lf-block-trust-reviews__carousel');
					var step = 15;
					if (carousel && carousel.dataset.reviewsStep) {
						var parsed = parseInt(carousel.dataset.reviewsStep, 10);
						step = isNaN(parsed) ? step : parsed;
					}
					button.addEventListener('click', function () {
						var hidden = section.querySelectorAll('.lf-block-trust-reviews__item.is-hidden');
						if (!hidden.length) {
							button.remove();
							return;
						}
						for (var i = 0; i < step && i < hidden.length; i++) {
							hidden[i].classList.remove('is-hidden');
						}
						if (section.querySelectorAll('.lf-block-trust-reviews__item.is-hidden').length === 0) {
							button.remove();
						}
					});
				});
			});
		</script>
	<?php endif; ?>
<?php endif; ?>
