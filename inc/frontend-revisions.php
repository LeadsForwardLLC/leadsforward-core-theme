<?php
/**
 * Front-end editor: layout revision history + restore (multi-user aware).
 *
 * Captures full page-builder config + inline DOM/image overrides after each save.
 * Uses a monotonic version for optimistic concurrency on restore.
 *
 * @package LeadsForward_Core
 * @since 0.1.24
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_FE_REVISION_MAX = 60;
const LF_FE_REVISION_META = '_lf_fe_layout_revisions';
const LF_FE_REVISION_VERSION_META = '_lf_fe_layout_version';
const LF_FE_REVISION_HOME_OPTION = 'lf_homepage_layout_revisions';
const LF_FE_REVISION_HOME_VERSION_OPTION = 'lf_homepage_layout_version';

/**
 * Skip recording when applying a restore (prevents recursive snapshots).
 */
function lf_fe_revision_internal(): bool {
	return !empty($GLOBALS['lf_fe_revision_internal']);
}

/**
 * @param callable():void $fn
 */
function lf_fe_revision_run_internal(callable $fn): void {
	$GLOBALS['lf_fe_revision_internal'] = true;
	try {
		$fn();
	} finally {
		unset($GLOBALS['lf_fe_revision_internal']);
	}
}

/**
 * Widget key matches lfAiFloating: homepage | (post_type, post_id).
 *
 * @return array{0:string,1:string}|null
 */
function lf_fe_revision_widget_key_for_post(\WP_Post $post): ?array {
	if ($post->post_type === 'page' && (int) $post->ID === (int) get_option('page_on_front')) {
		return ['homepage', 'homepage'];
	}
	return [(string) $post->post_type, (string) $post->ID];
}

function lf_fe_revision_storage_key(string $context_type, string $context_id): string {
	return $context_type . '|' . $context_id;
}

/**
 * @return array<string, mixed>
 */
function lf_fe_revision_capture_snapshot(string $context_type, string $context_id): array {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		$cfg = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
		$order = null;
		if (defined('LF_HOMEPAGE_ORDER_OPTION')) {
			$order = get_option(LF_HOMEPAGE_ORDER_OPTION, null);
		}
		return [
			'kind' => 'homepage',
			'homepage_config' => is_array($cfg) ? $cfg : [],
			'homepage_order' => is_array($order) ? $order : null,
			'inline_dom' => function_exists('lf_ai_get_inline_dom_overrides') ? lf_ai_get_inline_dom_overrides('homepage', 'homepage') : [],
			'inline_img' => function_exists('lf_ai_get_inline_image_overrides') ? lf_ai_get_inline_image_overrides('homepage', 'homepage') : [],
		];
	}
	$pid = absint($context_id);
	if ($pid <= 0) {
		return [];
	}
	$post = get_post($pid);
	if (!$post instanceof \WP_Post || !defined('LF_PB_META_KEY') || !function_exists('lf_pb_get_post_config') || !function_exists('lf_ai_pb_context_for_post')) {
		return [];
	}
	$pb_ctx = lf_ai_pb_context_for_post($post);
	if ($pb_ctx === '') {
		$pb = [];
	} else {
		$pb = lf_pb_get_post_config($pid, $pb_ctx);
	}
	return [
		'kind' => 'post',
		'post_id' => $pid,
		'pb' => is_array($pb) ? $pb : [],
		'inline_dom' => function_exists('lf_ai_get_inline_dom_overrides') ? lf_ai_get_inline_dom_overrides($context_type, $context_id) : [],
		'inline_img' => function_exists('lf_ai_get_inline_image_overrides') ? lf_ai_get_inline_image_overrides($context_type, $context_id) : [],
	];
}

function lf_fe_revision_fingerprint(array $snap): string {
	$copy = $snap;
	ksort($copy);
	return hash('sha256', wp_json_encode($copy));
}

function lf_fe_revision_user_can_edit(string $context_type, string $context_id): bool {
	$cap = defined('LF_AI_CAP') ? LF_AI_CAP : 'edit_posts';
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		return current_user_can($cap);
	}
	$pid = absint($context_id);
	return $pid > 0 && current_user_can('edit_post', $pid);
}

/**
 * @return list<array<string, mixed>>
 */
