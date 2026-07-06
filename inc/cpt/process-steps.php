<?php
/**
 * Process steps CPT. Reusable steps for homepage / Page Builder process sections (like FAQs).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_taxonomy_process_groups(): void {
	$labels = [
		'name'              => _x('Process context', 'taxonomy general name', 'leadsforward-core'),
		'singular_name'     => _x('Process context', 'taxonomy singular name', 'leadsforward-core'),
		'search_items'      => __('Search contexts', 'leadsforward-core'),
		'all_items'         => __('All contexts', 'leadsforward-core'),
		'edit_item'         => __('Edit context', 'leadsforward-core'),
		'update_item'       => __('Update context', 'leadsforward-core'),
		'add_new_item'      => __('Add new context', 'leadsforward-core'),
		'new_item_name'     => __('New context name', 'leadsforward-core'),
		'menu_name'         => __('Process context', 'leadsforward-core'),
	];
	register_taxonomy('lf_process_group', ['lf_process_step'], [
		'hierarchical'      => false,
		'labels'            => $labels,
		'description'       => __('Use slugs that match a service permalink slug for auto steps on that service, or use homepage-primary for the front page. Assigned services (ACF) is usually easier for organization.', 'leadsforward-core'),
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'public'            => false,
		'rewrite'           => false,
	]);
}
add_action('init', 'lf_register_taxonomy_process_groups');

function lf_register_cpt_process_steps(): void {
	$labels = [
		'name'               => _x('Process steps', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Process step', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Process steps', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'process step', 'leadsforward-core'),
		'add_new_item'       => __('Add process step', 'leadsforward-core'),
		'edit_item'          => __('Edit process step', 'leadsforward-core'),
		'new_item'           => __('New process step', 'leadsforward-core'),
		'view_item'          => __('View process step', 'leadsforward-core'),
		'search_items'       => __('Search process steps', 'leadsforward-core'),
		'not_found'          => __('No process steps found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No process steps found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'process-steps',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'process-steps', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => 24,
		'menu_icon'           => 'dashicons-editor-ol',
		'supports'            => ['title', 'editor', 'page-attributes', 'revisions'],
	];
	register_post_type('lf_process_step', $args);
}
add_action('init', 'lf_register_cpt_process_steps');

add_filter('manage_lf_process_step_posts_columns', 'lf_process_step_admin_columns');
add_action('manage_lf_process_step_posts_custom_column', 'lf_process_step_admin_column_content', 10, 2);

/**
 * @param string[] $columns
 * @return string[]
 */
function lf_process_step_admin_columns(array $columns): array {
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['lf_ps_services'] = __('Assigned services', 'leadsforward-core');
		}
	}
	return $new;
}

function lf_process_step_admin_column_content(string $column, int $post_id): void {
	if ($column !== 'lf_ps_services') {
		return;
	}
	$titles = [];
	if (function_exists('get_field')) {
		$rels = get_field('lf_process_step_related_services', $post_id);
		if (is_array($rels)) {
			foreach ($rels as $item) {
				$sid = 0;
				if ($item instanceof \WP_Post) {
					$sid = (int) $item->ID;
				} elseif (is_numeric($item)) {
					$sid = (int) $item;
				}
				if ($sid > 0) {
					$t = get_the_title($sid);
					if (is_string($t) && $t !== '') {
						$titles[] = $t;
					}
				}
			}
		}
	}
	if ($titles === []) {
		$csv_ids = lf_process_step_parse_service_ids_csv_meta((string) get_post_meta($post_id, '_lf_process_step_service_ids_csv', true));
		foreach ($csv_ids as $sid) {
			$t = get_the_title($sid);
			if (is_string($t) && $t !== '') {
				$titles[] = $t;
			}
		}
	}
	if ($titles === []) {
		echo '<span class="description">' . esc_html__('—', 'leadsforward-core') . '</span>';
		return;
	}
	echo esc_html(implode(', ', array_slice($titles, 0, 6)));
	$extra = count($titles) - 6;
	if ($extra > 0) {
		echo ' <span class="description">+' . esc_html((string) (int) $extra) . '</span>';
	}
}

/**
 * @param array<int|string|\WP_Post> $rels
 * @return list<int>
 */
function lf_process_step_normalize_related_service_ids(array $rels): array {
	$out = [];
	foreach ($rels as $item) {
		if ($item instanceof \WP_Post) {
			if ($item->post_type === 'lf_service') {
				$out[] = (int) $item->ID;
			}
			continue;
		}
		if (is_numeric($item)) {
			$pid = (int) $item;
			if ($pid > 0) {
				$out[] = $pid;
			}
		}
	}
	return array_values(array_unique(array_filter($out)));
}

/**
 * Stored as ",12,34," for reliable LIKE queries on service ID.
 *
 * @param list<int> $ids
 */
