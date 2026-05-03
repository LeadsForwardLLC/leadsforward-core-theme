<?php
/**
 * SEO quality scoring + SERP intent templates.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('save_post', 'lf_seo_refresh_quality_score_on_save', 30, 2);

function lf_seo_serp_intent_options(): array {
	return [
		'transactional' => __('Transactional', 'leadsforward-core'),
		'local' => __('Local', 'leadsforward-core'),
		'informational' => __('Informational', 'leadsforward-core'),
		'navigational' => __('Navigational', 'leadsforward-core'),
	];
}

function lf_seo_default_intent_templates(): array {
	return [
		'title' => [
			'transactional' => '{{primary_keyword}} | {{city}} | {{brand}}',
			'local' => '{{primary_keyword}} in {{city}} | {{brand}}',
			'informational' => '{{page_title}}: {{primary_keyword}} Guide | {{brand}}',
			'navigational' => '{{brand}} | {{page_title}}',
		],
		'description' => [
			'transactional' => '{{primary_keyword}} in {{city}} from {{brand}}. Get clear pricing, scope, and scheduling with fast quote turnaround.',
			'local' => 'Local {{primary_keyword}} in {{city}} by {{brand}}. Licensed team, clear timelines, and service-area coverage.',
			'informational' => 'Learn {{primary_keyword}} with practical guidance from {{brand}} in {{city}}. Includes process, pricing factors, and expert tips.',
			'navigational' => '{{page_title}} at {{brand}}. Find services, coverage areas, and next steps quickly.',
		],
	];
}

function lf_seo_detect_serp_intent(int $post_id, string $primary_keyword = ''): string {
	$override = trim((string) get_post_meta($post_id, '_lf_seo_serp_intent', true));
	if ($override !== '' && array_key_exists($override, lf_seo_serp_intent_options())) {
		return $override;
	}
	$post_type = (string) get_post_type($post_id);
	if ($post_type === 'post') {
		return 'informational';
	}
	if ($post_type === 'lf_service_area') {
		return 'local';
	}
	$keyword = strtolower(trim($primary_keyword));
	if ($keyword !== '') {
		foreach (['cost', 'price', 'quote', 'near me', 'company', 'contractor', 'service'] as $needle) {
			if (strpos($keyword, $needle) !== false) {
				return $needle === 'near me' ? 'local' : 'transactional';
			}
		}
		foreach (['guide', 'how to', 'tips', 'checklist', 'what is'] as $needle) {
			if (strpos($keyword, $needle) !== false) {
				return 'informational';
			}
		}
	}
	return in_array($post_type, ['lf_service', 'page'], true) ? 'transactional' : 'navigational';
}

function lf_seo_get_intent_template(string $intent, string $template_type): string {
	$defaults = lf_seo_default_intent_templates();
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$saved = is_array($settings['serp'][$template_type] ?? null) ? $settings['serp'][$template_type] : [];
	$templates = array_replace($defaults[$template_type] ?? [], $saved);
	return (string) ($templates[$intent] ?? '');
}

function lf_seo_generate_meta_title_for_intent(int $post_id, string $primary_keyword): string {
	if (function_exists('lf_seo_get_setting') && !lf_seo_get_setting('ai.enable_serp_templates', true)) {
		return function_exists('lf_seo_generate_meta_title_from_keywords')
			? lf_seo_generate_meta_title_from_keywords($primary_keyword)
			: '';
	}
	$intent = lf_seo_detect_serp_intent($post_id, $primary_keyword);
	$template = lf_seo_get_intent_template($intent, 'title');
	if ($template === '' || !function_exists('lf_seo_get_template_vars')) {
		return function_exists('lf_seo_generate_meta_title_from_keywords')
			? lf_seo_generate_meta_title_from_keywords($primary_keyword)
			: '';
	}
	$vars = lf_seo_get_template_vars($post_id);
	$vars['{{intent}}'] = $intent;
	$title = trim((string) lf_seo_apply_template($template, $vars));
	if ($title === '') {
		return function_exists('lf_seo_generate_meta_title_from_keywords')
			? lf_seo_generate_meta_title_from_keywords($primary_keyword)
			: '';
	}
	if (function_exists('lf_seo_truncate_meta_title')) {
		return lf_seo_truncate_meta_title($title, 62);
	}
	if (function_exists('mb_substr') && mb_strlen($title) > 62) {
		return rtrim(mb_substr($title, 0, 62));
	}
	return $title;
}

function lf_seo_generate_meta_description_for_intent(int $post_id, string $primary_keyword, array $secondary_keywords = []): string {
	if (function_exists('lf_seo_get_setting') && !lf_seo_get_setting('ai.enable_serp_templates', true)) {
		return function_exists('lf_seo_generate_meta_description_from_keywords')
			? lf_seo_generate_meta_description_from_keywords($post_id, $primary_keyword, $secondary_keywords)
			: '';
	}
	$intent = lf_seo_detect_serp_intent($post_id, $primary_keyword);
	$template = lf_seo_get_intent_template($intent, 'description');
	if ($template === '' || !function_exists('lf_seo_get_template_vars')) {
		return function_exists('lf_seo_generate_meta_description_from_keywords')
			? lf_seo_generate_meta_description_from_keywords($post_id, $primary_keyword, $secondary_keywords)
			: '';
	}
	$vars = lf_seo_get_template_vars($post_id);
	$vars['{{intent}}'] = $intent;
	$vars['{{secondary_keywords}}'] = implode(', ', array_slice($secondary_keywords, 0, 3));
	$description = trim((string) lf_seo_apply_template($template, $vars));
	if ($description === '') {
		return function_exists('lf_seo_generate_meta_description_from_keywords')
			? lf_seo_generate_meta_description_from_keywords($post_id, $primary_keyword, $secondary_keywords)
			: '';
	}
	if (function_exists('mb_substr') && mb_strlen($description) > 160) {
		$description = rtrim(mb_substr($description, 0, 157), " \t\n\r\0\x0B,.;:-") . '...';
	}
	return $description;
}

function lf_seo_refresh_quality_score_on_save(int $post_id, \WP_Post $post): void {
	if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		return;
	}
	if (!in_array($post->post_type, ['page', 'post', 'lf_service', 'lf_service_area'], true)) {
		return;
	}
	lf_seo_calculate_content_quality($post_id);
}

function lf_seo_collect_scoring_text(int $post_id): string {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return '';
	}
	$chunks = [];
	$chunks[] = (string) $post->post_title;
	$chunks[] = (string) $post->post_excerpt;
	$chunks[] = (string) $post->post_content;
	$config = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (is_array($config)) {
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		foreach ($sections as $section) {
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			foreach ($settings as $value) {
				if (!is_string($value)) {
					continue;
				}
				$clean = trim(wp_strip_all_tags($value));
				if ($clean !== '') {
					$chunks[] = $clean;
				}
			}
		}
	}
	return trim(implode("\n", $chunks));
}

/**
 * Raw HTML-ish strings from post content + Page Builder fields (for link / image checks).
 */
