<?php
/**
 * Blog helpers: archive URL and reading time.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_blog_base_url(): string {
	$page_for_posts = (int) get_option('page_for_posts');
	if ($page_for_posts > 0) {
		$link = get_permalink($page_for_posts);
		if (is_string($link) && $link !== '') {
			return $link;
		}
	}

	// Many LeadsForward sites use a "blog" Page without assigning it under Settings → Reading.
	$blog_page = get_page_by_path('blog');
	if ($blog_page instanceof WP_Post && $blog_page->post_status === 'publish') {
		$link = get_permalink($blog_page);
		if (is_string($link) && $link !== '') {
			return $link;
		}
	}

	return home_url('/');
}

function lf_blog_reading_time(string $content, int $wpm = 200): int {
	$words = str_word_count(wp_strip_all_tags($content));
	if ($words <= 0) {
		return 1;
	}
	return max(1, (int) ceil($words / max(1, $wpm)));
}

function lf_blog_get_featured_image_id(int $post_id): int {
	$thumb = (int) get_post_thumbnail_id($post_id);
	if ($thumb) {
		return $thumb;
	}
	if (function_exists('lf_get_placeholder_image_id')) {
		return lf_get_placeholder_image_id();
	}
	return 0;
}
