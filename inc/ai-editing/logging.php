<?php
/**
 * AI edit log: who, when, which fields, old/new. One-click rollback.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_AI_LOG_OPTION = 'lf_ai_edit_log';
const LF_AI_LOG_MAX = 50;
const LF_AI_INLINE_OVERRIDES_OPTION = 'lf_ai_inline_dom_overrides_homepage';
const LF_AI_INLINE_OVERRIDES_META_KEY = '_lf_ai_inline_dom_overrides';
const LF_AI_INLINE_IMAGE_OVERRIDES_OPTION = 'lf_ai_inline_image_overrides_homepage';
const LF_AI_INLINE_IMAGE_OVERRIDES_META_KEY = '_lf_ai_inline_image_overrides';

/**
 * Append one AI action to the log. Returns the log entry id.
 */
function lf_ai_log_action(string $context_type, $context_id, array $changes_old, array $changes_new, string $prompt_snippet = ''): string {
	$log = get_option(LF_AI_LOG_OPTION, []);
	if (!is_array($log)) {
		$log = [];
	}
	$id = 'ai-' . wp_generate_password(8, false);
	$entry = [
		'id'          => $id,
		'user_id'     => get_current_user_id(),
		'time'        => time(),
		'context_type'=> $context_type,
		'context_id'  => $context_id,
		'changes_old' => $changes_old,
		'changes_new' => $changes_new,
		'prompt'      => $prompt_snippet,
		'rolled_back' => false,
	];
	array_unshift($log, $entry);
	$log = array_slice($log, 0, LF_AI_LOG_MAX);
	update_option(LF_AI_LOG_OPTION, $log);
	return $id;
}

/**
 * Append one AI creation action to the log for rollback of created drafts.
 */
function lf_ai_log_creation_action(string $context_type, $context_id, array $created_post_ids, string $prompt_snippet = ''): string {
	$log = get_option(LF_AI_LOG_OPTION, []);
	if (!is_array($log)) {
		$log = [];
	}
	$created_post_ids = array_values(array_filter(array_map('intval', $created_post_ids), static function (int $id): bool {
		return $id > 0;
	}));
	if (empty($created_post_ids)) {
		return '';
	}
	$id = 'ai-' . wp_generate_password(8, false);
	$entry = [
		'id'               => $id,
		'user_id'          => get_current_user_id(),
		'time'             => time(),
		'context_type'     => $context_type,
		'context_id'       => $context_id,
		'changes_old'      => [],
		'changes_new'      => [],
		'created_post_ids' => $created_post_ids,
		'action_type'      => 'create',
		'prompt'           => $prompt_snippet,
		'rolled_back'      => false,
	];
	array_unshift($log, $entry);
	$log = array_slice($log, 0, LF_AI_LOG_MAX);
	update_option(LF_AI_LOG_OPTION, $log);
	return $id;
}

/**
 * Get full log (recent first).
 */
function lf_ai_get_log(): array {
	$log = get_option(LF_AI_LOG_OPTION, []);
	return is_array($log) ? $log : [];
}

/**
 * Get one log entry by id.
 */
function lf_ai_get_log_entry(string $id): ?array {
	foreach (lf_ai_get_log() as $entry) {
		if (($entry['id'] ?? '') === $id) {
			return $entry;
		}
	}
	return null;
}

/**
 * Allowed inline HTML for frontend DOM overrides (links and minimal emphasis).
 *
 * @return array<string, array<string, bool>>
 */
function lf_ai_inline_link_allowed_kses(): array {
	return [
		'a'      => [
			'href'   => true,
			'title'  => true,
			'target' => true,
			'rel'    => true,
			'class'  => true,
		],
		'br'     => [],
		'strong' => [],
		'em'     => [],
		'b'      => [],
		'i'      => [],
	];
}

function lf_ai_sanitize_inline_dom_html( string $html ): string {
	return trim( wp_kses( $html, lf_ai_inline_link_allowed_kses() ) );
}

function lf_ai_is_inline_dom_html_string( string $value ): bool {
	$value = trim( $value );
	if ( $value === '' ) {
		return false;
	}
	return (bool) preg_match( '/<[a-z][^>]*>/i', $value );
}

/**
 * Get persisted inline DOM text overrides for a context.
 */
function lf_ai_get_inline_dom_overrides(string $context_type, $context_id): array {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		$stored = get_option(LF_AI_INLINE_OVERRIDES_OPTION, []);
		return is_array($stored) ? $stored : [];
	}
	if (!is_numeric($context_id)) {
		return [];
	}
	$stored = get_post_meta((int) $context_id, LF_AI_INLINE_OVERRIDES_META_KEY, true);
	return is_array($stored) ? $stored : [];
}

