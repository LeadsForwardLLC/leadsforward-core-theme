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
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$section_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
$name     = $entity['name'] ?? (function_exists('lf_get_option') ? lf_get_option('lf_business_name', 'option') : '');
$phone    = $entity['phone_display'] ?? (function_exists('lf_get_option') ? lf_get_option('lf_business_phone', 'option') : '');
$email    = $entity['email'] ?? (function_exists('lf_get_option') ? lf_get_option('lf_business_email', 'option') : '');
$address  = $entity['address'] ?? (function_exists('lf_get_option') ? lf_get_option('lf_business_address', 'option') : '');
$place_id = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_id', '') : '';
$place_id = is_string($place_id) ? trim($place_id) : '';
if (is_string($place_id) && stripos($place_id, 'place_id:') === 0) {
	$place_id = trim(substr($place_id, strlen('place_id:')));
}
if ($place_id !== '' && (strlen($place_id) < 12 || preg_match('/\s/', $place_id) === 1)) {
	$place_id = '';
}
$place_name = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_name', '') : '';
$place_address = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_place_address', '') : '';
$map_embed_override = function_exists('lf_get_business_info_value') ? lf_get_business_info_value('lf_business_map_embed', '') : '';
$map_embed_override = is_string($map_embed_override) ? trim($map_embed_override) : '';
$maps_api_key = get_option('lf_maps_api_key', '');
$maps_api_key = is_string($maps_api_key) ? $maps_api_key : '';
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Areas We Serve', 'leadsforward-core');
$intro   = !empty($section['section_intro']) ? $section['section_intro'] : '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'map_nap', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'map_nap', 'left', 'lf-heading-icon') : '';
$list_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'map_nap', 'list', 'lf-block-map-nap__icon') : '';
$is_contact_layout = (string) ($section['section_intent'] ?? '') === 'contact'
	|| (function_exists('is_page') && is_page('contact'));
$cta_embed = function_exists('lf_get_option') ? lf_get_option('lf_cta_ghl_embed', 'option') : '';
$cta_embed = is_string($cta_embed) ? $cta_embed : '';
if (function_exists('lf_resolve_cta')) {
	$cta = lf_resolve_cta($context ?? [], $section, []);
	if (!empty($cta['ghl_embed']) && is_string($cta['ghl_embed'])) {
		$cta_embed = $cta['ghl_embed'];
	}
}
$intro = is_string($intro) ? $intro : '';
if ($is_contact_layout) {
	if ($heading === '' || $heading === __('Areas We Serve', 'leadsforward-core') || $heading === __('Our service area', 'leadsforward-core')) {
		$heading = __('Get in touch', 'leadsforward-core');
	}
	if ($intro === '' || $intro === __('See the areas we cover and find us on the map.', 'leadsforward-core')) {
		$intro = __('Share a few details and we will reply with next steps.', 'leadsforward-core');
	}
}
$social = is_array($entity['social'] ?? null) ? $entity['social'] : [];
$social_map = [
	'facebook' => ['label' => __('Facebook', 'leadsforward-core'), 'icon' => 'facebook'],
	'instagram' => ['label' => __('Instagram', 'leadsforward-core'), 'icon' => 'instagram'],
	'youtube' => ['label' => __('YouTube', 'leadsforward-core'), 'icon' => 'youtube'],
	'linkedin' => ['label' => __('LinkedIn', 'leadsforward-core'), 'icon' => 'linkedin'],
	'tiktok' => ['label' => __('TikTok', 'leadsforward-core'), 'icon' => 'video'],
	'x' => ['label' => __('X', 'leadsforward-core'), 'icon' => 'twitter'],
];
$social_links = [];
foreach ($social_map as $key => $meta) {
	$url = isset($social[$key]) ? trim((string) $social[$key]) : '';
	if ($url !== '') {
		$social_links[] = [
			'url' => $url,
			'label' => $meta['label'],
			'icon' => $meta['icon'],
		];
	}
}

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
if (post_type_exists('lf_testimonial')) {
	$rating_posts = get_posts([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	foreach ($rating_posts as $rating_post_id) {
		$rating_value = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', (int) $rating_post_id) : 5;
		if ($rating_value <= 1) {
			continue;
		}
		$reviews_total++;
	}
} elseif (is_array($reviews_query->posts)) {
	$reviews_total = count($reviews_query->posts);
}
$ratings_total = 0;
$ratings_count = 0;
if (!empty($reviews_query->posts)) {
	foreach ($reviews_query->posts as $review_post) {
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $review_post->ID) : 5;
		if ($rating <= 1) {
			continue;
		}
		$ratings_total += $rating;
		$ratings_count++;
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
	$query = $map_title !== '' ? $map_title : ($place_name !== '' ? $place_name : __('Business', 'leadsforward-core'));
	$map_view_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query) . '&query_place_id=' . rawurlencode($place_id);
	$map_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=place_id:' . rawurlencode($place_id);
} elseif (is_string($address) && $address !== '') {
	$map_view_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
	$map_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);
}
$map_embed_url = '';
if (is_string($map_embed_override) && $map_embed_override !== '') {
	$map_embed_url = trim((string) $map_embed_override);
} elseif (is_string($place_id) && $place_id !== '' && $maps_api_key !== '') {
	$map_embed_url = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($maps_api_key) . '&q=place_id:' . rawurlencode($place_id);
} elseif (is_string($place_id) && $place_id !== '') {
	$map_embed_url = 'https://www.google.com/maps?q=place_id:' . rawurlencode($place_id) . '&output=embed';
} elseif (is_string($address) && trim($address) !== '' && $maps_api_key !== '') {
	// Fallback when place_id is missing or invalid: embed by address so the map still works.
	$map_embed_url = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($maps_api_key) . '&q=' . rawurlencode(trim((string) $address));
} elseif (is_string($address) && trim($address) !== '') {
	$map_embed_url = 'https://www.google.com/maps?q=' . rawurlencode(trim((string) $address)) . '&output=embed';
}

