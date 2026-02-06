<?php
/**
 * Build guarded prompt for AI and validate structured JSON response.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Build system prompt: context (niche, page type, read-only identifiers) and strict rules.
 */
function lf_ai_build_system_prompt(string $context_type, $context_id, array $editable_fields): string {
	$niche = function_exists('lf_get_variation_profile') ? lf_get_variation_profile() : 'a';
	$profile_label = [
		'a' => 'Clean + Minimal',
		'b' => 'Bold + High Contrast',
		'c' => 'Trust Heavy',
		'd' => 'Service Heavy',
		'e' => 'Offer/Promo Heavy',
	][$niche] ?? $niche;
	$read_only = [];
	if ($context_type === 'lf_service' && $context_id) {
		$post = get_post((int) $context_id);
		if ($post) {
			$read_only['service_title'] = $post->post_title;
			$read_only['service_slug']   = $post->post_name;
		}
	}
	if ($context_type === 'lf_service_area' && $context_id) {
		$post = get_post((int) $context_id);
		if ($post) {
			$read_only['city'] = $post->post_title;
			$read_only['slug'] = $post->post_name;
		}
	}
	$allowed_keys = implode(', ', array_keys($editable_fields));
	$system = "You are an editor for a local business website. You must follow these rules:\n";
	$system .= "- You may ONLY edit these fields: " . $allowed_keys . ".\n";
	$system .= "- You must NOT change URLs, slugs, post titles, H1, schema fields, business name/address/phone, or any relationship/identifier fields.\n";
	$system .= "- Page type: " . $context_type . ". Variation profile: " . $profile_label . ".\n";
	if (!empty($read_only)) {
		$system .= "- Read-only context (do not change): " . wp_json_encode($read_only) . ".\n";
	}
	$system .= "- Respond with a single JSON object: keys are field_key strings, values are the new content strings. Only include keys you are allowed to edit. No explanation, no markdown, only valid JSON.\n";
	return $system;
}

/**
 * Validate and filter AI response. Returns only allowed field_key => new_value. Rejects invalid/locked.
 */
function lf_ai_validate_response(string $raw_response, $context_id): array {
	$editable = lf_get_ai_editable_fields($context_id);
	$allowed_keys = array_keys($editable);
	$decoded = json_decode(trim($raw_response), true);
	if (!is_array($decoded)) {
		return [];
	}
	$out = [];
	foreach ($decoded as $key => $value) {
		if (!in_array($key, $allowed_keys, true)) {
			continue;
		}
		if (!lf_is_field_ai_editable($key)) {
			continue;
		}
		$out[$key] = is_string($value) ? $value : (string) $value;
	}
	return $out;
}
