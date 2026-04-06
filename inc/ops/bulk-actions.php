<?php
/**
 * Bulk actions: variation profile, CTA site-wide, schema toggles, rebuild linking. Preview + confirm.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_ops_bulk_handle');

function lf_ops_bulk_handle(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (isset($_POST['lf_ops_bulk_preview']) && isset($_POST['lf_ops_bulk_action'])) {
		check_admin_referer('lf_ops_bulk', 'lf_ops_bulk_nonce');
		$action = sanitize_text_field(wp_unslash($_POST['lf_ops_bulk_action']));
		if (in_array($action, ['design_preset', 'cta_sitewide', 'schema_toggles', 'rebuild_linking'], true)) {
			wp_safe_redirect(admin_url('admin.php?page=lf-ops-bulk&preview=' . rawurlencode($action)));
			exit;
		}
	}
	$action = isset($_POST['lf_ops_bulk_action']) ? sanitize_text_field(wp_unslash($_POST['lf_ops_bulk_action'])) : '';
	if ($action === '') {
		return;
	}
	check_admin_referer('lf_ops_bulk', 'lf_ops_bulk_nonce');
	if (empty($_POST['lf_ops_bulk_confirm']) || $_POST['lf_ops_bulk_confirm'] !== '1') {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops-bulk&error=confirm'));
		exit;
	}

	switch ($action) {
		case 'design_preset':
			$preset = isset($_POST['lf_ops_design_preset']) ? sanitize_text_field(wp_unslash($_POST['lf_ops_design_preset'])) : '';
			$prev = [
				'design_preset' => get_option('lf_global_design_preset', 'clean-precision'),
				'variation_profile' => function_exists('get_field') ? get_field('variation_profile', 'option') : null,
			];
			if (function_exists('lf_apply_global_design_preset') && lf_apply_global_design_preset($preset)) {
				$profile = function_exists('lf_design_preset_to_variation_profile')
					? lf_design_preset_to_variation_profile($preset)
					: 'a';
				lf_ops_audit_log('bulk_design_preset', ['design_preset' => $preset, 'variation_profile' => $profile], $prev);
			}
			break;
		case 'cta_sitewide':
			$primary = isset($_POST['lf_ops_cta_primary']) ? sanitize_text_field(wp_unslash($_POST['lf_ops_cta_primary'])) : '';
			$secondary = isset($_POST['lf_ops_cta_secondary']) ? sanitize_text_field(wp_unslash($_POST['lf_ops_cta_secondary'])) : '';
			$prev = [];
			if (function_exists('get_field')) {
				$prev['lf_cta_primary_text'] = get_field('lf_cta_primary_text', 'option');
				$prev['lf_cta_secondary_text'] = get_field('lf_cta_secondary_text', 'option');
			}
			if (function_exists('update_field')) {
				if ($primary !== '') {
					update_field('lf_cta_primary_text', $primary, 'option');
				}
				if ($secondary !== '') {
					update_field('lf_cta_secondary_text', $secondary, 'option');
				}
			}
			lf_ops_audit_log('bulk_cta_sitewide', ['primary' => $primary, 'secondary' => $secondary], $prev);
			break;
		case 'schema_toggles':
			$toggles = [
				'lf_schema_organization'   => isset($_POST['lf_schema_organization']),
				'lf_schema_local_business' => isset($_POST['lf_schema_local_business']),
				'lf_schema_faq'            => isset($_POST['lf_schema_faq']),
				'lf_schema_review'         => isset($_POST['lf_schema_review']),
			];
			$prev = [];
			if (function_exists('get_field')) {
				foreach (array_keys($toggles) as $k) {
					$prev[$k] = get_field($k, 'option');
				}
			}
			if (function_exists('update_field')) {
				foreach ($toggles as $key => $on) {
					update_field($key, $on, 'option');
				}
			}
			lf_ops_audit_log('bulk_schema_toggles', $toggles, $prev);
			break;
		case 'rebuild_linking':
			$result = lf_ops_bulk_rebuild_linking();
			lf_ops_audit_log('bulk_rebuild_linking', $result, []);
			break;
		default:
			wp_safe_redirect(admin_url('admin.php?page=lf-ops-bulk&error=invalid'));
			return;
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-ops-bulk&done=1'));
	exit;
}

/**
 * Rebuild service ↔ service area relationships. No slug/URL changes.
 */
