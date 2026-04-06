<?php
/**
 * Block editor: global design preset sidebar + REST save.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('rest_api_init', 'lf_editor_design_preset_register_rest');
add_action('enqueue_block_editor_assets', 'lf_editor_design_preset_enqueue');

function lf_editor_design_preset_register_rest(): void {
	register_rest_route(
		'leadsforward/v1',
		'/design-preset',
		[
			'methods'             => 'POST',
			'permission_callback' => static function (): bool {
				return current_user_can('edit_theme_options');
			},
			'callback'            => 'lf_editor_design_preset_rest_save',
			'args'                => [
				'preset' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]
	);
}

/**
 * @param \WP_REST_Request $request Request.
 */
function lf_editor_design_preset_rest_save(\WP_REST_Request $request) {
	if (!function_exists('lf_apply_global_design_preset')) {
		require_once LF_THEME_DIR . '/inc/ops/common.php';
	}
	$preset = (string) $request->get_param('preset');
	$prev = [
		'design_preset'     => get_option('lf_global_design_preset', 'clean-precision'),
		'variation_profile' => function_exists('get_field') ? get_field('variation_profile', 'option') : null,
	];
	if (!function_exists('lf_apply_global_design_preset') || !lf_apply_global_design_preset($preset)) {
		return new \WP_Error('invalid_preset', __('Unknown design preset.', 'leadsforward-core'), ['status' => 400]);
	}
	$profile = function_exists('lf_design_preset_to_variation_profile')
		? lf_design_preset_to_variation_profile($preset)
		: 'a';
	if (function_exists('lf_ops_audit_log') && current_user_can('edit_theme_options')) {
		lf_ops_audit_log('editor_design_preset', ['design_preset' => $preset, 'variation_profile' => $profile], $prev);
	}
	return [
		'success' => true,
		'message' => __('Design preset updated. Refresh the preview if styles look stale.', 'leadsforward-core'),
	];
}

function lf_editor_design_preset_enqueue(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || (string) $screen->base !== 'post') {
		return;
	}
	if (!function_exists('lf_design_presets')) {
		return;
	}
	$presets = lf_design_presets();
	if ($presets === []) {
		return;
	}
	$handle = 'lf-editor-design-preset';
	wp_enqueue_script(
		$handle,
		LF_THEME_URI . '/assets/js/editor-design-preset.js',
		[
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-api-fetch',
		],
		LF_THEME_VERSION,
		true
	);
	wp_localize_script(
		$handle,
		'lfDesignPresetData',
		[
			'presets' => $presets,
			'current' => (string) get_option('lf_global_design_preset', 'clean-precision'),
			'strings' => [
				'label'         => __('Global design preset', 'leadsforward-core'),
				'help'          => __('Matches Bulk Tools → Reassign design preset. Syncs variation profile for block variants.', 'leadsforward-core'),
				'apply'         => __('Apply preset', 'leadsforward-core'),
				'saving'        => __('Saving…', 'leadsforward-core'),
				'sidebarTitle'  => __('LeadsForward', 'leadsforward-core'),
				'menuLabel'     => __('LeadsForward design', 'leadsforward-core'),
			],
		]
	);
}
