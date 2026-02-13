<?php
/**
 * Lightweight duplicate post action (no plugin dependency).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('post_row_actions', 'lf_duplicate_post_row_action', 10, 2);
add_filter('page_row_actions', 'lf_duplicate_post_row_action', 10, 2);
add_action('admin_action_lf_duplicate_post', 'lf_duplicate_post_handle_action');

function lf_duplicate_post_row_action(array $actions, \WP_Post $post): array {
	if (!current_user_can('edit_post', $post->ID)) {
		return $actions;
	}
	$nonce = wp_create_nonce('lf_duplicate_post_' . $post->ID);
	$url = admin_url('admin.php?action=lf_duplicate_post&post=' . $post->ID . '&_wpnonce=' . $nonce);
	$actions['lf_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate', 'leadsforward-core') . '</a>';
	return $actions;
}

function lf_duplicate_post_handle_action(): void {
	if (!isset($_GET['post'], $_GET['_wpnonce'])) {
		wp_safe_redirect(admin_url('edit.php'));
		exit;
	}
	$post_id = (int) $_GET['post'];
	$nonce = (string) $_GET['_wpnonce'];
	if (!wp_verify_nonce($nonce, 'lf_duplicate_post_' . $post_id)) {
		wp_die(esc_html__('Invalid duplicate request.', 'leadsforward-core'));
	}
	if (!current_user_can('edit_post', $post_id)) {
		wp_die(esc_html__('You do not have permission to duplicate this content.', 'leadsforward-core'));
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		wp_safe_redirect(admin_url('edit.php'));
		exit;
	}

	$new_id = wp_insert_post([
		'post_title'     => $post->post_title . ' ' . __('Copy', 'leadsforward-core'),
		'post_content'   => $post->post_content,
		'post_excerpt'   => $post->post_excerpt,
		'post_status'    => 'draft',
		'post_type'      => $post->post_type,
		'post_parent'    => $post->post_parent,
		'menu_order'     => $post->menu_order,
		'post_password'  => $post->post_password,
		'comment_status' => $post->comment_status,
		'ping_status'    => $post->ping_status,
		'post_author'    => get_current_user_id(),
	]);
	if (is_wp_error($new_id)) {
		wp_die(esc_html__('Failed to duplicate content.', 'leadsforward-core'));
	}

	$taxonomies = get_object_taxonomies($post->post_type);
	foreach ($taxonomies as $taxonomy) {
		$terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
		if (!is_wp_error($terms)) {
			wp_set_object_terms((int) $new_id, $terms, $taxonomy, false);
		}
	}

	$meta = get_post_meta($post_id);
	foreach ($meta as $key => $values) {
		foreach ($values as $value) {
			add_post_meta((int) $new_id, $key, $value);
		}
	}

	wp_safe_redirect(admin_url('post.php?action=edit&post=' . (int) $new_id));
	exit;
}
