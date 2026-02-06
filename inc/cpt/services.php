<?php
/**
 * Services CPT. SEO-safe URLs: /services/service-name/
 * show_in_rest for blocks and future AI tooling.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_services(): void {
	$labels = [
		'name'               => _x('Services', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Service', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Services', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'service', 'leadsforward-core'),
		'add_new_item'       => __('Add New Service', 'leadsforward-core'),
		'edit_item'          => __('Edit Service', 'leadsforward-core'),
		'new_item'           => __('New Service', 'leadsforward-core'),
		'view_item'          => __('View Service', 'leadsforward-core'),
		'search_items'       => __('Search Services', 'leadsforward-core'),
		'not_found'          => __('No services found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No services found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'services',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'services', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-hammer',
		'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
	];
	register_post_type('lf_service', $args);
}
add_action('init', 'lf_register_cpt_services');
