<?php
/**
 * AI Studio REST endpoints.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/ai-studio-identity.php';
require_once __DIR__ . '/ai-studio-orchestrator-utils.php';

add_action('rest_api_init', 'lf_ai_studio_register_rest');

function lf_ai_studio_register_rest(): void {
	register_rest_route('leadsforward/v1', '/blueprint', [
		'methods' => 'GET',
		'callback' => 'lf_ai_studio_rest_blueprint',
		'permission_callback' => 'lf_ai_studio_rest_auth',
	]);
	register_rest_route('leadsforward/v1', '/apply', [
		'methods' => 'POST',
		'callback' => 'lf_ai_studio_rest_apply',
		'permission_callback' => 'lf_ai_studio_rest_auth',
	]);
	register_rest_route('leadsforward/v1', '/orchestrator', [
		'methods' => 'POST',
		'callback' => 'lf_ai_studio_rest_orchestrator',
		'permission_callback' => 'lf_ai_studio_rest_auth',
	]);
	register_rest_route('leadsforward/v1', '/progress', [
		'methods' => 'POST',
		'callback' => 'lf_ai_studio_rest_progress',
		'permission_callback' => 'lf_ai_studio_rest_auth',
	]);
	register_rest_route('leadsforward/v1', '/airtable-webhook', [
		'methods' => 'POST',
		'callback' => 'lf_ai_studio_rest_airtable_webhook',
		'permission_callback' => 'lf_ai_studio_rest_auth',
	]);
}

function lf_ai_studio_rest_auth(\WP_REST_Request $request) {
	$secret = trim((string) get_option('lf_ai_studio_secret', ''));
	if ($secret === '') {
		return new \WP_Error('lf_ai_auth_missing', 'Shared secret is not configured.', ['status' => 401]);
	}
	$mode = function_exists('lf_ai_studio_auth_mode') ? lf_ai_studio_auth_mode() : 'compatibility';
	$requires_hmac = lf_ai_studio_rest_requires_hmac($request);
	$hmac_result = lf_ai_studio_rest_hmac_auth($request, $secret, $requires_hmac);
	if ($hmac_result === true) {
		return true;
	}
	$legacy_result = lf_ai_studio_rest_legacy_auth($request, $secret);
	if ($legacy_result === true) {
		if ($mode === 'strict_hmac' && $requires_hmac) {
			return new \WP_Error('lf_ai_auth_hmac_required', 'HMAC signature headers are required for this endpoint.', ['status' => 401]);
		}
		update_option('lf_ai_studio_auth_legacy_last_seen', time(), false);
		return true;
	}
	if ($mode === 'compatibility' && (string) $request->get_route() === '/leadsforward/v1/progress') {
		$progress_binding = lf_ai_studio_rest_progress_binding_auth($request);
		if ($progress_binding === true) {
			update_option('lf_ai_studio_auth_compat_progress_last_seen', time(), false);
			return true;
		}
	}
	if ($mode === 'compatibility' && !$requires_hmac) {
		return $legacy_result instanceof \WP_Error ? $legacy_result : $hmac_result;
	}
	return $hmac_result instanceof \WP_Error ? $hmac_result : $legacy_result;
}

function lf_ai_studio_rest_requires_hmac(\WP_REST_Request $request): bool {
	$route = (string) $request->get_route();
	return $route === '/leadsforward/v1/orchestrator' || $route === '/leadsforward/v1/progress' || $route === '/leadsforward/v1/airtable-webhook';
}

function lf_ai_studio_rest_legacy_auth(\WP_REST_Request $request, string $secret) {
	$auth = (string) $request->get_header('authorization');
	$token = '';
	if ($auth === '') {
		// Production hardening: query-string tokens are disabled by default.
		$allow_query_token_default = wp_get_environment_type() !== 'production';
		$allow_query_token = (bool) apply_filters('lf_ai_studio_allow_query_token', $allow_query_token_default, $request);
		if (!$allow_query_token) {
			return new \WP_Error('lf_ai_auth_missing', 'Missing Authorization token.', ['status' => 401]);
		}
		$token = (string) $request->get_param('token');
	} else {
		if (stripos($auth, 'bearer ') !== 0) {
			return new \WP_Error('lf_ai_auth_invalid', 'Invalid Authorization header.', ['status' => 401]);
		}
		$token = trim(substr($auth, 7));
	}
	if ($token === '') {
		return new \WP_Error('lf_ai_auth_missing', 'Missing Authorization token.', ['status' => 401]);
	}
	if (!hash_equals($secret, $token)) {
		return new \WP_Error('lf_ai_auth_invalid', 'Invalid bearer token.', ['status' => 401]);
	}
	return true;
}

function lf_ai_studio_rest_hmac_auth(\WP_REST_Request $request, string $secret, bool $required) {
	$timestamp_raw = trim((string) $request->get_header('x-lf-timestamp'));
	$nonce = trim((string) $request->get_header('x-lf-nonce'));
	$signature = trim((string) $request->get_header('x-lf-signature'));
	if ($timestamp_raw === '' || $nonce === '' || $signature === '') {
		return $required
			? new \WP_Error('lf_ai_auth_hmac_missing', 'Missing HMAC signature headers.', ['status' => 401])
			: new \WP_Error('lf_ai_auth_hmac_missing', 'Missing HMAC signature headers.', ['status' => 401]);
	}
	$timestamp = (int) $timestamp_raw;
	if ($timestamp <= 0) {
		return new \WP_Error('lf_ai_auth_hmac_invalid', 'Invalid signature timestamp.', ['status' => 401]);
	}
	$tolerance = function_exists('lf_ai_studio_hmac_tolerance_seconds')
		? lf_ai_studio_hmac_tolerance_seconds()
		: 300;
	if (abs(time() - $timestamp) > $tolerance) {
		return new \WP_Error('lf_ai_auth_hmac_stale', 'Signature timestamp outside allowed window.', ['status' => 401]);
	}
	$nonce_key = 'lf_ai_hmac_nonce_' . md5($nonce);
	if (get_transient($nonce_key)) {
		return new \WP_Error('lf_ai_auth_hmac_replay', 'Signature nonce already used.', ['status' => 401]);
	}
	$body = (string) $request->get_body();
	$expected = hash_hmac('sha256', $timestamp_raw . "\n" . $nonce . "\n" . $body, $secret);
	if (!hash_equals($expected, strtolower($signature))) {
		return new \WP_Error('lf_ai_auth_hmac_invalid', 'Invalid HMAC signature.', ['status' => 401]);
	}
	set_transient($nonce_key, 1, $tolerance);
	return true;
}

function lf_ai_studio_rest_payload_hash(array $payload): string {
	return hash('sha256', (string) wp_json_encode($payload));
}

function lf_ai_studio_rest_progress_binding_auth(\WP_REST_Request $request): bool {
	$payload = $request->get_json_params();
	if (!is_array($payload)) {
		$raw = (string) $request->get_body();
		if ($raw === '') {
			return false;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return false;
		}
		$payload = $decoded;
	}
	$job_id = isset($payload['job_id']) ? absint($payload['job_id']) : 0;
	$request_id = sanitize_text_field((string) ($payload['request_id'] ?? ''));
	if ($job_id <= 0 || $request_id === '') {
		return false;
	}
	$job = get_post($job_id);
	if (!$job instanceof \WP_Post || $job->post_type !== LF_AI_STUDIO_JOB_CPT) {
		return false;
	}
	$stored_request_id = (string) get_post_meta($job_id, 'lf_ai_job_request_id', true);
	if ($stored_request_id === '') {
		return false;
	}
	return hash_equals($stored_request_id, $request_id);
}

function lf_ai_studio_rest_validate_callback_binding(int $job_id, string $request_id, string $payload_hash): array {
	if ($job_id <= 0 || $request_id === '') {
		return ['ok' => false, 'error' => 'missing_job_or_request', 'status' => 400];
	}
	$job = get_post($job_id);
	if (!$job instanceof \WP_Post || $job->post_type !== LF_AI_STUDIO_JOB_CPT) {
		return ['ok' => false, 'error' => 'invalid_job', 'status' => 404];
	}
	$stored_request_id = (string) get_post_meta($job_id, 'lf_ai_job_request_id', true);
	if ($stored_request_id !== '' && !hash_equals($stored_request_id, $request_id)) {
		return ['ok' => false, 'error' => 'request_id_mismatch', 'status' => 409];
	}
	$last_hash = (string) get_post_meta($job_id, 'lf_ai_job_last_callback_hash', true);
	$current_status = (string) get_post_meta($job_id, 'lf_ai_job_status', true);
	if ($last_hash !== '' && hash_equals($last_hash, $payload_hash) && in_array($current_status, ['done', 'failed'], true)) {
		return ['ok' => true, 'idempotent' => true, 'status' => 200];
	}
	$idem_key = 'lf_ai_cb_' . md5($job_id . '|' . $request_id . '|' . $payload_hash);
	if (get_transient($idem_key)) {
		return ['ok' => true, 'idempotent' => true, 'status' => 200];
	}
	set_transient($idem_key, 1, 15 * MINUTE_IN_SECONDS);
	return ['ok' => true, 'idempotent' => false, 'status' => 200];
}

function lf_ai_studio_rest_blueprint(\WP_REST_Request $request): \WP_REST_Response {
	$payload = lf_ai_studio_build_blueprint_rest();
	return new \WP_REST_Response($payload, 200);
}

function lf_ai_studio_build_blueprint_rest(): array {
	$site_url = home_url('/');
	$site_name = get_bloginfo('name');
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$niche_option = defined('LF_HOMEPAGE_NICHE_OPTION') ? LF_HOMEPAGE_NICHE_OPTION : 'lf_homepage_niche_slug';
	$niche_default = function_exists('lf_default_niche_slug') ? lf_default_niche_slug() : 'foundation-repair';
	$niche = (string) get_option($niche_option, $niche_default);
	$niche_profile = function_exists('lf_get_niche') ? lf_get_niche($niche) : ['slug' => $niche];
	$homepage = lf_ai_studio_collect_homepage();
	$inventory = lf_ai_studio_collect_pages_inventory();
	$internal_links = lf_ai_studio_internal_link_requirements($inventory);
	return [
		'schema_version' => '1.0',
		'site' => [
			'url' => $site_url,
			'name' => $site_name,
		],
		'business_entity' => $entity,
		'niche_profile' => $niche_profile,
		'homepage' => $homepage,
		'pages' => $inventory,
		'section_schema' => lf_ai_studio_section_schema(),
		'internal_linking' => $internal_links,
	];
}

function lf_ai_studio_collect_homepage(): array {
	$home_id = (int) get_option('page_on_front');
	if (!$home_id || !function_exists('lf_get_homepage_section_config')) {
		return [];
	}
	$config = lf_get_homepage_section_config();
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : array_keys($config);
	$sections = [];
	$field_schema = [];
	$sanitized_config = [];
	foreach ($order as $type) {
		if (!isset($config[$type]) || !is_array($config[$type])) {
			continue;
		}
		$sanitized_config[$type] = lf_sections_sanitize_settings($type, $config[$type]);
		$sections[] = [
			'type' => $type,
			'fields' => array_keys($config[$type]),
		];
		$field_schema[$type] = lf_ai_studio_section_schema_for_type($type);
	}
	return [
		'id' => $home_id,
		'template_type' => 'homepage',
		'section_order' => $order,
		'field_schema' => $field_schema,
		'config' => $sanitized_config,
		'sections' => $sections,
	];
}

function lf_ai_studio_collect_pages_inventory(): array {
	$pages = [];
	$home_id = (int) get_option('page_on_front');
	if ($home_id) {
		$home = get_post($home_id);
		if ($home instanceof \WP_Post) {
			$pages[] = lf_ai_studio_collect_page_item($home, 'home');
		}
	}
	$page_slugs = [
		'about' => 'about-us',
		'contact' => 'contact',
		'reviews' => 'reviews',
		'blog' => 'blog',
		'sitemap' => 'sitemap',
		'privacy' => 'privacy-policy',
		'terms' => 'terms-of-service',
		'thank_you' => 'thank-you',
		'services_hub' => 'services',
		'service_areas_hub' => 'service-areas',
	];
	foreach ($page_slugs as $key => $slug) {
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post) {
			$pages[] = lf_ai_studio_collect_page_item($page, $key);
		} else {
			$pages[] = [
				'id' => 0,
				'slug' => $slug,
				'title' => '',
				'post_type' => 'page',
				'template_type' => $key,
				'context' => '',
				'section_order' => [],
				'section_types' => [],
				'field_schema' => [],
				'sections' => [],
				'missing' => true,
			];
		}
	}

	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($services as $service) {
		$pages[] = lf_ai_studio_collect_page_item($service, 'service');
	}

	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($areas as $area) {
		$pages[] = lf_ai_studio_collect_page_item($area, 'service_area');
	}
	return $pages;
}

function lf_ai_studio_collect_page_item(\WP_Post $post, string $template_type): array {
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	$config = ($context !== '' && function_exists('lf_pb_get_post_config')) ? lf_pb_get_post_config($post->ID, $context) : [];
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	$section_keys = [];
	foreach ($order as $instance_id) {
		$row = $sections[$instance_id] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$type = $row['type'] ?? '';
		if ($type !== '') {
			$section_keys[] = $type;
		}
	}
	$field_schema = [];
	foreach ($section_keys as $type) {
		$field_schema[$type] = lf_ai_studio_section_schema_for_type($type);
	}
	return [
		'id' => $post->ID,
		'slug' => $post->post_name,
		'title' => $post->post_title,
		'post_type' => $post->post_type,
		'template_type' => $template_type,
		'context' => $context,
		'section_order' => $order,
		'section_types' => $section_keys,
		'field_schema' => $field_schema,
		'sections' => $sections,
	];
}

function lf_ai_studio_section_schema(): array {
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$schema = [];
	foreach ($registry as $id => $section) {
		$fields = $section['fields'] ?? [];
		$schema[$id] = array_map(function ($field) use ($id) {
			$key = $field['key'] ?? '';
			$required = ($id === 'hero' && $key === 'hero_headline');
			return [
				'key' => $key,
				'type' => $field['type'] ?? 'text',
				'required' => $required,
			];
		}, $fields);
	}
	return $schema;
}

function lf_ai_studio_section_schema_for_type(string $type): array {
	$schema = lf_ai_studio_section_schema();
	return $schema[$type] ?? [];
}

function lf_ai_studio_internal_link_requirements(array $pages): array {
	$service_ids = [];
	$area_ids = [];
	$services_hub = null;
	$areas_hub = null;
	$home_id = (int) get_option('page_on_front');
	foreach ($pages as $page) {
		if (($page['template_type'] ?? '') === 'services_hub') {
			$services_hub = $page['id'];
		}
		if (($page['template_type'] ?? '') === 'service_areas_hub') {
			$areas_hub = $page['id'];
		}
		if (($page['post_type'] ?? '') === 'lf_service') {
			$service_ids[] = $page['id'];
		}
		if (($page['post_type'] ?? '') === 'lf_service_area') {
			$area_ids[] = $page['id'];
		}
	}
	$top_services = array_slice($service_ids, 0, 6);
	$top_areas = array_slice($area_ids, 0, 6);
	return [
		'services_to_areas' => true,
		'areas_to_services' => true,
		'hubs_to_children' => true,
		'homepage_to_top_services' => true,
		'homepage_to_top_areas' => true,
		'home_id' => $home_id,
		'services_hub_id' => $services_hub,
		'service_areas_hub_id' => $areas_hub,
		'service_ids' => $service_ids,
		'service_area_ids' => $area_ids,
		'top_services' => $top_services,
		'top_service_areas' => $top_areas,
	];
}

function lf_ai_studio_rest_apply(\WP_REST_Request $request): \WP_REST_Response {
	$payload = $request->get_json_params();
	if (!is_array($payload)) {
		$raw = (string) $request->get_body();
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$payload = $decoded;
			} elseif (is_string($decoded)) {
				$decoded_again = json_decode($decoded, true);
				if (is_array($decoded_again)) {
					$payload = $decoded_again;
				}
			}
		}
	}
	if (function_exists('lf_ai_studio_error_log')) {
		$keys = is_array($payload) ? array_keys($payload) : [];
		lf_ai_studio_error_log('rest_apply: payload received', 'INFO', [
			'route' => (string) $request->get_route(),
			'keys' => array_slice($keys, 0, 40),
			'job_id' => isset($payload['job_id']) ? absint($payload['job_id']) : 0,
		]);
		update_option('lf_ai_studio_last_rest_apply_payload', $payload, false);
		update_option('lf_ai_studio_last_rest_apply_at', time(), false);
	}
	if (!is_array($payload)) {
		if (function_exists('lf_ai_studio_error_log')) {
			lf_ai_studio_error_log('orchestrator: invalid_json payload', 'ERROR');
		} else {
			error_log('LF AI STUDIO [ERROR]: orchestrator: invalid_json payload');
		}
		return new \WP_REST_Response(['error' => 'invalid_json'], 400);
	}
	if (isset($payload['payload']) && is_string($payload['payload'])) {
		$decoded_payload = json_decode($payload['payload'], true);
		if (is_array($decoded_payload)) {
			$payload = $decoded_payload;
		}
	}
	$job_id = isset($payload['job_id']) ? absint($payload['job_id']) : 0;
	if ($job_id) {
		$job = get_post($job_id);
		if (!$job instanceof \WP_Post || $job->post_type !== LF_AI_STUDIO_JOB_CPT) {
			$job_id = 0;
		}
	}
	if (!$job_id) {
		$job_id = lf_ai_studio_create_job(['source' => 'rest_apply']);
	}
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
	update_post_meta($job_id, 'lf_ai_job_request', $payload);
	$errors = lf_ai_studio_validate_apply_payload($payload);
	if (!empty($errors)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', implode('; ', $errors));
		return new \WP_REST_Response(['error' => 'validation_failed', 'messages' => $errors, 'job_id' => $job_id], 400);
	}
	$apply = lf_ai_studio_apply_payload_strict($payload, $job_id);
	update_post_meta($job_id, 'lf_ai_job_status', $apply['success'] ? 'done' : 'failed');
	if (!empty($apply['summary'])) {
		update_post_meta($job_id, 'lf_ai_job_summary', $apply['summary']);
	}
	if (!empty($apply['changes'])) {
		update_post_meta($job_id, 'lf_ai_job_changes', $apply['changes']);
	}
	if (!empty($apply['error'])) {
		update_post_meta($job_id, 'lf_ai_job_error', $apply['error']);
	}
	if (!empty($apply['success']) && function_exists('lf_ai_studio_run_content_audit')) {
		$report = lf_ai_studio_run_content_audit('rest_apply', is_array($payload) ? $payload : []);
		lf_ai_studio_store_audit_report($report, $job_id);
		lf_ai_studio_maybe_requeue_from_audit($job_id, $report);
	}
	return new \WP_REST_Response([
		'job_id' => $job_id,
		'success' => $apply['success'],
		'error' => $apply['error'] ?? '',
	], $apply['success'] ? 200 : 400);
}

function lf_ai_studio_rest_orchestrator(\WP_REST_Request $request): \WP_REST_Response {
	$payload = $request->get_json_params();
	if (!is_array($payload)) {
		$raw = (string) $request->get_body();
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$payload = $decoded;
			} elseif (is_string($decoded)) {
				$decoded_again = json_decode($decoded, true);
				if (is_array($decoded_again)) {
					$payload = $decoded_again;
				}
			}
		}
	}
	if (!is_array($payload)) {
		return new \WP_REST_Response(['error' => 'invalid_json'], 400);
	}
	if (function_exists('lf_ai_studio_error_log')) {
		$keys = is_array($payload) ? array_keys($payload) : [];
		lf_ai_studio_error_log('orchestrator_callback: payload received', 'INFO', [
			'route' => (string) $request->get_route(),
			'keys' => array_slice($keys, 0, 60),
			'job_id' => isset($payload['job_id']) ? absint($payload['job_id']) : 0,
			'request_id' => sanitize_text_field((string) ($payload['request_id'] ?? '')),
			'blueprints' => (isset($payload['blueprints']) && is_array($payload['blueprints'])) ? count($payload['blueprints']) : 0,
		]);
		update_option('lf_ai_studio_last_orchestrator_callback_payload', $payload, false);
		update_option('lf_ai_studio_last_orchestrator_callback_at', time(), false);
	}
	if (!empty($payload['research_document']) && is_array($payload['research_document'])) {
		$errors = lf_ai_studio_validate_research_document($payload['research_document']);
		if (empty($errors)) {
			update_option('lf_site_research_document', $payload['research_document'], false);
			delete_option('lf_ai_studio_research_errors');
		} else {
			update_option('lf_ai_studio_research_errors', $errors, false);
		}
	}
	if (isset($payload['payload']) && is_string($payload['payload'])) {
		$decoded_payload = json_decode($payload['payload'], true);
		if (is_array($decoded_payload)) {
			$payload = $decoded_payload;
		}
	}
	$job_id = isset($payload['job_id']) ? absint($payload['job_id']) : 0;
	$request_id = sanitize_text_field((string) ($payload['request_id'] ?? ''));
	$force_apply = function_exists('lf_ai_studio_orchestrator_force_apply_enabled')
		? lf_ai_studio_orchestrator_force_apply_enabled($payload)
		: false;
	$dry_run = get_option('lf_ai_autonomy_dry_run', '0') === '1';
	$diagnostics = [
		'force_apply' => $force_apply,
		'dry_run' => $dry_run,
		'idempotent' => false,
		'idempotent_would_have_been' => false,
		'errors' => [],
		'apply_counts' => [
			'homepage_updated' => false,
			'posts_updated' => 0,
			'faqs_updated' => 0,
			'service_meta_updated' => 0,
		],
	];
	$payload_hash = lf_ai_studio_rest_payload_hash($payload);
	$binding = lf_ai_studio_rest_validate_callback_binding($job_id, $request_id, $payload_hash);
	if (empty($binding['ok'])) {
		if (function_exists('lf_ai_studio_error_log')) {
			lf_ai_studio_error_log('orchestrator: invalid_binding', 'ERROR', [
				'job_id' => $job_id,
				'request_id' => $request_id,
				'error' => (string) ($binding['error'] ?? 'invalid_binding'),
				'status' => (int) ($binding['status'] ?? 400),
			]);
		}
		return new \WP_REST_Response(array_merge($diagnostics, [
			'error' => (string) ($binding['error'] ?? 'invalid_binding'),
		]), (int) ($binding['status'] ?? 400));
	}
	if (!empty($binding['idempotent'])) {
		if ($force_apply) {
			$diagnostics['idempotent'] = false;
			$diagnostics['idempotent_would_have_been'] = true;
		} else {
			$diagnostics['idempotent'] = true;
			return new \WP_REST_Response(array_merge($diagnostics, [
				'job_id' => $job_id,
				'success' => true,
				'idempotent' => true,
				'errors' => [],
			]), 200);
		}
	}
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
	update_post_meta($job_id, 'lf_ai_job_response', $payload);
	update_post_meta($job_id, 'lf_ai_job_request_id', $request_id);
	update_post_meta($job_id, 'lf_ai_job_last_callback_hash', $payload_hash);

	$apply_payload = $payload['apply'] ?? $payload;
	$stored_request = get_post_meta($job_id, 'lf_ai_job_request', true);
	$job_request = is_array($stored_request) ? $stored_request : [];
	$manifest = function_exists('lf_ai_studio_get_manifest') ? lf_ai_studio_get_manifest() : [];
	if (!is_array($manifest)) {
		$manifest = [];
	}
	$options = [
		'lf_business_name' => (string) get_option('lf_business_name', ''),
		'lf_city_region' => (string) get_option('lf_city_region', ''),
		'lf_homepage_city' => (string) get_option('lf_homepage_city', ''),
		'lf_homepage_niche_slug' => (string) get_option('lf_homepage_niche_slug', ''),
	];
	$business_expected = lf_ai_studio_identity_build_expected($job_request, $manifest, $options);
	$business_incoming = lf_ai_studio_identity_build_incoming($apply_payload, $payload);
	$business_decision = lf_ai_studio_identity_guard_decision($business_expected, $business_incoming, $job_id);
	$log_trimmed = static function ($value): string {
		$text = is_string($value) ? $value : wp_json_encode($value);
		$text = wp_strip_all_tags((string) $text);
		if (strlen($text) > 120) {
			$text = substr($text, 0, 120) . '...';
		}
		return $text;
	};
	if (empty($business_decision['allow'])) {
		error_log('LF ORCH DEBUG: business_expected ' . $log_trimmed($business_expected));
		error_log('LF ORCH DEBUG: business_incoming ' . $log_trimmed($business_incoming));
		error_log('LF ORCH DEBUG: business_match ' . $log_trimmed($business_decision['reason'] ?? ''));
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('LF ORCH DEBUG: business_expected_full ' . wp_json_encode($business_expected));
			error_log('LF ORCH DEBUG: business_incoming_full ' . wp_json_encode($business_incoming));
		}
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', 'business_identity_mismatch');
		update_post_meta($job_id, 'lf_ai_job_summary', __('Orchestrator callback blocked due to business identity mismatch.', 'leadsforward-core'));
		update_post_meta($job_id, 'lf_ai_job_changes', []);
		if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
			lf_ai_autonomy_mark_generation_failed($job_id, 'business_identity_mismatch');
		}
		$biz_resp = $business_decision['response'] ?? [];
		return new \WP_REST_Response(array_merge($diagnostics, is_array($biz_resp) ? $biz_resp : []), 200);
	}
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log('LF ORCH DEBUG: business_expected ' . $log_trimmed($business_expected));
		error_log('LF ORCH DEBUG: business_incoming ' . $log_trimmed($business_incoming));
		error_log('LF ORCH DEBUG: business_match ' . $log_trimmed($business_decision['reason'] ?? ''));
		error_log('LF ORCH DEBUG: business_expected_full ' . wp_json_encode($business_expected));
		error_log('LF ORCH DEBUG: business_incoming_full ' . wp_json_encode($business_incoming));
		if (($business_decision['reason'] ?? '') === 'no_comparable_fields') {
			error_log('LF ORCH DEBUG: business_match warning no_comparable_fields');
		}
	}
	if (defined('WP_DEBUG') && WP_DEBUG) {
		$updates = $apply_payload['updates'] ?? null;
		if (!is_array($updates)) {
			error_log('LF ORCH DEBUG: updates missing or not array');
		} else {
			$target_counts = [];
			$sample_updates = [];
			foreach ($updates as $update) {
				if (!is_array($update)) {
					continue;
				}
				$target = (string) ($update['target'] ?? 'unknown');
				$target_counts[$target] = ($target_counts[$target] ?? 0) + 1;
				if (count($sample_updates) < 5) {
					$fields = $update['fields'] ?? $update['data'] ?? [];
					$sample_updates[] = [
						'target' => $target,
						'id' => $update['id'] ?? '',
						'field_keys' => is_array($fields) ? array_slice(array_keys($fields), 0, 12) : [],
					];
				}
			}
			error_log('LF ORCH DEBUG: update_target_counts ' . wp_json_encode($target_counts));
			error_log('LF ORCH DEBUG: update_samples ' . wp_json_encode($sample_updates));
			if (isset($apply_payload['page_type_counts']) && is_array($apply_payload['page_type_counts'])) {
				error_log('LF ORCH DEBUG: page_type_counts ' . wp_json_encode($apply_payload['page_type_counts']));
			}
			if (isset($apply_payload['update_target_counts']) && is_array($apply_payload['update_target_counts'])) {
				error_log('LF ORCH DEBUG: merge_target_counts ' . wp_json_encode($apply_payload['update_target_counts']));
			}
		}
	}
	$quality_warnings = [];
	$candidate_warnings = $payload['quality_warnings'] ?? ($apply_payload['quality_warnings'] ?? []);
	if (is_array($candidate_warnings)) {
		foreach ($candidate_warnings as $warning) {
			$text = sanitize_text_field((string) $warning);
			if ($text !== '') {
				$quality_warnings[] = $text;
			}
		}
	}
	$media_annotations = $payload['media_annotations'] ?? $apply_payload['media_annotations'] ?? [];
	if (!is_array($media_annotations) || empty($media_annotations)) {
		$media_annotations = $payload['vision']['media_annotations'] ?? $payload['image_analysis']['media_annotations'] ?? [];
	}
	if ((!is_array($media_annotations) || empty($media_annotations)) && is_array($stored_request)) {
		$media_annotations = lf_ai_studio_rest_build_fallback_media_annotations($stored_request);
		if (!empty($media_annotations)) {
			$quality_warnings[] = __('No media_annotations returned from orchestrator; generated fallback annotations from available media candidates.', 'leadsforward-core');
		}
	}
	$strict_media_annotations = get_option('lf_ai_require_media_annotations', '0') === '1';
	$annotation_required = is_array($stored_request) && !empty($stored_request['media_annotation_required']);
	$annotation_min_expected = is_array($stored_request)
		? max(0, (int) ($stored_request['media_annotation_min_expected'] ?? 0))
		: 0;
	if ($strict_media_annotations && $annotation_required && $annotation_min_expected > 0) {
		$annotation_count = is_array($media_annotations) ? count($media_annotations) : 0;
		if ($annotation_count < $annotation_min_expected) {
			$missing_error = sprintf(
				/* translators: 1: minimum expected annotations, 2: actual count */
				__('Missing required media_annotations in callback (expected at least %1$d, got %2$d). Ensure n8n vision analysis is enabled and mapped to media_annotations.', 'leadsforward-core'),
				$annotation_min_expected,
				$annotation_count
			);
			update_post_meta($job_id, 'lf_ai_job_status', 'failed');
			update_post_meta($job_id, 'lf_ai_job_error', $missing_error);
			if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
				lf_ai_autonomy_mark_generation_failed($job_id, 'media_annotations_missing');
			}
			return new \WP_REST_Response(array_merge($diagnostics, [
				'error' => 'media_annotations_missing',
				'messages' => [$missing_error],
				'job_id' => $job_id,
			]), 400);
		}
	}
	if (is_array($media_annotations) && !empty($media_annotations) && function_exists('lf_image_intelligence_apply_vision_annotations')) {
		$vision_result = lf_image_intelligence_apply_vision_annotations($media_annotations);
		update_post_meta($job_id, 'lf_ai_job_media_annotations_applied', (int) ($vision_result['applied'] ?? 0));
		if ((int) ($vision_result['applied'] ?? 0) === 0) {
			$quality_warnings[] = __('Vision annotations were provided but none were applied. Check attachment_id mapping and field names.', 'leadsforward-core');
		}
	} else {
		$quality_warnings[] = __('No media_annotations returned from orchestrator; image metadata stayed on theme fallback mode.', 'leadsforward-core');
	}
	if (!empty($quality_warnings)) {
		$quality_warnings = array_values(array_unique(array_filter(array_map(static function ($w): string {
			return sanitize_text_field((string) $w);
		}, $quality_warnings))));
		update_post_meta($job_id, 'lf_ai_job_quality_warnings', $quality_warnings);
		update_option('lf_ai_studio_quality_warnings', $quality_warnings, false);
	}
	$errors = function_exists('lf_ai_studio_validate_payload')
		? lf_ai_studio_validate_payload($apply_payload)
		: [];
	if (!empty($errors)) {
		if (function_exists('lf_ai_studio_error_log')) {
			lf_ai_studio_error_log('orchestrator: validation_failed', 'ERROR', [
				'job_id' => $job_id,
				'request_id' => $request_id,
				'errors' => array_values($errors),
			]);
		}
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', implode('; ', $errors));
		if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
			lf_ai_autonomy_mark_generation_failed($job_id, 'validation_failed');
		}
		return new \WP_REST_Response(array_merge($diagnostics, [
			'error' => 'validation_failed',
			'messages' => $errors,
			'job_id' => $job_id,
		]), 400);
	}

	// Optional n8n split: validate full payload once, then apply only homepage or only interior updates.
	if (function_exists('lf_ai_studio_orchestrator_filter_updates_for_scope')) {
		$scope_pack = lf_ai_studio_orchestrator_filter_updates_for_scope($apply_payload, $payload);
		$apply_payload = $scope_pack['payload'];
		if ($scope_pack['scope'] !== 'full') {
			$diagnostics['apply_scope'] = $scope_pack['scope'];
			$diagnostics['updates_filtered_from'] = $scope_pack['filtered_from'];
			$diagnostics['updates_filtered_to'] = $scope_pack['filtered_to'];
			if (function_exists('lf_ai_studio_orchestrator_resolve_run_phase')) {
				$rp = lf_ai_studio_orchestrator_resolve_run_phase($apply_payload, $payload);
				if ($rp === 'repair') {
					$had_explicit_scope = isset($apply_payload['apply_scope']) || isset($payload['apply_scope']);
					if (!$had_explicit_scope && function_exists('lf_ai_studio_repair_interior_only_enabled') && lf_ai_studio_repair_interior_only_enabled()) {
						$diagnostics['repair_interior_only_default'] = true;
					}
				}
			}
		}
		if (
			$scope_pack['scope'] !== 'full'
			&& $scope_pack['filtered_from'] > 0
			&& $scope_pack['filtered_to'] === 0
		) {
			$scope_err = sprintf(
				/* translators: 1: apply_scope value, 2: number of updates before filter */
				__('apply_scope "%1$s" matched no updates (payload had %2$d update items). Use "homepage", "interior", or omit for full apply.', 'leadsforward-core'),
				$scope_pack['scope'],
				$scope_pack['filtered_from']
			);
			if (function_exists('lf_ai_studio_error_log')) {
				lf_ai_studio_error_log('orchestrator: apply_scope_empty', 'ERROR', [
					'job_id' => $job_id,
					'request_id' => $request_id,
					'apply_scope' => (string) ($scope_pack['scope'] ?? ''),
					'filtered_from' => (int) ($scope_pack['filtered_from'] ?? 0),
				]);
			}
			update_post_meta($job_id, 'lf_ai_job_status', 'failed');
			update_post_meta($job_id, 'lf_ai_job_error', $scope_err);
			if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
				lf_ai_autonomy_mark_generation_failed($job_id, 'apply_scope_empty');
			}
			return new \WP_REST_Response(array_merge($diagnostics, [
				'error' => 'apply_scope_empty',
				'messages' => [$scope_err],
				'job_id' => $job_id,
			]), 400);
		}
	}
	if (defined('WP_DEBUG') && WP_DEBUG && $dry_run) {
		if ($force_apply) {
			error_log('LF ORCH DEBUG: dry_run enabled but force_apply; continuing apply for job ' . $job_id);
		} else {
			error_log('LF ORCH DEBUG: dry_run enabled; skipping apply for job ' . $job_id);
		}
	}
	if ($dry_run && !$force_apply) {
		update_post_meta($job_id, 'lf_ai_job_status', 'done');
		update_post_meta($job_id, 'lf_ai_job_summary', 'Dry-run validation succeeded; no writes committed.');
		if (function_exists('lf_ai_autonomy_mark_generation_success')) {
			lf_ai_autonomy_mark_generation_success($job_id, ['dry_run' => true, 'request_id' => $request_id]);
		}
		return new \WP_REST_Response(array_merge($diagnostics, [
			'job_id' => $job_id,
			'success' => true,
			'dry_run' => true,
		]), 200);
	}

	// Orchestrator failure callbacks (n8n): ok:false / success:false / error string with zero updates.
	// Return HTTP 200 so webhooks succeed while the job is marked failed in WordPress (400 breaks n8n HTTP Request).
	$updates_for_ack = $apply_payload['updates'] ?? [];
	$updates_empty_for_ack = !is_array($updates_for_ack) || count($updates_for_ack) === 0;
	$orchestrator_reported_failure = (
		(array_key_exists('ok', $apply_payload) && $apply_payload['ok'] === false)
		|| (array_key_exists('success', $apply_payload) && $apply_payload['success'] === false)
		|| (isset($apply_payload['error']) && is_string($apply_payload['error']) && trim($apply_payload['error']) !== '')
	);
	if ($updates_empty_for_ack && $orchestrator_reported_failure) {
		$failure_parts = [];
		if (isset($apply_payload['error']) && is_string($apply_payload['error']) && trim($apply_payload['error']) !== '') {
			$failure_parts[] = sanitize_text_field((string) $apply_payload['error']);
		}
		if (!empty($quality_warnings) && is_array($quality_warnings)) {
			foreach ($quality_warnings as $w) {
				$t = sanitize_text_field((string) $w);
				if ($t !== '') {
					$failure_parts[] = $t;
				}
			}
		}
		$failure_parts = array_values(array_unique($failure_parts));
		$failure_message = !empty($failure_parts)
			? implode('; ', $failure_parts)
			: __('Orchestrator returned no updates.', 'leadsforward-core');

		if (function_exists('lf_ai_studio_error_log')) {
			lf_ai_studio_error_log('orchestrator: no_updates_reported_failure', 'ERROR', [
				'job_id' => $job_id,
				'request_id' => $request_id,
				'message' => $failure_message,
			]);
		}
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', $failure_message);
		update_post_meta($job_id, 'lf_ai_job_summary', __('No updates applied (orchestrator reported failure).', 'leadsforward-core'));
		update_post_meta($job_id, 'lf_ai_job_changes', []);
		if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
			lf_ai_autonomy_mark_generation_failed($job_id, 'orchestrator_no_updates');
		}

		return new \WP_REST_Response(array_merge($diagnostics, [
			'job_id' => $job_id,
			'success' => false,
			'error' => [$failure_message],
			'errors' => [$failure_message],
			'acknowledged' => true,
		]), 200);
	}

	$apply_result = lf_apply_orchestrator_updates($apply_payload, ['force_apply' => $force_apply]);
	if (function_exists('lf_ai_studio_error_log')) {
		lf_ai_studio_error_log('orchestrator: apply_result', !empty($apply_result['success']) ? 'INFO' : 'ERROR', [
			'job_id' => $job_id,
			'request_id' => $request_id,
			'success' => !empty($apply_result['success']),
			'error_count' => is_array($apply_result['errors'] ?? null) ? count($apply_result['errors']) : 0,
		]);
	}
	update_post_meta($job_id, 'lf_ai_job_status', $apply_result['success'] ? 'done' : 'failed');
	update_post_meta($job_id, 'lf_ai_job_summary', $apply_result['summary'] ?? '');
	update_post_meta($job_id, 'lf_ai_job_changes', $apply_result['changes'] ?? []);
	update_post_meta($job_id, 'lf_ai_job_error', !empty($apply_result['errors']) ? implode('; ', $apply_result['errors']) : '');
	if (empty($apply_result['success']) && function_exists('lf_ai_autonomy_mark_generation_failed')) {
		lf_ai_autonomy_mark_generation_failed($job_id, 'apply_failed');
	}

	if (!empty($apply_result['success'])) {
		$request = get_post_meta($job_id, 'lf_ai_job_request', true);
		if (is_array($request)) {
			lf_ai_studio_seed_dummy_posts((string) ($request['business_name'] ?? ''));
			$scope = is_array($request['generation_scope'] ?? null) ? $request['generation_scope'] : [];
			$should_seed_projects = array_key_exists('projects', $scope)
				? !empty($scope['projects'])
				: (get_option('lf_ai_gen_projects', '1') === '1');
			if ($should_seed_projects) {
				lf_ai_studio_seed_sample_projects();
			}
		}
		if (function_exists('lf_ai_studio_run_content_audit')) {
			// Gate audits should evaluate only what this callback actually changed.
			// This prevents smoke tests (e.g. homepage + 1 service + 1 area) from failing due to unrelated pages
			// that may be present in the request blueprint list.
			$audit_request = is_array($request) ? $request : [];
			$audit_post_ids = [];
			$audit_include_homepage = false;
			if (!empty($apply_payload['updates']) && is_array($apply_payload['updates'])) {
				foreach ($apply_payload['updates'] as $u) {
					if (!is_array($u)) {
						continue;
					}
					$target = (string) ($u['target'] ?? '');
					$id = (string) ($u['id'] ?? '');
					if ($target === 'options' && $id === 'homepage') {
						$audit_include_homepage = true;
						continue;
					}
					if ($target === 'post_meta') {
						$post_id = absint($id);
						if ($post_id > 0) {
							$audit_post_ids[] = $post_id;
						}
					}
				}
			}
			$audit_post_ids = array_values(array_unique(array_filter($audit_post_ids)));
			if ($audit_include_homepage || !empty($audit_post_ids)) {
				$audit_request['blueprints'] = [];
				if ($audit_include_homepage) {
					$audit_request['blueprints'][] = ['page' => 'homepage'];
				}
				foreach ($audit_post_ids as $pid) {
					$audit_request['blueprints'][] = ['post_id' => (int) $pid];
				}
			}
			$report = lf_ai_studio_run_content_audit('orchestrator', $audit_request);
			if (!empty($quality_warnings)) {
				$report['quality_warnings'] = $quality_warnings;
			}
			lf_ai_studio_store_audit_report($report, $job_id);
			$gate_errors = [];
			$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
			$missing_fields = (int) ($summary['missing_fields'] ?? 0);
			$uniq_dupes = (int) ($summary['uniqueness_duplicates'] ?? 0);

			// Gate policy: keep audits visible, but don't fail a run for optional/quality-only items by default.
			// Failures should be reserved for hard correctness issues (e.g. missing required sections),
			// otherwise the system appears "broken" even when most content applied successfully.
			$gate_missing_threshold = (int) apply_filters('lf_ai_studio_gate_missing_fields_threshold', 50);
			$gate_enforce_uniqueness = (bool) apply_filters('lf_ai_studio_gate_enforce_cross_page_uniqueness', false);

			if ($missing_fields > $gate_missing_threshold) {
				$gate_errors[] = sprintf(__('Content audit: %d missing/default fields detected.', 'leadsforward-core'), $missing_fields);
			}
			if ($gate_enforce_uniqueness && $uniq_dupes > 0) {
				$gate_errors[] = sprintf(__('Content audit: %d cross-page uniqueness duplicates detected.', 'leadsforward-core'), $uniq_dupes);
			}

			// Always log quality warnings (even when not gating).
			if (($missing_fields > 0 || $uniq_dupes > 0) && function_exists('lf_ai_studio_error_log')) {
				lf_ai_studio_error_log('orchestrator: post_apply_quality_warnings', 'INFO', [
					'job_id' => $job_id,
					'request_id' => $request_id,
					'missing_fields' => $missing_fields,
					'uniqueness_duplicates' => $uniq_dupes,
					'gate_missing_threshold' => $gate_missing_threshold,
					'gate_enforce_uniqueness' => $gate_enforce_uniqueness,
				]);
			}

			if (!empty($gate_errors)) {
				// Include a small sample of issues to speed debugging (scoped audits are usually small).
				$issue_samples = [];
				$pages = is_array($report['pages'] ?? null) ? $report['pages'] : [];
				foreach ($pages as $page) {
					if (!is_array($page)) {
						continue;
					}
					$pid = isset($page['id']) ? absint($page['id']) : 0;
					$slug = (string) ($page['slug'] ?? '');
					$issues = is_array($page['issues'] ?? null) ? $page['issues'] : [];
					foreach ($issues as $issue) {
						if (!is_array($issue)) {
							continue;
						}
						$issue_samples[] = [
							'id' => $pid,
							'slug' => $slug,
							'section' => (string) ($issue['section'] ?? ''),
							'field' => (string) ($issue['field'] ?? ''),
							'reason' => (string) ($issue['reason'] ?? ''),
						];
						if (count($issue_samples) >= 12) {
							break 2;
						}
					}
				}
				if (function_exists('lf_ai_studio_error_log')) {
					lf_ai_studio_error_log('orchestrator: post_apply_gate_failed', 'ERROR', [
						'job_id' => $job_id,
						'request_id' => $request_id,
						'missing_fields' => $missing_fields,
						'uniqueness_duplicates' => $uniq_dupes,
						'errors' => $gate_errors,
						'issue_samples' => $issue_samples,
					]);
				}
				update_post_meta($job_id, 'lf_ai_job_status', 'failed');
				update_post_meta($job_id, 'lf_ai_job_error', implode('; ', $gate_errors));
				if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
					lf_ai_autonomy_mark_generation_failed($job_id, 'post_apply_gate_failed');
				}
				$apply_result['success'] = false;
				$apply_result['errors'] = array_merge((array) ($apply_result['errors'] ?? []), $gate_errors);
				$apply_result['summary'] = __('Applied updates, but post-apply validation failed.', 'leadsforward-core');
			} else {
				if (function_exists('lf_ai_autonomy_mark_generation_success')) {
					lf_ai_autonomy_mark_generation_success($job_id, $report);
				}
			}
			lf_ai_studio_maybe_requeue_from_audit($job_id, $report);
		}
	}

	$diagnostics['apply_counts'] = function_exists('lf_ai_studio_orchestrator_build_apply_counts')
		? lf_ai_studio_orchestrator_build_apply_counts($apply_payload, $apply_result)
		: $diagnostics['apply_counts'];
	$apply_errors = $apply_result['errors'] ?? [];
	$diagnostics['errors'] = is_array($apply_errors) ? $apply_errors : [];

	// Always return 200 so orchestrator callbacks don't hard-fail workflow runners (n8n, etc).
	// The job is still marked failed in post meta when gating fails.
	return new \WP_REST_Response(array_merge($diagnostics, [
		'job_id' => $job_id,
		'success' => (bool) $apply_result['success'],
		'error' => $apply_errors,
		'errors' => $apply_errors,
	]), 200);
}

