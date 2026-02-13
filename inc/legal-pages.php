<?php
/**
 * Legal page helpers and auto-installers.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check whether a page is a fixed legal template.
 */
function lf_is_legal_page(?\WP_Post $post): bool {
	if (!$post || $post->post_type !== 'page') {
		return false;
	}
	return in_array($post->post_name, ['privacy-policy', 'terms-of-service'], true);
}

/**
 * Resolve business info for legal templates.
 *
 * @return array{name:string,address:string,phone:string,email:string}
 */
function lf_legal_business_details(): array {
	$nap = function_exists('lf_nap_data') ? lf_nap_data() : [];
	$name = trim((string) ($nap['name'] ?? ''));
	if ($name === '') {
		$name = get_bloginfo('name');
	}
	return [
		'name' => $name,
		'address' => trim((string) ($nap['address'] ?? '')),
		'phone' => trim((string) ($nap['phone'] ?? '')),
		'email' => trim((string) ($nap['email'] ?? '')),
	];
}

/**
 * Ensure privacy policy and terms pages exist with fixed templates.
 *
 * @return array{privacy?:int,terms?:int,error?:string}
 */
function lf_ensure_legal_pages(): array {
	$out = [];
	$pages = [
		'privacy-policy' => __('Privacy Policy', 'leadsforward-core'),
		'terms-of-service' => __('Terms of Service', 'leadsforward-core'),
	];
	foreach ($pages as $slug => $title) {
		$existing = get_page_by_path($slug, OBJECT, 'page');
		if (!$existing && $slug === 'terms-of-service') {
			$legacy = get_page_by_path('terms-of-use', OBJECT, 'page');
			if ($legacy) {
				wp_update_post([
					'ID' => $legacy->ID,
					'post_name' => $slug,
					'post_title' => $title,
				]);
				$existing = get_post($legacy->ID);
			}
		}
		if ($existing) {
			wp_update_post([
				'ID' => $existing->ID,
				'post_title' => $title,
				'post_status' => 'publish',
				'post_content' => '',
				'post_excerpt' => '',
			]);
			delete_post_meta($existing->ID, LF_PB_META_KEY);
			update_post_meta($existing->ID, '_wp_page_template', 'page-' . $slug . '.php');
			$out[$slug === 'privacy-policy' ? 'privacy' : 'terms'] = $existing->ID;
			continue;
		}
		$pid = wp_insert_post([
			'post_title' => $title,
			'post_name' => $slug,
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_author' => get_current_user_id(),
			'post_content' => '',
		], true);
		if (is_wp_error($pid)) {
			return ['error' => $pid->get_error_message()];
		}
		update_post_meta((int) $pid, '_wp_page_template', 'page-' . $slug . '.php');
		$out[$slug === 'privacy-policy' ? 'privacy' : 'terms'] = (int) $pid;
	}
	return $out;
}

function lf_ensure_legal_pages_on_activation(): void {
	lf_ensure_legal_pages();
}
add_action('after_switch_theme', 'lf_ensure_legal_pages_on_activation', 15);
