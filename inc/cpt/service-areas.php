<?php
/**
 * Service Areas CPT. SEO-safe URLs: /service-areas/city-name/
 * For local lead-gen: cities, regions, zip codes.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_service_areas(): void {
	$labels = [
		'name'               => _x('Service Areas', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Service Area', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Service Areas', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'service area', 'leadsforward-core'),
		'add_new_item'       => __('Add New Service Area', 'leadsforward-core'),
		'edit_item'          => __('Edit Service Area', 'leadsforward-core'),
		'new_item'           => __('New Service Area', 'leadsforward-core'),
		'view_item'          => __('View Service Area', 'leadsforward-core'),
		'search_items'       => __('Search Service Areas', 'leadsforward-core'),
		'not_found'          => __('No service areas found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No service areas found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'service-areas',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'service-areas', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 21,
		'menu_icon'           => 'dashicons-location-alt',
		'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
	];
	register_post_type('lf_service_area', $args);
}
add_action('init', 'lf_register_cpt_service_areas');
