<?php
/**
 * Page publish → n8n webhook (image placement and related automations).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_N8N_PAGE_EVENTS_OPTION = 'lf_n8n_page_events_webhook';
const LF_N8N_APPLYING_IMAGES_LOCK = 'lf_n8n_applying_image_updates';

add_action('transition_post_status', 'lf_n8n_on_post_status_publish', 20, 3);

/**
 * Webhook URL for page lifecycle events (separate from full-site orchestrator).
 */
function lf_n8n_page_events_webhook_url(): string {
	$url = trim((string) get_option(LF_N8N_PAGE_EVENTS_OPTION, ''));
	if ($url === '') {
		$url = trim((string) get_option('lf_ai_studio_webhook', ''));
	}

	return (string) apply_filters('lf_n8n_page_events_webhook_url', $url);
}

function lf_n8n_page_events_is_enabled(): bool {
	return lf_n8n_page_events_webhook_url() !== ''
		&& (bool) apply_filters('lf_n8n_page_events_is_enabled', true);
}

function lf_n8n_on_post_status_publish(string $new_status, string $old_status, \WP_Post $post): void {
	if ($new_status !== 'publish' || $old_status === 'publish') {
		return;
	}
	if (!lf_n8n_page_events_is_enabled()) {
		return;
	}
	if (get_transient(LF_N8N_APPLYING_IMAGES_LOCK)) {
		return;
	}
	if (!in_array($post->post_type, ['page', 'lf_service', 'lf_service_area', 'post'], true)) {
		return;
	}
	if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
		return;
	}

	lf_n8n_queue_page_event('page_published', (int) $post->ID);
}

/**
 * Queue async delivery so publish requests stay fast.
 */
function lf_n8n_queue_page_event(string $event, int $post_id): void {
	$post_id = absint($post_id);
	if ($post_id <= 0) {
		return;
	}
	wp_schedule_single_event(time() + 2, 'lf_n8n_deliver_page_event', [$event, $post_id]);
}
add_action('lf_n8n_deliver_page_event', 'lf_n8n_deliver_page_event', 10, 2);

/**
 * @return array<string, mixed>
 */
function lf_n8n_build_page_event_payload(string $event, int $post_id): array {
	$post = get_post($post_id);
	$secret = (string) get_option('lf_ai_studio_secret', '');
	$page_payload = function_exists('lf_image_slot_build_page_payload')
		? lf_image_slot_build_page_payload($post_id)
		: [];

	$payload = [
		'event' => sanitize_key($event),
		'site_url' => home_url('/'),
		'timestamp' => wp_date('c'),
		'callback_url' => function_exists('lf_ai_studio_build_callback_url')
			? lf_ai_studio_build_callback_url()
			: rest_url('leadsforward/v1/orchestrator'),
		'page' => $page_payload,
		'business' => [
			'name' => (string) get_option('lf_business_name', get_bloginfo('name')),
			'city' => (string) get_option('lf_city_region', get_option('lf_homepage_city', '')),
			'niche' => (string) get_option('lf_homepage_niche_slug', ''),
		],
	];
	if ($secret !== '') {
		$payload['callback_authorization'] = 'Bearer ' . $secret;
	}
	if ($post instanceof \WP_Post) {
		$payload['post_id'] = (int) $post->ID;
		$payload['post_type'] = (string) $post->post_type;
		$payload['slug'] = (string) $post->post_name;
	}

	return (array) apply_filters('lf_n8n_page_event_payload', $payload, $event, $post_id);
}

function lf_n8n_deliver_page_event(string $event, int $post_id): void {
	if (!lf_n8n_page_events_is_enabled()) {
		return;
	}
	$webhook = lf_n8n_page_events_webhook_url();
	if ($webhook === '') {
		return;
	}

	$payload = lf_n8n_build_page_event_payload($event, $post_id);
	$secret = (string) get_option('lf_ai_studio_secret', '');
	$headers = ['Content-Type' => 'application/json'];
	if ($secret !== '') {
		$headers['Authorization'] = 'Bearer ' . $secret;
	}

	wp_remote_post($webhook, [
		'timeout' => 15,
		'blocking' => false,
		'headers' => $headers,
		'body' => wp_json_encode($payload),
	]);
}

/**
 * Prevent publish webhooks while n8n image callbacks are applying.
 */
function lf_n8n_begin_image_apply_lock(): void {
	set_transient(LF_N8N_APPLYING_IMAGES_LOCK, 1, 2 * MINUTE_IN_SECONDS);
}

function lf_n8n_end_image_apply_lock(): void {
	delete_transient(LF_N8N_APPLYING_IMAGES_LOCK);
}

add_action('lf_n8n_before_apply_image_updates', 'lf_n8n_begin_image_apply_lock');
add_action('lf_n8n_after_apply_image_updates', 'lf_n8n_end_image_apply_lock');