function lf_seo_collect_scoring_html(int $post_id): string {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return '';
	}
	$parts = [(string) $post->post_content];
	$key = defined('LF_PB_META_KEY') ? LF_PB_META_KEY : 'lf_pb_config';
	$config = get_post_meta($post_id, $key, true);
	if (!is_array($config)) {
		return implode("\n", $parts);
	}
	$sections = $config['sections'] ?? [];
	if (!is_array($sections)) {
		return implode("\n", $parts);
	}
	foreach ($sections as $section) {
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		foreach ($settings as $value) {
			if (is_string($value) && $value !== '') {
				$parts[] = $value;
			}
		}
	}
	return implode("\n", $parts);
}

/**
 * Primary city for local/transactional coverage hints (business entity → manifest fallback).
 */
function lf_seo_get_primary_city_for_coverage(): string {
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		if (is_array($entity)) {
			$city = trim((string) ($entity['address_parts']['city'] ?? ''));
			if ($city !== '') {
				return $city;
			}
		}
	}
	$manifest = get_option('lf_site_manifest', []);
	return is_array($manifest) ? lf_seo_get_manifest_city($manifest) : '';
}

function lf_seo_text_contains_keyword_phrase(string $haystack, string $keyword): bool {
	$haystack = trim(wp_strip_all_tags($haystack));
	$keyword = preg_replace('/\s+/u', ' ', trim($keyword));
	if ($haystack === '' || $keyword === '') {
		return false;
	}
	if (function_exists('mb_strlen') && mb_strlen($keyword, 'UTF-8') < 5) {
		return false;
	}
	return function_exists('mb_stripos')
		? mb_stripos($haystack, $keyword, 0, 'UTF-8') !== false
		: stripos($haystack, $keyword) !== false;
}

/**
 * @return list<string>
 */
