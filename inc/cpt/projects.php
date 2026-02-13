<?php
/**
 * Projects CPT. Portfolio/gallery items with before/after support.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_projects(): void {
	$labels = [
		'name'               => _x('Projects', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Project', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Projects', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'project', 'leadsforward-core'),
		'add_new_item'       => __('Add New Project', 'leadsforward-core'),
		'edit_item'          => __('Edit Project', 'leadsforward-core'),
		'new_item'           => __('New Project', 'leadsforward-core'),
		'view_item'          => __('View Project', 'leadsforward-core'),
		'search_items'       => __('Search Projects', 'leadsforward-core'),
		'not_found'          => __('No projects found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No projects found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'projects',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'projects', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 21,
		'menu_icon'           => 'dashicons-format-gallery',
		'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
	];
	register_post_type('lf_project', $args);
}
add_action('init', 'lf_register_cpt_projects');

function lf_register_project_taxonomy(): void {
	$labels = [
		'name'              => _x('Project Types', 'taxonomy general name', 'leadsforward-core'),
		'singular_name'     => _x('Project Type', 'taxonomy singular name', 'leadsforward-core'),
		'search_items'      => __('Search Project Types', 'leadsforward-core'),
		'all_items'         => __('All Project Types', 'leadsforward-core'),
		'edit_item'         => __('Edit Project Type', 'leadsforward-core'),
		'update_item'       => __('Update Project Type', 'leadsforward-core'),
		'add_new_item'      => __('Add New Project Type', 'leadsforward-core'),
		'new_item_name'     => __('New Project Type', 'leadsforward-core'),
		'menu_name'         => __('Project Types', 'leadsforward-core'),
	];

	register_taxonomy('lf_project_type', ['lf_project'], [
		'hierarchical'      => false,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => ['slug' => 'project-type', 'with_front' => false],
	]);
}
add_action('init', 'lf_register_project_taxonomy');
