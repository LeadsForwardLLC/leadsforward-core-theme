<?php
/**
 * Fleet page slug aliases — one canonical WP page per hub (prevents setup vs sitemap duplicates).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Legacy / Airtable slug → canonical fleet slug (theme setup uses the canonical side).
 *
 * @return array<string, string> alias => canonical
 */
function lf_fleet_page_slug_aliases(): array {
	$map = [
		'about' => 'about-us',
		'why' => 'why-choose-us',
		'why-us' => 'why-choose-us',
		'our-services' => 'services',
	];

	return (array) apply_filters('lf_fleet_page_slug_aliases', $map);
}

/**
 * Resolve any slug (canonical or alias) to the fleet canonical slug.
 */
function lf_fleet_canonical_page_slug(string $slug): string {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return '';
	}
	$aliases = lf_fleet_page_slug_aliases();

	return $aliases[$slug] ?? $slug;
}

/**
 * Map sitemap template/path to the fleet page slug used in setup.
 */
function lf_fleet_canonical_slug_from_sitemap(string $resolved_slug, string $slug_template = ''): string {
	$resolved_norm = function_exists('lf_sitemap_normalize_slug_path')
		? lf_sitemap_normalize_slug_path($resolved_slug)
		: ('/' . trim($resolved_slug, '/') . '/');
	$template_norm = $slug_template !== '' && function_exists('lf_sitemap_normalize_slug_template_for_key')
		? lf_sitemap_normalize_slug_template_for_key($slug_template)
		: ('/' . trim($slug_template, '/') . '/');

	$path_map = [
		'/' => 'home',
		'/about/' => 'about-us',
		'/why/' => 'why-choose-us',
		'/why-us/' => 'why-choose-us',
		'/services/' => 'services',
		'/service-areas/' => 'service-areas',
		'/contact/' => 'contact',
		'/reviews/' => 'reviews',
		'/blog/' => 'blog',
		'/faq/' => 'faq',
		'/sitemap/' => 'sitemap',
	];

	foreach ([$resolved_norm, $template_norm] as $path) {
		if ($path !== '' && isset($path_map[$path])) {
			return $path_map[$path];
		}
	}

	$basename = sanitize_title((string) basename(trim($resolved_norm, '/')));

	return lf_fleet_canonical_page_slug($basename !== '' ? $basename : 'home');
}

/**
 * Find the fleet page by canonical slug, including legacy alias slugs.
 */
function lf_fleet_find_page_by_slug(string $slug): ?\WP_Post {
	$canonical = lf_fleet_canonical_page_slug($slug);
	if ($canonical === '') {
		return null;
	}
	if ($canonical === 'home') {
		$front = (int) get_option('page_on_front');
		if ($front > 0) {
			$post = get_post($front);
			if ($post instanceof \WP_Post && $post->post_type === 'page') {
				return $post;
			}
		}
	}

	$page = get_page_by_path($canonical, OBJECT, 'page');
	if ($page instanceof \WP_Post) {
		return $page;
	}

	foreach (lf_fleet_page_slug_aliases() as $alias => $target) {
		if ($target !== $canonical) {
			continue;
		}
		$alt = get_page_by_path($alias, OBJECT, 'page');
		if ($alt instanceof \WP_Post) {
			return $alt;
		}
	}

	return null;
}

/**
 * Trash duplicate alias pages when the canonical fleet page already exists.
 *
 * @return list<string> trashed alias slugs
 */
function lf_fleet_dedupe_alias_pages(): array {
	$trashed = [];
	$aliases = lf_fleet_page_slug_aliases();
	$by_canonical = [];
	foreach ($aliases as $alias => $canonical) {
		$by_canonical[$canonical][] = $alias;
	}

	foreach ($by_canonical as $canonical => $alias_slugs) {
		$keep = get_page_by_path($canonical, OBJECT, 'page');
		if (!$keep instanceof \WP_Post) {
			// Promote a lone alias to the canonical slug when no canonical page exists.
			foreach ($alias_slugs as $alias) {
				$only = get_page_by_path($alias, OBJECT, 'page');
				if ($only instanceof \WP_Post) {
					wp_update_post([
						'ID' => (int) $only->ID,
						'post_name' => $canonical,
					]);
					break;
				}
			}
			continue;
		}
		foreach ($alias_slugs as $alias) {
			$dup = get_page_by_path($alias, OBJECT, 'page');
			if (!$dup instanceof \WP_Post || (int) $dup->ID === (int) $keep->ID) {
				continue;
			}
			wp_trash_post((int) $dup->ID);
			$trashed[] = $alias;
		}
	}

	return $trashed;
}