function lf_seo_extract_heading_plaintexts_from_html(string $html, string $tag): array {
	$allowed = ['h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1];
	$tag = strtolower(preg_replace('/[^a-z0-9]/', '', $tag));
	if ($tag === '' || !isset($allowed[$tag]) || trim($html) === '') {
		return [];
	}
	if (!preg_match_all('/<' . $tag . '\b[^>]*>(.*?)<\/\s*' . $tag . '\s*>/is', $html, $m)) {
		return [];
	}
	$out = [];
	foreach ($m[1] as $inner) {
		$t = trim(wp_strip_all_tags((string) $inner));
		if ($t !== '') {
			$out[] = $t;
		}
	}
	return $out;
}

function lf_seo_count_internal_links_in_html(string $html, string $home_host): int {
	$html = is_string($html) ? $html : '';
	$home_host = strtolower(trim($home_host));
	if ($html === '' || $home_host === '' || !preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $m)) {
		return 0;
	}
	$n = 0;
	foreach ($m[1] as $url) {
		$url = trim((string) $url);
		if ($url === '' || strpos($url, '#') === 0 || stripos($url, 'mailto:') === 0 || stripos($url, 'tel:') === 0) {
			continue;
		}
		$h = wp_parse_url($url, PHP_URL_HOST);
		$h = is_string($h) ? strtolower($h) : '';
		if ($h !== '' && $h === $home_host) {
			$n++;
			continue;
		}
		if ($h === '' && preg_match('#^/(?!/)#', $url)) {
			$n++;
		}
	}
	return $n;
}

/**
 * @return list<array{ok:bool,label:string,detail:string}>
 */
