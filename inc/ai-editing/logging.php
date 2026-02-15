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
 * Apply a field-value map to a specific AI context.
 */
function lf_ai_apply_changes_to_context(string $context_type, $context_id, array $changes): void {
	if (empty($changes)) {
		return;
	}
	if ($context_type === 'homepage') {
		$hero_keys = ['hero_headline', 'hero_subheadline', 'cta_primary_override'];
		$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
		if (!empty($config['hero']) && is_array($config['hero'])) {
			foreach ($hero_keys as $hk) {
				if (array_key_exists($hk, $changes)) {
					$config['hero'][$hk] = $changes[$hk];
				}
			}
			if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
		}
		foreach ($changes as $field_key => $value) {
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
	foreach ($changes as $field_key => $value) {
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
