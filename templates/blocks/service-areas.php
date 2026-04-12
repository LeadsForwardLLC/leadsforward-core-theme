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
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$map_heading = !empty($section['map_heading']) ? (string) $section['map_heading'] : __('Service area map', 'leadsforward-core');
$map_intro = !empty($section['map_intro']) ? (string) $section['map_intro'] : __('Map pins show the areas currently covered by our team.', 'leadsforward-core');
$search_placeholder = !empty($section['search_placeholder']) ? (string) $section['search_placeholder'] : __('Search city or neighborhood', 'leadsforward-core');
$filter_label = !empty($section['filter_label']) ? (string) $section['filter_label'] : __('Filter by service area', 'leadsforward-core');
$filter_all_label = !empty($section['filter_all_label']) ? (string) $section['filter_all_label'] : __('All areas', 'leadsforward-core');
$no_results_text = !empty($section['no_results_text']) ? (string) $section['no_results_text'] : __('No service areas match your search yet. Clear filters to view all coverage.', 'leadsforward-core');
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'soft') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$section_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_areas', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_areas', 'left', 'lf-heading-icon') : '';
$card_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_areas', 'list', 'lf-block-service-areas__icon') : '';
$render_id = $block_id !== '' ? $block_id : 'block-' . uniqid();

$query = new WP_Query([
	'post_type'      => 'lf_service_area',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);

$business_geo = [];
if (function_exists('lf_get_option')) {
	$raw_business_geo = lf_get_option('lf_business_geo', 'option');
	if (is_array($raw_business_geo)) {
		$business_geo = $raw_business_geo;
	}
}

$business_lat = isset($business_geo['lat']) ? (float) $business_geo['lat'] : 0.0;
$business_lng = isset($business_geo['lng']) ? (float) $business_geo['lng'] : 0.0;
$has_business_geo = $business_lat !== 0.0 && $business_lng !== 0.0;
$areas = [];
if ($query->have_posts()) {
	while ($query->have_posts()) {
		$query->the_post();
		$area_id = (int) get_the_ID();
		$area_title = (string) get_the_title();
		$area_url = (string) get_permalink();
		$area_state = '';
		if (function_exists('get_field')) {
			$area_state = (string) get_field('lf_service_area_state', $area_id);
		}
		if ($area_state === '') {
			$area_state = (string) get_post_meta($area_id, 'lf_service_area_state', true);
		}
		$area_state = strtoupper(trim($area_state));
		$geo = function_exists('get_field') ? get_field('lf_service_area_geo', $area_id) : null;
		$lat = 0.0;
		$lng = 0.0;
		$exact_geo = false;
		if (is_array($geo) && isset($geo['lat'], $geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '') {
			$lat = (float) $geo['lat'];
			$lng = (float) $geo['lng'];
			$exact_geo = true;
		}
		if (($lat === 0.0 || $lng === 0.0) && $has_business_geo) {
			$seed = abs((int) crc32(sanitize_title($area_title)));
			$angle = deg2rad((float) ($seed % 360));
			$distance = 0.08 + (($seed % 35) / 1000); // approximate ring around business HQ
			$lat = $business_lat + ($distance * cos($angle));
			$lng = $business_lng + ($distance * sin($angle));
		}
		$areas[] = [
			'id' => $area_id,
			'title' => wp_strip_all_tags($area_title),
			'url' => esc_url_raw($area_url),
			'state' => $area_state,
			'lat' => $lat,
			'lng' => $lng,
			'has_coords' => ($lat !== 0.0 || $lng !== 0.0),
			'exact_geo' => $exact_geo,
		];
	}
	wp_reset_postdata();
}
$area_filter_options = $areas;
usort(
	$area_filter_options,
	static function (array $a, array $b): int {
		return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
	}
);
$map_embed_html = '';
if (function_exists('lf_get_business_info_value')) {
	$map_embed_html = trim((string) lf_get_business_info_value('lf_business_map_embed', ''));
}
$use_embed_map = $map_embed_html !== '';
$points_json = wp_json_encode(array_map(static function (array $area): array {
	return [
		'title' => (string) ($area['title'] ?? ''),
		'url' => (string) ($area['url'] ?? ''),
		'state' => (string) ($area['state'] ?? ''),
		'lat' => (float) ($area['lat'] ?? 0.0),
		'lng' => (float) ($area['lng'] ?? 0.0),
		'has_coords' => !empty($area['has_coords']),
		'exact' => !empty($area['exact_geo']),
	];
}, $areas));
?>
<section class="lf-block lf-block-service-areas <?php echo esc_attr($surface['class']); ?> lf-block-service-areas--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($render_id); ?>" data-variant="<?php echo esc_attr($variant); ?>"<?php echo $section_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-service-areas__inner">
		<header class="lf-block-service-areas__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-areas__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
				</div>
			<?php else : ?>
				<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-areas__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
			<?php endif; ?>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-areas__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if (!empty($areas)) : ?>
			<div class="lf-block-service-areas__controls">
				<div class="lf-block-service-areas__control">
					<label for="<?php echo esc_attr(($block_id ?: 'service-areas') . '-search'); ?>" class="lf-block-service-areas__control-label"><?php esc_html_e('Search', 'leadsforward-core'); ?></label>
					<input id="<?php echo esc_attr(($block_id ?: 'service-areas') . '-search'); ?>" type="search" class="lf-block-service-areas__search" placeholder="<?php echo esc_attr($search_placeholder); ?>" data-service-areas-search />
				</div>
				<div class="lf-block-service-areas__control">
					<label for="<?php echo esc_attr(($block_id ?: 'service-areas') . '-filter'); ?>" class="lf-block-service-areas__control-label"><?php echo esc_html($filter_label); ?></label>
					<select id="<?php echo esc_attr(($block_id ?: 'service-areas') . '-filter'); ?>" class="lf-block-service-areas__filter" data-service-areas-filter>
						<option value=""><?php echo esc_html($filter_all_label); ?></option>
						<?php foreach ($area_filter_options as $opt) : ?>
							<?php
							$fid = isset($opt['id']) ? (int) $opt['id'] : 0;
							$ftitle = isset($opt['title']) ? (string) $opt['title'] : '';
							if ($fid <= 0 || $ftitle === '') {
								continue;
							}
							?>
							<option value="<?php echo esc_attr((string) $fid); ?>"><?php echo esc_html($ftitle); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="lf-block-service-areas__map-wrap">
				<h3 class="lf-block-service-areas__map-heading"><?php echo esc_html($map_heading); ?></h3>
				<?php if ($map_intro !== '') : ?>
					<p class="lf-block-service-areas__map-intro"><?php echo esc_html($map_intro); ?></p>
				<?php endif; ?>
				<?php if ($use_embed_map) : ?>
					<div class="lf-block-service-areas__map lf-block-service-areas__map--embed">
						<?php
						$allowed_iframe = function_exists('lf_map_embed_allowed_iframe_kses')
							? lf_map_embed_allowed_iframe_kses()
							: ['iframe' => ['src' => true, 'width' => true, 'height' => true]];
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iframe HTML from Global Settings, passed through wp_kses.
						echo wp_kses($map_embed_html, $allowed_iframe);
						?>
					</div>
				<?php else : ?>
					<div class="lf-block-service-areas__map" data-service-areas-map data-map-points="<?php echo esc_attr((string) $points_json); ?>"></div>
				<?php endif; ?>
			</div>

			<ul class="lf-block-service-areas__list lf-cpt-driven-links" role="list" data-service-areas-list>
				<?php foreach ($areas as $index => $area) : ?>
					<li class="lf-block-service-areas__item" data-title="<?php echo esc_attr((string) ($area['title'] ?? '')); ?>" data-area-id="<?php echo esc_attr((string) (int) ($area['id'] ?? 0)); ?>" data-point-index="<?php echo esc_attr((string) $index); ?>">
						<a href="<?php echo esc_url((string) ($area['url'] ?? '')); ?>" class="lf-block-service-areas__link">
							<?php if ($card_icon) : ?><span class="lf-block-service-areas__icon"><?php echo $card_icon; ?></span><?php endif; ?>
							<span class="lf-block-service-areas__card-title"><?php echo esc_html((string) ($area['title'] ?? '')); ?></span>
							<span class="lf-block-service-areas__card-action" aria-hidden="true"><?php esc_html_e('View area', 'leadsforward-core'); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="lf-block-service-areas__no-results" data-service-areas-empty hidden><?php echo esc_html($no_results_text); ?></p>
		<?php else : ?>
			<p class="lf-block-service-areas__empty"><?php esc_html_e('No service areas yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php if (!empty($areas)) : ?>
	<script>
		(function () {
			var root = document.getElementById(<?php echo wp_json_encode($render_id); ?>);
			if (!root) return;
			var searchInput = root.querySelector('[data-service-areas-search]');
			var filterSelect = root.querySelector('[data-service-areas-filter]');
			var list = root.querySelector('[data-service-areas-list]');
			var emptyState = root.querySelector('[data-service-areas-empty]');
			var mapEl = root.querySelector('[data-service-areas-map]');
			var useLeafletMap = !!(mapEl && mapEl.hasAttribute('data-map-points'));
			var items = list ? Array.prototype.slice.call(list.querySelectorAll('.lf-block-service-areas__item')) : [];
			var points = [];
			if (useLeafletMap) {
				try {
					points = JSON.parse(mapEl.getAttribute('data-map-points') ? mapEl.getAttribute('data-map-points') : '[]');
				} catch (e) {
					points = [];
				}
			}
			var map = null;
			var markerLayer = null;

			function loadLeaflet(callback) {
				if (window.L) {
					callback();
					return;
				}
				if (!document.querySelector('link[data-lf-leaflet]')) {
					var link = document.createElement('link');
					link.rel = 'stylesheet';
					link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
					link.setAttribute('data-lf-leaflet', '1');
					document.head.appendChild(link);
				}
				if (!document.querySelector('script[data-lf-leaflet]')) {
					var script = document.createElement('script');
					script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
					script.async = true;
					script.setAttribute('data-lf-leaflet', '1');
					script.onload = callback;
					document.body.appendChild(script);
				} else {
					var wait = window.setInterval(function () {
						if (window.L) {
							window.clearInterval(wait);
							callback();
						}
					}, 120);
				}
			}

			function visibleIndexes() {
				var query = searchInput ? String(searchInput.value || '').toLowerCase().trim() : '';
				var areaId = filterSelect ? String(filterSelect.value || '').trim() : '';
				var visible = [];
				var shown = 0;
				items.forEach(function (item, idx) {
					var title = String(item.getAttribute('data-title') || '').toLowerCase();
					var itemId = String(item.getAttribute('data-area-id') || '').trim();
					var matchesQuery = !query || title.indexOf(query) !== -1;
					var matchesArea = !areaId || itemId === areaId;
					var show = matchesQuery && matchesArea;
					item.hidden = !show;
					if (show) {
						visible.push(idx);
						shown += 1;
					}
				});
				if (emptyState) {
					emptyState.hidden = shown > 0;
				}
				return visible;
			}

			function renderMap() {
				if (!mapEl || !window.L || !Array.isArray(points) || !points.length) {
					if (mapEl) mapEl.setAttribute('data-map-empty', '1');
					return;
				}
				if (!map) {
					map = window.L.map(mapEl, { scrollWheelZoom: false });
					window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
						attribution: '&copy; OpenStreetMap contributors'
					}).addTo(map);
				}
				if (markerLayer) {
					markerLayer.remove();
				}
				markerLayer = window.L.layerGroup();
				var indexes = visibleIndexes();
				var bounds = [];
				indexes.forEach(function (idx) {
					var point = points[idx];
					if (!point || !point.has_coords || !point.lat || !point.lng) return;
					var marker = window.L.circleMarker([point.lat, point.lng], {
						radius: 7,
						color: '#15803d',
						fillColor: '#16a34a',
						fillOpacity: 0.9,
						weight: 2
					});
					var title = String(point.title || '');
					var url = String(point.url || '#');
					marker.bindPopup('<a href=\"' + url + '\">' + title + '</a>');
					marker.addTo(markerLayer);
					bounds.push([point.lat, point.lng]);
				});
				markerLayer.addTo(map);
				if (bounds.length) {
					mapEl.removeAttribute('data-map-empty');
					map.fitBounds(bounds, { padding: [28, 28], maxZoom: 10 });
				} else {
					mapEl.setAttribute('data-map-empty', '1');
				}
			}

			function onFilterChange() {
				visibleIndexes();
				if (map) {
					renderMap();
				}
			}

			if (searchInput) searchInput.addEventListener('input', onFilterChange);
			if (filterSelect) filterSelect.addEventListener('change', onFilterChange);
			visibleIndexes();
			if (useLeafletMap) {
				loadLeaflet(renderMap);
			}
		})();
	</script>
<?php endif; ?>
