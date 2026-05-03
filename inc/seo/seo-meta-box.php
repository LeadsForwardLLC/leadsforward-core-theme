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
	$intent = (string) get_post_meta($post->ID, '_lf_seo_serp_intent', true);
	$quality_score = (int) get_post_meta($post->ID, '_lf_seo_quality_score', true);
	$quality_grade = (string) get_post_meta($post->ID, '_lf_seo_quality_grade', true);
	$detected_intent = (string) get_post_meta($post->ID, '_lf_seo_serp_intent_detected', true);
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
			<td>
				<input type="text" class="large-text" id="lf_seo_primary_keyword" name="lf_seo_primary_keyword" value="<?php echo esc_attr($primary); ?>" />
				<p class="description"><?php esc_html_e('This phrase names what the URL is optimized for. Airtable Sitemap sync writes it here for matching pages and CPTs; after sync, meta may refresh automatically for transactional/local intents. The on-page checklist below is advisory only—it never edits copy. When you change the keyword manually, revisit title/description or rerun sitemap sync.', 'leadsforward-core'); ?></p>
			</td>
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
			<th scope="row"><label for="lf_seo_serp_intent"><?php esc_html_e('SERP Intent', 'leadsforward-core'); ?></label></th>
			<td>
				<select id="lf_seo_serp_intent" name="lf_seo_serp_intent">
					<option value=""><?php esc_html_e('Auto-detect', 'leadsforward-core'); ?></option>
					<?php
					$intent_options = function_exists('lf_seo_serp_intent_options') ? lf_seo_serp_intent_options() : [];
					foreach ($intent_options as $value => $label) {
						echo '<option value="' . esc_attr((string) $value) . '"' . selected($intent === (string) $value, true, false) . '>' . esc_html((string) $label) . '</option>';
					}
					?>
				</select>
				<p class="description">
					<?php
					if ($quality_score > 0) {
						echo esc_html(sprintf(__('SEO quality score: %1$d (%2$s).', 'leadsforward-core'), $quality_score, $quality_grade !== '' ? $quality_grade : ''));
					} else {
						esc_html_e('SEO quality score will be calculated after save/generation.', 'leadsforward-core');
					}
					if ($detected_intent !== '') {
						echo ' ' . esc_html(sprintf(__('Detected intent: %s.', 'leadsforward-core'), $detected_intent));
					}
					?>
				</p>
				<?php if (function_exists('lf_seo_get_onpage_checklist_rows')) : ?>
					<div class="lf-seo-onpage-checklist" style="margin-top:12px;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
						<strong style="display:block;margin-bottom:8px;"><?php esc_html_e('On-page depth checklist', 'leadsforward-core'); ?></strong>
						<ul style="margin:0;padding-left:1.1rem;line-height:1.55;">
							<?php foreach (lf_seo_get_onpage_checklist_rows((int) $post->ID) as $row) : ?>
								<li>
									<span style="color:<?php echo !empty($row['ok']) ? '#15803d' : '#b45309'; ?>;font-weight:600;"><?php echo !empty($row['ok']) ? '✓' : '○'; ?></span>
									<?php echo esc_html((string) ($row['label'] ?? '')); ?> —
									<span class="description"><?php echo esc_html((string) ($row['detail'] ?? '')); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</td>
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
	$intent = isset($_POST['lf_seo_serp_intent']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_serp_intent'])) : '';
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
	lf_seo_update_post_meta($post_id, '_lf_seo_serp_intent', $intent);
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
	if (function_exists('lf_seo_calculate_content_quality')) {
		lf_seo_calculate_content_quality($post_id);
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
 * @param bool         $airtable_keyword_authoritative When true (e.g. Airtable sitemap sync), refresh generated title/description when the primary phrase is absent for transactional/local intent.
 */
function lf_seo_maybe_populate_generated_meta(int $post_id, string $primary = '', $secondary = '', bool $airtable_keyword_authoritative = false): void {
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

	if (function_exists('lf_seo_normalize_primary_keyword_core')) {
		$persisted = lf_seo_normalize_primary_keyword_core($primary_keyword, $post_id);
		if (
			$persisted !== ''
			&& $persisted !== $primary_keyword
			&& (mb_strlen($primary_keyword, 'UTF-8') - mb_strlen($persisted, 'UTF-8')) > 25
			&& $airtable_keyword_authoritative
		) {
			lf_seo_update_post_meta($post_id, '_lf_seo_primary_keyword', $persisted);
			$primary_keyword = $persisted;
			if (function_exists('lf_seo_register_keyword_map_for_post')) {
				lf_seo_register_keyword_map_for_post($post_id, $persisted);
			}
		}
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

	$enforce_keyword_phrase = false;
	if ($airtable_keyword_authoritative && function_exists('lf_seo_detect_serp_intent')) {
		$intent = lf_seo_detect_serp_intent($post_id, $primary_keyword);
		$enforce_keyword_phrase = in_array($intent, ['transactional', 'local'], true);
	}

	$current_title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
	if (
		$current_title === ''
		|| lf_seo_meta_text_needs_upgrade($current_title, 'title')
		|| (
			$enforce_keyword_phrase
			&& lf_seo_meta_field_missing_primary_keyword($current_title, $primary_keyword)
		)
	) {
		$title = function_exists('lf_seo_generate_meta_title_for_intent')
			? lf_seo_generate_meta_title_for_intent($post_id, $primary_keyword)
			: lf_seo_generate_meta_title_from_keywords($primary_keyword, $post_id);
		if ($title !== '') {
			update_post_meta($post_id, '_lf_seo_meta_title', $title);
		}
	}

	$current_description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
	if (
		$current_description === ''
		|| lf_seo_meta_text_needs_upgrade($current_description, 'description')
		|| (
			$enforce_keyword_phrase
			&& lf_seo_meta_field_missing_primary_keyword($current_description, $primary_keyword)
		)
	) {
		$description = function_exists('lf_seo_generate_meta_description_for_intent')
			? lf_seo_generate_meta_description_for_intent($post_id, $primary_keyword, $secondary_list)
			: lf_seo_generate_meta_description_from_keywords($post_id, $primary_keyword, $secondary_list);
		if ($description !== '') {
			update_post_meta($post_id, '_lf_seo_meta_description', $description);
		}
	}
	if (function_exists('lf_seo_calculate_content_quality')) {
		lf_seo_calculate_content_quality($post_id);
	}
}

/**
 * Whether visible meta text lacks the target primary phrase (case-insensitive).
 * Short phrases are skipped to avoid wiping navigational snippets for tiny tokens.
 */
function lf_seo_meta_field_missing_primary_keyword(string $text, string $primary_keyword): bool {
	$text = trim(wp_strip_all_tags($text));
	$kw = preg_replace('/\s+/u', ' ', trim($primary_keyword));
	if ($text === '' || $kw === '') {
		return false;
	}
	if (function_exists('mb_strlen') && mb_strlen($kw, 'UTF-8') < 8) {
		return false;
	}
	return function_exists('mb_stripos')
		? mb_stripos($text, $kw, 0, 'UTF-8') === false
		: stripos($text, $kw) === false;
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

function lf_seo_generate_meta_title_from_keywords(string $primary_keyword, int $post_id = 0): string {
	if ($post_id > 0 && function_exists('lf_seo_detect_serp_intent')) {
		$intent = lf_seo_detect_serp_intent($post_id, $primary_keyword);
		if (
			function_exists('lf_seo_structured_serp_meta_enabled')
			&& lf_seo_structured_serp_meta_enabled()
			&& function_exists('lf_seo_compose_structured_meta_title')
		) {
			$composed = lf_seo_compose_structured_meta_title($post_id, $primary_keyword, $intent);
			if ($composed !== '') {
				return $composed;
			}
		}
	}
	$primary_keyword = trim($primary_keyword);
	if ($primary_keyword === '') {
		return '';
	}
	$kern = function_exists('lf_seo_normalize_primary_keyword_core')
		? lf_seo_normalize_primary_keyword_core($primary_keyword, $post_id)
		: $primary_keyword;
	$disp = function_exists('lf_seo_title_case_display_phrase')
		? lf_seo_title_case_display_phrase($kern)
		: $kern;
	if ($disp === '') {
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
	if ($city === '' && function_exists('lf_seo_get_city_name')) {
		$city = trim((string) lf_seo_get_city_name());
	}
	$brand = function_exists('lf_seo_short_brand_for_serp')
		? lf_seo_short_brand_for_serp()
		: trim((string) get_bloginfo('name'));
	$brand = $brand !== '' ? $brand : trim((string) get_bloginfo('name'));

	$core = ($city !== ''
		&& !lf_seo_phrase_contains_place(mb_strtolower($disp, 'UTF-8'), mb_strtolower($city, 'UTF-8')))
		? trim($disp . ' in ' . lf_seo_title_case_display_phrase($city))
		: $disp;
	$title_parts = [$core];
	if ($city !== ''
		&& !(function_exists('lf_seo_phrase_contains_place') && lf_seo_phrase_contains_place(mb_strtolower($disp, 'UTF-8'), mb_strtolower($city, 'UTF-8')))
		&& stripos($core, $city) === false) {
		$title_parts[] = $city;
	}
	if ($brand !== ''
		&& !(function_exists('lf_seo_phrase_contains_place') && lf_seo_phrase_contains_place(mb_strtolower($core, 'UTF-8'), mb_strtolower($brand, 'UTF-8')))) {
		$title_parts[] = $brand;
	}
	$title = trim(implode(' | ', array_filter($title_parts)));
	if (function_exists('lf_seo_truncate_meta_title')) {
		return lf_seo_truncate_meta_title($title, 62);
	}
	if (function_exists('mb_substr') && mb_strlen($title) > 62) {
		return rtrim(mb_substr($title, 0, 62));
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
		'get clear pricing, scope',
	];
	foreach ($generic_patterns as $pattern) {
		if (strpos($lower, $pattern) !== false) {
			return true;
		}
	}
	if ($type === 'description') {
		$city = function_exists('lf_seo_get_city_name') ? trim((string) lf_seo_get_city_name()) : '';
		if (
			$city !== ''
			&& preg_match('/\b' . preg_quote(mb_strtolower($city, 'UTF-8'), '/') . '\b.*\bin\b\s+' . preg_quote(mb_strtolower($city, 'UTF-8'), '/') . '/u', $lower)
		) {
			return true;
		}
		if (strpos($lower, 'learn ') === 0 && strpos($lower, '?') !== false) {
			return true;
		}
		if (strpos($lower, ' from ') !== false && strpos($lower, '|') !== false) {
			// Older pipe-y templates occasionally leaked into descriptions.
			return true;
		}
	}
	if ($type === 'title') {
		if (function_exists('lf_seo_get_brand_name') && function_exists('lf_seo_title_brand_anchor')) {
			$bn = trim((string) lf_seo_get_brand_name());
			$ank = lf_seo_title_brand_anchor($bn);
			if ($ank !== '' && strlen($ank) >= 5) {
				$vl = function_exists('mb_strtolower')
					? mb_strtolower($value, 'UTF-8')
					: strtolower($value);
				if (substr_count($vl, $ank) >= 2) {
					return true;
				}
			}
		}
		if (preg_match('/\s\|\s.+\|\s/ms', $value)) {
			return true;
		}
		if (strpos($lower, '|') !== false && preg_match('/\|[\s\S]{2,}$/', $value)) {
			$pipes = preg_split('/\s*\|\s*/', $value) ?: [];
			$tail = $pipes !== [] ? trim((string) $pipes[count($pipes) - 1]) : '';
			if ($tail !== '' && function_exists('mb_strlen') && mb_strlen($tail, 'UTF-8') <= 18 && preg_match('/[a-z]/', $tail) && !preg_match('/[.!?]$/', $tail)) {
				return true;
			}
		}
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
	if (
		$post_id > 0
		&& function_exists('lf_seo_structured_serp_meta_enabled')
		&& lf_seo_structured_serp_meta_enabled()
		&& function_exists('lf_seo_detect_serp_intent')
		&& function_exists('lf_seo_compose_structured_meta_description')
	) {
		$intent = lf_seo_detect_serp_intent($post_id, $primary_keyword);
		$c = lf_seo_compose_structured_meta_description($post_id, $primary_keyword, $intent, $secondary_keywords);
		if ($c !== '') {
			return $c;
		}
	}
	$primary_keyword = trim($primary_keyword);
	if ($primary_keyword === '') {
		return '';
	}
	$secondary_keywords = array_values(array_filter($secondary_keywords, static function (string $keyword) use ($primary_keyword): bool {
		return strcasecmp($keyword, $primary_keyword) !== 0;
	}));
	$secondary_keywords = array_slice($secondary_keywords, 0, 2);

	$kern_raw = function_exists('lf_seo_normalize_primary_keyword_core')
		? lf_seo_normalize_primary_keyword_core($primary_keyword, $post_id)
		: $primary_keyword;
	$kern_disp = function_exists('lf_seo_title_case_display_phrase')
		? lf_seo_title_case_display_phrase($kern_raw)
		: $kern_raw;
	$post_title = trim((string) get_the_title($post_id));
	if ($post_title === '') {
		$post_title = __('our services', 'leadsforward-core');
	}
	$location = lf_seo_post_location_phrase($post_id);
	$brand = function_exists('lf_seo_short_brand_for_serp')
		? lf_seo_short_brand_for_serp()
		: trim((string) get_bloginfo('name'));
	$brand = $brand !== '' ? $brand : trim((string) get_bloginfo('name'));
	$secondary_phrase = '';
	if (!empty($secondary_keywords)) {
		$secondary_phrase = implode(', ', array_slice($secondary_keywords, 0, 2));
	}

	$phrase = function_exists('lf_seo_readable_service_phrase_for_sentence')
		? lf_seo_readable_service_phrase_for_sentence($kern_raw)
		: mb_strtolower(trim($kern_raw), 'UTF-8');
	if ($phrase === '') {
		$phrase = mb_strtolower(trim($kern_disp), 'UTF-8');
	}

	$loc_chunk = '';
	if (
		$location !== ''
		&& function_exists('lf_seo_phrase_contains_place')
		&& !lf_seo_phrase_contains_place(mb_strtolower($phrase, 'UTF-8'), mb_strtolower(trim($location), 'UTF-8'))
	) {
		$loc_chunk = sprintf(
			/* translators: %s: city or service area */
			__(' in %s', 'leadsforward-core'),
			$location
		);
	}

	$who = $brand !== '' ? $brand : __('our team', 'leadsforward-core');

	if ($location !== '' && function_exists('lf_seo_phrase_contains_place') && lf_seo_phrase_contains_place(mb_strtolower($phrase, 'UTF-8'), mb_strtolower(trim($location), 'UTF-8'))) {
		$description = sprintf(
			/* translators: 1: brand, 2: page title */
			__('%1$s explains %2$s with clear scope, realistic timelines, and what typically drives cost.', 'leadsforward-core'),
			$who,
			$post_title
		);
	} else {
		$subject = $kern_disp !== '' ? $kern_disp : $phrase;
		$description = sprintf(
			/* translators: 1: subject, 2: optional location chunk, 3: brand, 4: page title or context */
			__('%1$s%2$s from %3$s — practical notes for %4$s. Process, timelines, and how to compare quotes.', 'leadsforward-core'),
			$subject,
			$loc_chunk,
			$who,
			$post_title
		);
	}
	if ($secondary_phrase !== '') {
		$description .= ' ' . sprintf(
			/* translators: %s secondary keywords list */
			__('Related topics: %s.', 'leadsforward-core'),
			$secondary_phrase
		);
	}
	$description .= ' ' . __('Request a quote when you are ready for a tailored plan.', 'leadsforward-core');

	$description = trim(preg_replace('/\s+/', ' ', $description));
	if (function_exists('lf_seo_truncate_meta_description_smart')) {
		return lf_seo_truncate_meta_description_smart($description, 160);
	}
	if (function_exists('mb_substr') && mb_strlen($description) > 160) {
		return rtrim(mb_substr($description, 0, 157), " \t\n\r\0\x0B,.;:-") . '...';
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
			$new_title = function_exists('lf_seo_generate_meta_title_for_intent')
				? lf_seo_generate_meta_title_for_intent($post_id, $primary)
				: lf_seo_generate_meta_title_from_keywords($primary, $post_id);
			if ($new_title !== '') {
				update_post_meta($post_id, '_lf_seo_meta_title', $new_title);
			}
		}
		$current_description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
		if (lf_seo_meta_text_needs_upgrade($current_description, 'description')) {
			$new_description = function_exists('lf_seo_generate_meta_description_for_intent')
				? lf_seo_generate_meta_description_for_intent($post_id, $primary, $secondary)
				: lf_seo_generate_meta_description_from_keywords($post_id, $primary, $secondary);
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
		if (function_exists('lf_seo_calculate_content_quality')) {
			lf_seo_calculate_content_quality($post_id);
		}
	}
}
