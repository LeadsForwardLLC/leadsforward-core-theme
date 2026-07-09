<?php
/**
 * Site Builder — Airtable/template path without n8n orchestrator.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

lf_load_inc('site-builder/writer-guidance.php');
lf_load_inc('site-builder/service-cards.php');
lf_load_inc('site-builder/niche-media-pack.php');

/**
 * Build a templated site from a normalized manifest (no orchestrator).
 *
 * @param array<string, mixed> $manifest
 * @return array{ok: bool, error?: string, scaffold?: array<string, mixed>, guidance?: array<string, int>, media?: array<string, mixed>}
 */
function lf_site_builder_run_from_manifest(array $manifest): array {
	if ($manifest === []) {
		return ['ok' => false, 'error' => __('Manifest is empty.', 'leadsforward-core')];
	}
	if (!function_exists('lf_ai_studio_scaffold_manifest')) {
		return ['ok' => false, 'error' => __('Scaffold runner not available.', 'leadsforward-core')];
	}

	$scaffold = lf_ai_studio_scaffold_manifest($manifest);
	if (empty($scaffold['success'])) {
		$message = (string) ($scaffold['message'] ?? __('Manifest scaffold failed.', 'leadsforward-core'));
		$home_page = function_exists('lf_fleet_find_page_by_slug')
			? lf_fleet_find_page_by_slug('home')
			: get_page_by_path('home', OBJECT, 'page');
		if (!$home_page instanceof \WP_Post) {
			return ['ok' => false, 'error' => $message, 'scaffold' => $scaffold];
		}
		$scaffold['success'] = true;
		$scaffold['message'] = __('Setup completed with warnings.', 'leadsforward-core');
	}

	if (function_exists('lf_publish_schedule_seed_defaults_if_empty')) {
		lf_publish_schedule_seed_defaults_if_empty();
	}

	if (function_exists('lf_ai_studio_sync_manifest_posts')) {
		lf_ai_studio_sync_manifest_posts($manifest);
	} elseif (function_exists('lf_publish_schedule_apply_site_pages')) {
		lf_publish_schedule_apply_site_pages();
	}
	if (function_exists('lf_ai_studio_ensure_core_page_sections')) {
		lf_ai_studio_ensure_core_page_sections($manifest, true);
	}
	if (function_exists('lf_site_builder_sync_service_card_sections')) {
		lf_site_builder_sync_service_card_sections();
	}

	$media = lf_site_builder_apply_niche_media_pack($manifest);
	$guidance = lf_site_builder_fill_writer_guidance();
	if (function_exists('lf_site_builder_strip_writer_placeholders')) {
		lf_site_builder_strip_writer_placeholders();
	}
	if (function_exists('lf_sections_purge_hidden_non_editable_fields_site_wide')) {
		lf_sections_purge_hidden_non_editable_fields_site_wide();
	}

	$summary = [
		'timestamp' => time(),
		'niche' => (string) ($media['niche'] ?? ''),
		'guidance' => $guidance,
		'media' => $media,
	];
	update_option('lf_site_builder_last_run', $summary, false);

	return [
		'ok' => true,
		'scaffold' => $scaffold,
		'guidance' => $guidance,
		'media' => $media,
	];
}
