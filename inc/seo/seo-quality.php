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
	if (function_exists('mb_substr') && mb_strlen($title) > 62) {
		$title = rtrim(mb_substr($title, 0, 62));
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
	$internal_links = preg_match_all('/<a\s[^>]*href=/i', (string) $post->post_content);

	$score = 0;
	$signals = [];
	$min_words = $post->post_type === 'post' ? 450 : 180;
	if ($word_count >= $min_words) {
		$score += 25;
		$signals[] = 'word_count_ok';
	}
	if ($primary !== '' && stripos($text, $primary) !== false) {
		$score += 20;
		$signals[] = 'primary_keyword_present';
	}
	$title_strong = $title !== '' && (!function_exists('lf_seo_meta_text_needs_upgrade') || !lf_seo_meta_text_needs_upgrade($title, 'title'));
	if ($title_strong) {
		$score += 20;
		$signals[] = 'meta_title_strong';
	}
	$description_strong = $description !== '' && (!function_exists('lf_seo_meta_text_needs_upgrade') || !lf_seo_meta_text_needs_upgrade($description, 'description'));
	if ($description_strong) {
		$score += 20;
		$signals[] = 'meta_description_strong';
	}
	if ((int) $internal_links > 0) {
		$score += 10;
		$signals[] = 'internal_links_present';
	}
	if ($intent !== '') {
		$score += 5;
		$signals[] = 'intent_assigned';
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
