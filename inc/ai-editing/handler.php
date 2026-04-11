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
 * Normalize common LLM JSON quirks before json_decode.
 */
function lf_ai_json_apply_model_response_repairs(string $chunk): string {
	$chunk = str_replace(
		["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"],
		['"', '"', "'", "'"],
		$chunk
	);
	return $chunk;
}

/**
 * json_decode with UTF-8 substitution when available.
 *
 * @return array<string, mixed>|null
 */
function lf_ai_json_decode_assoc(string $chunk): ?array {
	$chunk = trim($chunk);
	if ($chunk === '') {
		return null;
	}
	$flags = 0;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	}
	$decoded = json_decode($chunk, true, 512, $flags);
	return is_array($decoded) ? $decoded : null;
}

/**
 * Decode JSON; repair trailing commas (common in LLM output) and retry.
 *
 * @return array<string, mixed>|null
 */
function lf_ai_json_decode_lenient(string $chunk): ?array {
	$chunk = lf_ai_json_apply_model_response_repairs(trim($chunk));
	if ($chunk === '') {
		return null;
	}
	$chunk = preg_replace('/([}\]])\s*,\s*$/', '$1', $chunk);
	if (!is_string($chunk)) {
		return null;
	}
	$decoded = lf_ai_json_decode_assoc($chunk);
	if ($decoded !== null) {
		return $decoded;
	}
	for ($pass = 0; $pass < 6; $pass++) {
		$next = preg_replace('/,\s*([\]}])/', '$1', $chunk);
		if (!is_string($next) || $next === $chunk) {
			break;
		}
		$chunk = $next;
		$decoded = lf_ai_json_decode_assoc($chunk);
		if ($decoded !== null) {
			return $decoded;
		}
	}
	return null;
}

/**
 * Extract a balanced {...} or [...] fragment starting at $start (must point at $open).
 */
function lf_ai_extract_balanced_json_fragment(string $s, int $start, string $open, string $close): ?string {
	$len = strlen($s);
	if ($start < 0 || $start >= $len || ($s[ $start ] ?? '') !== $open) {
		return null;
	}
	$depth = 0;
	$in_str = false;
	$esc = false;
	for ($i = $start; $i < $len; $i++) {
		$ch = $s[ $i ];
		if ($in_str) {
			if ($esc) {
				$esc = false;
			} elseif ($ch === '\\') {
				$esc = true;
			} elseif ($ch === '"') {
				$in_str = false;
			}
			continue;
		}
		if ($ch === '"') {
			$in_str = true;
			continue;
		}
		if ($ch === $open) {
			$depth++;
		} elseif ($ch === $close) {
			$depth--;
			if ($depth === 0) {
				return substr($s, $start, $i - $start + 1);
			}
		}
	}
	return null;
}

/**
 * Whether decoded JSON looks like assistant creation / batch payload (skip prose "{}" snippets).
 */
function lf_ai_decoded_json_looks_like_assistant_payload(array $d): bool {
	if ($d === []) {
		return false;
	}
	if (isset($d['title']) && is_string($d['title']) && trim($d['title']) !== '') {
		return true;
	}
	if (isset($d['items']) && is_array($d['items'])) {
		return true;
	}
	if (isset($d['page_builder']) && is_array($d['page_builder']) && $d['page_builder'] !== []) {
		return true;
	}
	if (isset($d['content']) && is_string($d['content']) && strlen(trim(wp_strip_all_tags($d['content']))) >= 20) {
		return true;
	}
	return false;
}

/**
 * Decode JSON from an LLM response: trim, strip BOM, fenced markdown blocks, lenient decode, scan all {...} candidates.
 *
 * @return array<string, mixed>|null
 */
