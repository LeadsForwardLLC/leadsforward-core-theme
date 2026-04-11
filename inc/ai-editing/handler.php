<?php
/**
 * AI edit handler: generate (call AI, validate), apply (write + log), rollback.
 * AI is invoked via filter; no API key in theme.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_AI_PROPOSED_TRANSIENT_PREFIX = 'lf_ai_proposed_';

/**
 * Map known editable fields to frontend inline selector targets.
 *
 * @return string[]
 */
function lf_ai_inline_selectors_for_field(string $field_key): array {
	if ($field_key === 'hero_headline') {
		return [
			'main > article:nth-of-type(1) > section:nth-of-type(1) h1:nth-of-type(1)',
			'main > article:nth-of-type(1) > section:nth-of-type(1) h2:nth-of-type(1)',
			'main .lf-hero-basic__title',
			'main .lf-hero-stack__title',
			'main .lf-hero-form__title',
			'main .lf-hero-visual__title',
			'main .lf-hero-split__title',
		];
	}
	if ($field_key === 'hero_subheadline') {
		return [
			'main .lf-hero-basic__subtitle',
			'main .lf-hero-stack__subtitle',
			'main .lf-hero-form__subtitle',
			'main .lf-hero-visual__subtitle',
			'main .lf-hero-split__subtitle',
		];
	}
	return [];
}

function lf_ai_get_inline_dom_override_for_field(string $context_type, $context_id, string $field_key): string {
	if (!function_exists('lf_ai_get_inline_dom_overrides')) {
		return '';
	}
	$map = lf_ai_get_inline_dom_overrides($context_type, $context_id);
	if (!is_array($map) || empty($map)) {
		return '';
	}
	foreach (lf_ai_inline_selectors_for_field($field_key) as $selector) {
		$value = trim((string) ($map[$selector] ?? ''));
		if ($value !== '') {
			return $value;
		}
	}
	return '';
}

function lf_ai_sync_inline_dom_overrides_for_fields(string $context_type, $context_id, array $updates): void {
	if (!function_exists('lf_ai_get_inline_dom_overrides') || !function_exists('lf_ai_set_inline_dom_overrides')) {
		return;
	}
	$map = lf_ai_get_inline_dom_overrides($context_type, $context_id);
	if (!is_array($map)) {
		$map = [];
	}
	$changed = false;
	foreach ($updates as $field_key => $value) {
		$field_key = (string) $field_key;
		$text = trim((string) $value);
		if ($text === '') {
			continue;
		}
		$selectors = lf_ai_inline_selectors_for_field($field_key);
		if (empty($selectors)) {
			continue;
		}
		foreach ($selectors as $selector) {
			$map[$selector] = $text;
			$changed = true;
		}
	}
	if ($changed) {
		lf_ai_set_inline_dom_overrides($context_type, $context_id, $map);
	}
}

function lf_ai_pb_context_for_post(\WP_Post $post): string {
	if ($post->post_type === 'page') {
		return 'page';
	}
	if ($post->post_type === 'post') {
		return 'post';
	}
	if ($post->post_type === 'lf_service') {
		return 'service';
	}
	if ($post->post_type === 'lf_service_area') {
		return 'service_area';
	}
	return '';
}

function lf_ai_get_pb_hero_settings_for_post(int $post_id): array {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || !function_exists('lf_pb_get_post_config')) {
		return [];
	}
	$context = lf_ai_pb_context_for_post($post);
	if ($context === '') {
		return [];
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	foreach ($order as $instance_id) {
		$row = is_array($sections[$instance_id] ?? null) ? $sections[$instance_id] : null;
		if (!is_array($row) || (($row['type'] ?? '') !== 'hero')) {
			continue;
		}
		$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		return [
			'hero_headline' => (string) ($settings['hero_headline'] ?? ''),
			'hero_subheadline' => (string) ($settings['hero_subheadline'] ?? ''),
		];
	}
	return [];
}

function lf_ai_update_pb_hero_settings_for_post(int $post_id, array $updates): bool {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || !function_exists('lf_pb_get_post_config')) {
		return false;
	}
	$context = lf_ai_pb_context_for_post($post);
	if ($context === '') {
		return false;
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	foreach ($order as $instance_id) {
		$row = is_array($sections[$instance_id] ?? null) ? $sections[$instance_id] : null;
		if (!is_array($row) || (($row['type'] ?? '') !== 'hero')) {
			continue;
		}
		$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		foreach (['hero_headline', 'hero_subheadline'] as $key) {
			if (array_key_exists($key, $updates)) {
				$settings[$key] = (string) $updates[$key];
			}
		}
		$sections[$instance_id]['settings'] = $settings;
		$config['sections'] = $sections;
		if (defined('LF_PB_META_KEY')) {
			update_post_meta($post_id, LF_PB_META_KEY, $config);
			return true;
		}
		return false;
	}
	return false;
}

