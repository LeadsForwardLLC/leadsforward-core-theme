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
 * Get hero section from homepage config (for reading/writing hero_* in options).
 */
function lf_ai_get_homepage_hero_row(): ?array {
	if (!function_exists('lf_get_homepage_section_config')) {
		return null;
	}
	$config = lf_get_homepage_section_config();
	$hero = $config['hero'] ?? null;
	if (!is_array($hero)) {
		return null;
	}
	return array_merge(['section_type' => 'hero'], $hero);
}

/**
 * Get current values for given field keys in context (for diff and rollback).
 */
function lf_ai_get_current_values(string $context_type, $context_id, array $field_keys): array {
	$out = [];
	if ($context_type === 'homepage') {
		$hero_row = lf_ai_get_homepage_hero_row();
		foreach ($field_keys as $key) {
			if (in_array($key, ['hero_headline', 'hero_subheadline', 'hero_cta_override'], true)) {
				$out[$key] = $hero_row[$key] ?? '';
			} else {
				$out[$key] = function_exists('get_field') ? (string) get_field($key, 'option') : '';
			}
		}
		return $out;
	}
	$pid = (int) $context_id;
	foreach ($field_keys as $key) {
		if ($key === 'post_content') {
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
function lf_ai_generate_proposal(string $context_type, $context_id, string $user_prompt): array {
	$editable = lf_get_ai_editable_fields($context_id);
	if (empty($editable)) {
		return ['success' => false, 'proposed' => [], 'error' => __('No editable fields for this context.', 'leadsforward-core')];
	}
	$system = lf_ai_build_system_prompt($context_type, $context_id, $editable);
	$current = lf_ai_get_current_values($context_type, $context_id, array_keys($editable));
	$user_message = $user_prompt . "\n\nCurrent values (for reference):\n" . wp_json_encode($current);

	// Plugin or mu-plugin should add filter: add_filter('lf_ai_completion', fn($r, $sys, $user, $ctx_type, $ctx_id) => call_api($sys, $user), 10, 5);
	// Return raw JSON string; theme validates and strips disallowed keys. No API key in theme.
	$response = apply_filters('lf_ai_completion', '', $system, $user_message, $context_type, $context_id);
	if (!is_string($response) || $response === '') {
		return ['success' => false, 'proposed' => [], 'error' => __('AI is not available. Install an AI provider plugin or add the lf_ai_completion filter.', 'leadsforward-core')];
	}

	$proposed = lf_ai_validate_response($response, $context_id);
	if (empty($proposed)) {
		return ['success' => false, 'proposed' => [], 'error' => __('AI response was invalid or contained no allowed edits.', 'leadsforward-core')];
	}

	$transient_key = LF_AI_PROPOSED_TRANSIENT_PREFIX . get_current_user_id() . '_' . $context_type . '_' . (is_numeric($context_id) ? $context_id : 'opt');
	set_transient($transient_key, ['proposed' => $proposed, 'context_type' => $context_type, 'context_id' => $context_id, 'current' => $current], 600);
	return ['success' => true, 'proposed' => $proposed, 'error' => ''];
}

/**
 * Apply a proposed set of changes. Logs and clears transient. Returns [ 'success' => bool, 'log_id' => '' ].
 */
function lf_ai_apply_proposal(string $context_type, $context_id, array $proposed, string $prompt_snippet = ''): array {
	$editable = lf_get_ai_editable_fields($context_id);
	$to_apply = [];
	foreach ($proposed as $key => $value) {
		if (lf_is_field_ai_editable($key) && isset($editable[$key])) {
			$to_apply[$key] = $value;
		}
	}
	if (empty($to_apply)) {
		return ['success' => false, 'log_id' => ''];
	}
	$current = lf_ai_get_current_values($context_type, $context_id, array_keys($to_apply));
	if ($context_type === 'homepage') {
		$hero_keys = ['hero_headline', 'hero_subheadline', 'hero_cta_override'];
		$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
		if (!empty($config['hero'])) {
			foreach ($hero_keys as $hk) {
				if (isset($to_apply[$hk])) {
					$config['hero'][$hk] = $to_apply[$hk];
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
	} else {
		$pid = (int) $context_id;
		foreach ($to_apply as $key => $value) {
			if ($key === 'post_content') {
				wp_update_post(['ID' => $pid, 'post_content' => $value]);
			} elseif (function_exists('update_field')) {
				update_field($key, $value, $pid);
			}
		}
	}
	$log_id = lf_ai_log_action($context_type, $context_id, $current, $to_apply, $prompt_snippet);
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
