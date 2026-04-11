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

