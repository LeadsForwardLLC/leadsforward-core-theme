<?php
/**
 * SEO per-page meta box (pages, posts, services, service areas).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('add_meta_boxes', 'lf_seo_register_meta_box');
add_action('save_post', 'lf_seo_save_meta_box', 10, 2);

function lf_seo_register_meta_box(): void {
	$screens = ['page', 'post', 'lf_service', 'lf_service_area'];
	foreach ($screens as $screen) {
		add_meta_box(
			'lf-seo-meta',
			__('SEO', 'leadsforward-core'),
			'lf_seo_render_meta_box',
			$screen,
			'normal',
			'default'
		);
	}
}

function lf_seo_render_meta_box(\WP_Post $post): void {
	wp_nonce_field('lf_seo_meta_box', 'lf_seo_meta_nonce');
	$primary = (string) get_post_meta($post->ID, '_lf_seo_primary_keyword', true);
	$secondary = (string) get_post_meta($post->ID, '_lf_seo_secondary_keywords', true);
	$title = (string) get_post_meta($post->ID, '_lf_seo_meta_title', true);
	$description = (string) get_post_meta($post->ID, '_lf_seo_meta_description', true);
	$canonical = (string) get_post_meta($post->ID, '_lf_seo_canonical_url', true);
	$noindex = (string) get_post_meta($post->ID, '_lf_seo_noindex', true) === '1';
	$nofollow = (string) get_post_meta($post->ID, '_lf_seo_nofollow', true) === '1';
	$og_image_id = (int) get_post_meta($post->ID, '_lf_seo_og_image_id', true);
	$og_image_url = $og_image_id ? wp_get_attachment_image_url($og_image_id, 'medium') : '';
	$scripts = get_post_meta($post->ID, '_lf_seo_scripts', true);
	if (!is_array($scripts)) {
		$scripts = [];
	}
	$header_scripts = (string) ($scripts['header'] ?? '');
	$footer_scripts = (string) ($scripts['footer'] ?? '');
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lf_seo_primary_keyword"><?php esc_html_e('Primary Target Keyword', 'leadsforward-core'); ?></label></th>
			<td><input type="text" class="large-text" id="lf_seo_primary_keyword" name="lf_seo_primary_keyword" value="<?php echo esc_attr($primary); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_secondary_keywords"><?php esc_html_e('Secondary Keywords', 'leadsforward-core'); ?></label></th>
			<td><textarea class="large-text" rows="2" id="lf_seo_secondary_keywords" name="lf_seo_secondary_keywords"><?php echo esc_textarea($secondary); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_meta_title"><?php esc_html_e('Custom Meta Title', 'leadsforward-core'); ?></label></th>
			<td><input type="text" class="large-text" id="lf_seo_meta_title" name="lf_seo_meta_title" value="<?php echo esc_attr($title); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_meta_description"><?php esc_html_e('Custom Meta Description', 'leadsforward-core'); ?></label></th>
			<td><textarea class="large-text" rows="2" id="lf_seo_meta_description" name="lf_seo_meta_description"><?php echo esc_textarea($description); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_canonical_url"><?php esc_html_e('Canonical URL', 'leadsforward-core'); ?></label></th>
			<td><input type="url" class="large-text" id="lf_seo_canonical_url" name="lf_seo_canonical_url" value="<?php echo esc_attr($canonical); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('Indexing', 'leadsforward-core'); ?></th>
			<td>
				<label style="margin-right:16px;">
					<input type="checkbox" name="lf_seo_noindex" value="1" <?php checked($noindex); ?> />
					<?php esc_html_e('Noindex', 'leadsforward-core'); ?>
				</label>
				<label>
					<input type="checkbox" name="lf_seo_nofollow" value="1" <?php checked($nofollow); ?> />
					<?php esc_html_e('Nofollow', 'leadsforward-core'); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e('OG Image Override', 'leadsforward-core'); ?></th>
			<td>
				<div style="display:flex;align-items:center;gap:1rem;">
					<div>
						<img id="lf-seo-og-preview" src="<?php echo esc_url($og_image_url); ?>" style="max-height:80px;<?php echo $og_image_url ? '' : 'display:none;'; ?>" alt="" />
					</div>
					<input type="hidden" name="lf_seo_og_image_id" id="lf_seo_og_image_id" value="<?php echo esc_attr((string) $og_image_id); ?>" />
					<button type="button" class="button" id="lf-seo-og-select"><?php esc_html_e('Select Image', 'leadsforward-core'); ?></button>
					<button type="button" class="button" id="lf-seo-og-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_header_scripts_override"><?php esc_html_e('Header script override', 'leadsforward-core'); ?></label></th>
			<td>
				<textarea class="large-text code" rows="3" id="lf_seo_header_scripts_override" name="lf_seo_header_scripts_override"><?php echo esc_textarea($header_scripts); ?></textarea>
				<p class="description"><?php esc_html_e('Overrides global header scripts for this page only.', 'leadsforward-core'); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lf_seo_footer_scripts_override"><?php esc_html_e('Footer script override', 'leadsforward-core'); ?></label></th>
			<td>
				<textarea class="large-text code" rows="3" id="lf_seo_footer_scripts_override" name="lf_seo_footer_scripts_override"><?php echo esc_textarea($footer_scripts); ?></textarea>
				<p class="description"><?php esc_html_e('Overrides global footer scripts for this page only.', 'leadsforward-core'); ?></p>
			</td>
		</tr>
	</table>
	<script>
		(function () {
			var frame;
			var selectBtn = document.getElementById('lf-seo-og-select');
			var clearBtn = document.getElementById('lf-seo-og-clear');
			var input = document.getElementById('lf_seo_og_image_id');
			var preview = document.getElementById('lf-seo-og-preview');
			if (selectBtn) {
				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select OG Image', button: { text: 'Use image' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (input) input.value = attachment.id;
						if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
					});
					frame.open();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (input) input.value = '';
					if (preview) { preview.src = ''; preview.style.display = 'none'; }
				});
			}
		})();
	</script>
	<?php
}

function lf_seo_save_meta_box(int $post_id, \WP_Post $post): void {
	if (!isset($_POST['lf_seo_meta_nonce']) || !wp_verify_nonce($_POST['lf_seo_meta_nonce'], 'lf_seo_meta_box')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$primary = isset($_POST['lf_seo_primary_keyword']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_primary_keyword'])) : '';
	$secondary = isset($_POST['lf_seo_secondary_keywords']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_secondary_keywords'])) : '';
	$title = isset($_POST['lf_seo_meta_title']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_meta_title'])) : '';
	$description = isset($_POST['lf_seo_meta_description']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_meta_description'])) : '';
	$canonical = isset($_POST['lf_seo_canonical_url']) ? esc_url_raw(wp_unslash($_POST['lf_seo_canonical_url'])) : '';
	$noindex = !empty($_POST['lf_seo_noindex']) ? '1' : '';
	$nofollow = !empty($_POST['lf_seo_nofollow']) ? '1' : '';
	$og_image_id = isset($_POST['lf_seo_og_image_id']) ? (int) $_POST['lf_seo_og_image_id'] : 0;
	$header_scripts = isset($_POST['lf_seo_header_scripts_override']) ? wp_unslash($_POST['lf_seo_header_scripts_override']) : '';
	$footer_scripts = isset($_POST['lf_seo_footer_scripts_override']) ? wp_unslash($_POST['lf_seo_footer_scripts_override']) : '';

	lf_seo_update_post_meta($post_id, '_lf_seo_primary_keyword', $primary);
	lf_seo_update_post_meta($post_id, '_lf_seo_secondary_keywords', $secondary);
	lf_seo_update_post_meta($post_id, '_lf_seo_meta_title', $title);
	lf_seo_update_post_meta($post_id, '_lf_seo_meta_description', $description);
	lf_seo_update_post_meta($post_id, '_lf_seo_canonical_url', $canonical);
	lf_seo_update_post_meta($post_id, '_lf_seo_noindex', $noindex);
	lf_seo_update_post_meta($post_id, '_lf_seo_nofollow', $nofollow);
	lf_seo_update_post_meta($post_id, '_lf_seo_og_image_id', $og_image_id ? (string) $og_image_id : '');

	$header_scripts = function_exists('lf_seo_sanitize_scripts') ? lf_seo_sanitize_scripts((string) $header_scripts) : '';
	$footer_scripts = function_exists('lf_seo_sanitize_scripts') ? lf_seo_sanitize_scripts((string) $footer_scripts) : '';
	if ($header_scripts === '' && $footer_scripts === '') {
		delete_post_meta($post_id, '_lf_seo_scripts');
	} else {
		update_post_meta($post_id, '_lf_seo_scripts', [
			'header' => $header_scripts,
			'footer' => $footer_scripts,
		]);
	}

	if (function_exists('lf_seo_register_keyword_map_for_post')) {
		lf_seo_register_keyword_map_for_post($post_id, $primary);
	}
	if (function_exists('lf_seo_maybe_populate_generated_meta')) {
		lf_seo_maybe_populate_generated_meta($post_id, $primary, $secondary);
	}
}

function lf_seo_update_post_meta(int $post_id, string $key, string $value): void {
	if ($value === '') {
		delete_post_meta($post_id, $key);
		return;
	}
	update_post_meta($post_id, $key, $value);
}

/**
 * Auto-fill SEO fields when keyword data exists but meta fields are blank.
 *
 * @param int          $post_id Post ID.
 * @param string       $primary Optional primary keyword override.
 * @param string|array $secondary Optional secondary keywords (CSV/newlines or array).
 */