/**
 * Get hero section from homepage config (for reading/writing hero_* in options).
 */
function lf_ai_get_homepage_hero_row(): ?array {
	if (!function_exists('lf_get_homepage_section_config')) {
		return null;
	}
	$config = lf_get_homepage_section_config();
	$hero = $config['hero'] ?? null;
	if (!is_array($hero) && is_array($config)) {
		foreach ($config as $sid => $row) {
			if (!is_string($sid) || !is_array($row)) {
				continue;
			}
			$base = $sid;
			if (function_exists('lf_homepage_base_section_type')) {
				$base = lf_homepage_base_section_type($sid);
			}
			$row_type = (string) ($row['section_type'] ?? $row['type'] ?? '');
			if ($base === 'hero' || $row_type === 'hero') {
				$hero = $row;
				break;
			}
		}
	}
	if (!is_array($hero)) {
		return null;
	}
	return array_merge(['section_type' => 'hero'], $hero);
}

/**
 * Get current values for given field keys in context (for diff and rollback).
 */
function lf_ai_get_current_values(string $context_type, $context_id, array $field_keys, string $homepage_row_id = ''): array {
	$out = [];
	if ($context_type === 'homepage') {
		$homepage_row_id = sanitize_text_field($homepage_row_id);
		$hero_field_keys = function_exists('lf_ai_homepage_hero_row_field_keys') ? lf_ai_homepage_hero_row_field_keys() : ['hero_headline', 'hero_subheadline', 'cta_primary_override'];
		if ($homepage_row_id !== '' && function_exists('lf_get_homepage_section_config')) {
			$config = lf_get_homepage_section_config();
			$row = is_array($config[ $homepage_row_id ] ?? null) ? $config[ $homepage_row_id ] : null;
			if (is_array($row)) {
				$resolved_primary_cta = '';
				if (function_exists('lf_resolve_cta')) {
					$merged = array_merge(
						['section_type' => (string) ($row['section_type'] ?? $row['type'] ?? '')],
						$row
					);
					$cta = lf_resolve_cta(['homepage' => true, 'section' => $merged], $merged, []);
					if (is_array($cta) && isset($cta['primary_text'])) {
						$resolved_primary_cta = (string) $cta['primary_text'];
					}
				}
				$hero_row = lf_ai_get_homepage_hero_row();
				$hero_resolved_cta = '';
				if (function_exists('lf_resolve_cta') && is_array($hero_row)) {
					$hcta = lf_resolve_cta(['homepage' => true, 'section' => $hero_row], $hero_row, []);
					if (is_array($hcta) && isset($hcta['primary_text'])) {
						$hero_resolved_cta = (string) $hcta['primary_text'];
					}
				}
				foreach ($field_keys as $key) {
					if (in_array($key, $hero_field_keys, true)) {
						$value = is_array($hero_row) ? (string) ($hero_row[ $key ] ?? '') : '';
						if ($key === 'cta_primary_override' && $value === '' && $hero_resolved_cta !== '') {
							$value = $hero_resolved_cta;
						}
						$override = lf_ai_get_inline_dom_override_for_field($context_type, $context_id, (string) $key);
						$out[ $key ] = $override !== '' ? $override : $value;
					} else {
						$raw = $row[ $key ] ?? '';
						$out[ $key ] = is_array($raw) ? wp_json_encode($raw) : (string) $raw;
					}
				}
				return $out;
			}
		}
		$hero_row = lf_ai_get_homepage_hero_row();
		$resolved_primary_cta = '';
		if (function_exists('lf_resolve_cta') && is_array($hero_row)) {
			$cta = lf_resolve_cta(['homepage' => true, 'section' => $hero_row], $hero_row, []);
			if (is_array($cta) && isset($cta['primary_text'])) {
				$resolved_primary_cta = (string) $cta['primary_text'];
			}
		}
		foreach ($field_keys as $key) {
			if (in_array($key, ['hero_headline', 'hero_subheadline', 'cta_primary_override'], true)) {
				$value = (string) ($hero_row[$key] ?? '');
				if ($key === 'cta_primary_override' && $value === '' && $resolved_primary_cta !== '') {
					$value = $resolved_primary_cta;
				}
				$override = lf_ai_get_inline_dom_override_for_field($context_type, $context_id, (string) $key);
				$out[$key] = $override !== '' ? $override : $value;
			} else {
				$out[$key] = function_exists('get_field') ? (string) get_field($key, 'option') : '';
			}
		}
		return $out;
	}
	$pid = (int) $context_id;
	$hero_settings = lf_ai_get_pb_hero_settings_for_post($pid);
	foreach ($field_keys as $key) {
		if (in_array($key, ['hero_headline', 'hero_subheadline'], true) && isset($hero_settings[$key])) {
			$value = (string) $hero_settings[$key];
			$override = lf_ai_get_inline_dom_override_for_field($context_type, $context_id, (string) $key);
			$out[$key] = $override !== '' ? $override : $value;
		} elseif ($key === 'post_content') {
			$post = get_post($pid);
			$out[$key] = $post ? $post->post_content : '';
		} else {
			$out[$key] = function_exists('get_field') ? (string) get_field($key, $pid) : '';
		}
	}
	return $out;
}