function lf_ai_decode_model_json_response(string $raw): ?array {
	$s = trim($raw);
	if ($s === '') {
		return null;
	}
	if (str_starts_with($s, "\xEF\xBB\xBF")) {
		$s = trim(substr($s, 3));
	}
	if (preg_match('/```(?:json|javascript|js|[a-z]+)?\s*\R?([\s\S]*?)\R?```/i', $s, $m)) {
		$s = trim($m[1]);
	}
	$decoded = lf_ai_json_decode_lenient($s);
	if ($decoded !== null && lf_ai_decoded_json_looks_like_assistant_payload($decoded)) {
		return $decoded;
	}
	if ($decoded !== null && $decoded !== []) {
		return $decoded;
	}
	$candidates = [];
	$pos = 0;
	while (($pos = strpos($s, '{', $pos)) !== false) {
		$frag = lf_ai_extract_balanced_json_fragment($s, $pos, '{', '}');
		if ($frag !== null && strlen($frag) > 1) {
			$candidates[] = $frag;
		}
		$pos++;
	}
	usort($candidates, static function (string $a, string $b): int {
		return strlen($b) <=> strlen($a);
	});
	$best = null;
	$best_score = -1;
	foreach ($candidates as $frag) {
		$try = lf_ai_json_decode_lenient($frag);
		if ($try === null || $try === []) {
			continue;
		}
		$score = lf_ai_decoded_json_looks_like_assistant_payload($try) ? 1000 + strlen($frag) : strlen($frag);
		if ($score > $best_score) {
			$best_score = $score;
			$best = $try;
		}
	}
	if ($best !== null) {
		return $best;
	}
	$pos = 0;
	while (($pos = strpos($s, '[', $pos)) !== false) {
		$frag = lf_ai_extract_balanced_json_fragment($s, $pos, '[', ']');
		if ($frag !== null && strlen($frag) > 1) {
			$try = lf_ai_json_decode_lenient($frag);
			if ($try !== null && $try !== []) {
				return $try;
			}
		}
		$pos++;
	}
	return null;
}

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
	if ($post->post_type === 'post' || $post->post_type === 'lf_project') {
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

/**
 * Transient key for a pending multi-section Page Builder patch (full-page AI edit).
 */
function lf_ai_pb_patch_transient_key(int $user_id, int $post_id): string {
	return 'lf_ai_pb_patch_' . $user_id . '_' . $post_id;
}

/**
 * Whether the user asked to rewrite copy across Page Builder sections (not hero-only).
 */
function lf_ai_assistant_prompt_requests_pb_expand(string $prompt, bool $force_via_post_flag): bool {
	if ($force_via_post_flag) {
		return true;
	}
	$p = strtolower($prompt);
	$needles = [
		'full page',
		'entire page',
		'all sections',
		'page builder',
		'below the hero',
		'body copy',
		'main content',
		'content section',
		'flesh out',
		'complete the page',
		'throughout the page',
		'rewrite the page',
		'tighten this page',
		'this page copy',
		'opening copy',
		'every section',
		'optimize copy',
		'optimize the copy',
		'serp intent',
		'metadata and opening',
	];
	foreach ($needles as $n) {
		if (strpos($p, $n) !== false) {
			return true;
		}
	}
	if (strpos($p, 'improve cta') !== false || strpos($p, 'cta language') !== false) {
		return true;
	}
	return false;
}

/**
 * Truncate long setting values for the LLM context payload.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function lf_ai_pb_truncate_settings_for_prompt(array $settings, int $max_each = 320): array {
	$out = [];
	foreach ($settings as $k => $v) {
		$key = (string) $k;
		if (!is_string($v)) {
			$out[ $key ] = $v;
			continue;
		}
		$s = $v;
		if (strlen($s) > $max_each) {
			$s = substr($s, 0, $max_each) . '…';
		}
		$out[ $key ] = $s;
	}
	return $out;
}

/**
 * Ask the model for a page_builder_patch keyed by section instance id (hero-1, content-1, …).
 *
 * @return array{success:bool, patch?: array<string, array<string, mixed>>, preview?: string, error?: string, error_code?: string}
 */
function lf_ai_generate_pb_page_builder_patch(int $post_id, string $user_prompt): array {
	$post_id = max(0, $post_id);
	if ($post_id === 0 || !function_exists('lf_pb_get_post_config') || !function_exists('lf_ai_pb_filter_section_patch')) {
		return ['success' => false, 'error' => __('Page Builder is not available for this post.', 'leadsforward-core')];
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return ['success' => false, 'error' => __('Invalid post.', 'leadsforward-core')];
	}
	$ctx = lf_ai_pb_context_for_post($post);
	if ($ctx === '') {
		return ['success' => false, 'error' => __('This post type does not use Page Builder.', 'leadsforward-core')];
	}
	$config = lf_pb_get_post_config($post_id, $ctx);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if ($order === [] || $sections === []) {
		return ['success' => false, 'error' => __('No sections found to edit.', 'leadsforward-core'), 'error_code' => 'no_pb_sections'];
	}
	$rl_key = 'lf_ai_rl_' . get_current_user_id();
	if (get_transient($rl_key)) {
		return ['success' => false, 'error' => __('Please wait a few seconds before generating again.', 'leadsforward-core')];
	}
	set_transient($rl_key, true, 5);
	$instances = [];
	$brief = [];
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row) || empty($row['enabled'])) {
			continue;
		}
		$type = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($type === '') {
			continue;
		}
		$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		$instances[] = $instance_id . ' (' . $type . ')';
		$brief[ $instance_id ] = [
			'type' => $type,
			'settings' => lf_ai_pb_truncate_settings_for_prompt($settings),
		];
	}
	if ($instances === []) {
		return ['success' => false, 'error' => __('No enabled Page Builder sections to edit.', 'leadsforward-core'), 'error_code' => 'no_pb_sections'];
	}
	$prompt = trim($user_prompt);
	if (strlen($prompt) > 2000) {
		$prompt = substr($prompt, 0, 2000);
	}
	$system = "You are a conversion copywriter for a local service website.\n";
	$system .= "Return ONLY valid JSON (no markdown). Schema:\n";
	$system .= "{\"page_builder_patch\": {\"INSTANCE_ID\": {\"copy_field_key\": \"value\", ...}, ...}}\n";
	$system .= "Rules:\n";
	$system .= "- Use ONLY these instance IDs: " . implode(', ', $instances) . "\n";
	$system .= "- For each instance, only include keys that are plain-text, textarea, list (newline-separated), or HTML richtext fields for that section type.\n";
	$system .= "- Omit instances you do not change, or use {}.\n";
	$system .= "- Service detail sections use service_details_body for main HTML; generic content sections use section_body.\n";
	$system .= "- Write substantial, specific copy for every section you touch; do not leave body fields empty if the user asked to improve the page.\n";
	$user_message = $prompt . "\n\nCurrent section data (JSON):\n" . wp_json_encode($brief);
	$response = apply_filters('lf_ai_completion', '', $system, $user_message, 'page', (string) $post_id);
	if (is_wp_error($response)) {
		return ['success' => false, 'error' => $response->get_error_message()];
	}
	if (!is_string($response) || trim($response) === '') {
		return ['success' => false, 'error' => __('AI response was empty.', 'leadsforward-core')];
	}
	$decoded = lf_ai_decode_model_json_response($response);
	if (!is_array($decoded)) {
		return ['success' => false, 'error' => __('AI did not return valid JSON.', 'leadsforward-core')];
	}
	$raw_patch = $decoded['page_builder_patch'] ?? null;
	if (!is_array($raw_patch)) {
		return ['success' => false, 'error' => __('AI JSON missing page_builder_patch.', 'leadsforward-core')];
	}
	$validated = [];
	$preview_lines = [];
	foreach ($raw_patch as $iid_raw => $patch_raw) {
		$iid = sanitize_text_field((string) $iid_raw);
		if ($iid === '' || !isset($sections[ $iid ]) || !is_array($sections[ $iid ])) {
			continue;
		}
		$type = sanitize_text_field((string) ($sections[ $iid ]['type'] ?? ''));
		if ($type === '' || !is_array($patch_raw)) {
			continue;
		}
		$patch = lf_ai_pb_filter_section_patch($type, $patch_raw);
		if ($patch === []) {
			continue;
		}
		$validated[ $iid ] = $patch;
		foreach ($patch as $pk => $pv) {
			$pv_s = is_string($pv) ? wp_strip_all_tags($pv) : wp_json_encode($pv);
			if (strlen($pv_s) > 120) {
				$pv_s = substr($pv_s, 0, 117) . '…';
			}
			$preview_lines[] = $iid . '.' . (string) $pk . ': ' . $pv_s;
		}
	}
	if ($validated === []) {
		return ['success' => false, 'error' => __('AI returned no usable section fields.', 'leadsforward-core')];
	}
	return [
		'success' => true,
		'patch' => $validated,
		'preview' => implode("\n", $preview_lines),
	];
}