function lf_process_step_service_ids_to_csv(array $ids): string {
	$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
	sort($ids);
	if ($ids === []) {
		return '';
	}
	return ',' . implode(',', $ids) . ',';
}

/**
 * @return list<int>
 */
function lf_process_step_parse_service_ids_csv_meta(string $raw): array {
	$raw = trim($raw);
	if ($raw === '') {
		return [];
	}
	$inner = trim($raw, ',');
	if ($inner === '') {
		return [];
	}
	return array_values(array_unique(array_filter(array_map('absint', explode(',', $inner)))));
}

const LF_PROCESS_STEP_CANONICAL_META = '_lf_process_step_canonical_key';
const LF_PROCESS_STEP_GROUP_META = '_lf_process_group_slug';

/**
 * Stable dedupe key: {group_slug}::{title-slug}. Use library title before token fill.
 */
function lf_process_step_canonical_key(string $group_slug, string $title): string {
	$group_slug = sanitize_title($group_slug !== '' ? $group_slug : 'general');
	$title_slug = sanitize_title(wp_strip_all_tags($title));
	return $group_slug . '::' . ($title_slug !== '' ? $title_slug : 'step');
}

function lf_process_step_find_by_canonical_key(string $canonical_key): int {
	if ($canonical_key === '' || !post_type_exists('lf_process_step')) {
		return 0;
	}
	$posts = get_posts([
		'post_type' => 'lf_process_step',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 1,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
		'meta_key' => LF_PROCESS_STEP_CANONICAL_META,
		'meta_value' => $canonical_key,
	]);
	return !empty($posts[0]) ? (int) $posts[0] : 0;
}

function lf_process_step_ids_for_group(string $group_slug): array {
	$group_slug = sanitize_title($group_slug);
	if ($group_slug === '') {
		return [];
	}
	$by_meta = get_posts([
		'post_type' => 'lf_process_step',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 500,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
		'meta_key' => LF_PROCESS_STEP_GROUP_META,
		'meta_value' => $group_slug,
	]);
	$by_tax = [];
	if (taxonomy_exists('lf_process_group') && term_exists($group_slug, 'lf_process_group')) {
		$by_tax = get_posts([
			'post_type' => 'lf_process_step',
			'post_status' => ['publish', 'draft', 'pending', 'private'],
			'posts_per_page' => 500,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
			'tax_query' => [
				[
					'taxonomy' => 'lf_process_group',
					'field' => 'slug',
					'terms' => [$group_slug],
				],
			],
		]);
	}
	return array_values(array_unique(array_map('absint', array_merge(
		is_array($by_meta) ? $by_meta : [],
		is_array($by_tax) ? $by_tax : []
	))));
}

/**
 * Upsert process steps with canonical keys (prevents duplicates across syncs).
 *
 * @param list<array{title:string,body:string,key?:string}> $steps
 * @return list<int>
 */
function lf_process_step_upsert_batch(string $group_slug, array $steps, bool $overwrite_bodies = false): array {
	if ($steps === [] || !post_type_exists('lf_process_step')) {
		return [];
	}
	$group_slug = sanitize_title($group_slug !== '' ? $group_slug : 'homepage-primary');
	$term_id = function_exists('lf_ai_studio_ensure_process_group_term')
		? lf_ai_studio_ensure_process_group_term($group_slug)
		: 0;

	$out_ids = [];
	$seen_keys = [];
	foreach ($steps as $i => $row) {
		if (!is_array($row)) {
			continue;
		}
		$title = trim((string) ($row['title'] ?? ''));
		$body = trim((string) ($row['body'] ?? ''));
		if ($title === '') {
			continue;
		}
		$canonical_key = trim((string) ($row['key'] ?? ''));
		if ($canonical_key === '') {
			$canonical_key = lf_process_step_canonical_key($group_slug, $title);
		}
		if (isset($seen_keys[$canonical_key])) {
			continue;
		}
		$seen_keys[$canonical_key] = true;

		$post_id = lf_process_step_find_by_canonical_key($canonical_key);
		if ($post_id <= 0) {
			$post_id = (int) wp_insert_post([
				'post_type' => 'lf_process_step',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_content' => $body,
				'menu_order' => max(0, (int) $i),
			], true);
			if (is_wp_error($post_id) || $post_id <= 0) {
				continue;
			}
		} else {
			$update = [
				'ID' => $post_id,
				'post_title' => $title,
				'menu_order' => max(0, (int) $i),
			];
			$current_content = trim((string) get_post_field('post_content', $post_id));
			if ($body !== '' && ($overwrite_bodies || $current_content === '')) {
				$update['post_content'] = $body;
			}
			wp_update_post($update);
		}
		update_post_meta($post_id, LF_PROCESS_STEP_CANONICAL_META, $canonical_key);
		update_post_meta($post_id, LF_PROCESS_STEP_GROUP_META, $group_slug);
		if ($term_id > 0) {
			wp_set_object_terms($post_id, [$term_id], 'lf_process_group', false);
		}
		$out_ids[] = $post_id;
	}

	if ($out_ids !== []) {
		lf_process_step_dedupe_group($group_slug, $out_ids);
	}

	return array_values(array_filter(array_map('absint', $out_ids)));
}

