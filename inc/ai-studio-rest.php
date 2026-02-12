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
}

function lf_ai_studio_rest_auth(\WP_REST_Request $request) {
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if ($secret === '') {
		return new \WP_Error('lf_ai_auth_missing', 'Shared secret is not configured.', ['status' => 401]);
	}
	$auth = (string) $request->get_header('authorization');
	$token = '';
	if ($auth === '') {
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

function lf_ai_studio_rest_blueprint(\WP_REST_Request $request): \WP_REST_Response {
	$payload = lf_ai_studio_build_blueprint_rest();
	return new \WP_REST_Response($payload, 200);
}

function lf_ai_studio_build_blueprint_rest(): array {
	$site_url = home_url('/');
	$site_name = get_bloginfo('name');
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$niche_option = defined('LF_HOMEPAGE_NICHE_OPTION') ? LF_HOMEPAGE_NICHE_OPTION : 'lf_homepage_niche_slug';
	$niche = (string) get_option($niche_option, 'general');
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
		'services_hub' => 'our-services',
		'service_areas_hub' => 'our-service-areas',
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
	if (!is_array($payload)) {
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
		$job_id = lf_ai_studio_create_job(['source' => 'orchestrator_callback']);
	}
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
	update_post_meta($job_id, 'lf_ai_job_response', $payload);
	if (!empty($payload['request_id'])) {
		update_post_meta($job_id, 'lf_ai_job_request_id', sanitize_text_field((string) $payload['request_id']));
	}

	$apply_payload = $payload['apply'] ?? $payload;
	$errors = function_exists('lf_ai_studio_validate_payload')
		? lf_ai_studio_validate_payload($apply_payload)
		: [];
	if (!empty($errors)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', implode('; ', $errors));
		return new \WP_REST_Response(['error' => 'validation_failed', 'messages' => $errors, 'job_id' => $job_id], 400);
	}

	$apply_result = lf_apply_orchestrator_updates($apply_payload);
	update_post_meta($job_id, 'lf_ai_job_status', $apply_result['success'] ? 'done' : 'failed');
	update_post_meta($job_id, 'lf_ai_job_summary', $apply_result['summary'] ?? '');
	update_post_meta($job_id, 'lf_ai_job_changes', $apply_result['changes'] ?? []);
	update_post_meta($job_id, 'lf_ai_job_error', !empty($apply_result['errors']) ? implode('; ', $apply_result['errors']) : '');

	if (!empty($apply_result['success'])) {
		$request = get_post_meta($job_id, 'lf_ai_job_request', true);
		if (is_array($request)) {
			lf_ai_studio_seed_dummy_posts((string) ($request['business_name'] ?? ''));
		}
	}

	return new \WP_REST_Response([
		'job_id' => $job_id,
		'success' => $apply_result['success'],
		'error' => $apply_result['errors'] ?? [],
	], $apply_result['success'] ? 200 : 400);
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