function lf_ai_studio_rest_candidate_score(array $target, array $candidate): int {
	$haystack = strtolower(trim(
		(string) ($candidate['filename'] ?? '') . ' ' .
		(string) ($candidate['title'] ?? '') . ' ' .
		(string) ($candidate['alt'] ?? '') . ' ' .
		(string) ($candidate['caption'] ?? '')
	));
	if ($haystack === '') {
		return 0;
	}
	$score = 0;
	$slot = strtolower((string) ($target['slot'] ?? ''));
	$section_type = strtolower((string) ($target['section_type'] ?? ''));
	$target_type = strtolower((string) ($target['target'] ?? ''));
	if ($slot !== '' && strpos($haystack, $slot) !== false) {
		$score += 5;
	}
	if ($section_type !== '' && strpos($haystack, $section_type) !== false) {
		$score += 4;
	}
	if ($target_type !== '' && strpos($haystack, $target_type) !== false) {
		$score += 2;
	}
	$context = is_array($target['context'] ?? null) ? $target['context'] : [];
	foreach (['city', 'niche', 'service_slug', 'area_slug', 'page_type'] as $key) {
		$term = strtolower(trim((string) ($context[$key] ?? '')));
		if ($term !== '' && strpos($haystack, $term) !== false) {
			$score += 2;
		}
	}
	return $score;
}