/**
 * Generate AI edit proposal. Returns [ 'success' => bool, 'proposed' => [], 'error' => '' ].
 * Proposed is filtered to editable keys only; stored in transient for review step.
 */
function lf_ai_generate_proposal(string $context_type, $context_id, string $user_prompt, string $homepage_section_row_id = '', ?array $scoped_editable_fields = null): array {
	$homepage_section_row_id = sanitize_text_field($homepage_section_row_id);
	$editable = $scoped_editable_fields !== null ? $scoped_editable_fields : lf_get_ai_editable_fields($context_id);
	if (empty($editable)) {
		return ['success' => false, 'proposed' => [], 'error' => __('No editable fields for this context.', 'leadsforward-core')];
	}
	$prompt = trim($user_prompt);
	if ($prompt === '') {
		return ['success' => false, 'proposed' => [], 'error' => __('Prompt cannot be empty.', 'leadsforward-core')];
	}
	// Rate-limit per user to prevent abuse.
	$rl_key = 'lf_ai_rl_' . get_current_user_id();
	if (get_transient($rl_key)) {
		return ['success' => false, 'proposed' => [], 'error' => __('Please wait a few seconds before generating again.', 'leadsforward-core')];
	}
	set_transient($rl_key, true, 5);
	// Clamp prompt length for safety.
	if (strlen($prompt) > 800) {
		$prompt = substr($prompt, 0, 800);
	}
	$system = lf_ai_build_system_prompt($context_type, $context_id, $editable);
	$row_for_read = ($context_type === 'homepage' && $homepage_section_row_id !== '') ? $homepage_section_row_id : '';
	$current = lf_ai_get_current_values($context_type, $context_id, array_keys($editable), $row_for_read);
	$user_message = $prompt . "\n\nCurrent values (for reference):\n" . wp_json_encode($current);

	// AI providers hook into lf_ai_completion. The OpenAI provider is bundled; others may override.
	// Return raw JSON string; theme validates and strips disallowed keys.
	$response = apply_filters('lf_ai_completion', '', $system, $user_message, $context_type, $context_id);
	if (is_wp_error($response)) {
		return ['success' => false, 'proposed' => [], 'error' => $response->get_error_message()];
	}
	if (!is_string($response) || $response === '') {
		if (!has_filter('lf_ai_completion')) {
			return ['success' => false, 'proposed' => [], 'error' => __('AI is not available. Add an AI provider for lf_ai_completion.', 'leadsforward-core')];
		}
		return ['success' => false, 'proposed' => [], 'error' => __('AI request failed. Check your OpenAI key, model access, and billing.', 'leadsforward-core')];
	}

	$proposed = lf_ai_validate_response($response, $context_id, array_keys($editable));
	if (empty($proposed)) {
		return ['success' => false, 'proposed' => [], 'error' => __('AI response was invalid or contained no allowed edits.', 'leadsforward-core')];
	}

	$transient_key = LF_AI_PROPOSED_TRANSIENT_PREFIX . get_current_user_id() . '_' . $context_type . '_' . (is_numeric($context_id) ? $context_id : 'opt');
	set_transient($transient_key, [
		'proposed' => $proposed,
		'context_type' => $context_type,
		'context_id' => $context_id,
		'current' => $current,
		'homepage_section_row_id' => ($context_type === 'homepage' && $homepage_section_row_id !== '') ? $homepage_section_row_id : '',
	], 600);
	return ['success' => true, 'proposed' => $proposed, 'error' => ''];
}

/**
 * Apply a proposed set of changes. Logs and clears transient. Returns [ 'success' => bool, 'log_id' => '' ].
 */