/**
 * Persist inline DOM text overrides for a context.
 */
function lf_ai_set_inline_dom_overrides(string $context_type, $context_id, array $overrides): void {
	$clean = [];
	foreach ($overrides as $selector => $value) {
		$selector_clean = sanitize_text_field((string) $selector);
		if ($selector_clean === '') {
			continue;
		}
		$value_raw = (string) $value;
		if ( lf_ai_is_inline_dom_html_string( $value_raw ) ) {
			$value_clean = lf_ai_sanitize_inline_dom_html( $value_raw );
		} else {
			$value_clean = trim( sanitize_textarea_field( $value_raw ) );
		}
		if ($value_clean === '') {
			continue;
		}
		$clean[$selector_clean] = $value_clean;
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		update_option(LF_AI_INLINE_OVERRIDES_OPTION, $clean, false);
		return;
	}
	if (!is_numeric($context_id)) {
		return;
	}
	update_post_meta((int) $context_id, LF_AI_INLINE_OVERRIDES_META_KEY, $clean);
}

/**
 * Get persisted inline image overrides for a context.
 */
function lf_ai_get_inline_image_overrides(string $context_type, $context_id): array {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		$stored = get_option(LF_AI_INLINE_IMAGE_OVERRIDES_OPTION, []);
		return is_array($stored) ? $stored : [];
	}
	if (!is_numeric($context_id)) {
		return [];
	}
	$stored = get_post_meta((int) $context_id, LF_AI_INLINE_IMAGE_OVERRIDES_META_KEY, true);
	return is_array($stored) ? $stored : [];
}

/**
 * Persist inline image overrides for a context.
 */
function lf_ai_set_inline_image_overrides(string $context_type, $context_id, array $overrides): void {
	$clean = [];
	foreach ($overrides as $selector => $row) {
		$selector_clean = sanitize_text_field((string) $selector);
		if ($selector_clean === '' || !is_array($row)) {
			continue;
		}
		$attachment_id = (int) ($row['attachment_id'] ?? 0);
		$url = esc_url_raw((string) ($row['url'] ?? ''));
		$alt = sanitize_text_field((string) ($row['alt'] ?? ''));
		if ($attachment_id <= 0 || $url === '') {
			continue;
		}
		$clean[$selector_clean] = [
			'attachment_id' => $attachment_id,
			'url' => $url,
			'alt' => $alt,
		];
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		update_option(LF_AI_INLINE_IMAGE_OVERRIDES_OPTION, $clean, false);
		return;
	}
	if (!is_numeric($context_id)) {
		return;
	}
	update_post_meta((int) $context_id, LF_AI_INLINE_IMAGE_OVERRIDES_META_KEY, $clean);
}

/**
 * Persist section order for homepage/page-builder contexts.
 */