function lf_fe_revision_get_list(string $context_type, string $context_id): array {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		$raw = get_option(LF_FE_REVISION_HOME_OPTION, []);
		return is_array($raw) ? array_values($raw) : [];
	}
	$pid = absint($context_id);
	if ($pid <= 0) {
		return [];
	}
	$raw = get_post_meta($pid, LF_FE_REVISION_META, true);
	return is_array($raw) ? array_values($raw) : [];
}

function lf_fe_revision_set_list(string $context_type, string $context_id, array $list): void {
	$list = array_values(array_slice($list, 0, LF_FE_REVISION_MAX));
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		update_option(LF_FE_REVISION_HOME_OPTION, $list, false);
		return;
	}
	$pid = absint($context_id);
	if ($pid > 0) {
		update_post_meta($pid, LF_FE_REVISION_META, $list);
	}
}

function lf_fe_revision_get_version(string $context_type, string $context_id): int {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		return (int) get_option(LF_FE_REVISION_HOME_VERSION_OPTION, 0);
	}
	$pid = absint($context_id);
	if ($pid <= 0) {
		return 0;
	}
	return (int) get_post_meta($pid, LF_FE_REVISION_VERSION_META, true);
}

function lf_fe_revision_set_version(string $context_type, string $context_id, int $v): void {
	if ($context_type === 'homepage' || $context_id === 'homepage') {
		update_option(LF_FE_REVISION_HOME_VERSION_OPTION, $v, false);
		return;
	}
	$pid = absint($context_id);
	if ($pid > 0) {
		update_post_meta($pid, LF_FE_REVISION_VERSION_META, $v);
	}
}

/**
 * Append snapshot if it differs from the latest (dedupe).
 */
function lf_fe_revision_maybe_append(string $context_type, string $context_id, string $summary = ''): void {
	if (lf_fe_revision_internal()) {
		return;
	}
	if (!lf_fe_revision_user_can_edit($context_type, $context_id)) {
		return;
	}
	$snap = lf_fe_revision_capture_snapshot($context_type, $context_id);
	if ($snap === []) {
		return;
	}
	$fp = lf_fe_revision_fingerprint($snap);
	$list = lf_fe_revision_get_list($context_type, $context_id);
	if ($list !== []) {
		$last = $list[0];
		if (is_array($last) && ($last['fingerprint'] ?? '') === $fp) {
			return;
		}
	}
	$ver = lf_fe_revision_get_version($context_type, $context_id) + 1;
	lf_fe_revision_set_version($context_type, $context_id, $ver);
	$uid = get_current_user_id();
	$user = $uid > 0 ? get_userdata($uid) : false;
	$entry = [
		'id' => 'rev_' . gmdate('Ymd_His') . '_' . wp_generate_password(6, false, false),
		'time' => time(),
		'user_id' => $uid,
		'user_name' => $user instanceof \WP_User ? $user->display_name : '',
		'summary' => $summary !== '' ? $summary : __('Saved', 'leadsforward-core'),
		'fingerprint' => $fp,
		'snapshot' => $snap,
		'layout_version' => $ver,
	];
	array_unshift($list, $entry);
	$list = array_slice($list, 0, LF_FE_REVISION_MAX);
	lf_fe_revision_set_list($context_type, $context_id, $list);
}

/** @var array<string, true> */
function lf_fe_revision_mark_dirty(string $context_type, string $context_id): void {
	if (lf_fe_revision_internal()) {
		return;
	}
	$key = lf_fe_revision_storage_key($context_type, $context_id);
	if (!isset($GLOBALS['lf_fe_revision_dirty'])) {
		$GLOBALS['lf_fe_revision_dirty'] = [];
	}
	$GLOBALS['lf_fe_revision_dirty'][ $key ] = true;
}

function lf_fe_revision_flush_dirty(): void {
	$dirty = $GLOBALS['lf_fe_revision_dirty'] ?? [];
	if (!is_array($dirty) || $dirty === []) {
		return;
	}
	foreach (array_keys($dirty) as $key) {
		if (!is_string($key)) {
			continue;
		}
		$parts = explode('|', $key, 2);
		if (count($parts) !== 2) {
			continue;
		}
		lf_fe_revision_maybe_append($parts[0], $parts[1]);
	}
	$GLOBALS['lf_fe_revision_dirty'] = [];
}

/**
 * @return array{ok:bool,message?:string,new_version?:int}
 */