$areas_query = new WP_Query([
	'post_type'      => 'lf_service_area',
	'posts_per_page' => 8,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-map-nap <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-map-nap--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>"<?php echo $section_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-map-nap__inner">
		<div class="lf-block-map-nap__grid">
			<div class="lf-block-map-nap__areas">
				<header class="lf-block-map-nap__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($icon_left) : ?>
						<div class="lf-heading-row">
							<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
							<h2 class="lf-block-map-nap__title"><?php echo esc_html($heading); ?></h2>
						</div>
					<?php else : ?>
						<h2 class="lf-block-map-nap__title"><?php echo esc_html($heading); ?></h2>
					<?php endif; ?>
					<?php if ($intro !== '') : ?>
						<p class="lf-block-map-nap__intro"><?php echo esc_html($intro); ?></p>
					<?php endif; ?>
				</header>
				<?php if ($is_contact_layout) : ?>
					<div class="lf-block-map-nap__contact">
						<?php if ($name !== '') : ?>
							<p class="lf-block-map-nap__name"><?php echo esc_html($name); ?></p>
						<?php endif; ?>
						<?php if ($address !== '') : ?>
							<p class="lf-block-map-nap__address">
								<?php if (function_exists('lf_icon')) : ?>
									<span class="lf-block-map-nap__icon" aria-hidden="true"><?php echo lf_icon('map-pin', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
								<?php endif; ?>
								<span><?php echo esc_html($address); ?></span>
							</p>
						<?php endif; ?>
						<?php if ($phone !== '') : ?>
							<a class="lf-block-map-nap__phone" href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>">
								<?php if (function_exists('lf_icon')) : ?>
									<span class="lf-block-map-nap__icon" aria-hidden="true"><?php echo lf_icon('phone', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
								<?php endif; ?>
								<span><?php echo esc_html($phone); ?></span>
							</a>
						<?php endif; ?>
						<?php if ($email !== '') : ?>
							<a class="lf-block-map-nap__email" href="mailto:<?php echo esc_attr($email); ?>">
								<?php if (function_exists('lf_icon')) : ?>
									<span class="lf-block-map-nap__icon" aria-hidden="true"><?php echo lf_icon('mail', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
								<?php endif; ?>
								<span><?php echo esc_html($email); ?></span>
							</a>
						<?php endif; ?>
						<?php if (!empty($social_links)) : ?>
							<div class="lf-block-map-nap__social" aria-label="<?php esc_attr_e('Social links', 'leadsforward-core'); ?>">
								<?php foreach ($social_links as $item) : ?>
									<a class="lf-block-map-nap__social-link" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer">
										<?php
										if (function_exists('lf_icon')) {
											echo lf_icon($item['icon'], ['class' => 'lf-icon--sm lf-icon--inherit']);
										}
										?>
										<span class="screen-reader-text"><?php echo esc_html($item['label']); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php elseif ($areas_query->have_posts()) : ?>
					<ul class="lf-block-map-nap__areas-list" role="list">
						<?php
						while ($areas_query->have_posts()) : $areas_query->the_post();
							$label = get_the_title();
						?>
							<li class="lf-block-map-nap__areas-item">
								<a href="<?php the_permalink(); ?>" class="lf-block-map-nap__areas-link">
									<?php if ($list_icon) : ?><span class="lf-block-map-nap__icon"><?php echo $list_icon; ?></span><?php endif; ?>
									<span><?php echo esc_html($label); ?></span>
								</a>
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
					<?php if ($is_contact_layout) : ?>
						<div class="lf-block-map-nap__contact-form">
							<?php if (function_exists('lf_contact_form_render')) : ?>
								<?php lf_contact_form_render(); ?>
							<?php else : ?>
								<p class="lf-block-map-nap__empty"><?php esc_html_e('Contact form is unavailable.', 'leadsforward-core'); ?></p>
							<?php endif; ?>
						</div>
					<?php elseif (is_string($map_embed_override) && $map_embed_override !== '') : ?>
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
										__('Add your Google Maps API key and select a place (or paste a map iframe) in <strong>Global Settings → Business Entity</strong> on <a href="%s">LeadsForward → Global Settings</a> to show the map.', 'leadsforward-core'),
										esc_url(admin_url('admin.php?page=lf-global#lf-business-map-embed'))
									),
									['a' => ['href' => true], 'strong' => []]
								);
							} else {
								esc_html_e('Map will appear here.', 'leadsforward-core');
							}
							?>
						</p>
					<?php endif; ?>
					<?php if (!$is_contact_layout && ($map_title || $map_view_url)) : ?>
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
