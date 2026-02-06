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