function lf_seo_get_onpage_checklist_rows(int $post_id): array {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return [];
	}
	$rows = [];
	$text = lf_seo_collect_scoring_text($post_id);
	$plain = wp_strip_all_tags($text);
	$primary = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
	$title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
	$description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
	$tl = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
	$dl = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
	$fallback_title = get_the_title($post);
	$eff_title = $title !== '' ? $title : (string) $fallback_title;
	$etl = function_exists('mb_strlen') ? mb_strlen($eff_title) : strlen($eff_title);
	$html_blob = lf_seo_collect_scoring_html($post_id);
	$intent_for_coverage = lf_seo_detect_serp_intent($post_id, $primary);
	$pw = trim($primary);
	$primary_len = function_exists('mb_strlen') ? mb_strlen($pw, 'UTF-8') : strlen($pw);
	$primary_strict = $pw !== '' && $primary_len >= 8;

	$rows[] = [
		'ok' => $primary !== '',
		'label' => __('Primary keyword', 'leadsforward-core'),
		'detail' => $primary !== '' ? $primary : __('Set a target keyword in this box.', 'leadsforward-core'),
	];
	$rows[] = [
		'ok' => $etl >= 30 && $etl <= 65,
		'label' => __('Title length (SEO)', 'leadsforward-core'),
		'detail' => sprintf(
			/* translators: 1: current length, 2: guideline */
			__('Effective title is %1$d characters (aim for ~30–60).', 'leadsforward-core'),
			(int) $etl
		),
	];
	$rows[] = [
		'ok' => $description === '' || ($dl >= 110 && $dl <= 170),
		'label' => __('Meta description length', 'leadsforward-core'),
		'detail' => $description === ''
			? __('Optional; if set, aim for ~120–160 characters.', 'leadsforward-core')
			: sprintf(__('Current: %d characters.', 'leadsforward-core'), (int) $dl),
	];
	if ($primary !== '') {
		if ($primary_strict && $title === '') {
			$rows[] = [
				'ok' => true,
				'label' => __('Custom meta title + keyword', 'leadsforward-core'),
				'detail' => __('No custom meta title is saved here; the public &lt;title&gt; comes from SEO → templates and intent rules on the front end. Preview the live page or use SEO Health to confirm the phrase appears.', 'leadsforward-core'),
			];
		} elseif ($primary_strict && $title !== '') {
			$t_ok = lf_seo_text_contains_keyword_phrase($title, $primary);
			$rows[] = [
				'ok' => $t_ok,
				'label' => __('Custom meta title + keyword', 'leadsforward-core'),
				'detail' => $t_ok
					? __('Saved meta title mentions the primary phrase.', 'leadsforward-core')
					: __('Consider weaving the primary phrase into the saved meta title (still reads naturally—no stuffing).', 'leadsforward-core'),
			];
		}
		if ($primary_strict && $description === '') {
			$rows[] = [
				'ok' => true,
				'label' => __('Custom meta description + keyword', 'leadsforward-core'),
				'detail' => __('No custom meta description saved; SERP snippets use SEO templates until you fill this field.', 'leadsforward-core'),
			];
		} elseif ($primary_strict && $description !== '') {
			$d_ok = lf_seo_text_contains_keyword_phrase($description, $primary);
			$rows[] = [
				'ok' => $d_ok,
				'label' => __('Custom meta description + keyword', 'leadsforward-core'),
				'detail' => $d_ok
					? __('Saved description mentions the primary phrase.', 'leadsforward-core')
					: __('Consider including the primary phrase once in the description in natural language.', 'leadsforward-core'),
			];
		}
	}
	$first_chunk = strtolower(function_exists('mb_substr') ? mb_substr($plain, 0, 280) : substr($plain, 0, 280));
	$rows[] = [
		'ok' => $primary === '' || $first_chunk === '' || strpos($first_chunk, strtolower($primary)) !== false,
		'label' => __('Keyword in opening content', 'leadsforward-core'),
		'detail' => __('Primary keyword should appear naturally in the first screen of readable text.', 'leadsforward-core'),
	];
	if ($primary_strict && function_exists('lf_heading_primary_text')) {
		$h1_text = trim((string) lf_heading_primary_text($post_id));
		if ($h1_text !== '') {
			$h1_ok = lf_seo_text_contains_keyword_phrase($h1_text, $primary);
			$rows[] = [
				'ok' => $h1_ok,
				'label' => __('Primary headline (H1) + keyword', 'leadsforward-core'),
				'detail' => $h1_ok
					? __('Theme headline field includes the target phrase.', 'leadsforward-core')
					: __('The visible H1 comes from hero or title fields; aligning it with the target phrase usually helps topical clarity.', 'leadsforward-core'),
			];
		}
	}
	if ($primary_strict && $html_blob !== '') {
		$h2_texts = lf_seo_extract_heading_plaintexts_from_html($html_blob, 'h2');
		if (count($h2_texts) > 0) {
			$h2_ok = false;
			foreach ($h2_texts as $h2_line) {
				if (lf_seo_text_contains_keyword_phrase($h2_line, $primary)) {
					$h2_ok = true;
					break;
				}
			}
			$rows[] = [
				'ok' => $h2_ok,
				'label' => __('Section headings (H2) + keyword', 'leadsforward-core'),
				'detail' => $h2_ok
					? __('At least one H2 mentions the topic in natural language.', 'leadsforward-core')
					: __('Add topical H2 subheads in Page Builder richtext; at least one should reflect the URL’s main phrase without repeating it awkwardly.', 'leadsforward-core'),
			];
		}
	}
	$coverage_city = lf_seo_get_primary_city_for_coverage();
	if (
		$primary_strict
		&& $coverage_city !== ''
		&& in_array($intent_for_coverage, ['transactional', 'local'], true)
		&& $first_chunk !== ''
		&& (function_exists('mb_strlen') ? mb_strlen(trim($coverage_city), 'UTF-8') : strlen(trim($coverage_city))) >= 3
	) {
		$city_lc = strtolower($coverage_city);
		$c_ok = strpos($first_chunk, $city_lc) !== false;
		$rows[] = [
			'ok' => $c_ok,
			'label' => __('Opening text + primary city', 'leadsforward-core'),
			'detail' => $c_ok
				? sprintf(
					/* translators: %s: city name */
					__('First screen of copy references %s—a strong local cue for transactional pages.', 'leadsforward-core'),
					$coverage_city
				)
				: sprintf(
					/* translators: %s: city name */
					__('Near the top of the readable copy, clarify service area once (often %s) so humans and bots see local intent.', 'leadsforward-core'),
					$coverage_city
				),
		];
	}
	$host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
	$int_n = lf_seo_count_internal_links_in_html($html_blob, $host);
	$rows[] = [
		'ok' => $int_n > 0,
		'label' => __('Internal links', 'leadsforward-core'),
		'detail' => $int_n > 0
			? sprintf(
				/* translators: %d: link count */
				_n('%d internal link detected in content/Page Builder HTML.', '%d internal links detected in content/Page Builder HTML.', $int_n, 'leadsforward-core'),
				$int_n
			)
			: __('Add at least one link to a related service, area, or core page.', 'leadsforward-core'),
	];
	$img_missing_alt = 0;
	if ($html_blob !== '' && preg_match_all('/<img\b[^>]*>/i', $html_blob, $im)) {
		foreach ($im[0] as $tag) {
			if (!preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $tag) && !preg_match('/\balt\s*=\s*[^\s>]+/i', $tag)) {
				$img_missing_alt++;
			}
		}
	}
	$rows[] = [
		'ok' => $img_missing_alt === 0,
		'label' => __('Image alt text', 'leadsforward-core'),
		'detail' => $img_missing_alt === 0
			? __('No <img> tags without an alt attribute found in stored HTML fields.', 'leadsforward-core')
			: sprintf(
				/* translators: %d: count */
				__('About %d image(s) may be missing alt attributes in Page Builder / content HTML—fix in the editor.', 'leadsforward-core'),
				$img_missing_alt
			),
	];
	$needs_thumb = in_array($post->post_type, ['post', 'lf_service'], true);
	$rows[] = [
		'ok' => !$needs_thumb || has_post_thumbnail($post_id),
		'label' => __('Featured image', 'leadsforward-core'),
		'detail' => $needs_thumb
			? (has_post_thumbnail($post_id) ? __('Set for social previews and SERP richness.', 'leadsforward-core') : __('Set a featured image for this post type.', 'leadsforward-core'))
			: __('Not required for this post type.', 'leadsforward-core'),
	];

	return $rows;
}

