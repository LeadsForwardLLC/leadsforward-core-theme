<?php
/**
 * Wiring report: registry -> blueprint -> payload checks.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_ai_studio_wiring_check_registry(array $registry): array {
	$issues = [];
	foreach ($registry as $section_id => $section) {
		if (!is_array($section)) {
			$issues[] = "Section {$section_id} must be an array.";
			continue;
		}
		$fields = $section['fields'] ?? [];
		if (!is_array($fields) || empty($fields)) {
			$issues[] = "Section {$section_id} has no fields.";
			continue;
		}
		foreach ($fields as $field) {
			if (!is_array($field)) {
				$issues[] = "Section {$section_id} has a non-array field definition.";
				continue;
			}
			$key = (string) ($field['key'] ?? '');
			$type = (string) ($field['type'] ?? '');
			$label = (string) ($field['label'] ?? '');
			if ($key === '' || $type === '' || $label === '') {
				$issues[] = "Section {$section_id} has an incomplete field definition.";
			}
		}
		$render = (string) ($section['render'] ?? '');
		if ($render !== '' && !function_exists($render)) {
			$issues[] = "Section {$section_id} render function missing: {$render}.";
		}
	}
	return $issues;
}

function lf_ai_studio_wiring_check_blueprints(array $payload): array {
	$issues = [];
	$blueprints = $payload['blueprints'] ?? [];
	if (!is_array($blueprints) || empty($blueprints)) {
		return ['Payload missing blueprints[].'];
	}
	foreach ($blueprints as $idx => $blueprint) {
		if (!is_array($blueprint)) {
			$issues[] = "Blueprint {$idx} must be an object.";
			continue;
		}
		$sections = $blueprint['sections'] ?? [];
		if (!is_array($sections) || empty($sections)) {
			$page = (string) ($blueprint['page'] ?? $blueprint['page_type'] ?? 'unknown');
			$issues[] = "Blueprint {$idx} ({$page}) missing sections[].";
			continue;
		}
		foreach ($sections as $section) {
			$section_id = (string) ($section['section_id'] ?? '');
			$allowed = $section['allowed_field_keys'] ?? [];
			if ($section_id === '' || !is_array($allowed) || empty($allowed)) {
				$issues[] = "Blueprint {$idx} section has missing section_id or allowed_field_keys.";
			}
		}
	}
	return $issues;
}

function lf_ai_studio_wiring_payload_summary(array $payload): array {
	$blueprints = is_array($payload['blueprints'] ?? null) ? $payload['blueprints'] : [];
	$page_types = [];
	foreach ($blueprints as $bp) {
		if (!is_array($bp)) {
			continue;
		}
		$page = (string) ($bp['page'] ?? $bp['page_type'] ?? '');
		if ($page !== '') {
			$page_types[ $page ] = ( $page_types[ $page ] ?? 0 ) + 1;
		}
	}
	return [
		'count'        => count($blueprints),
		'page_types'   => $page_types,
		'generation_scope' => is_array($payload['generation_scope'] ?? null) ? $payload['generation_scope'] : [],
	];
}

function lf_ai_studio_wiring_report(): array {
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$payload = function_exists('lf_ai_studio_build_full_site_payload') ? lf_ai_studio_build_full_site_payload(true) : [];
	$registry_issues = lf_ai_studio_wiring_check_registry(is_array($registry) ? $registry : []);
	$blueprint_issues = lf_ai_studio_wiring_check_blueprints(is_array($payload) ? $payload : []);
	$payload_summary = lf_ai_studio_wiring_payload_summary(is_array($payload) ? $payload : []);

	return [
		'registry_issues'  => $registry_issues,
		'blueprint_issues' => $blueprint_issues,
		'payload_summary'  => $payload_summary,
		'timestamp'        => time(),
	];
}