function lf_ai_apply_proposal(string $context_type, $context_id, array $proposed, string $prompt_snippet = '', string $homepage_section_row_fallback = ''): array {
	$stored = lf_ai_get_stored_proposal($context_type, $context_id);
	$scoped_homepage_row = '';
	if (is_array($stored) && !empty($stored['homepage_section_row_id'])) {
		$scoped_homepage_row = sanitize_text_field((string) $stored['homepage_section_row_id']);
	}
	if ($scoped_homepage_row === '') {
		$scoped_homepage_row = sanitize_text_field($homepage_section_row_fallback);
	}
	$editable = lf_get_ai_editable_fields($context_id);
	$to_apply = [];
	foreach ($proposed as $key => $value) {
		if (!lf_is_field_ai_editable($key)) {
			continue;
		}
		if ($context_type === 'homepage' && $scoped_homepage_row !== '') {
			$to_apply[ $key ] = $value;
		} elseif (isset($editable[ $key ])) {
			$to_apply[ $key ] = $value;
		}
	}
	if (empty($to_apply)) {
		return ['success' => false, 'log_id' => ''];
	}
	$current = lf_ai_get_current_values($context_type, $context_id, array_keys($to_apply), $scoped_homepage_row);
	if ($context_type === 'homepage') {
		$hero_keys = function_exists('lf_ai_homepage_hero_row_field_keys') ? lf_ai_homepage_hero_row_field_keys() : ['hero_headline', 'hero_subheadline', 'cta_primary_override'];
		$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
		if ($scoped_homepage_row !== '' && !is_array($config[ $scoped_homepage_row ] ?? null)) {
			return ['success' => false, 'log_id' => ''];
		}
		$option_only_keys = ['lf_homepage_cta_primary', 'lf_homepage_cta_secondary'];
		$hero_section_key = '';
		if (is_array($config['hero'] ?? null)) {
			$hero_section_key = 'hero';
		} elseif (is_array($config)) {
			foreach ($config as $sid => $row) {
				if (!is_string($sid) || !is_array($row)) {
					continue;
				}
				$base = $sid;
				if (function_exists('lf_homepage_base_section_type')) {
					$base = lf_homepage_base_section_type($sid);
				}
				$row_type = (string) ($row['section_type'] ?? $row['type'] ?? '');
				if ($base === 'hero' || $row_type === 'hero') {
					$hero_section_key = $sid;
					break;
				}
			}
		}
		if ($scoped_homepage_row !== '' && is_array($config[ $scoped_homepage_row ] ?? null)) {
			foreach ($to_apply as $key => $value) {
				if (in_array($key, $option_only_keys, true) && function_exists('update_field')) {
					update_field($key, $value, 'option');
					continue;
				}
				if (in_array($key, $hero_keys, true)) {
					if ($hero_section_key !== '' && is_array($config[ $hero_section_key ] ?? null)) {
						$config[ $hero_section_key ][ $key ] = $value;
					}
					continue;
				}
				$config[ $scoped_homepage_row ][ $key ] = $value;
			}
			if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
		} else {
			if ($hero_section_key !== '' && !empty($config[ $hero_section_key ]) && is_array($config[ $hero_section_key ])) {
				foreach ($hero_keys as $hk) {
					if (isset($to_apply[ $hk ])) {
						$config[ $hero_section_key ][ $hk ] = $to_apply[ $hk ];
					}
				}
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
			foreach ($to_apply as $key => $value) {
				if (in_array($key, $hero_keys, true)) {
					continue;
				}
				if (function_exists('update_field')) {
					update_field($key, $value, 'option');
				}
			}
		}
	} else {
		$pid = (int) $context_id;
		$hero_updates = [];
		foreach ($to_apply as $key => $value) {
			if (in_array($key, ['hero_headline', 'hero_subheadline'], true)) {
				$hero_updates[$key] = $value;
			} elseif ($key === 'post_content') {
				wp_update_post(['ID' => $pid, 'post_content' => $value]);
			} elseif (function_exists('update_field')) {
				update_field($key, $value, $pid);
			}
		}
		if (!empty($hero_updates)) {
			lf_ai_update_pb_hero_settings_for_post($pid, $hero_updates);
		}
	}
	$log_id = lf_ai_log_action($context_type, $context_id, $current, $to_apply, $prompt_snippet);
	lf_ai_sync_inline_dom_overrides_for_fields($context_type, $context_id, $to_apply);
	$transient_key = LF_AI_PROPOSED_TRANSIENT_PREFIX . get_current_user_id() . '_' . $context_type . '_' . (is_numeric($context_id) ? $context_id : 'opt');
	delete_transient($transient_key);
	return ['success' => true, 'log_id' => $log_id];
}

/**
 * Get stored proposal for current user (for review screen).
 */
function lf_ai_get_stored_proposal(string $context_type, $context_id): ?array {
	$key = LF_AI_PROPOSED_TRANSIENT_PREFIX . get_current_user_id() . '_' . $context_type . '_' . (is_numeric($context_id) ? $context_id : 'opt');
	$stored = get_transient($key);
	return is_array($stored) ? $stored : null;
}
