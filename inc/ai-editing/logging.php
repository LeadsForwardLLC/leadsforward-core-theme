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
	$old          = $found['changes_old'] ?? [];
	if ($context_type === 'homepage') {
		$hero_keys = ['hero_headline', 'hero_subheadline', 'hero_cta_override'];
		$sections = function_exists('get_field') ? get_field('homepage_sections', 'option') : [];
		if (!empty($sections) && is_array($sections)) {
			foreach ($sections as $i => $row) {
				if (($row['section_type'] ?? '') === 'hero') {
					foreach ($hero_keys as $hk) {
						if (array_key_exists($hk, $old)) {
							$sections[$i][$hk] = $old[$hk];
						}
					}
					if (function_exists('update_field')) {
						update_field('homepage_sections', $sections, 'option');
					}
					break;
				}
			}
		}
		foreach ($old as $field_key => $value) {
			if (in_array($field_key, $hero_keys, true)) {
				continue;
			}
			if (function_exists('update_field')) {
				update_field($field_key, $value, 'option');
			}
		}
	} elseif (is_numeric($context_id)) {
		$pid = (int) $context_id;
		foreach ($old as $field_key => $value) {
			if ($field_key === 'post_content') {
				wp_update_post(['ID' => $pid, 'post_content' => $value]);
			} elseif (function_exists('update_field')) {
				update_field($field_key, $value, $pid);
			}
		}
	}
	$log[$index]['rolled_back'] = true;
	update_option(LF_AI_LOG_OPTION, $log);
	return true;
}