function lf_ai_studio_rest_pick_best_candidate(array $target, array $candidates, array $used_ids): int {
	$best_id = 0;
	$best_score = -1;
	foreach ($candidates as $candidate) {
		if (!is_array($candidate)) {
			continue;
		}
		$id = (int) ($candidate['attachment_id'] ?? 0);
		if ($id <= 0 || isset($used_ids[$id])) {
			continue;
		}
		$score = lf_ai_studio_rest_candidate_score($target, $candidate);
		if ($score > $best_score) {
			$best_score = $score;
			$best_id = $id;
		}
	}
	if ($best_id > 0) {
		return $best_id;
	}
	foreach ($candidates as $candidate) {
		$id = (int) (is_array($candidate) ? ($candidate['attachment_id'] ?? 0) : 0);
		if ($id > 0 && !isset($used_ids[$id])) {
			return $id;
		}
	}
	return 0;
}

function lf_ai_studio_rest_find_media_candidate_row(array $candidates, int $attachment_id): ?array {
	foreach ($candidates as $candidate) {
		if (!is_array($candidate)) {
			continue;
		}
		if ((int) ($candidate['attachment_id'] ?? 0) === $attachment_id) {
			return $candidate;
		}
	}
	return null;
}

/**
 * Business/geo context from the stored orchestrator request (no full manifest on the job).
 *
 * @return array{business:string,city:string,state:string,niche:string,primary:string}
 */
