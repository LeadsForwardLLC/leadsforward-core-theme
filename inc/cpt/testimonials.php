<?php
/**
 * Reviews CPT. Private (not on front end), queryable for blocks and shortcodes.
 * Use in widgets, blocks, or single template when made public later.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_testimonials(): void {
	$labels = [
		'name'               => _x('Reviews', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Review', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Reviews', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'review', 'leadsforward-core'),
		'add_new_item'       => __('Add New Review', 'leadsforward-core'),
		'edit_item'          => __('Edit Review', 'leadsforward-core'),
		'new_item'           => __('New Review', 'leadsforward-core'),
		'view_item'          => __('View Review', 'leadsforward-core'),
		'search_items'       => __('Search Reviews', 'leadsforward-core'),
		'not_found'          => __('No reviews found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No reviews found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'reviews',
		'query_var'           => true,
		'rewrite'             => false,
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => 22,
		'menu_icon'           => 'dashicons-format-quote',
		'supports'            => ['title', 'editor', 'thumbnail', 'revisions'],
	];
	register_post_type('lf_testimonial', $args);
}
add_action('init', 'lf_register_cpt_testimonials');