function lf_seo_maybe_populate_generated_meta(int $post_id, string $primary = '', $secondary = ''): void {
	if ($post_id <= 0) {
		return;
	}
	$post_type = get_post_type($post_id);
	if (!in_array($post_type, ['page', 'post', 'lf_service', 'lf_service_area'], true)) {
		return;
	}

	$primary_keyword = trim($primary);
	if ($primary_keyword === '') {
		$primary_keyword = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
	}
	if ($primary_keyword === '') {
		return;
	}

	$secondary_list = lf_seo_normalize_secondary_keywords($secondary);
	if (empty($secondary_list)) {
		$secondary_list = lf_seo_normalize_secondary_keywords((string) get_post_meta($post_id, '_lf_seo_secondary_keywords', true));
	}
	if (empty($secondary_list)) {
		$map = function_exists('lf_seo_get_keyword_map') ? lf_seo_get_keyword_map() : [];
		$pool = is_array($map['secondary']['pool'] ?? null) ? $map['secondary']['pool'] : [];
		$pool = array_values(array_filter(array_map('sanitize_text_field', $pool)));
		foreach ($pool as $candidate) {
			if (strcasecmp($candidate, $primary_keyword) === 0) {
				continue;
			}
			$secondary_list[] = $candidate;
			if (count($secondary_list) >= 2) {
				break;
			}
		}
	}

	$current_secondary = trim((string) get_post_meta($post_id, '_lf_seo_secondary_keywords', true));
	if ($current_secondary === '' && !empty($secondary_list)) {
		update_post_meta($post_id, '_lf_seo_secondary_keywords', implode(', ', $secondary_list));
	}

	$current_title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
	if ($current_title === '' || lf_seo_meta_text_needs_upgrade($current_title, 'title')) {
		$title = lf_seo_generate_meta_title_from_keywords($primary_keyword);
		if ($title !== '') {
			update_post_meta($post_id, '_lf_seo_meta_title', $title);
		}
	}

	$current_description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
	if ($current_description === '' || lf_seo_meta_text_needs_upgrade($current_description, 'description')) {
		$description = lf_seo_generate_meta_description_from_keywords($post_id, $primary_keyword, $secondary_list);
		if ($description !== '') {
			update_post_meta($post_id, '_lf_seo_meta_description', $description);
		}
	}
}