function lf_ai_studio_rest_stored_request_geo_context(array $stored_request): array {
	$entity = is_array($stored_request['business_entity'] ?? null) ? $stored_request['business_entity'] : [];
	$parts = is_array($entity['address_parts'] ?? null) ? $entity['address_parts'] : [];
	$city = trim((string) ($stored_request['city_region'] ?? ($parts['city'] ?? '')));
	$state = trim((string) ($parts['state'] ?? ''));
	$business = trim((string) ($stored_request['business_name'] ?? ($entity['name'] ?? '')));
	$niche = trim((string) ($stored_request['niche'] ?? ($entity['niche'] ?? '')));
	$kw = is_array($stored_request['keywords'] ?? null) ? $stored_request['keywords'] : [];
	$primary = trim((string) ($kw['primary'] ?? ''));
	return [
		'business' => sanitize_text_field($business),
		'city' => sanitize_text_field($city),
		'state' => sanitize_text_field($state),
		'niche' => sanitize_text_field($niche),
		'primary' => sanitize_text_field($primary),
	];
}

function lf_ai_studio_rest_build_fallback_media_annotations(array $stored_request): array {
	$plan = is_array($stored_request['image_generation'] ?? null) ? $stored_request['image_generation'] : [];
	$targets = is_array($plan['targets'] ?? null) ? $plan['targets'] : [];
	$candidates = is_array($stored_request['media_library_candidates'] ?? null) ? $stored_request['media_library_candidates'] : [];
	if (empty($targets) || empty($candidates)) {
		return [];
	}
	$geo = lf_ai_studio_rest_stored_request_geo_context($stored_request);
	$used_ids = [];
	$out = [];
	foreach ($targets as $target) {
		if (!is_array($target)) {
			continue;
		}
		$attachment_id = lf_ai_studio_rest_pick_best_candidate($target, $candidates, $used_ids);
		if ($attachment_id <= 0) {
			continue;
		}
		$used_ids[$attachment_id] = true;
		$candidate = lf_ai_studio_rest_find_media_candidate_row($candidates, $attachment_id);
		$tctx = is_array($target['context'] ?? null) ? $target['context'] : [];
		$city = sanitize_text_field((string) ($tctx['city'] ?? $geo['city']));
		$state = sanitize_text_field((string) ($geo['state'] ?? ''));
		$label = '';
		if (is_array($candidate) && function_exists('lf_image_intelligence_humanize_upload_stem')) {
			$from_file = lf_image_intelligence_humanize_upload_stem((string) ($candidate['filename'] ?? ''));
			if (strlen($from_file) >= 4) {
				$label = $from_file;
			}
			$ct = trim((string) ($candidate['title'] ?? ''));
			if (
				($label === '' || strlen($label) < 6)
				&& $ct !== ''
				&& function_exists('lf_image_intelligence_media_text_needs_upgrade')
				&& !lf_image_intelligence_media_text_needs_upgrade($ct)
			) {
				$label = function_exists('lf_image_intelligence_clean_media_text')
					? lf_image_intelligence_clean_media_text($ct)
					: $ct;
			}
		}
		if ($label === '') {
			$label = $geo['primary'] !== '' ? $geo['primary'] : ($geo['niche'] !== '' ? $geo['niche'] : ($geo['business'] !== '' ? $geo['business'] : __('Local service project', 'leadsforward-core')));
		}
		$headline = function_exists('lf_image_intelligence_format_image_headline')
			? lf_image_intelligence_format_image_headline($label, $city, $state)
			: $label;
		if ($headline === '') {
			$headline = $label;
		}
		$keyword_for_desc = $geo['primary'] !== '' ? $geo['primary'] : $geo['niche'];
		$description = function_exists('lf_image_intelligence_build_image_seo_description')
			? lf_image_intelligence_build_image_seo_description($label, $city, $state, $geo['business'], $keyword_for_desc)
			: $headline;
		$slug_base = sanitize_title($label . ($city !== '' ? '-' . $city : ''));
		$out[] = [
			'attachment_id' => $attachment_id,
			'title' => $headline,
			'alt_text' => $headline,
			'caption' => '',
			'description' => $description,
			'keywords' => array_values(array_filter([$label, $city, $state, $geo['niche'], $geo['primary']])),
			'recommended_filename' => ($slug_base !== '' ? $slug_base : 'project-image') . '.jpg',
		];
	}
	return $out;
}