/**
 * Utility/legal fleet pages that must always be published (never draft).
 *
 * @return list<string>
 */
function lf_fleet_always_publish_utility_page_slugs(): array {
	return ['sitemap', 'privacy-policy', 'terms-of-service'];
}

/**
 * Whether a fleet page slug is a utility page that must stay published.
 */
function lf_fleet_is_always_publish_utility_slug(string $slug): bool {
	$slug = function_exists('lf_fleet_canonical_page_slug')
		? lf_fleet_canonical_page_slug($slug)
		: sanitize_title($slug);

	return in_array($slug, lf_fleet_always_publish_utility_page_slugs(), true);
}

/**
 * Publish utility/legal fleet pages if they exist but are not live.
 *
 * @return list<string> canonical slugs published
 */
function lf_fleet_publish_utility_pages(): array {
	$published = [];
	foreach (lf_fleet_always_publish_utility_page_slugs() as $slug) {
		$page = lf_fleet_find_page_by_slug($slug);
		if (!$page instanceof \WP_Post || $page->post_status === 'publish') {
			continue;
		}
		wp_update_post([
			'ID' => (int) $page->ID,
			'post_status' => 'publish',
		]);
		$published[] = $slug;
	}
	if (function_exists('lf_ensure_legal_pages')) {
		lf_ensure_legal_pages();
	}

	return $published;
}

/**
 * Publish fleet pages that should be live after initial build.
 *
 * @return list<string> slugs published
 */
function lf_fleet_publish_build_pages(): array {
	$slugs = function_exists('lf_wizard_default_publish_page_slugs')
		? lf_wizard_default_publish_page_slugs()
		: ['home', 'services', 'service-areas', 'why-choose-us', 'faq', 'contact'];
	$published = [];
	foreach ($slugs as $slug) {
		$page = lf_fleet_find_page_by_slug($slug);
		if (!$page instanceof \WP_Post || $page->post_status === 'publish') {
			continue;
		}
		wp_update_post([
			'ID' => (int) $page->ID,
			'post_status' => 'publish',
		]);
		$published[] = lf_fleet_canonical_page_slug($slug);
	}

	return $published;
}

/**
 * Whether at least one published review/testimonial exists on this site.
 */
function lf_fleet_has_published_testimonials(): bool {
	if (!post_type_exists('lf_testimonial')) {
		return false;
	}
	$ids = get_posts([
		'post_type' => 'lf_testimonial',
		'post_status' => 'publish',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	]);
	return is_array($ids) && $ids !== [];
}

/**
 * Target WP status for the Reviews fleet page (publish only when review CPTs exist).
 */
function lf_fleet_reviews_page_target_status(): string {
	return lf_fleet_has_published_testimonials() ? 'publish' : 'draft';
}

/**
 * Publish or unpublish the Reviews page based on available testimonial CPT rows.
 *
 * @return 'missing'|'unchanged'|'publish'|'draft'
 */
function lf_fleet_sync_reviews_page_status(): string {
	$page = lf_fleet_find_page_by_slug('reviews');
	if (!$page instanceof \WP_Post) {
		return 'missing';
	}
	$target = lf_fleet_reviews_page_target_status();
	if ((string) $page->post_status === $target) {
		return 'unchanged';
	}
	wp_update_post([
		'ID' => (int) $page->ID,
		'post_status' => $target,
	]);
	return $target;
}

/**
 * Sync Reviews page visibility and refresh header menu when testimonial inventory changes.
 *
 * @return 'missing'|'unchanged'|'publish'|'draft'
 */
function lf_fleet_sync_reviews_page_status_and_menu(): string {
	$result = lf_fleet_sync_reviews_page_status();
	if ($result === 'publish' || $result === 'draft') {
		if (function_exists('lf_header_menu_force_structure_repair')) {
			lf_header_menu_force_structure_repair();
		}
	}
	return $result;
}

/**
 * React to testimonial CPT saves (publish, draft, trash) so Reviews page + nav stay in sync without waiting for cron.
 */
function lf_fleet_on_testimonial_lifecycle_change(int $post_id): void {
	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'lf_testimonial') {
		return;
	}
	lf_fleet_sync_reviews_page_status_and_menu();
}

function lf_fleet_register_reviews_page_hooks(): void {
	add_action('save_post_lf_testimonial', 'lf_fleet_on_testimonial_lifecycle_change', 25);
	add_action('trashed_post', 'lf_fleet_on_testimonial_lifecycle_change');
	add_action('untrashed_post', 'lf_fleet_on_testimonial_lifecycle_change');
}
add_action('init', 'lf_fleet_register_reviews_page_hooks');