/**
 * @param string|array $value
 * @return string[]
 */
function lf_seo_normalize_secondary_keywords($value): array {
	if (is_array($value)) {
		$items = $value;
	} else {
		$raw = trim((string) $value);
		$items = $raw === '' ? [] : preg_split('/\r\n|\r|\n|,/', $raw);
	}
	if (!is_array($items)) {
		return [];
	}
	$items = array_values(array_unique(array_filter(array_map(static function ($item): string {
		return sanitize_text_field((string) $item);
	}, $items))));
	return $items;
}

function lf_seo_generate_meta_title_from_keywords(string $primary_keyword): string {
	$primary_keyword = trim($primary_keyword);
	if ($primary_keyword === '') {
		return '';
	}
	$city = '';
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$city = trim((string) ($entity['address_parts']['city'] ?? ''));
	}
	if ($city === '') {
		$city = trim((string) get_option('lf_homepage_city', ''));
	}
	$brand = trim((string) get_bloginfo('name'));
	$title_parts = [$primary_keyword];
	if ($city !== '' && stripos($primary_keyword, $city) === false) {
		$title_parts[] = $city;
	}
	if ($brand !== '' && stripos($primary_keyword, $brand) === false) {
		$title_parts[] = $brand;
	}
	$title = trim(implode(' | ', array_filter($title_parts)));
	if (function_exists('mb_substr') && mb_strlen($title) > 62) {
		$title = rtrim(mb_substr($title, 0, 62));
	}
	return $title;
}

function lf_seo_meta_text_needs_upgrade(string $value, string $type = 'description'): bool {
	$value = trim(wp_strip_all_tags($value));
	if ($value === '') {
		return true;
	}
	$lower = strtolower($value);
	$generic_patterns = [
		'get a fast quote today',
		'lorem ipsum',
		'placeholder',
		'sample',
		'trusted experts',
	];
	foreach ($generic_patterns as $pattern) {
		if (strpos($lower, $pattern) !== false) {
			return true;
		}
	}
	if ($type === 'title') {
		return function_exists('mb_strlen') ? mb_strlen($value) < 32 : strlen($value) < 32;
	}
	return function_exists('mb_strlen') ? mb_strlen($value) < 120 : strlen($value) < 120;
}