function lf_ai_studio_rest_progress(\WP_REST_Request $request): \WP_REST_Response {
	$payload = $request->get_json_params();
	if (!is_array($payload)) {
		$raw = (string) $request->get_body();
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$payload = $decoded;
			}
		}
	}
	if (!is_array($payload)) {
		return new \WP_REST_Response(['error' => 'invalid_json'], 400);
	}
	$job_id = isset($payload['job_id']) ? absint($payload['job_id']) : 0;
	if (!$job_id) {
		return new \WP_REST_Response(['error' => 'missing_job_id'], 400);
	}
	$request_id = sanitize_text_field((string) ($payload['request_id'] ?? ''));
	if ($request_id === '') {
		return new \WP_REST_Response(['error' => 'missing_request_id'], 400);
	}
	$job = get_post($job_id);
	if (!$job instanceof \WP_Post || $job->post_type !== LF_AI_STUDIO_JOB_CPT) {
		return new \WP_REST_Response(['error' => 'invalid_job'], 404);
	}
	$stored_request_id = (string) get_post_meta($job_id, 'lf_ai_job_request_id', true);
	if ($stored_request_id !== '' && !hash_equals($stored_request_id, $request_id)) {
		return new \WP_REST_Response(['error' => 'request_id_mismatch'], 409);
	}
	$current_status = (string) get_post_meta($job_id, 'lf_ai_job_status', true);
	if (!in_array($current_status, ['done', 'failed'], true)) {
		$status = sanitize_text_field((string) ($payload['status'] ?? 'running'));
		if ($status !== '') {
			update_post_meta($job_id, 'lf_ai_job_status', $status);
		}
	}
	$percent = isset($payload['percent']) ? (float) $payload['percent'] : 0;
	$percent = max(0, min(100, $percent));
	$step = sanitize_text_field((string) ($payload['step'] ?? ''));
	$message = sanitize_text_field((string) ($payload['message'] ?? ''));
	$progress = [
		'percent' => $percent,
		'step' => $step,
		'message' => $message,
		'updated' => time(),
	];
	update_post_meta($job_id, 'lf_ai_job_progress', $progress);
	return new \WP_REST_Response(['ok' => true], 200);
}