function lf_fe_revision_restore(string $context_type, string $context_id, string $rev_id, int $client_version): array {
	if (!lf_fe_revision_user_can_edit($context_type, $context_id)) {
		return ['ok' => false, 'message' => __('Permission denied.', 'leadsforward-core')];
	}
	$server_v = lf_fe_revision_get_version($context_type, $context_id);
	if ($client_version !== $server_v) {
		return [
			'ok' => false,
			'message' => __('Someone else saved changes while you were editing. Refresh the page, then try restoring again.', 'leadsforward-core'),
		];
	}
	$list = lf_fe_revision_get_list($context_type, $context_id);
	$found = null;
	foreach ($list as $row) {
		if (is_array($row) && (string) ($row['id'] ?? '') === $rev_id) {
			$found = $row;
			break;
		}
	}
	if (!is_array($found) || empty($found['snapshot']) || !is_array($found['snapshot'])) {
		return ['ok' => false, 'message' => __('That revision was not found.', 'leadsforward-core')];
	}
	$snap = $found['snapshot'];
	$ok = false;
	lf_fe_revision_run_internal(static function () use ($context_type, $context_id, $snap, &$ok): void {
		if (($snap['kind'] ?? '') === 'homepage') {
			if (defined('LF_HOMEPAGE_CONFIG_OPTION') && isset($snap['homepage_config']) && is_array($snap['homepage_config'])) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $snap['homepage_config'], true);
			}
			if (defined('LF_HOMEPAGE_ORDER_OPTION') && isset($snap['homepage_order']) && is_array($snap['homepage_order'])) {
				update_option(LF_HOMEPAGE_ORDER_OPTION, $snap['homepage_order'], true);
			}
			if (function_exists('lf_ai_set_inline_dom_overrides')) {
				lf_ai_set_inline_dom_overrides('homepage', 'homepage', is_array($snap['inline_dom'] ?? null) ? $snap['inline_dom'] : []);
			}
			if (function_exists('lf_ai_set_inline_image_overrides')) {
				lf_ai_set_inline_image_overrides('homepage', 'homepage', is_array($snap['inline_img'] ?? null) ? $snap['inline_img'] : []);
			}
			$ok = true;
			return;
		}
		$pid = (int) ($snap['post_id'] ?? 0);
		if ($pid <= 0 || !defined('LF_PB_META_KEY')) {
			return;
		}
		$post = get_post($pid);
		if (!$post instanceof \WP_Post || !function_exists('lf_ai_pb_context_for_post')) {
			return;
		}
		if (isset($snap['pb']) && is_array($snap['pb'])) {
			update_post_meta($pid, LF_PB_META_KEY, $snap['pb']);
		}
		if (function_exists('lf_ai_set_inline_dom_overrides')) {
			lf_ai_set_inline_dom_overrides($context_type, $context_id, is_array($snap['inline_dom'] ?? null) ? $snap['inline_dom'] : []);
		}
		if (function_exists('lf_ai_set_inline_image_overrides')) {
			lf_ai_set_inline_image_overrides($context_type, $context_id, is_array($snap['inline_img'] ?? null) ? $snap['inline_img'] : []);
		}
		$ok = true;
	});
	if (!$ok) {
		return ['ok' => false, 'message' => __('Could not apply that revision.', 'leadsforward-core')];
	}
	$new_v = $server_v + 1;
	lf_fe_revision_set_version($context_type, $context_id, $new_v);
	$uid = get_current_user_id();
	$user = $uid > 0 ? get_userdata($uid) : false;
	$after_snap = lf_fe_revision_capture_snapshot($context_type, $context_id);
	$restore_entry = [
		'id' => 'rev_' . gmdate('Ymd_His') . '_' . wp_generate_password(6, false, false),
		'time' => time(),
		'user_id' => $uid,
		'user_name' => $user instanceof \WP_User ? $user->display_name : '',
		/* translators: %s: short revision id */
		'summary' => sprintf(__('Restored revision %s', 'leadsforward-core'), $rev_id),
		'fingerprint' => lf_fe_revision_fingerprint($after_snap),
		'snapshot' => $after_snap,
		'layout_version' => $new_v,
	];
	array_unshift($list, $restore_entry);
	$list = array_slice($list, 0, LF_FE_REVISION_MAX);
	lf_fe_revision_set_list($context_type, $context_id, $list);
	return ['ok' => true, 'new_version' => $new_v];
}

