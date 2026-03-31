<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DOCS_SLUG = 'theme-docs';

add_action('init', 'lf_docs_register_route');
add_filter('query_vars', 'lf_docs_register_query_var');
add_action('template_redirect', 'lf_docs_template_loader');
add_action('after_switch_theme', 'lf_docs_flush_rewrite');
add_filter('wp_robots', 'lf_docs_add_noindex', 10, 2);
add_action('wp_enqueue_scripts', 'lf_docs_enqueue_assets');
add_filter('body_class', 'lf_docs_body_class');

function lf_docs_body_class(array $classes): array {
	if (lf_docs_is_request()) {
		$classes[] = 'lf-theme-docs';
	}
	return $classes;
}

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

function lf_docs_is_request(): bool {
	if ((int) get_query_var('lf_docs') === 1) {
		return true;
	}
	// Match by URL so we still load docs when a real WP Page with slug `theme-docs`
	// (or stale rules) wins the rewrite race and never sets `lf_docs`.
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	$req_path = wp_parse_url($request_uri, PHP_URL_PATH);
	$req_path = is_string($req_path) ? untrailingslashit($req_path) : '';
	$docs_path = wp_parse_url(home_url('/' . LF_DOCS_SLUG . '/'), PHP_URL_PATH);
	$docs_path = is_string($docs_path) ? untrailingslashit($docs_path) : '';
	return $req_path !== '' && $docs_path !== '' && $req_path === $docs_path;
}

function lf_docs_template_loader(): void {
	if (!lf_docs_is_request()) {
		return;
	}
	if (!is_user_logged_in()) {
		$login_url = wp_login_url(home_url('/' . LF_DOCS_SLUG . '/'));
		wp_safe_redirect($login_url);
		exit;
	}
	$template = LF_THEME_DIR . '/templates/lf-docs.php';
	if (!is_readable($template)) {
		status_header(404);
		exit;
	}
	include $template;
	exit;
}

function lf_docs_add_noindex(array $robots, $context): array {
	if (!lf_docs_is_request()) {
		return $robots;
	}
	$robots['noindex'] = true;
	$robots['nofollow'] = true;
	return $robots;
}

function lf_docs_enqueue_assets(): void {
	if (!lf_docs_is_request()) {
		return;
	}
	wp_enqueue_style('lf-docs', LF_THEME_URI . '/assets/css/docs-page.css', [], LF_THEME_VERSION);
}