/**
 * Trash duplicate process steps in a group; keep canonical posts from $keeper_ids first.
 *
 * @param list<int> $keeper_ids
 * @return int Number trashed
 */
function lf_process_step_dedupe_group(string $group_slug, array $keeper_ids = []): int {
	$group_slug = sanitize_title($group_slug);
	if ($group_slug === '') {
		return 0;
	}
	$keepers = array_fill_keys(array_map('absint', $keeper_ids), true);
	$candidates = lf_process_step_ids_for_group($group_slug);

	// Also sweep untagged duplicates that share canonical keys with keepers.
	$all = get_posts([
		'post_type' => 'lf_process_step',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 500,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$candidates = array_values(array_unique(array_merge($candidates, array_map('absint', $all))));

	$by_key = [];
	foreach ($candidates as $pid) {
		$pid = (int) $pid;
		if ($pid <= 0) {
			continue;
		}
		$key = (string) get_post_meta($pid, LF_PROCESS_STEP_CANONICAL_META, true);
		if ($key === '') {
			$key = lf_process_step_canonical_key($group_slug, (string) get_the_title($pid));
		}
		if (!str_starts_with($key, $group_slug . '::')) {
			continue;
		}
		$by_key[$key][] = $pid;
	}

	$trashed = 0;
	foreach ($by_key as $key => $ids) {
		if (count($ids) < 2) {
			$only = (int) $ids[0];
			if ($only > 0 && get_post_meta($only, LF_PROCESS_STEP_CANONICAL_META, true) === '') {
				update_post_meta($only, LF_PROCESS_STEP_CANONICAL_META, $key);
				update_post_meta($only, LF_PROCESS_STEP_GROUP_META, $group_slug);
			}
			continue;
		}
		sort($ids, SORT_NUMERIC);
		$keep = 0;
		foreach ($ids as $id) {
			if (isset($keepers[$id])) {
				$keep = $id;
				break;
			}
		}
		if ($keep <= 0) {
			$keep = (int) $ids[0];
		}
		update_post_meta($keep, LF_PROCESS_STEP_CANONICAL_META, $key);
		update_post_meta($keep, LF_PROCESS_STEP_GROUP_META, $group_slug);
		foreach ($ids as $id) {
			if ($id === $keep) {
				continue;
			}
			if (get_post_status($id) === 'trash') {
				continue;
			}
			if (wp_trash_post($id)) {
				$trashed++;
			}
		}
	}
	return $trashed;
}

/**
 * Detect process step titles created by a bad template import (mashed Key: labels).
 */
function lf_process_step_title_looks_like_import_junk(string $title): bool {
	$title = trim(wp_strip_all_tags($title));
	if ($title === '') {
		return false;
	}
	if (preg_match('/\b(Heading|Intro|Subheadline|Body|Items|Step|Template|Slug):\s*/i', $title)) {
		return true;
	}
	if (preg_match('/\bStep:\s*.+\|\|/i', $title)) {
		return true;
	}
	return strlen($title) > 200;
}

/**
 * Trash published process steps whose titles look like mashed import junk.
 *
 * @return int Number trashed
 */
function lf_process_step_trash_import_junk(): int {
	if (!post_type_exists('lf_process_step')) {
		return 0;
	}
	$trashed = 0;
	$posts = get_posts([
		'post_type' => 'lf_process_step',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 500,
		'no_found_rows' => true,
	]);
	foreach ($posts as $post) {
		if (!$post instanceof \WP_Post) {
			continue;
		}
		if (!lf_process_step_title_looks_like_import_junk((string) $post->post_title)) {
			continue;
		}
		if (wp_trash_post((int) $post->ID)) {
			$trashed++;
		}
	}
	return $trashed;
}

/**
 * Published process step IDs for a group, excluding import-junk titles.
 *
 * @return list<int>
 */
function lf_process_step_published_ids_for_group(string $group_slug): array {
	$ids = [];
	foreach (lf_process_step_ids_for_group($group_slug) as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$post = get_post($id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'lf_process_step' || $post->post_status !== 'publish') {
			continue;
		}
		if (lf_process_step_title_looks_like_import_junk((string) $post->post_title)) {
			continue;
		}
		$ids[] = $id;
	}
	return $ids;
}