/**
 * Apply a validated page_builder_patch and log for rollback (uses __section_record:: keys).
 *
 * @param array<string, array<string, mixed>> $patch_by_instance
 * @return array{success: bool, log_id?: string, message?: string}
 */
function lf_ai_apply_logged_pb_patch(string $context_type, string $context_id, array $patch_by_instance, string $prompt_snippet = ''): array {
	$pid = (int) $context_id;
	if ($pid <= 0 || $patch_by_instance === []) {
		return ['success' => false, 'message' => __('Nothing to apply.', 'leadsforward-core')];
	}
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return ['success' => false, 'message' => __('Invalid post.', 'leadsforward-core')];
	}
	$ctx = lf_ai_pb_context_for_post($post);
	if ($ctx === '' || !function_exists('lf_pb_get_post_config') || !function_exists('lf_sections_sanitize_settings')) {
		return ['success' => false, 'message' => __('Page Builder unavailable.', 'leadsforward-core')];
	}
	$config = lf_pb_get_post_config($pid, $ctx);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$old_changes = [];
	$new_changes = [];
	foreach ($patch_by_instance as $iid => $patch) {
		if (!is_array($patch) || !isset($sections[ $iid ]) || !is_array($sections[ $iid ])) {
			continue;
		}
		$row = $sections[ $iid ];
		$type = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($type === '') {
			continue;
		}
		$filtered = lf_ai_pb_filter_section_patch($type, $patch);
		if ($filtered === []) {
			continue;
		}
		$base = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		$new_settings = lf_sections_sanitize_settings($type, array_merge($base, $filtered));
		$new_row = [
			'type' => $type,
			'enabled' => !empty($row['enabled']),
			'deletable' => !empty($row['deletable']),
			'settings' => $new_settings,
		];
		$old_changes['__section_record::' . $iid] = $row;
		$new_changes['__section_record::' . $iid] = $new_row;
	}
	if ($new_changes === []) {
		return ['success' => false, 'message' => __('No matching sections to update.', 'leadsforward-core')];
	}
	$log_id = lf_ai_log_action($context_type, $context_id, $old_changes, $new_changes, $prompt_snippet);
	lf_ai_apply_changes_to_context($context_type, $context_id, $new_changes);
	$hero_updates = [];
	foreach ($patch_by_instance as $iid => $patch) {
		if (!is_array($patch)) {
			continue;
		}
		if (isset($patch['hero_headline'])) {
			$hero_updates['hero_headline'] = (string) $patch['hero_headline'];
		}
		if (isset($patch['hero_subheadline'])) {
			$hero_updates['hero_subheadline'] = (string) $patch['hero_subheadline'];
		}
	}
	if ($hero_updates !== []) {
		lf_ai_sync_inline_dom_overrides_for_fields($context_type, $context_id, $hero_updates);
	}
	return ['success' => true, 'log_id' => $log_id];
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