function lf_ai_apply_section_order_to_context(string $context_type, $context_id, array $order): bool {
	$order = array_values(array_filter(array_map(static function ($id): string {
		return sanitize_text_field((string) $id);
	}, $order)));
	if (empty($order)) {
		return false;
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		if (!defined('LF_HOMEPAGE_ORDER_OPTION') || !function_exists('lf_homepage_sanitize_order')) {
			return false;
		}
		$sanitized = lf_homepage_sanitize_order($order, true);
		update_option(LF_HOMEPAGE_ORDER_OPTION, $sanitized, true);
		return true;
	}
	if (!is_numeric($context_id) || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return false;
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return false;
	}
	$pb_context = '';
	if (function_exists('lf_ai_pb_context_for_post')) {
		$pb_context = lf_ai_pb_context_for_post($post);
	}
	if ($pb_context === '') {
		if ($post->post_type === 'page') {
			$pb_context = 'page';
		} elseif ($post->post_type === 'post') {
			$pb_context = 'post';
		} elseif ($post->post_type === 'lf_service') {
			$pb_context = 'service';
		} elseif ($post->post_type === 'lf_service_area') {
			$pb_context = 'service_area';
		}
	}
	if ($pb_context === '') {
		return false;
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$current = is_array($config['order'] ?? null) ? $config['order'] : [];
	if (empty($current)) {
		return false;
	}
	$clean = [];
	foreach ($order as $id) {
		if (in_array($id, $current, true) && !in_array($id, $clean, true)) {
			$clean[] = $id;
		}
	}
	foreach ($current as $id) {
		if (!in_array($id, $clean, true)) {
			$clean[] = $id;
		}
	}
	$config['order'] = $clean;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Persist service-details column layout for a section in homepage/page-builder contexts.
 */
function lf_ai_apply_section_layout_to_context(string $context_type, $context_id, string $section_id, string $layout): bool {
	$layout = $layout === 'media_content' ? 'media_content' : 'content_media';
	$allowed_types = ['service_details', 'content_image', 'content_image_a', 'image_content', 'image_content_b', 'content_image_c'];
	$section_id = sanitize_text_field($section_id);
	if ($section_id === '') {
		return false;
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			return false;
		}
		if (!in_array($section_id, $allowed_types, true)) {
			return false;
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config) || !is_array($config[$section_id] ?? null)) {
			return false;
		}
		$config[$section_id]['service_details_layout'] = $layout;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		return true;
	}
	if (!is_numeric($context_id) || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return false;
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return false;
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		return false;
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	if (!is_array($config['sections'] ?? null) || !is_array($config['sections'][$section_id] ?? null)) {
		return false;
	}
	$type = (string) ($config['sections'][$section_id]['type'] ?? '');
	if (!in_array($type, $allowed_types, true)) {
		return false;
	}
	$config['sections'][$section_id]['settings']['service_details_layout'] = $layout;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Persist section visibility for homepage/page-builder contexts.
 */
function lf_ai_apply_section_enabled_to_context(string $context_type, $context_id, string $section_id, bool $enabled): bool {
	$section_id = sanitize_text_field($section_id);
	if ($section_id === '') {
		return false;
	}
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
			return false;
		}
		$config = lf_get_homepage_section_config();
		if (!is_array($config[$section_id] ?? null)) {
			return false;
		}
		$config[$section_id]['enabled'] = $enabled;
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		return true;
	}
	if (!is_numeric($context_id) || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return false;
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return false;
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		return false;
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	if (!is_array($config['sections'] ?? null) || !is_array($config['sections'][$section_id] ?? null)) {
		return false;
	}
	$config['sections'][$section_id]['enabled'] = $enabled;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Persist full section record for page-builder contexts (used by delete undo/redo).
 */
function lf_ai_apply_section_record_to_context(string $context_type, $context_id, string $section_id, array $record): bool {
	$section_id = sanitize_text_field($section_id);
	if ($section_id === '' || $context_type === 'homepage' || $context_id === 'homepage') {
		return false;
	}
	if (!is_numeric($context_id) || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config')) {
		return false;
	}
	$pid = (int) $context_id;
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return false;
	}
	$pb_context = function_exists('lf_ai_pb_context_for_post') ? lf_ai_pb_context_for_post($post) : '';
	if ($pb_context === '') {
		return false;
	}
	$config = lf_pb_get_post_config($pid, $pb_context);
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if (empty($record)) {
		unset($sections[$section_id]);
		$config['sections'] = $sections;
		update_post_meta($pid, LF_PB_META_KEY, $config);
		return true;
	}
	$sections[$section_id] = $record;
	$config['sections'] = $sections;
	update_post_meta($pid, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Persist full homepage section row by section key.
 */
function lf_ai_apply_homepage_section_row(string $section_id, array $row): bool {
	$section_id = sanitize_text_field($section_id);
	if ($section_id === '' || !defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_get_homepage_section_config')) {
		return false;
	}
	$config = lf_get_homepage_section_config();
	if (!is_array($config[$section_id] ?? null)) {
		return false;
	}
	$config[$section_id] = $row;
	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	return true;
}

/**
 * Apply a field-value map to a specific AI context.
 */
function lf_ai_apply_changes_to_context(string $context_type, $context_id, array $changes): void {
	if (empty($changes)) {
		return;
	}
	$dom_updates = [];
	$image_updates = [];
	$order_updates = null;
	$layout_updates = [];
	$section_enabled_updates = [];
	$section_record_updates = [];
	$homepage_section_row_updates = [];
	$content_updates = [];
	foreach ($changes as $field_key => $value) {
		$key = (string) $field_key;
		if ($key === '__section_order') {
			if (is_array($value)) {
				$order_updates = $value;
			}
			continue;
		}
		if (str_starts_with($key, '__dom_override::')) {
			$selector = substr($key, strlen('__dom_override::'));
			$selector = sanitize_text_field((string) $selector);
			if ($selector !== '') {
				$dom_updates[$selector] = sanitize_textarea_field((string) $value);
			}
			continue;
		}
		if (str_starts_with($key, '__img_override::')) {
			$selector = substr($key, strlen('__img_override::'));
			$selector = sanitize_text_field((string) $selector);
			if ($selector !== '') {
				$image_updates[$selector] = is_array($value) ? $value : [];
			}
			continue;
		}
		if (str_starts_with($key, '__section_layout::')) {
			$section_id = sanitize_text_field((string) substr($key, strlen('__section_layout::')));
			$layout = sanitize_text_field((string) $value);
			if ($section_id !== '' && in_array($layout, ['content_media', 'media_content'], true)) {
				$layout_updates[$section_id] = $layout;
			}
			continue;
		}
		if (str_starts_with($key, '__section_enabled::')) {
			$section_id = sanitize_text_field((string) substr($key, strlen('__section_enabled::')));
			if ($section_id !== '') {
				$section_enabled_updates[$section_id] = !empty($value);
			}
			continue;
		}
		if (str_starts_with($key, '__section_record::')) {
			$section_id = sanitize_text_field((string) substr($key, strlen('__section_record::')));
			if ($section_id !== '') {
				$section_record_updates[$section_id] = is_array($value) ? $value : [];
			}
			continue;
		}
		if (str_starts_with($key, '__homepage_section_row::')) {
			$section_id = sanitize_text_field((string) substr($key, strlen('__homepage_section_row::')));
			if ($section_id !== '' && is_array($value)) {
				$homepage_section_row_updates[$section_id] = $value;
			}
			continue;
		}
		$content_updates[$key] = $value;
	}
	if (!empty($dom_updates)) {
		$current_overrides = lf_ai_get_inline_dom_overrides($context_type, $context_id);
		foreach ($dom_updates as $selector => $text_value) {
			if ($text_value === '') {
				unset($current_overrides[$selector]);
			} else {
				$current_overrides[$selector] = $text_value;
			}
		}
		lf_ai_set_inline_dom_overrides($context_type, $context_id, $current_overrides);
	}
	if (!empty($image_updates)) {
		$current_images = lf_ai_get_inline_image_overrides($context_type, $context_id);
		foreach ($image_updates as $selector => $row) {
			if (!is_array($row) || empty($row['url']) || (int) ($row['attachment_id'] ?? 0) <= 0) {
				unset($current_images[$selector]);
				continue;
			}
			$current_images[$selector] = [
				'attachment_id' => (int) ($row['attachment_id'] ?? 0),
				'url' => esc_url_raw((string) ($row['url'] ?? '')),
				'alt' => sanitize_text_field((string) ($row['alt'] ?? '')),
			];
		}
		lf_ai_set_inline_image_overrides($context_type, $context_id, $current_images);
	}
	if (is_array($order_updates)) {
		lf_ai_apply_section_order_to_context($context_type, $context_id, $order_updates);
	}
	if (!empty($layout_updates)) {
		foreach ($layout_updates as $section_id => $layout) {
			lf_ai_apply_section_layout_to_context($context_type, $context_id, (string) $section_id, (string) $layout);
		}
	}
	if (!empty($section_record_updates)) {
		foreach ($section_record_updates as $section_id => $record) {
			lf_ai_apply_section_record_to_context($context_type, $context_id, (string) $section_id, is_array($record) ? $record : []);
		}
	}
	if (!empty($section_enabled_updates)) {
		foreach ($section_enabled_updates as $section_id => $enabled) {
			lf_ai_apply_section_enabled_to_context($context_type, $context_id, (string) $section_id, (bool) $enabled);
		}
	}
	if (($context_type === 'homepage' || $context_id === 'homepage') && !empty($homepage_section_row_updates)) {
		foreach ($homepage_section_row_updates as $section_id => $row) {
			lf_ai_apply_homepage_section_row((string) $section_id, is_array($row) ? $row : []);
		}
	}
	if (empty($content_updates)) {
		return;
	}
	if ($context_type === 'homepage') {
		$hero_keys = ['hero_headline', 'hero_subheadline', 'cta_primary_override'];
		$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
		if (!empty($config['hero']) && is_array($config['hero'])) {
			foreach ($hero_keys as $hk) {
				if (array_key_exists($hk, $content_updates)) {
					$config['hero'][$hk] = $content_updates[$hk];
				}
			}
			if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
		}
		foreach ($content_updates as $field_key => $value) {
			if (in_array($field_key, $hero_keys, true)) {
				continue;
			}
			if (function_exists('update_field')) {
				update_field($field_key, $value, 'option');
			}
		}
		return;
	}
	if (!is_numeric($context_id)) {
		return;
	}
	$pid = (int) $context_id;
	$hero_updates = [];
	foreach ($content_updates as $field_key => $value) {
		if (in_array($field_key, ['hero_headline', 'hero_subheadline'], true)) {
			$hero_updates[$field_key] = $value;
		} elseif ($field_key === 'post_content') {
			wp_update_post(['ID' => $pid, 'post_content' => $value]);
		} elseif (function_exists('update_field')) {
			update_field($field_key, $value, $pid);
		}
	}
	if (!empty($hero_updates) && function_exists('lf_ai_update_pb_hero_settings_for_post')) {
		lf_ai_update_pb_hero_settings_for_post($pid, $hero_updates);
	}
}

/**
 * Rollback one AI action: restore old values and mark entry as rolled_back.
 */
function lf_ai_rollback(string $id): bool {
	$log = lf_ai_get_log();
	$found = null;
	$index = null;
	foreach ($log as $i => $entry) {
		if (($entry['id'] ?? '') === $id) {
			$found = $entry;
			$index = $i;
			break;
		}
	}
	if ($found === null || !empty($found['rolled_back'])) {
		return false;
	}
	$context_type = $found['context_type'] ?? '';
	$context_id   = $found['context_id'] ?? '';
	$action_type  = (string) ($found['action_type'] ?? 'edit');
	$old          = $found['changes_old'] ?? [];
	if ($action_type === 'create') {
		$created_ids = $found['created_post_ids'] ?? [];
		if (is_array($created_ids)) {
			foreach ($created_ids as $created_id) {
				$post_id = (int) $created_id;
				if ($post_id <= 0) {
					continue;
				}
				$post = get_post($post_id);
				// Safety: only remove drafts created by assistant rollback flow.
				if ($post && $post->post_status === 'draft') {
					wp_delete_post($post_id, true);
				}
			}
		}
		$log[$index]['rolled_back'] = true;
		update_option(LF_AI_LOG_OPTION, $log);
		return true;
	}
	lf_ai_apply_changes_to_context((string) $context_type, $context_id, is_array($old) ? $old : []);
	$log[$index]['rolled_back'] = true;
	update_option(LF_AI_LOG_OPTION, $log);
	return true;
}

/**
 * Re-apply one rolled-back AI edit action and mark it as active again.
 */
function lf_ai_redo(string $id): bool {
	$log = lf_ai_get_log();
	$found = null;
	$index = null;
	foreach ($log as $i => $entry) {
		if (($entry['id'] ?? '') === $id) {
			$found = $entry;
			$index = $i;
			break;
		}
	}
	if ($found === null || empty($found['rolled_back'])) {
		return false;
	}
	$action_type = (string) ($found['action_type'] ?? 'edit');
	if ($action_type === 'create') {
		// Create rollbacks delete draft posts, so redo is intentionally unsupported.
		return false;
	}
	$context_type = (string) ($found['context_type'] ?? '');
	$context_id = $found['context_id'] ?? '';
	$new = $found['changes_new'] ?? [];
	lf_ai_apply_changes_to_context($context_type, $context_id, is_array($new) ? $new : []);
	$log[$index]['rolled_back'] = false;
	update_option(LF_AI_LOG_OPTION, $log);
	return true;
}

/**
 * Find latest non-rolled-back log id for a context and user.
 */
function lf_ai_latest_rollback_candidate(string $context_type, $context_id, int $user_id): string {
	$context_id_string = (string) $context_id;
	foreach (lf_ai_get_log() as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		if (($entry['context_type'] ?? '') !== $context_type) {
			continue;
		}
		if ((string) ($entry['context_id'] ?? '') !== $context_id_string) {
			continue;
		}
		if (!empty($entry['rolled_back'])) {
			continue;
		}
		if ((int) ($entry['user_id'] ?? 0) !== $user_id) {
			continue;
		}
		$id = (string) ($entry['id'] ?? '');
		if ($id !== '') {
			return $id;
		}
	}
	return '';
}

/**
 * Find latest rolled-back log id for a context and user (redo candidate).
 */
function lf_ai_latest_redo_candidate(string $context_type, $context_id, int $user_id): string {
	$context_id_string = (string) $context_id;
	foreach (lf_ai_get_log() as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		if (($entry['context_type'] ?? '') !== $context_type) {
			continue;
		}
		if ((string) ($entry['context_id'] ?? '') !== $context_id_string) {
			continue;
		}
		if (empty($entry['rolled_back'])) {
			continue;
		}
		if ((int) ($entry['user_id'] ?? 0) !== $user_id) {
			continue;
		}
		if ((string) ($entry['action_type'] ?? 'edit') === 'create') {
			continue;
		}
		$id = (string) ($entry['id'] ?? '');
		if ($id !== '') {
			return $id;
		}
	}
	return '';
}
