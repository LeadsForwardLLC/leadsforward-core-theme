<?php
/**
 * FAQs CPT. Public, schema-ready for FAQPage JSON-LD. Clean URLs for future.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_faqs(): void {
	$labels = [
		'name'               => _x('FAQs', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('FAQ', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('FAQs', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'faq', 'leadsforward-core'),
		'add_new_item'       => __('Add New FAQ', 'leadsforward-core'),
		'edit_item'          => __('Edit FAQ', 'leadsforward-core'),
		'new_item'           => __('New FAQ', 'leadsforward-core'),
		'view_item'          => __('View FAQ', 'leadsforward-core'),
		'search_items'       => __('Search FAQs', 'leadsforward-core'),
		'not_found'          => __('No FAQs found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No FAQs found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'faqs',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'faqs', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 23,
		'menu_icon'           => 'dashicons-editor-help',
		'supports'            => ['title', 'editor', 'revisions'],
	];
	register_post_type('lf_faq', $args);
}
add_action('init', 'lf_register_cpt_faqs');

const LF_FAQ_CANONICAL_META = '_lf_faq_canonical_key';
const LF_FAQ_CONTEXT_META = '_lf_faq_context';

/**
 * Stable dedupe key: {context}::{question-slug}.
 */
function lf_faq_canonical_key(string $context, string $question): string {
	$context = sanitize_key($context !== '' ? $context : 'general');
	$slug = sanitize_title(wp_strip_all_tags($question));
	return $context . '::' . ($slug !== '' ? $slug : 'faq');
}

function lf_faq_find_by_canonical_key(string $canonical_key): int {
	if ($canonical_key === '' || !post_type_exists('lf_faq')) {
		return 0;
	}
	$posts = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 1,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
		'meta_key' => LF_FAQ_CANONICAL_META,
		'meta_value' => $canonical_key,
	]);
	return !empty($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * @return list<int>
 */
function lf_faq_ids_for_context(string $context): array {
	$context = sanitize_key($context);
	if ($context === '') {
		return [];
	}
	$posts = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 500,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
		'meta_key' => LF_FAQ_CONTEXT_META,
		'meta_value' => $context,
	]);
	return array_values(array_unique(array_map('absint', is_array($posts) ? $posts : [])));
}

/**
 * @param list<array{question:string,answer:string,key?:string}> $faqs
 * @return list<int>
 */
function lf_faq_upsert_batch(string $context, array $faqs, bool $overwrite_bodies = false): array {
	if ($faqs === [] || !post_type_exists('lf_faq')) {
		return [];
	}
	$context = sanitize_key($context !== '' ? $context : 'general');
	$out_ids = [];
	$seen_keys = [];

	foreach ($faqs as $row) {
		if (!is_array($row)) {
			continue;
		}
		$question = sanitize_text_field((string) ($row['question'] ?? ''));
		$answer = wp_kses_post((string) ($row['answer'] ?? ''));
		if ($question === '') {
			continue;
		}
		$canonical_key = trim((string) ($row['key'] ?? ''));
		if ($canonical_key === '') {
			$canonical_key = lf_faq_canonical_key($context, $question);
		}
		if (isset($seen_keys[$canonical_key])) {
			continue;
		}
		$seen_keys[$canonical_key] = true;

		$faq_id = lf_faq_find_by_canonical_key($canonical_key);
		if ($faq_id <= 0) {
			$faq_id = (int) wp_insert_post([
				'post_type' => 'lf_faq',
				'post_status' => 'publish',
				'post_title' => $question,
				'post_content' => $answer,
			], true);
			if (is_wp_error($faq_id) || $faq_id <= 0) {
				continue;
			}
		} else {
			$update = ['ID' => $faq_id, 'post_title' => $question];
			if ($answer !== '') {
				$current = trim((string) get_post_field('post_content', $faq_id));
				if ($overwrite_bodies || $current === '') {
					$update['post_content'] = $answer;
				}
			}
			wp_update_post($update);
		}

		update_post_meta($faq_id, LF_FAQ_CANONICAL_META, $canonical_key);
		update_post_meta($faq_id, LF_FAQ_CONTEXT_META, $context);

		if (function_exists('update_field')) {
			update_field('lf_faq_question', $question, $faq_id);
			if ($answer !== '') {
				$current_acf = trim((string) get_field('lf_faq_answer', $faq_id));
				if ($overwrite_bodies || $current_acf === '') {
					update_field('lf_faq_answer', $answer, $faq_id);
				}
			}
		} else {
			update_post_meta($faq_id, 'lf_faq_question', $question);
			if ($answer !== '') {
				$current_meta = trim((string) get_post_meta($faq_id, 'lf_faq_answer', true));
				if ($overwrite_bodies || $current_meta === '') {
					update_post_meta($faq_id, 'lf_faq_answer', $answer);
				}
			}
		}
		$out_ids[] = $faq_id;
	}

	if ($out_ids !== []) {
		lf_faq_dedupe_context($context, $out_ids);
	}

	return array_values(array_filter(array_map('absint', $out_ids)));
}

/**
 * @param list<int> $keeper_ids
 */
function lf_faq_dedupe_context(string $context, array $keeper_ids = []): int {
	$context = sanitize_key($context);
	if ($context === '') {
		return 0;
	}
	$keepers = array_fill_keys(array_map('absint', $keeper_ids), true);
	$candidates = lf_faq_ids_for_context($context);
	$all = get_posts([
		'post_type' => 'lf_faq',
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
		$row_context = (string) get_post_meta($pid, LF_FAQ_CONTEXT_META, true);
		$key = (string) get_post_meta($pid, LF_FAQ_CANONICAL_META, true);
		$question = function_exists('get_field') ? (string) get_field('lf_faq_question', $pid) : '';
		if ($question === '') {
			$question = (string) get_the_title($pid);
		}
		if ($key === '') {
			$row_context = $row_context !== '' ? $row_context : $context;
			$key = lf_faq_canonical_key($row_context, $question);
		}
		if ($row_context !== '' && $row_context !== $context) {
			continue;
		}
		if (!str_starts_with($key, $context . '::')) {
			continue;
		}
		$by_key[$key][] = $pid;
	}

	$trashed = 0;
	foreach ($by_key as $key => $ids) {
		if (count($ids) < 2) {
			$only = (int) $ids[0];
			if ($only > 0) {
				update_post_meta($only, LF_FAQ_CANONICAL_META, $key);
				update_post_meta($only, LF_FAQ_CONTEXT_META, $context);
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
		update_post_meta($keep, LF_FAQ_CANONICAL_META, $key);
		update_post_meta($keep, LF_FAQ_CONTEXT_META, $context);
		foreach ($ids as $id) {
			if ($id === $keep || get_post_status($id) === 'trash') {
				continue;
			}
			if (wp_trash_post($id)) {
				$trashed++;
			}
		}
	}
	return $trashed;
}