function lf_seo_calculate_content_quality(int $post_id): array {
	if (function_exists('lf_seo_get_setting') && !lf_seo_get_setting('ai.enable_quality_scorer', true)) {
		return ['score' => 0, 'grade' => 'F', 'signals' => []];
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return ['score' => 0, 'grade' => 'F', 'signals' => []];
	}
	$text = lf_seo_collect_scoring_text($post_id);
	$word_count = str_word_count(wp_strip_all_tags($text));
	$primary = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
	$title = trim((string) get_post_meta($post_id, '_lf_seo_meta_title', true));
	$description = trim((string) get_post_meta($post_id, '_lf_seo_meta_description', true));
	$intent = lf_seo_detect_serp_intent($post_id, $primary);
	$home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
	$html_blob = lf_seo_collect_scoring_html($post_id);
	$internal_pb = lf_seo_count_internal_links_in_html($html_blob, $home_host);
	$internal_raw = (int) preg_match_all('/<a\s[^>]*href=/i', (string) $post->post_content);
	$internal_total = max($internal_pb, $internal_raw);

	$score = 0;
	$signals = [];
	$min_words = $post->post_type === 'post' ? 450 : 180;
	if ($word_count >= $min_words) {
		$score += 27;
		$signals[] = 'word_count_ok';
	}
	if ($primary !== '' && stripos($text, $primary) !== false) {
		$score += 18;
		$signals[] = 'primary_keyword_present';
	}
	$title_strong = $title !== '' && (!function_exists('lf_seo_meta_text_needs_upgrade') || !lf_seo_meta_text_needs_upgrade($title, 'title'));
	if ($title_strong) {
		$score += 16;
		$signals[] = 'meta_title_strong';
	}
	$tlen = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
	if ($title !== '' && $tlen >= 30 && $tlen <= 62) {
		$score += 6;
		$signals[] = 'meta_title_length_band';
	}
	$description_strong = $description !== '' && (!function_exists('lf_seo_meta_text_needs_upgrade') || !lf_seo_meta_text_needs_upgrade($description, 'description'));
	if ($description_strong) {
		$score += 14;
		$signals[] = 'meta_description_strong';
	}
	$dlen = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
	if ($description !== '' && $dlen >= 120 && $dlen <= 165) {
		$score += 4;
		$signals[] = 'meta_description_length_band';
	}
	if ($internal_total > 0) {
		$score += 10;
		$signals[] = 'internal_links_present';
	}
	if ($intent !== '') {
		$score += 5;
		$signals[] = 'intent_assigned';
	}
	$needs_thumb = in_array($post->post_type, ['post', 'lf_service'], true);
	if ($needs_thumb && has_post_thumbnail($post_id)) {
		$score += 5;
		$signals[] = 'featured_image_set';
	}
	$score = max(0, min(100, $score));
	$grade = 'F';
	if ($score >= 90) {
		$grade = 'A';
	} elseif ($score >= 80) {
		$grade = 'B';
	} elseif ($score >= 70) {
		$grade = 'C';
	} elseif ($score >= 60) {
		$grade = 'D';
	}

	update_post_meta($post_id, '_lf_seo_quality_score', (string) $score);
	update_post_meta($post_id, '_lf_seo_quality_grade', $grade);
	update_post_meta($post_id, '_lf_seo_quality_signals', $signals);
	update_post_meta($post_id, '_lf_seo_serp_intent_detected', $intent);

	return [
		'score' => $score,
		'grade' => $grade,
		'signals' => $signals,
		'word_count' => $word_count,
		'intent' => $intent,
	];
}
