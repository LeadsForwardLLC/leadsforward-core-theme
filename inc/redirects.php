<?php
/**
 * LeadsForward Core Theme — Redirects
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Canonicalize legacy Services overview URL.
 *
 * We now use the `lf_service` archive at `/services/` as the overview hub.
 * Keep `/our-services/` as a permanent redirect for backlinks.
 */
function lf_redirect_our_services_to_services_archive(): void {
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}
	if (!is_page('our-services')) {
		return;
	}

	$target = home_url('/services/');
	if (!is_string($target) || $target === '') {
		return;
	}
	wp_safe_redirect($target, 301);
	exit;
}
add_action('template_redirect', 'lf_redirect_our_services_to_services_archive', 1);

