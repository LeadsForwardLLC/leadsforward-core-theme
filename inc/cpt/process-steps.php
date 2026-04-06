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
		'supports'            => ['title', 'editor', 'revisions'],
	];
	register_post_type('lf_process_step', $args);
}
add_action('init', 'lf_register_cpt_process_steps');