function lf_ai_studio_rest_airtable_webhook(\WP_REST_Request $request): \WP_REST_Response {
	$payload = $request->get_json_params();
	if (!is_array($payload)) {
		$raw = (string) $request->get_body();
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$payload = $decoded;
			}
		}
	}
	if (!is_array($payload)) {
		return new \WP_REST_Response(['error' => 'invalid_json'], 400);
	}
	$record_id = sanitize_text_field((string) ($payload['record_id'] ?? ''));
	$updated_at = sanitize_text_field((string) ($payload['updated_at'] ?? ''));
	$source = sanitize_text_field((string) ($payload['source'] ?? 'airtable_webhook'));
	if ($record_id === '' && function_exists('lf_ai_studio_airtable_get_stored_record_id')) {
		$record_id = lf_ai_studio_airtable_get_stored_record_id();
	}
	if ($record_id === '') {
		return new \WP_REST_Response(['error' => 'missing_record_id'], 400);
	}
	if (!function_exists('lf_ai_studio_airtable_enqueue_generation_run')) {
		return new \WP_REST_Response(['error' => 'airtable_queue_unavailable'], 500);
	}
	$enqueue = lf_ai_studio_airtable_enqueue_generation_run($record_id, $updated_at, $source);
	if (empty($enqueue['ok'])) {
		return new \WP_REST_Response(['error' => (string) ($enqueue['error'] ?? 'enqueue_failed')], 400);
	}
	return new \WP_REST_Response([
		'ok' => true,
		'queued' => !empty($enqueue['queued']),
		'queue_size' => (int) ($enqueue['queue_size'] ?? 0),
	], 200);
}

