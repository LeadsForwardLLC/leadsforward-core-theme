<?php
/**
 * ACF field group: Team member CPT.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_team_member_fields');

function lf_acf_add_team_member_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'    => 'group_lf_team_member',
		'title'  => __('Team member details', 'leadsforward-core'),
		'fields' => [
			[
				'key'           => 'field_lf_team_role',
				'label'         => __('Role / title', 'leadsforward-core'),
				'name'          => 'lf_team_role',
				'type'          => 'text',
				'instructions'  => __('Job title shown under the name (e.g. Project Manager).', 'leadsforward-core'),
				'placeholder'   => __('Project Manager', 'leadsforward-core'),
			],
			[
				'key'          => 'field_lf_team_bio',
				'label'        => __('Bio', 'leadsforward-core'),
				'name'         => 'lf_team_bio',
				'type'         => 'textarea',
				'rows'         => 4,
				'instructions' => __('Short bio. You can also use the Excerpt field; bio wins if both are set.', 'leadsforward-core'),
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_team_member',
				],
			],
		],
	]);
}