function lf_fe_revision_on_updated_post_meta($meta_id, $object_id, $meta_key, $_meta_value): void {
	if (lf_fe_revision_internal()) {
		return;
	}
	$watch = [LF_PB_META_KEY, LF_AI_INLINE_OVERRIDES_META_KEY, LF_AI_INLINE_IMAGE_OVERRIDES_META_KEY];
	if (!in_array($meta_key, $watch, true)) {
		return;
	}
	$post = get_post((int) $object_id);
	if (!$post instanceof \WP_Post) {
		return;
	}
	$key = lf_fe_revision_widget_key_for_post($post);
	if ($key === null) {
		return;
	}
	lf_fe_revision_mark_dirty($key[0], $key[1]);
}

/**
 * @param mixed $old_value
 * @param mixed $value
 */
function lf_fe_revision_on_updated_option(string $option, $old_value, $value): void {
	if (lf_fe_revision_internal()) {
		return;
	}
	$home_opts = [
		'lf_homepage_section_config',
		LF_AI_INLINE_OVERRIDES_OPTION,
		LF_AI_INLINE_IMAGE_OVERRIDES_OPTION,
	];
	if (defined('LF_HOMEPAGE_ORDER_OPTION')) {
		$home_opts[] = LF_HOMEPAGE_ORDER_OPTION;
	}
	if (!in_array($option, $home_opts, true)) {
		return;
	}
	lf_fe_revision_mark_dirty('homepage', 'homepage');
}

function lf_fe_ajax_revision_list(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	$cap = defined('LF_AI_CAP') ? LF_AI_CAP : 'edit_posts';
	if (!current_user_can($cap)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field((string) wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field((string) wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid context.', 'leadsforward-core')]);
	}
	if (!lf_fe_revision_user_can_edit($context_type, $context_id)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$list = lf_fe_revision_get_list($context_type, $context_id);
	$out = [];
	foreach ($list as $row) {
		if (!is_array($row)) {
			continue;
		}
		$out[] = [
			'id' => (string) ($row['id'] ?? ''),
			'time' => (int) ($row['time'] ?? 0),
			'user_name' => (string) ($row['user_name'] ?? ''),
			'summary' => (string) ($row['summary'] ?? ''),
			'layout_version' => (int) ($row['layout_version'] ?? 0),
		];
	}
	wp_send_json_success([
		'revisions' => $out,
		'layout_version' => lf_fe_revision_get_version($context_type, $context_id),
	]);
}

function lf_fe_ajax_revision_restore(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	$cap = defined('LF_AI_CAP') ? LF_AI_CAP : 'edit_posts';
	if (!current_user_can($cap)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field((string) wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field((string) wp_unslash($_POST['context_id'])) : '';
	$rev_id = isset($_POST['revision_id']) ? sanitize_text_field((string) wp_unslash($_POST['revision_id'])) : '';
	$client_version = isset($_POST['layout_version']) ? absint($_POST['layout_version']) : 0;
	if ($context_type === '' || $context_id === '' || $rev_id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	if (!lf_fe_revision_user_can_edit($context_type, $context_id)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$res = lf_fe_revision_restore($context_type, $context_id, $rev_id, $client_version);
	if (empty($res['ok'])) {
		wp_send_json_error(['message' => (string) ($res['message'] ?? __('Restore failed.', 'leadsforward-core'))]);
	}
	wp_send_json_success([
		'message' => __('Revision restored. Reloading…', 'leadsforward-core'),
		'layout_version' => (int) ($res['new_version'] ?? 0),
	]);
}

add_action('updated_post_meta', 'lf_fe_revision_on_updated_post_meta', 50, 4);
add_action('updated_option', 'lf_fe_revision_on_updated_option', 50, 3);
add_action('shutdown', 'lf_fe_revision_flush_dirty', 99999);

function lf_fe_ajax_revision_ping(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	$cap = defined('LF_AI_CAP') ? LF_AI_CAP : 'edit_posts';
	if (!current_user_can($cap)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field((string) wp_unslash($_POST['context_type'])) : '';
	$context_id = isset($_POST['context_id']) ? sanitize_text_field((string) wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '' || !lf_fe_revision_user_can_edit($context_type, $context_id)) {
		wp_send_json_error(['message' => __('Invalid context.', 'leadsforward-core')]);
	}
	wp_send_json_success(['layout_version' => lf_fe_revision_get_version($context_type, $context_id)]);
}

add_action('wp_ajax_lf_fe_revision_list', 'lf_fe_ajax_revision_list');
add_action('wp_ajax_lf_fe_revision_restore', 'lf_fe_ajax_revision_restore');
add_action('wp_ajax_lf_fe_revision_ping', 'lf_fe_ajax_revision_ping');