function lf_ops_bulk_rebuild_linking(): array {
	$services = get_posts([
		'post_type'      => 'lf_service',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	$areas = get_posts([
		'post_type'      => 'lf_service_area',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	if (!function_exists('update_field')) {
		return ['services' => 0, 'areas' => 0, 'error' => 'ACF not available'];
	}
	foreach ($areas as $aid) {
		update_field('lf_service_area_services', $services, $aid);
	}
	return ['services' => 0, 'areas' => count($areas)];
}

function lf_ops_bulk_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
	$done = isset($_GET['done']);
	$preview_action = isset($_GET['preview']) ? sanitize_text_field($_GET['preview']) : '';

	echo '<div class="wrap"><h1>' . esc_html__('Bulk Actions', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Apply site-wide changes with preview. No bulk delete or slug edits.', 'leadsforward-core') . '</p>';
	echo '<p class="description">' . esc_html__('Design presets sync Global Design + variation profile. You can also change the preset from the block editor (⋮ menu → LeadsForward design) or Global Settings.', 'leadsforward-core') . '</p>';
	if ($done) {
		echo '<div class="notice notice-success"><p>' . esc_html__('Action completed.', 'leadsforward-core') . '</p></div>';
	}
	if ($error === 'confirm') {
		echo '<div class="notice notice-error"><p>' . esc_html__('You must confirm before applying.', 'leadsforward-core') . '</p></div>';
	}
	if ($error === 'invalid') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Invalid action.', 'leadsforward-core') . '</p></div>';
	}

	$design_presets = function_exists('lf_design_presets') ? lf_design_presets() : [];
	if (empty($design_presets)) {
		$design_presets = [
			'clean-precision' => __('Clean Precision', 'leadsforward-core'),
			'bold-authority' => __('Bold Authority', 'leadsforward-core'),
			'friendly-approachable' => __('Friendly & Approachable', 'leadsforward-core'),
			'high-contrast' => __('High-Contrast Conversion Engine', 'leadsforward-core'),
			'modern-edge' => __('Modern Edge', 'leadsforward-core'),
			'structured-modular' => __('Structured Modular', 'leadsforward-core'),
		];
	}
	$current_preset = (string) get_option('lf_global_design_preset', 'clean-precision');
	if (!isset($design_presets[$current_preset])) {
		$current_preset = array_key_first($design_presets);
	}
	$profile_labels = function_exists('lf_variation_profile_labels') ? lf_variation_profile_labels() : [];
	$current_profile = function_exists('lf_design_preset_to_variation_profile')
		? lf_design_preset_to_variation_profile($current_preset)
		: 'a';
	$current_profile_label = $profile_labels[$current_profile] ?? strtoupper((string) $current_profile);
	$current_primary = function_exists('get_field') ? get_field('lf_cta_primary_text', 'option') : '';
	$current_secondary = function_exists('get_field') ? get_field('lf_cta_secondary_text', 'option') : '';
	$schema_current = [];
	if (function_exists('get_field')) {
		foreach (['lf_schema_organization', 'lf_schema_local_business', 'lf_schema_faq', 'lf_schema_review'] as $k) {
			$schema_current[$k] = (bool) get_field($k, 'option');
		}
	}
	$linking_preview = ['services' => 0, 'areas' => 0];
	$services = get_posts(['post_type' => 'lf_service', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
	$areas = get_posts(['post_type' => 'lf_service_area', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
	if (is_array($services)) {
		$linking_preview['services'] = count($services);
	}
	if (is_array($areas)) {
		$linking_preview['areas'] = count($areas);
	}

	// --- Design preset ---
	echo '<div class="card" style="max-width:600px; margin:1em 0;"><h2>' . esc_html__('Reassign design preset', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Syncs Global Design presets with the variation profile (block variants + section order).', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_bulk', 'lf_ops_bulk_nonce');
	echo '<input type="hidden" name="lf_ops_bulk_action" value="design_preset" />';
	echo '<p><label>' . esc_html__('Design preset:', 'leadsforward-core') . ' <select name="lf_ops_design_preset">';
	foreach ($design_presets as $slug => $label) {
		echo '<option value="' . esc_attr($slug) . '"' . selected($current_preset, $slug, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p class="description">' . esc_html(sprintf(__('Synced variation profile: %s', 'leadsforward-core'), $current_profile_label)) . '</p>';
	if ($preview_action === 'design_preset') {
		echo '<p><strong>' . esc_html__('Preview:', 'leadsforward-core') . '</strong> ' . esc_html__('Design preset and variation profile will be updated site-wide. No URLs or slugs changed.', 'leadsforward-core') . '</p>';
	}
	echo '<p><label><input type="checkbox" name="lf_ops_bulk_confirm" value="1" required /> ' . esc_html__('I understand this will overwrite the current setting.', 'leadsforward-core') . '</label></p>';
	echo '<p><input type="submit" name="lf_ops_bulk_preview" class="button" value="' . esc_attr__('Preview changes', 'leadsforward-core') . '" /> <input type="submit" class="button button-primary" value="' . esc_attr__('Apply', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';

	// --- CTA site-wide ---
	echo '<div class="card" style="max-width:600px; margin:1em 0;"><h2>' . esc_html__('Update CTA text site-wide', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Change global primary and/or secondary CTA text.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_bulk', 'lf_ops_bulk_nonce');
	echo '<input type="hidden" name="lf_ops_bulk_action" value="cta_sitewide" />';
	echo '<p><label>' . esc_html__('Primary CTA:', 'leadsforward-core') . ' <input type="text" name="lf_ops_cta_primary" class="regular-text" value="' . esc_attr($current_primary) . '" placeholder="' . esc_attr__('Leave blank to keep current', 'leadsforward-core') . '" /></label></p>';
	echo '<p><label>' . esc_html__('Secondary CTA:', 'leadsforward-core') . ' <input type="text" name="lf_ops_cta_secondary" class="regular-text" value="' . esc_attr($current_secondary) . '" placeholder="' . esc_attr__('Leave blank to keep current', 'leadsforward-core') . '" /></label></p>';
	if ($preview_action === 'cta_sitewide') {
		echo '<p><strong>' . esc_html__('Preview:', 'leadsforward-core') . '</strong> ' . esc_html__('Global CTA options will be updated. No per-post slugs changed.', 'leadsforward-core') . '</p>';
	}
	echo '<p><label><input type="checkbox" name="lf_ops_bulk_confirm" value="1" required /> ' . esc_html__('I understand this will overwrite existing CTA text.', 'leadsforward-core') . '</label></p>';
	echo '<p><input type="submit" name="lf_ops_bulk_preview" class="button" value="' . esc_attr__('Preview changes', 'leadsforward-core') . '" /> <input type="submit" class="button button-primary" value="' . esc_attr__('Apply', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';

	// --- Schema toggles ---
	echo '<div class="card" style="max-width:600px; margin:1em 0;"><h2>' . esc_html__('Toggle schema types site-wide', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Enable or disable schema output per type. No schema content rewritten.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_bulk', 'lf_ops_bulk_nonce');
	echo '<input type="hidden" name="lf_ops_bulk_action" value="schema_toggles" />';
	$schema_labels = ['lf_schema_organization' => __('Organization', 'leadsforward-core'), 'lf_schema_local_business' => __('LocalBusiness', 'leadsforward-core'), 'lf_schema_faq' => __('FAQ', 'leadsforward-core'), 'lf_schema_review' => __('Review', 'leadsforward-core')];
	echo '<ul style="list-style:none;">';
	foreach ($schema_labels as $key => $label) {
		$checked = !empty($schema_current[$key]);
		echo '<li><label><input type="checkbox" name="' . esc_attr($key) . '" value="1"' . ($checked ? ' checked' : '') . ' /> ' . esc_html($label) . '</label></li>';
	}
	echo '</ul>';
	if ($preview_action === 'schema_toggles') {
		echo '<p><strong>' . esc_html__('Preview:', 'leadsforward-core') . '</strong> ' . esc_html__('Schema toggles will be updated. No schema content rewritten.', 'leadsforward-core') . '</p>';
	}
	echo '<p><label><input type="checkbox" name="lf_ops_bulk_confirm" value="1" required /> ' . esc_html__('I understand this will update schema toggles.', 'leadsforward-core') . '</label></p>';
	echo '<p><input type="submit" name="lf_ops_bulk_preview" class="button" value="' . esc_attr__('Preview changes', 'leadsforward-core') . '" /> <input type="submit" class="button button-primary" value="' . esc_attr__('Apply', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';

	// --- Rebuild linking ---
	echo '<div class="card" style="max-width:600px; margin:1em 0;"><h2>' . esc_html__('Rebuild internal linking relationships', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Re-link all services to all service areas (and vice versa). Use after adding new services/areas. No slugs or URLs changed.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_bulk', 'lf_ops_bulk_nonce');
	echo '<input type="hidden" name="lf_ops_bulk_action" value="rebuild_linking" />';
	echo '<p><strong>' . esc_html__('Preview:', 'leadsforward-core') . '</strong> ' . sprintf(esc_html__('%1$s services and %2$s areas will be cross-linked.', 'leadsforward-core'), (int) $linking_preview['services'], (int) $linking_preview['areas']) . '</p>';
	if ($preview_action === 'rebuild_linking') {
		echo '<p><em>' . esc_html__('Dry run: no changes made. Confirm and click Apply to execute.', 'leadsforward-core') . '</em></p>';
	}
	echo '<p><label><input type="checkbox" name="lf_ops_bulk_confirm" value="1" required /> ' . esc_html__('I understand this will overwrite existing service–area relationships.', 'leadsforward-core') . '</label></p>';
	echo '<p><input type="submit" name="lf_ops_bulk_preview" class="button" value="' . esc_attr__('Preview changes', 'leadsforward-core') . '" /> <input type="submit" class="button button-primary" value="' . esc_attr__('Apply', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';

	echo '</div>';
}
