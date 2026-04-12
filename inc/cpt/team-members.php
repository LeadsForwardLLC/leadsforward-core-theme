<?php
/**
 * Team members CPT. Managed in admin; rendered from the Team section (like FAQs).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_team_members(): void {
	$labels = [
		'name'               => _x('Team members', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Team member', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Team', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'team member', 'leadsforward-core'),
		'add_new_item'       => __('Add team member', 'leadsforward-core'),
		'edit_item'          => __('Edit team member', 'leadsforward-core'),
		'new_item'           => __('New team member', 'leadsforward-core'),
		'view_item'          => __('View team member', 'leadsforward-core'),
		'search_items'       => __('Search team members', 'leadsforward-core'),
		'not_found'          => __('No team members found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No team members found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => true,
		'rest_base'           => 'team-members',
		'exclude_from_search' => true,
		'query_var'           => false,
		'rewrite'             => false,
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => 24,
		'menu_icon'           => 'dashicons-groups',
		'supports'            => ['title', 'thumbnail', 'page-attributes', 'excerpt'],
	];
	register_post_type('lf_team_member', $args);
}
add_action('init', 'lf_register_cpt_team_members');
