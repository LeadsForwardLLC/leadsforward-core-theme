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
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
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
<section class="lf-block lf-block-map-nap <?php echo esc_attr($bg_class ?: 'lf-surface-light'); ?> lf-block-map-nap--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>">
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
			<div class="lf-block-map-nap__panel">
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
			</div>
		</div>
	</div>
</section>
