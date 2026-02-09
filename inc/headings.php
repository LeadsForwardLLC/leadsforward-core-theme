<?php
/**
 * Heading enforcement + validation.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_page_has_hero(int $post_id): bool {
	if ($post_id <= 0) {
		return false;
	}
	$front_id = (int) get_option('page_on_front');
	if ($front_id && $post_id === $front_id && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		$hero = is_array($config['hero'] ?? null) ? $config['hero'] : [];
		return !empty($hero['enabled']);
	}
	if (function_exists('lf_pb_get_context_for_post') && function_exists('lf_pb_get_post_config')) {
		$post = get_post($post_id);
		if ($post instanceof \WP_Post) {
			$context = lf_pb_get_context_for_post($post);
			if ($context !== '') {
				$config = lf_pb_get_post_config($post_id, $context);
				$sections = $config['sections'] ?? [];
				foreach ($sections as $section) {
					if (($section['type'] ?? '') !== 'hero') {
						continue;
					}
					if (!empty($section['enabled'])) {
						return true;
					}
				}
			}
		}
	}
	return false;
}

function lf_should_output_h1(array $context = []): bool {
	$post_id = (int) ($context['post_id'] ?? get_queried_object_id());
	$location = (string) ($context['location'] ?? 'title');
	$has_hero = array_key_exists('has_hero', $context)
		? (bool) $context['has_hero']
		: ($post_id ? lf_page_has_hero($post_id) : false);
	if ($location === 'hero') {
		return $has_hero;
	}
	if ($location === 'title') {
		return !$has_hero;
	}
	return !$has_hero;
}

function lf_heading_primary_source(int $post_id): string {
	if ($post_id <= 0) {
		return 'none';
	}
	return lf_page_has_hero($post_id) ? 'hero' : 'title';
}

function lf_heading_primary_text(int $post_id): string {
	$front_id = (int) get_option('page_on_front');
	$title = '';
	if ($front_id && $post_id === $front_id && function_exists('lf_get_homepage_hero_headline')) {
		$title = (string) lf_get_homepage_hero_headline();
	}
	if ($title === '' && function_exists('lf_get_pb_hero_headline')) {
		$title = (string) lf_get_pb_hero_headline($post_id);
	}
	if ($title === '') {
		$title = (string) get_the_title($post_id);
	}
	if ($title === '') {
		$title = (string) get_bloginfo('name');
	}
	return $title;
}

function lf_heading_extract_from_html(string $html): array {
	if ($html === '') {
		return [];
	}
	if (!preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
		return [];
	}
	$out = [];
	foreach ($matches as $match) {
		$level = (int) ($match[1] ?? 0);
		$text = trim(wp_strip_all_tags((string) ($match[2] ?? '')));
		if ($level < 1 || $level > 6) {
			continue;
		}
		$out[] = [
			'level' => $level,
			'text' => $text,
		];
	}
	return $out;
}

function lf_heading_validate(array $headings): array {
	$warnings = [];
	$h1_count = 0;
	$prev_level = null;
	$skips = [];
	$empties = 0;

	foreach ($headings as $heading) {
		$level = (int) ($heading['level'] ?? 0);
		$text = (string) ($heading['text'] ?? '');
		if ($level === 1) {
			$h1_count++;
		}
		if (trim($text) === '') {
			$empties++;
		}
		if ($prev_level !== null && $level > ($prev_level + 1)) {
			$skips[] = 'H' . $prev_level . ' -> H' . $level;
		}
		$prev_level = $level ?: $prev_level;
	}

	if ($h1_count > 1) {
		$warnings[] = __('Multiple H1s detected.', 'leadsforward-core');
	}
	if (!empty($skips)) {
		$warnings[] = sprintf(
			__('Skipped heading levels: %s.', 'leadsforward-core'),
			implode(', ', array_values(array_unique($skips)))
		);
	}
	if ($empties > 0) {
		$warnings[] = sprintf(
			_n('Empty heading detected.', 'Empty headings detected.', $empties, 'leadsforward-core'),
			$empties
		);
	}
	return $warnings;
}

function lf_heading_warnings_for_post(int $post_id): array {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return [];
	}
	if (!in_array($post->post_type, ['page', 'post', 'lf_service', 'lf_service_area'], true)) {
		return [];
	}
	$headings = [];
	$source = lf_heading_primary_source($post_id);
	if ($source === 'hero') {
		$headings[] = ['level' => 1, 'text' => lf_heading_primary_text($post_id)];
	}
	if ($source === 'title') {
		$headings[] = ['level' => 1, 'text' => (string) get_the_title($post_id)];
	}
	$headings = array_merge($headings, lf_heading_extract_from_html((string) $post->post_content));
	return lf_heading_validate($headings);
}

function lf_heading_collect_site_issues(): array {
	$ids = [];
	$front_id = (int) get_option('page_on_front');
	if ($front_id) {
		$ids[] = $front_id;
	}
	$slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
	foreach ($slugs as $slug) {
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post) {
			$ids[] = $page->ID;
		}
	}
	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 25,
		'fields' => 'ids',
	]);
	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 25,
		'fields' => 'ids',
	]);
	$posts = get_posts([
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => 10,
		'fields' => 'ids',
	]);
	$ids = array_values(array_unique(array_filter(array_merge($ids, $services, $areas, $posts))));
	$issues = [];
	foreach ($ids as $id) {
		$warnings = lf_heading_warnings_for_post((int) $id);
		if (!empty($warnings)) {
			$issues[(int) $id] = $warnings;
		}
	}
	return $issues;
}

function lf_demote_content_h1(string $content): string {
	if (is_admin() || $content === '') {
		return $content;
	}
	if (!is_singular(['page', 'post', 'lf_service', 'lf_service_area']) && !is_front_page()) {
		return $content;
	}
	$post_id = get_queried_object_id();
	if (!$post_id) {
		return $content;
	}
	$source = lf_heading_primary_source($post_id);
	if ($source === 'none') {
		return $content;
	}
	$content = preg_replace('/<\s*h1(\b[^>]*)>/i', '<h2$1>', $content);
	$content = preg_replace('/<\s*\/\s*h1\s*>/i', '</h2>', $content);
	return $content;
}
add_filter('the_content', 'lf_demote_content_h1', 20);

function lf_heading_admin_notice(): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->base, ['post', 'post-new'], true)) {
		return;
	}
	if (!in_array($screen->post_type, ['page', 'post', 'lf_service', 'lf_service_area'], true)) {
		return;
	}
	global $post;
	if (!$post instanceof \WP_Post) {
		return;
	}
	$warnings = lf_heading_warnings_for_post((int) $post->ID);
	if (empty($warnings)) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Heading rules:', 'leadsforward-core') . '</strong> ' . esc_html__('Fix these headings to keep a single H1 and clean hierarchy.', 'leadsforward-core') . '</p><ul>';
	foreach ($warnings as $warning) {
		echo '<li>' . esc_html($warning) . '</li>';
	}
	echo '</ul></div>';
}
if (is_admin()) {
	add_action('admin_notices', 'lf_heading_admin_notice');
}