function lf_seo_post_location_phrase(int $post_id): string {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return '';
	}
	$post_title = trim((string) $post->post_title);
	if ($post->post_type === 'lf_service_area' && $post_title !== '') {
		return $post_title;
	}
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$city = trim((string) ($entity['address_parts']['city'] ?? ''));
		if ($city !== '') {
			return $city;
		}
	}
	return trim((string) get_option('lf_homepage_city', ''));
}

function lf_seo_generate_meta_description_from_keywords(int $post_id, string $primary_keyword, array $secondary_keywords = []): string {
	$primary_keyword = trim($primary_keyword);
	if ($primary_keyword === '') {
		return '';
	}
	$secondary_keywords = array_values(array_filter($secondary_keywords, static function (string $keyword) use ($primary_keyword): bool {
		return strcasecmp($keyword, $primary_keyword) !== 0;
	}));
	$secondary_keywords = array_slice($secondary_keywords, 0, 2);

	$post_title = trim((string) get_the_title($post_id));
	if ($post_title === '') {
		$post_title = __('our services', 'leadsforward-core');
	}
	$location = lf_seo_post_location_phrase($post_id);
	$brand = trim((string) get_bloginfo('name'));
	$post_type = (string) get_post_type($post_id);
	$intent_phrase = $post_type === 'post'
		? __('in-depth guide', 'leadsforward-core')
		: __('local service', 'leadsforward-core');
	$secondary_phrase = '';
	if (!empty($secondary_keywords)) {
		$secondary_phrase = implode(', ', array_slice($secondary_keywords, 0, 2));
	}

	$description = sprintf(
		/* translators: 1: primary keyword, 2: location, 3: post title, 4: intent phrase, 5: secondary phrase, 6: brand */
		__('%1$s in %2$s from %6$s. This %4$s for %3$s covers process, pricing, timelines, and quality standards to help you choose confidently.', 'leadsforward-core'),
		$primary_keyword,
		$location !== '' ? $location : __('your area', 'leadsforward-core'),
		$post_title,
		$intent_phrase,
		$secondary_phrase,
		$brand !== '' ? $brand : __('our team', 'leadsforward-core')
	);
	if ($secondary_phrase !== '') {
		$description .= ' ' . sprintf(
			/* translators: %s secondary keywords list */
			__('Related services: %s.', 'leadsforward-core'),
			$secondary_phrase
		);
	}
	$description .= ' ' . __('Request a quote for a tailored plan.', 'leadsforward-core');

	$description = trim(preg_replace('/\s+/', ' ', $description));
	if (function_exists('mb_substr') && mb_strlen($description) > 160) {
		$description = rtrim(mb_substr($description, 0, 157), " \t\n\r\0\x0B,.;:-") . '...';
	}
	return $description;
}

function lf_seo_refresh_metadata_for_generated_content(int $limit = 400): void {
	$posts = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => $limit,
		'orderby' => 'date',
		'order' => 'DESC',
		'no_found_rows' => true,
	]);
	foreach ($posts as $post) {
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$post_id = (int) $post->ID;
		$primary = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
		if ($primary === '') {
			continue;
		}
		$secondary = lf_seo_normalize_secondary_keywords((string) get_post_meta($post_id, '_lf_seo_secondary_keywords', true));
		lf_seo_maybe_populate_generated_meta($post_id, $primary, $secondary);

		$current_title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
		if (lf_seo_meta_text_needs_upgrade($current_title, 'title')) {
			$new_title = lf_seo_generate_meta_title_from_keywords($primary);
			if ($new_title !== '') {
				update_post_meta($post_id, '_lf_seo_meta_title', $new_title);
			}
		}
		$current_description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
		if (lf_seo_meta_text_needs_upgrade($current_description, 'description')) {
			$new_description = lf_seo_generate_meta_description_from_keywords($post_id, $primary, $secondary);
			if ($new_description !== '') {
				update_post_meta($post_id, '_lf_seo_meta_description', $new_description);
			}
		}
		$canonical = trim((string) get_post_meta($post_id, '_lf_seo_canonical_url', true));
		$permalink = (string) get_permalink($post_id);
		if ($canonical === '' && $permalink !== '') {
			update_post_meta($post_id, '_lf_seo_canonical_url', esc_url_raw($permalink));
		}
		$og_image = (int) get_post_meta($post_id, '_lf_seo_og_image_id', true);
		if ($og_image <= 0 && has_post_thumbnail($post_id)) {
			update_post_meta($post_id, '_lf_seo_og_image_id', (string) get_post_thumbnail_id($post_id));
		}
	}
}
