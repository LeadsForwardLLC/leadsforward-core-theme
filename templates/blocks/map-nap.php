<?php
/**
 * Block: Map + NAP. Uses global business info. Map embed can be added via options later.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant  = $block['variant'] ?? 'default';
$context  = $block['context'] ?? [];
$section  = $context['section'] ?? [];
$name     = function_exists('lf_get_option') ? lf_get_option('lf_business_name', 'option') : '';
$phone    = function_exists('lf_get_option') ? lf_get_option('lf_business_phone', 'option') : '';
$email    = function_exists('lf_get_option') ? lf_get_option('lf_business_email', 'option') : '';
$address  = function_exists('lf_get_option') ? lf_get_option('lf_business_address', 'option') : '';
$place_id = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_id', '') : '';
$place_name = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_name', '') : '';
$place_address = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_address', '') : '';
$map_embed_override = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_map_embed', '') : '';
$maps_api_key = get_option('lf_maps_api_key', '');
$maps_api_key = is_string($maps_api_key) ? $maps_api_key : '';
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Areas We Serve', 'leadsforward-core');
$intro   = !empty($section['section_intro']) ? $section['section_intro'] : '';

$has_nap = ($name !== '' || $address !== '' || $phone !== '' || $email !== '');
$reviews_query = new WP_Query([
	'post_type'      => 'lf_testimonial',
	'posts_per_page' => 3,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
$reviews_total = 0;
if (function_exists('wp_count_posts')) {
	$counts = wp_count_posts('lf_testimonial');
	$reviews_total = isset($counts->publish) ? (int) $counts->publish : 0;
} elseif (is_array($reviews_query->posts)) {
	$reviews_total = count($reviews_query->posts);
}
$ratings_total = 0;
$ratings_count = 0;
if (!empty($reviews_query->posts)) {
	foreach ($reviews_query->posts as $review_post) {
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $review_post->ID) : 5;
		if ($rating > 0) {
			$ratings_total += $rating;
			$ratings_count++;
		}
	}
}
$avg_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 0;
$map_title = $name ?: $place_name;
$map_title = is_string($map_title) ? $map_title : '';
$map_subtitle = $address ?: $place_address;
$map_subtitle = is_string($map_subtitle) ? $map_subtitle : '';
$map_view_url = '';
$map_directions_url = '';
if (is_string($place_id) && $place_id !== '') {
	$map_view_url = 'https://www.google.com/maps/search/?api=1&query=Google&query_place_id=' . rawurlencode($place_id);
	$map_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=place_id:' . rawurlencode($place_id);
} elseif (is_string($address) && $address !== '') {
	$map_view_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
	$map_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);
}
$map_embed_url = '';
if (is_string($map_embed_override) && $map_embed_override !== '') {
	$map_embed_url = '';
} elseif (is_string($place_id) && $place_id !== '' && $maps_api_key !== '') {
	$map_embed_url = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($maps_api_key) . '&q=place_id:' . rawurlencode($place_id);
} elseif (is_string($place_id) && $place_id !== '') {
	$map_embed_url = 'https://www.google.com/maps?q=place_id:' . rawurlencode($place_id) . '&output=embed';
}

$areas_query = new WP_Query([
	'post_type'      => 'lf_service_area',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-map-nap lf-block-map-nap--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
	<div class="lf-block-map-nap__inner">
		<div class="lf-block-map-nap__grid">
			<div class="lf-block-map-nap__areas">
				<header class="lf-block-map-nap__header">
					<h2 class="lf-block-map-nap__title"><?php echo esc_html($heading); ?></h2>
					<?php if ($intro !== '') : ?>
						<p class="lf-block-map-nap__intro"><?php echo esc_html($intro); ?></p>
					<?php endif; ?>
				</header>
				<?php if ($areas_query->have_posts()) : ?>
					<ul class="lf-block-map-nap__areas-list" role="list">
						<?php while ($areas_query->have_posts()) : $areas_query->the_post(); ?>
							<li class="lf-block-map-nap__areas-item">
								<a href="<?php the_permalink(); ?>" class="lf-block-map-nap__areas-link"><?php the_title(); ?></a>
							</li>
						<?php endwhile; ?>
					</ul>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="lf-block-map-nap__empty"><?php esc_html_e('No service areas yet.', 'leadsforward-core'); ?></p>
				<?php endif; ?>
			</div>
			<div class="lf-block-map-nap__card">
				<div class="lf-block-map-nap__map-shell">
					<?php if (is_string($map_embed_override) && $map_embed_override !== '') : ?>
						<div class="lf-block-map-nap__map">
							<?php
							$allowed_embed = [
								'iframe' => [
									'src' => true,
									'width' => true,
									'height' => true,
									'style' => true,
									'loading' => true,
									'referrerpolicy' => true,
									'allowfullscreen' => true,
									'title' => true,
								],
							];
							echo wp_kses($map_embed_override, $allowed_embed);
							?>
						</div>
					<?php elseif ($map_embed_url !== '') : ?>
						<div class="lf-block-map-nap__map">
							<iframe title="<?php echo esc_attr($place_name !== '' ? $place_name : __('Business location', 'leadsforward-core')); ?>" src="<?php echo esc_url($map_embed_url); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
						</div>
					<?php else : ?>
						<p class="lf-block-map-nap__empty">
							<?php
							if (current_user_can('edit_theme_options')) {
								echo wp_kses(
									sprintf(
										/* translators: %s: link to Business Info on Homepage settings */
										__('Add your Google Maps API key and select a place in <strong>Business Info</strong> on <a href="%s">LeadsForward → Homepage</a> to show the map.', 'leadsforward-core'),
										esc_url(admin_url('admin.php?page=lf-homepage-settings#lf-business-info'))
									),
									['a' => ['href' => true], 'strong' => []]
								);
							} else {
								esc_html_e('Map will appear here.', 'leadsforward-core');
							}
							?>
						</p>
					<?php endif; ?>
					<?php if ($map_title || $map_view_url) : ?>
						<div class="lf-block-map-nap__map-meta">
							<div>
								<?php if ($map_title) : ?>
									<p class="lf-block-map-nap__map-title"><?php echo esc_html($map_title); ?></p>
								<?php endif; ?>
								<?php if ($map_subtitle) : ?>
									<p class="lf-block-map-nap__map-subtitle"><?php echo esc_html($map_subtitle); ?></p>
								<?php endif; ?>
							</div>
							<?php if ($map_view_url || $map_directions_url) : ?>
								<div class="lf-block-map-nap__map-actions">
									<?php if ($map_view_url) : ?>
										<a class="lf-block-map-nap__map-link" href="<?php echo esc_url($map_view_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View on Google Maps', 'leadsforward-core'); ?></a>
									<?php endif; ?>
									<?php if ($map_directions_url) : ?>
										<a class="lf-block-map-nap__map-link lf-block-map-nap__map-link--primary" href="<?php echo esc_url($map_directions_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'leadsforward-core'); ?></a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ($has_nap) : ?>
					<address class="lf-block-map-nap__address">
						<?php if ($name !== '') : ?>
							<span class="lf-block-map-nap__name"><?php echo esc_html($name); ?></span>
						<?php endif; ?>
						<?php if ($address !== '') : ?>
							<span class="lf-block-map-nap__street"><?php echo nl2br(esc_html($address)); ?></span>
						<?php endif; ?>
						<?php if ($phone !== '') : ?>
							<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>" class="lf-block-map-nap__phone"><?php echo esc_html($phone); ?></a>
						<?php endif; ?>
						<?php if ($email !== '') : ?>
							<a href="mailto:<?php echo esc_attr($email); ?>" class="lf-block-map-nap__email"><?php echo esc_html($email); ?></a>
						<?php endif; ?>
					</address>
				<?php elseif ($place_name || $place_address) : ?>
					<address class="lf-block-map-nap__address">
						<?php if ($place_name !== '') : ?>
							<span class="lf-block-map-nap__name"><?php echo esc_html($place_name); ?></span>
						<?php endif; ?>
						<?php if ($place_address !== '') : ?>
							<span class="lf-block-map-nap__street"><?php echo esc_html($place_address); ?></span>
						<?php endif; ?>
					</address>
				<?php endif; ?>

				<?php if ($reviews_query->have_posts()) : ?>
					<div class="lf-block-map-nap__reviews">
						<div class="lf-block-map-nap__reviews-head">
							<span class="lf-block-map-nap__reviews-title"><?php esc_html_e('Customer reviews', 'leadsforward-core'); ?></span>
							<?php if ($avg_rating > 0 || $reviews_total > 0) : ?>
								<span class="lf-block-map-nap__reviews-summary">
									<?php if ($avg_rating > 0) : ?>
										<span class="lf-block-map-nap__reviews-score"><?php echo esc_html(number_format($avg_rating, 1)); ?></span>
										<span class="lf-block-map-nap__reviews-stars" aria-hidden="true">
											<?php
											$filled = (int) round($avg_rating);
											for ($s = 1; $s <= 5; $s++) :
											?>
												<svg class="lf-block-map-nap__reviews-star<?php echo $s <= $filled ? ' lf-block-map-nap__reviews-star--filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
											<?php endfor; ?>
										</span>
									<?php endif; ?>
									<?php if ($reviews_total > 0) : ?>
										<span class="lf-block-map-nap__reviews-count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $reviews_total, 'leadsforward-core'), $reviews_total)); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</div>
						<ul class="lf-block-map-nap__review-list" role="list">
							<?php while ($reviews_query->have_posts()) : $reviews_query->the_post();
								$review_name = function_exists('get_field') ? get_field('lf_testimonial_reviewer_name') : '';
								$review_rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating') : 5;
								$review_text = function_exists('get_field') ? get_field('lf_testimonial_review_text') : get_the_excerpt();
								$review_source = function_exists('get_field') ? get_field('lf_testimonial_source') : '';
								if (!$review_name) {
									$review_name = get_the_title();
								}
								if (!$review_text) {
									$review_text = get_the_content();
								}
								$review_text = wp_trim_words($review_text, 22, '...');
							?>
								<li class="lf-block-map-nap__review-card">
									<p class="lf-block-map-nap__review-text"><?php echo esc_html($review_text); ?></p>
									<div class="lf-block-map-nap__review-meta">
										<?php if ($review_rating) : ?>
											<span class="lf-block-map-nap__review-stars" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $review_rating)); ?>">
												<?php for ($s = 1; $s <= 5; $s++) : ?>
													<svg class="lf-block-map-nap__review-star<?php echo $s <= $review_rating ? ' lf-block-map-nap__review-star--filled' : ''; ?>" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
												<?php endfor; ?>
											</span>
										<?php endif; ?>
										<span class="lf-block-map-nap__review-name"><?php echo esc_html($review_name); ?></span>
										<?php if ($review_source) : ?>
											<span class="lf-block-map-nap__review-source"><?php echo esc_html($review_source); ?></span>
										<?php endif; ?>
									</div>
								</li>
							<?php endwhile; ?>
						</ul>
						<?php wp_reset_postdata(); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
