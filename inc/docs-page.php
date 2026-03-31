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
	return (int) get_query_var('lf_docs') === 1;
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
