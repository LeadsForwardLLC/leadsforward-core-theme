<?php
/**
 * Virtual /theme-docs/ route: standalone HTML, logged-in only, no WP Page required.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DOCS_SLUG = 'theme-docs';

add_action('init', 'lf_docs_register_route');
add_filter('query_vars', 'lf_docs_register_query_var');
add_action('template_redirect', 'lf_docs_maybe_serve_standalone', 0);
add_action('after_switch_theme', 'lf_docs_flush_rewrite');

function lf_docs_register_route(): void {
	add_rewrite_rule('^' . LF_DOCS_SLUG . '/?$', 'index.php?lf_docs=1', 'top');
}

function lf_docs_register_query_var(array $vars): array {
	$vars[] = 'lf_docs';
	return $vars;
}

function lf_docs_flush_rewrite(): void {
	lf_docs_register_route();
	flush_rewrite_rules();
}

function lf_docs_uri_is_docs(): bool {
	if ((int) get_query_var('lf_docs') === 1) {
		return true;
	}
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	$path = wp_parse_url($request_uri, PHP_URL_PATH);
	if (!is_string($path) || $path === '') {
		return false;
	}
	$trimmed = trim($path, '/');
	if ($trimmed === '') {
		return false;
	}
	$segments = array_values(array_filter(explode('/', $trimmed), static fn (string $s): bool => $s !== ''));
	if ($segments === []) {
		return false;
	}
	$last = $segments[ count($segments) - 1 ];
	if ($last === 'index.php' && count($segments) >= 2) {
		$last = $segments[ count($segments) - 2 ];
	}
	return $last === LF_DOCS_SLUG;
}

function lf_docs_maybe_serve_standalone(): void {
	if (!lf_docs_uri_is_docs()) {
		return;
	}
	if (!is_user_logged_in()) {
		wp_safe_redirect(wp_login_url(home_url('/' . LF_DOCS_SLUG . '/')));
		exit;
	}
	nocache_headers();
	$file = LF_THEME_DIR . '/templates/lf-docs-standalone.php';
	if (!is_readable($file)) {
		wp_die(esc_html__('Theme documentation template is missing.', 'leadsforward-core'), esc_html__('Not found', 'leadsforward-core'), ['response' => 500]);
	}
	require $file;
	exit;
}
