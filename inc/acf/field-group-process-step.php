<?php
/**
 * ACF field group: Process step CPT — link steps to services for organization and auto-placement.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_process_step_fields');
add_action('acf/save_post', 'lf_acf_process_step_after_save_links', 25);

function lf_acf_add_process_step_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'    => 'group_lf_process_step',
		'title'  => __('Process step details', 'leadsforward-core'),
		'fields' => [
			[
				'key'           => 'field_lf_process_step_related_services',
				'label'         => __('Assigned services', 'leadsforward-core'),
				'name'          => 'lf_process_step_related_services',
				'type'          => 'relationship',
				'instructions'  => __('Pick which services use this step. Used for admin organization and auto-loading the process section. You can still use “Process context” terms (e.g. homepage-primary) for homepage-only steps.', 'leadsforward-core'),
				'post_type'     => ['lf_service'],
				'return_format' => 'id',
				'multiple'      => 1,
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_process_step',
				],
			],
		],
	]);
}

/**
 * Persist a query-friendly CSV of service post IDs and append matching lf_process_group terms.
 *
 * @param int|string $post_id
 */
function lf_acf_process_step_after_save_links($post_id): void {
	if (!is_numeric($post_id) || (int) $post_id <= 0) {
		return;
	}
	$pid = (int) $post_id;
	if (get_post_type($pid) !== 'lf_process_step') {
		return;
	}
	if (!function_exists('get_field')) {
		return;
	}
	$rels = get_field('lf_process_step_related_services', $pid);
	$int_ids = function_exists('lf_process_step_normalize_related_service_ids')
		? lf_process_step_normalize_related_service_ids(is_array($rels) ? $rels : [])
		: [];
	$csv = function_exists('lf_process_step_service_ids_to_csv')
		? lf_process_step_service_ids_to_csv($int_ids)
		: '';
	if ($csv === '') {
		delete_post_meta($pid, '_lf_process_step_service_ids_csv');
	} else {
		update_post_meta($pid, '_lf_process_step_service_ids_csv', $csv);
	}
	if (!taxonomy_exists('lf_process_group')) {
		return;
	}
	foreach ($int_ids as $sid) {
		$svc = get_post($sid);
		if (!$svc instanceof \WP_Post || $svc->post_type !== 'lf_service') {
			continue;
		}
		$slug = (string) $svc->post_name;
		if ($slug === '') {
			continue;
		}
		$exists = term_exists($slug, 'lf_process_group');
		if ($exists) {
			$tid = is_array($exists) ? (int) ($exists['term_id'] ?? 0) : (int) $exists;
			if ($tid > 0) {
				wp_set_object_terms($pid, [$tid], 'lf_process_group', true);
			}
			continue;
		}
		$ins = wp_insert_term($svc->post_title, 'lf_process_group', ['slug' => $slug]);
		if (!is_wp_error($ins) && isset($ins['term_id'])) {
			wp_set_object_terms($pid, [(int) $ins['term_id']], 'lf_process_group', true);
		}
	}
}
