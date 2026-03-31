<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DOCS_SLUG = 'theme-docs';

add_action('init', 'lf_docs_register_route');
add_filter('query_vars', 'lf_docs_register_query_var');
add_filter('template_include', 'lf_docs_template_include', 99);
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
	// WordPress resolved a real Page with this slug (empty editor = "blank" without this).
	if (function_exists('is_page') && is_page(LF_DOCS_SLUG)) {
		return true;
	}
	// Path after Site Address URL (handles subfolders; avoids home_url vs REQUEST_URI mismatches).
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	$req_full = wp_parse_url($request_uri, PHP_URL_PATH);
	$req_full = is_string($req_full) ? trim($req_full, '/') : '';
	$home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
	$home_path = is_string($home_path) ? trim($home_path, '/') : '';
	$tail = $req_full;
	if ($home_path !== '' && $req_full !== '' && str_starts_with($req_full, $home_path . '/')) {
		$tail = substr($req_full, strlen($home_path) + 1);
	} elseif ($home_path !== '' && $req_full === $home_path) {
		$tail = '';
	}
	return rtrim((string) $tail, '/') === LF_DOCS_SLUG;
}

function lf_docs_template_include(string $template): string {
	if (!lf_docs_is_request()) {
		return $template;
	}
	if (!is_user_logged_in()) {
		wp_safe_redirect(wp_login_url(home_url('/' . LF_DOCS_SLUG . '/')));
		exit;
	}
	$doc = LF_THEME_DIR . '/templates/lf-docs.php';
	if (!is_readable($doc)) {
		return $template;
	}
	return $doc;
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