function lf_ai_studio_validate_apply_payload(array $payload): array {
	$errors = [];
	if (!isset($payload['homepage']) && !isset($payload['posts'])) {
		$errors[] = 'Payload must include homepage or posts.';
	}
	if (isset($payload['homepage']) && !is_array($payload['homepage'])) {
		$errors[] = 'Homepage payload must be an object.';
	}
	if (isset($payload['posts']) && !is_array($payload['posts'])) {
		$errors[] = 'Posts payload must be an array.';
	}
	if (!empty($payload['posts']) && is_array($payload['posts'])) {
		foreach ($payload['posts'] as $index => $post_payload) {
			if (!is_array($post_payload)) {
				$errors[] = 'Post payload at index ' . $index . ' must be an object.';
				continue;
			}
			if (empty($post_payload['id'])) {
				$errors[] = 'Post payload at index ' . $index . ' missing id.';
			}
		}
	}
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if (!empty($payload['homepage']) && function_exists('lf_get_homepage_section_config')) {
		$current = lf_get_homepage_section_config();
		if (isset($payload['homepage']['template_type'])) {
			$errors[] = 'Homepage template_type cannot be changed.';
		}
		if (isset($payload['homepage']['section_order'])) {
			$errors[] = 'Homepage section_order cannot be changed.';
		}
		$incoming_homepage = $payload['homepage']['config'] ?? $payload['homepage'];
		if (!is_array($incoming_homepage)) {
			$errors[] = 'Homepage payload must include a config object.';
		}
		foreach ((array) $incoming_homepage as $type => $settings) {
			if (in_array($type, ['id', 'template_type', 'section_order', 'sections', 'field_schema'], true)) {
				continue;
			}
			if (!isset($current[$type])) {
				$errors[] = 'Homepage includes unknown section type: ' . $type;
			}
			if (!isset($registry[$type])) {
				$errors[] = 'Section type not in registry: ' . $type;
			}
			if (!is_array($settings)) {
				$errors[] = 'Homepage section settings must be an object: ' . $type;
			}
			if (isset($registry[$type])) {
				$allowed = lf_ai_studio_allowed_keys_for_type($registry[$type]);
				foreach ($settings as $key => $value) {
					if (!in_array($key, $allowed, true)) {
						$errors[] = 'Homepage section key not allowed: ' . $type . '.' . $key;
					}
				}
			}
		}
	}
	if (!empty($payload['posts']) && is_array($payload['posts'])) {
		foreach ($payload['posts'] as $index => $post_payload) {
			if (!is_array($post_payload) || empty($post_payload['id'])) {
				continue;
			}
			$post_id = absint($post_payload['id']);
			$post = get_post($post_id);
			if (!$post instanceof \WP_Post) {
				$errors[] = 'Post not found for id: ' . $post_id;
				continue;
			}
			$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
			if ($context === '') {
				$errors[] = 'Post not eligible for builder: ' . $post_id;
				continue;
			}
			if (isset($post_payload['slug'])) {
				$errors[] = 'Post slug cannot be changed: ' . $post_id;
			}
			if (isset($post_payload['template_type'])) {
				$errors[] = 'Post template_type cannot be changed: ' . $post_id;
			}
			if (isset($post_payload['section_order'])) {
				$errors[] = 'Post section_order cannot be changed: ' . $post_id;
			}
			$config = lf_pb_get_post_config($post_id, $context);
			$current_sections = $config['sections'] ?? [];
			$incoming_sections = $post_payload['sections'] ?? $post_payload['config']['sections'] ?? [];
			if (!is_array($incoming_sections)) {
				continue;
			}
			foreach ($incoming_sections as $instance_id => $incoming) {
				if (!isset($current_sections[$instance_id])) {
					$errors[] = 'Unknown section instance: ' . $instance_id . ' for post ' . $post_id;
					continue;
				}
				$type = $current_sections[$instance_id]['type'] ?? '';
				if ($type === '' || !isset($registry[$type])) {
					$errors[] = 'Invalid section type for instance: ' . $instance_id . ' on post ' . $post_id;
				}
				if (!is_array($incoming)) {
					$errors[] = 'Section settings must be an object: ' . $instance_id . ' on post ' . $post_id;
				}
				$settings = $incoming['settings'] ?? $incoming;
				if (isset($registry[$type]) && is_array($settings)) {
					$allowed = lf_ai_studio_allowed_keys_for_type($registry[$type]);
					foreach ($settings as $key => $value) {
						if (!in_array($key, $allowed, true)) {
							$errors[] = 'Section key not allowed: ' . $type . '.' . $key . ' on post ' . $post_id;
						}
					}
				}
			}
		}
	}
	return $errors;
}

function lf_ai_studio_allowed_keys_for_type(array $section): array {
	$fields = $section['fields'] ?? [];
	$keys = [];
	foreach ($fields as $field) {
		$key = $field['key'] ?? '';
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return $keys;
}

function lf_ai_studio_apply_payload_strict(array $payload, int $job_id): array {
	$changes = ['homepage' => false, 'posts' => []];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if (!empty($payload['homepage']) && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		$incoming = $payload['homepage']['config'] ?? $payload['homepage'];
		if (is_array($incoming)) {
			foreach ($config as $type => $settings) {
				if (!isset($incoming[$type]) || !is_array($incoming[$type])) {
					continue;
				}
				if (!isset($registry[$type])) {
					continue;
				}
				$config[$type] = array_merge($settings, lf_sections_sanitize_settings($type, $incoming[$type]));
			}
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			$changes['homepage'] = true;
		}
	}
	if (!empty($payload['posts']) && is_array($payload['posts'])) {
		foreach ($payload['posts'] as $post_payload) {
			if (!is_array($post_payload)) {
				continue;
			}
			$post_id = isset($post_payload['id']) ? absint($post_payload['id']) : 0;
			if (!$post_id) {
				continue;
			}
			$post = get_post($post_id);
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
			if ($context === '') {
				continue;
			}
			$config = lf_pb_get_post_config($post_id, $context);
			$sections = $config['sections'] ?? [];
			$incoming_sections = $post_payload['sections'] ?? $post_payload['config']['sections'] ?? [];
			if (is_array($incoming_sections)) {
				foreach ($sections as $instance_id => $section) {
					$type = $section['type'] ?? '';
					if ($type === '' || !isset($registry[$type])) {
						continue;
					}
					if (!isset($incoming_sections[$instance_id]) || !is_array($incoming_sections[$instance_id])) {
						continue;
					}
					$settings = $incoming_sections[$instance_id]['settings'] ?? $incoming_sections[$instance_id];
					if (!is_array($settings)) {
						continue;
					}
					$sections[$instance_id]['settings'] = array_merge(
						$section['settings'] ?? [],
						lf_sections_sanitize_settings($type, $settings)
					);
				}
			}
			$seo = $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false];
			if (isset($post_payload['seo']) && is_array($post_payload['seo'])) {
				$seo = array_merge($seo, [
					'title' => sanitize_text_field((string) ($post_payload['seo']['title'] ?? '')),
					'description' => sanitize_textarea_field((string) ($post_payload['seo']['description'] ?? '')),
					'noindex' => !empty($post_payload['seo']['noindex']),
				]);
			}
			update_post_meta($post_id, LF_PB_META_KEY, ['order' => $config['order'] ?? [], 'sections' => $sections, 'seo' => $seo]);
			$changes['posts'][] = $post_id;
		}
	}
	$summary_parts = [];
	if ($changes['homepage']) {
		$summary_parts[] = __('Homepage updated', 'leadsforward-core');
	}
	if (!empty($changes['posts'])) {
		$summary_parts[] = sprintf(__('Posts updated: %d', 'leadsforward-core'), count($changes['posts']));
	}
	return [
		'success' => true,
		'summary' => implode('; ', $summary_parts),
		'changes' => $changes,
	];
}
