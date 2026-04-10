<?php
/**
 * SEO settings panel (LeadsForward → SEO). Stores all settings in lf_seo_settings option.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_seo_register_menu', 40);
add_action('admin_init', 'lf_seo_handle_save');
add_action('admin_enqueue_scripts', 'lf_seo_admin_assets');
add_action('admin_post_lf_seo_export_keyword_map', 'lf_seo_handle_export_keyword_map');

function lf_seo_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('SEO & Site Health', 'leadsforward-core'),
		__('SEO & Site Health', 'leadsforward-core'),
		'edit_theme_options',
		'lf-seo',
		'lf_seo_render_settings_page'
	);
}

function lf_seo_admin_assets(string $hook): void {
	if (!in_array($hook, ['leadsforward_page_lf-seo'], true)) {
		return;
	}
	wp_enqueue_media();
}

function lf_seo_get_settings(): array {
	$defaults = [
		'general' => [
			'title_template' => '{{page_title}} | {{city}} | {{brand}}',
			'meta_description_template' => '{{page_title}} in {{city}} by {{brand}}. Call today for fast service.',
			'title_separator' => '|',
			'append_brand' => true,
		],
		'indexing' => [
			'noindex_archives' => true,
			'noindex_search' => true,
			'noindex_paginated' => true,
		],
		'social' => [
			'default_og_image_id' => 0,
			'facebook_app_id' => '',
			'twitter_card' => 'summary_large_image',
		],
		'scripts' => [
			'header' => '',
			'body_open' => '',
			'footer' => '',
		],
		'schema' => [
			'organization_type' => 'Organization',
			'enable_local_business' => true,
			'enable_service' => true,
		],
		'sitemap' => [
			'enable' => true,
			'include_services' => true,
			'include_service_areas' => true,
			'include_posts' => true,
		],
		'ai' => [
			'enable_auto_keywords' => true,
			'enable_keyword_map' => true,
			'enable_keyword_density' => true,
			'enable_quality_scorer' => true,
			'enable_serp_templates' => true,
		],
		'serp' => [
			'title' => [
				'transactional' => '{{primary_keyword}} | {{city}} | {{brand}}',
				'local' => '{{primary_keyword}} in {{city}} | {{brand}}',
				'informational' => '{{page_title}}: {{primary_keyword}} Guide | {{brand}}',
				'navigational' => '{{brand}} | {{page_title}}',
			],
			'description' => [
				'transactional' => '{{primary_keyword}} in {{city}} from {{brand}}. Get clear pricing, scope, and scheduling with fast quote turnaround.',
				'local' => 'Local {{primary_keyword}} in {{city}} by {{brand}}. Licensed team, clear timelines, and service-area coverage.',
				'informational' => 'Learn {{primary_keyword}} with practical guidance from {{brand}} in {{city}}. Includes process, pricing factors, and expert tips.',
				'navigational' => '{{page_title}} at {{brand}}. Find services, coverage areas, and next steps quickly.',
			],
		],
	];
	$saved = get_option('lf_seo_settings', []);
	if (!is_array($saved)) {
		$saved = [];
	}
	return array_replace_recursive($defaults, $saved);
}

function lf_seo_get_setting(string $path, $default = '') {
	$settings = lf_seo_get_settings();
	$segments = array_filter(explode('.', $path));
	$current = $settings;
	foreach ($segments as $segment) {
		if (!is_array($current) || !array_key_exists($segment, $current)) {
			return $default;
		}
		$current = $current[$segment];
	}
	return $current;
}

function lf_seo_handle_save(): void {
	if (!isset($_POST['lf_seo_settings_nonce'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_seo_settings_nonce'], 'lf_seo_settings')) {
		return;
	}
	$settings = lf_seo_get_settings();

	$title_template = isset($_POST['lf_seo_title_template']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_title_template'])) : '';
	$meta_template = isset($_POST['lf_seo_meta_description_template']) ? sanitize_textarea_field(wp_unslash($_POST['lf_seo_meta_description_template'])) : '';
	$title_separator = isset($_POST['lf_seo_title_separator']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_title_separator'])) : '|';

	$settings['general']['title_template'] = $title_template !== '' ? $title_template : '{{page_title}} | {{city}} | {{brand}}';
	$settings['general']['meta_description_template'] = $meta_template;
	$settings['general']['title_separator'] = $title_separator !== '' ? $title_separator : '|';
	$settings['general']['append_brand'] = !empty($_POST['lf_seo_append_brand']);

	$settings['indexing']['noindex_archives'] = !empty($_POST['lf_seo_noindex_archives']);
	$settings['indexing']['noindex_search'] = !empty($_POST['lf_seo_noindex_search']);
	$settings['indexing']['noindex_paginated'] = !empty($_POST['lf_seo_noindex_paginated']);

	$settings['social']['default_og_image_id'] = isset($_POST['lf_seo_default_og_image_id']) ? (int) $_POST['lf_seo_default_og_image_id'] : 0;
	$settings['social']['facebook_app_id'] = isset($_POST['lf_seo_facebook_app_id']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_facebook_app_id'])) : '';
	$twitter_card = isset($_POST['lf_seo_twitter_card']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_twitter_card'])) : 'summary_large_image';
	$settings['social']['twitter_card'] = in_array($twitter_card, ['summary', 'summary_large_image'], true) ? $twitter_card : 'summary_large_image';

	$header_scripts = isset($_POST['lf_seo_header_scripts']) ? wp_unslash($_POST['lf_seo_header_scripts']) : '';
	$body_open_scripts = isset($_POST['lf_seo_body_open_scripts']) ? wp_unslash($_POST['lf_seo_body_open_scripts']) : '';
	$footer_scripts = isset($_POST['lf_seo_footer_scripts']) ? wp_unslash($_POST['lf_seo_footer_scripts']) : '';
	$settings['scripts']['header'] = lf_seo_sanitize_scripts((string) $header_scripts);
	$settings['scripts']['body_open'] = lf_seo_sanitize_scripts((string) $body_open_scripts);
	$settings['scripts']['footer'] = lf_seo_sanitize_scripts((string) $footer_scripts);

	$org_type = isset($_POST['lf_seo_organization_type']) ? sanitize_text_field(wp_unslash($_POST['lf_seo_organization_type'])) : 'Organization';
	$allowed_org_types = [
		'Organization',
		'LocalBusiness',
		'HomeAndConstructionBusiness',
		'ProfessionalService',
		'GeneralContractor',
		'RoofingContractor',
		'Plumber',
		'HVACBusiness',
		'LandscapingBusiness',
	];
	$settings['schema']['organization_type'] = in_array($org_type, $allowed_org_types, true) ? $org_type : 'Organization';
	$settings['schema']['enable_local_business'] = !empty($_POST['lf_seo_enable_local_business']);
	$settings['schema']['enable_service'] = !empty($_POST['lf_seo_enable_service']);

	$settings['sitemap']['enable'] = !empty($_POST['lf_seo_sitemap_enable']);
	$settings['sitemap']['include_services'] = !empty($_POST['lf_seo_sitemap_include_services']);
	$settings['sitemap']['include_service_areas'] = !empty($_POST['lf_seo_sitemap_include_service_areas']);
	$settings['sitemap']['include_posts'] = !empty($_POST['lf_seo_sitemap_include_posts']);

	$settings['ai']['enable_auto_keywords'] = !empty($_POST['lf_seo_ai_auto_keywords']);
	$settings['ai']['enable_keyword_map'] = !empty($_POST['lf_seo_ai_keyword_map']);
	$settings['ai']['enable_keyword_density'] = !empty($_POST['lf_seo_ai_keyword_density']);
	$settings['ai']['enable_quality_scorer'] = !empty($_POST['lf_seo_ai_quality_scorer']);
	$settings['ai']['enable_serp_templates'] = !empty($_POST['lf_seo_ai_serp_templates']);

	$serp_intents = ['transactional', 'local', 'informational', 'navigational'];
	foreach ($serp_intents as $intent) {
		$title_key = 'lf_seo_serp_title_' . $intent;
		$desc_key = 'lf_seo_serp_desc_' . $intent;
		$settings['serp']['title'][$intent] = isset($_POST[$title_key])
			? sanitize_text_field(wp_unslash((string) $_POST[$title_key]))
			: (string) ($settings['serp']['title'][$intent] ?? '');
		$settings['serp']['description'][$intent] = isset($_POST[$desc_key])
			? sanitize_textarea_field(wp_unslash((string) $_POST[$desc_key]))
			: (string) ($settings['serp']['description'][$intent] ?? '');
	}

	update_option('lf_seo_settings', $settings);
	wp_safe_redirect(add_query_arg(['saved' => '1', 'tab' => 'settings'], admin_url('admin.php?page=lf-seo')));
	exit;
}

function lf_seo_sanitize_scripts(string $value): string {
	$value = trim($value);
	if ($value === '') {
		return '';
	}
	if (current_user_can('unfiltered_html')) {
		return $value;
	}
	$allowed = wp_kses_allowed_html('post');
	$allowed['script'] = [
		'type' => true,
		'src' => true,
		'async' => true,
		'defer' => true,
		'crossorigin' => true,
		'integrity' => true,
		'nomodule' => true,
		'referrerpolicy' => true,
		'id' => true,
		'nonce' => true,
	];
	$allowed['noscript'] = [];
	$allowed['iframe'] = [
		'src' => true,
		'height' => true,
		'width' => true,
		'style' => true,
		'frameborder' => true,
		'allow' => true,
		'allowfullscreen' => true,
		'loading' => true,
		'referrerpolicy' => true,
	];
	$allowed['link'] = [
		'rel' => true,
		'href' => true,
		'type' => true,
		'media' => true,
		'crossorigin' => true,
		'referrerpolicy' => true,
	];
	$allowed['meta'] = [
		'name' => true,
		'content' => true,
		'property' => true,
		'charset' => true,
	];
	$allowed['style'] = [
		'type' => true,
		'media' => true,
		'nonce' => true,
	];
	return wp_kses($value, $allowed);
}

function lf_seo_render_settings_page(): void {
	$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'settings';
	if (!in_array($tab, ['settings', 'keywords', 'health', 'links'], true)) {
		$tab = 'settings';
	}
	$settings = lf_seo_get_settings();
	$og_id = (int) ($settings['social']['default_og_image_id'] ?? 0);
	$og_url = $og_id ? wp_get_attachment_image_url($og_id, 'medium') : '';
	$org_type = (string) ($settings['schema']['organization_type'] ?? 'Organization');
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$base = admin_url('admin.php?page=lf-seo');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('SEO & Site Health', 'leadsforward-core'); ?></h1>
		<h2 class="nav-tab-wrapper" style="margin-bottom:1rem;">
			<a href="<?php echo esc_url(add_query_arg('tab', 'settings', $base)); ?>" class="nav-tab<?php echo $tab === 'settings' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('SEO settings', 'leadsforward-core'); ?></a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'keywords', $base)); ?>" class="nav-tab<?php echo $tab === 'keywords' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('Keywords', 'leadsforward-core'); ?></a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'health', $base)); ?>" class="nav-tab<?php echo $tab === 'health' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('Site health', 'leadsforward-core'); ?></a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'links', $base)); ?>" class="nav-tab<?php echo $tab === 'links' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('Link map', 'leadsforward-core'); ?></a>
		</h2>
		<?php if ($tab === 'settings') : ?>
		<?php if (function_exists('lf_admin_render_quality_summary_strip')) { lf_admin_render_quality_summary_strip('seo'); } ?>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('SEO settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<form method="post" class="lf-seo-settings-form">
			<?php wp_nonce_field('lf_seo_settings', 'lf_seo_settings_nonce'); ?>

			<h2><?php esc_html_e('General', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_seo_title_template"><?php esc_html_e('Default Title Template', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="large-text" id="lf_seo_title_template" name="lf_seo_title_template" value="<?php echo esc_attr((string) ($settings['general']['title_template'] ?? '')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_meta_description_template"><?php esc_html_e('Default Meta Description Template', 'leadsforward-core'); ?></label></th>
					<td><textarea class="large-text" rows="2" id="lf_seo_meta_description_template" name="lf_seo_meta_description_template"><?php echo esc_textarea((string) ($settings['general']['meta_description_template'] ?? '')); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_title_separator"><?php esc_html_e('Title Separator', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="small-text" id="lf_seo_title_separator" name="lf_seo_title_separator" value="<?php echo esc_attr((string) ($settings['general']['title_separator'] ?? '|')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Append Brand', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_append_brand" value="1" <?php checked(!empty($settings['general']['append_brand'])); ?> /> <?php esc_html_e('Append brand name to titles when missing.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Indexing', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Archives', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_archives" value="1" <?php checked(!empty($settings['indexing']['noindex_archives'])); ?> /> <?php esc_html_e('Noindex all archive pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Search', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_search" value="1" <?php checked(!empty($settings['indexing']['noindex_search'])); ?> /> <?php esc_html_e('Noindex search results.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Noindex Paginated', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_noindex_paginated" value="1" <?php checked(!empty($settings['indexing']['noindex_paginated'])); ?> /> <?php esc_html_e('Noindex paginated pages.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Social', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Default OG Image', 'leadsforward-core'); ?></th>
					<td>
						<div style="display:flex;align-items:center;gap:1rem;">
							<div>
								<img id="lf-seo-og-preview" src="<?php echo esc_url($og_url); ?>" style="max-height:80px;<?php echo $og_url ? '' : 'display:none;'; ?>" alt="" />
							</div>
							<input type="hidden" name="lf_seo_default_og_image_id" id="lf_seo_default_og_image_id" value="<?php echo esc_attr((string) $og_id); ?>" />
							<button type="button" class="button" id="lf-seo-og-select"><?php esc_html_e('Select Image', 'leadsforward-core'); ?></button>
							<button type="button" class="button" id="lf-seo-og-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_facebook_app_id"><?php esc_html_e('Facebook App ID', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="regular-text" id="lf_seo_facebook_app_id" name="lf_seo_facebook_app_id" value="<?php echo esc_attr((string) ($settings['social']['facebook_app_id'] ?? '')); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_twitter_card"><?php esc_html_e('Twitter Card Type', 'leadsforward-core'); ?></label></th>
					<td>
						<select name="lf_seo_twitter_card" id="lf_seo_twitter_card">
							<option value="summary" <?php selected(($settings['social']['twitter_card'] ?? '') === 'summary'); ?>><?php esc_html_e('Summary', 'leadsforward-core'); ?></option>
							<option value="summary_large_image" <?php selected(($settings['social']['twitter_card'] ?? '') === 'summary_large_image'); ?>><?php esc_html_e('Summary Large Image', 'leadsforward-core'); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Scripts', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_seo_header_scripts"><?php esc_html_e('Header scripts', 'leadsforward-core'); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" id="lf_seo_header_scripts" name="lf_seo_header_scripts"><?php echo esc_textarea((string) ($settings['scripts']['header'] ?? '')); ?></textarea>
						<p class="description"><?php esc_html_e('Injected into wp_head. Include full <script> tags as needed.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_body_open_scripts"><?php esc_html_e('Body open scripts', 'leadsforward-core'); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" id="lf_seo_body_open_scripts" name="lf_seo_body_open_scripts"><?php echo esc_textarea((string) ($settings['scripts']['body_open'] ?? '')); ?></textarea>
						<p class="description"><?php esc_html_e('Injected on wp_body_open right after <body>.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_seo_footer_scripts"><?php esc_html_e('Footer scripts', 'leadsforward-core'); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" id="lf_seo_footer_scripts" name="lf_seo_footer_scripts"><?php echo esc_textarea((string) ($settings['scripts']['footer'] ?? '')); ?></textarea>
						<p class="description"><?php esc_html_e('Injected into wp_footer before closing body.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Schema', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_seo_organization_type"><?php esc_html_e('Organization Type', 'leadsforward-core'); ?></label></th>
					<td>
						<select name="lf_seo_organization_type" id="lf_seo_organization_type">
							<?php
							$org_types = [
								'Organization' => __('Organization', 'leadsforward-core'),
								'LocalBusiness' => __('LocalBusiness', 'leadsforward-core'),
								'HomeAndConstructionBusiness' => __('HomeAndConstructionBusiness', 'leadsforward-core'),
								'ProfessionalService' => __('ProfessionalService', 'leadsforward-core'),
								'GeneralContractor' => __('GeneralContractor', 'leadsforward-core'),
								'RoofingContractor' => __('RoofingContractor', 'leadsforward-core'),
								'Plumber' => __('Plumber', 'leadsforward-core'),
								'HVACBusiness' => __('HVACBusiness', 'leadsforward-core'),
								'LandscapingBusiness' => __('LandscapingBusiness', 'leadsforward-core'),
							];
							foreach ($org_types as $value => $label) {
								echo '<option value="' . esc_attr($value) . '"' . selected($org_type === $value, true, false) . '>' . esc_html($label) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable LocalBusiness schema', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_enable_local_business" value="1" <?php checked(!empty($settings['schema']['enable_local_business'])); ?> /> <?php esc_html_e('Output LocalBusiness schema.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable Service schema', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_enable_service" value="1" <?php checked(!empty($settings['schema']['enable_service'])); ?> /> <?php esc_html_e('Output Service schema on service pages.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Sitemap', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable XML sitemap', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_enable" value="1" <?php checked(!empty($settings['sitemap']['enable'])); ?> /> <?php esc_html_e('Serve sitemap.xml.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Services', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_services" value="1" <?php checked(!empty($settings['sitemap']['include_services'])); ?> /> <?php esc_html_e('Include service pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Service Areas', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_service_areas" value="1" <?php checked(!empty($settings['sitemap']['include_service_areas'])); ?> /> <?php esc_html_e('Include service area pages.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Posts', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_sitemap_include_posts" value="1" <?php checked(!empty($settings['sitemap']['include_posts'])); ?> /> <?php esc_html_e('Include blog posts.', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('AI SEO Engine', 'leadsforward-core'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable automatic keyword assignment', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_auto_keywords" value="1" <?php checked(!empty($settings['ai']['enable_auto_keywords'])); ?> /> <?php esc_html_e('Assign primary keywords when content is generated.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable keyword-to-page mapping', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_keyword_map" value="1" <?php checked(!empty($settings['ai']['enable_keyword_map'])); ?> /> <?php esc_html_e('Store assigned keywords in lf_keyword_map.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable keyword density enforcement', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_keyword_density" value="1" <?php checked(!empty($settings['ai']['enable_keyword_density'])); ?> /> <?php esc_html_e('Apply density guardrails in AI content generation.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable content quality scorer', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_quality_scorer" value="1" <?php checked(!empty($settings['ai']['enable_quality_scorer'])); ?> /> <?php esc_html_e('Score each page for content depth, keyword coverage, metadata quality, and internal links.', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable SERP intent templates', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_seo_ai_serp_templates" value="1" <?php checked(!empty($settings['ai']['enable_serp_templates'])); ?> /> <?php esc_html_e('Generate meta titles/descriptions by intent (transactional, local, informational, navigational).', 'leadsforward-core'); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e('SERP Intent Templates', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Used when auto-generating meta tags. Available variables: {{page_title}}, {{city}}, {{brand}}, {{primary_keyword}}.', 'leadsforward-core'); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$serp_intents = [
					'transactional' => __('Transactional', 'leadsforward-core'),
					'local' => __('Local', 'leadsforward-core'),
					'informational' => __('Informational', 'leadsforward-core'),
					'navigational' => __('Navigational', 'leadsforward-core'),
				];
				foreach ($serp_intents as $intent => $label) :
					$title_value = (string) ($settings['serp']['title'][$intent] ?? '');
					$desc_value = (string) ($settings['serp']['description'][$intent] ?? '');
					?>
					<tr>
						<th scope="row"><label for="lf_seo_serp_title_<?php echo esc_attr($intent); ?>"><?php echo esc_html(sprintf(__('%s title template', 'leadsforward-core'), $label)); ?></label></th>
						<td><input type="text" class="large-text" id="lf_seo_serp_title_<?php echo esc_attr($intent); ?>" name="lf_seo_serp_title_<?php echo esc_attr($intent); ?>" value="<?php echo esc_attr($title_value); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_seo_serp_desc_<?php echo esc_attr($intent); ?>"><?php echo esc_html(sprintf(__('%s description template', 'leadsforward-core'), $label)); ?></label></th>
						<td><textarea class="large-text" rows="2" id="lf_seo_serp_desc_<?php echo esc_attr($intent); ?>" name="lf_seo_serp_desc_<?php echo esc_attr($intent); ?>"><?php echo esc_textarea($desc_value); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(__('Save SEO Settings', 'leadsforward-core')); ?>
		</form>
	<script>
		(function () {
			function buildSeoPanels() {
				var form = document.querySelector('.lf-seo-settings-form');
				if (!form) return;
				var storageKey = 'lfSeoPanelsStateV1';
				var saved = {};
				try {
					saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
				} catch (e) {
					saved = {};
				}
				var h2s = Array.prototype.slice.call(form.querySelectorAll(':scope > h2'));
				h2s.forEach(function (h2) {
					var panelId = (h2.textContent || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
					var panel = document.createElement('section');
					panel.className = 'lf-seo-panel';
					panel.setAttribute('data-panel-id', panelId);

					var header = document.createElement('button');
					header.type = 'button';
					header.className = 'lf-seo-panel__header';
					header.setAttribute('aria-expanded', 'false');
					header.innerHTML = '<span class="lf-seo-panel__title">' + (h2.textContent || '') + '</span><span class="lf-seo-panel__chevron" aria-hidden="true">▾</span>';

					var body = document.createElement('div');
					body.className = 'lf-seo-panel__body';
					body.hidden = true;

					var next = h2.nextElementSibling;
					while (next && next.tagName !== 'H2' && !(next.classList && next.classList.contains('submit'))) {
						var move = next;
						next = next.nextElementSibling;
						body.appendChild(move);
					}

					panel.appendChild(header);
					panel.appendChild(body);
					form.insertBefore(panel, h2);
					h2.remove();

					var startOpen = !!saved[panelId];
					panel.classList.toggle('is-open', startOpen);
					body.hidden = !startOpen;
					header.setAttribute('aria-expanded', startOpen ? 'true' : 'false');

					header.addEventListener('click', function () {
						var open = !panel.classList.contains('is-open');
						panel.classList.toggle('is-open', open);
						body.hidden = !open;
						header.setAttribute('aria-expanded', open ? 'true' : 'false');
						saved[panelId] = open;
						try {
							window.localStorage.setItem(storageKey, JSON.stringify(saved));
						} catch (e) {}
					});
				});
			}

			buildSeoPanels();

			var frame;
			var selectBtn = document.getElementById('lf-seo-og-select');
			var clearBtn = document.getElementById('lf-seo-og-clear');
			var input = document.getElementById('lf_seo_default_og_image_id');
			var preview = document.getElementById('lf-seo-og-preview');
			if (selectBtn) {
				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select OG Image', button: { text: 'Use image' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (input) input.value = attachment.id;
						if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
					});
					frame.open();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (input) input.value = '';
					if (preview) { preview.src = ''; preview.style.display = 'none'; }
				});
			}
		})();
	</script>
		<?php elseif ($tab === 'keywords') : ?>
			<?php if (function_exists('lf_admin_render_quality_summary_strip')) { lf_admin_render_quality_summary_strip('seo'); } ?>
			<?php lf_seo_render_keywords_tab(); ?>
		<?php elseif ($tab === 'health') : ?>
			<?php
			if (function_exists('lf_health_render_embedded_ui')) {
				lf_health_render_embedded_ui();
			} else {
				echo '<p>' . esc_html__('Site health is unavailable.', 'leadsforward-core') . '</p>';
			}
			?>
		<?php else : ?>
			<?php
			if (function_exists('lf_internal_link_map_render_embedded_ui')) {
				lf_internal_link_map_render_embedded_ui();
			} else {
				echo '<p>' . esc_html__('Internal link map is unavailable.', 'leadsforward-core') . '</p>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

function lf_seo_handle_export_keyword_map(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_seo_export_keyword_map', 'lf_seo_export_nonce');
	$map = function_exists('lf_seo_get_keyword_map') ? lf_seo_get_keyword_map() : (array) get_option('lf_keyword_map', []);
	$rows = [];
	$rows[] = ['target', 'post_id', 'post_type', 'slug', 'title', 'primary_keyword'];
	foreach (($map['primary'] ?? []) as $key => $keyword) {
		$key = (string) $key;
		$keyword = trim((string) $keyword);
		if ($keyword === '') {
			continue;
		}
		if ($key === 'homepage') {
			$rows[] = ['homepage', '', '', '', 'Homepage', $keyword];
			continue;
		}
		if (strpos($key, 'post:') === 0) {
			$post_id = absint(substr($key, 5));
			$post = $post_id ? get_post($post_id) : null;
			if ($post instanceof \WP_Post) {
				$rows[] = [
					'post',
					(string) $post_id,
					(string) $post->post_type,
					(string) $post->post_name,
					(string) $post->post_title,
					$keyword,
				];
			}
		}
	}
	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=lf-keyword-map.csv');
	$out = fopen('php://output', 'w');
	if ($out) {
		foreach ($rows as $row) {
			fputcsv($out, $row);
		}
		fclose($out);
	}
	exit;
}

function lf_seo_render_keywords_tab(): void {
	$map = function_exists('lf_seo_get_keyword_map') ? lf_seo_get_keyword_map() : (array) get_option('lf_keyword_map', []);
	$primary = is_array($map['primary'] ?? null) ? $map['primary'] : [];

	$manifest = get_option('lf_site_manifest', []);
	$home_primary = is_array($manifest) ? trim((string) ($manifest['homepage']['primary_keyword'] ?? '')) : '';
	$home_secondary = is_array($manifest) ? ($manifest['homepage']['secondary_keywords'] ?? []) : [];
	if (is_string($home_secondary)) {
		$home_secondary = preg_split('/\r\n|\r|\n|,/', $home_secondary);
	}
	$home_secondary = is_array($home_secondary) ? array_values(array_unique(array_filter(array_map('sanitize_text_field', $home_secondary)))) : [];

	// Build duplicate report.
	$counts = [];
	foreach ($primary as $k => $kw) {
		$kw = strtolower(trim((string) $kw));
		if ($kw === '') {
			continue;
		}
		$counts[$kw] = ($counts[$kw] ?? 0) + 1;
	}

	$post_types = ['lf_service', 'lf_service_area', 'page', 'post'];
	$items = get_posts([
		'post_type' => $post_types,
		'post_status' => 'publish',
		'posts_per_page' => 300,
		'orderby' => 'post_type menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);

	$missing = 0;
	$dupes = 0;
	foreach ($items as $p) {
		$kw = trim((string) get_post_meta((int) $p->ID, '_lf_seo_primary_keyword', true));
		if ($kw === '') {
			$missing++;
			continue;
		}
		if (($counts[strtolower($kw)] ?? 0) > 1) {
			$dupes++;
		}
	}
	?>
	<div class="card" style="max-width: 1100px;">
		<h2 style="margin-top:0;"><?php esc_html_e('Keyword assignments', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('Primary keywords are stored per URL in post meta and (optionally) mirrored into lf_keyword_map. Secondary keywords typically come from the manifest and help meta generation and content guidance.', 'leadsforward-core'); ?></p>
		<ul style="margin:0 0 1rem;">
			<li><?php echo esc_html(sprintf(__('Missing primary keywords: %d', 'leadsforward-core'), (int) $missing)); ?></li>
			<li><?php echo esc_html(sprintf(__('Duplicate primary keywords (counted by lf_keyword_map): %d', 'leadsforward-core'), (int) $dupes)); ?></li>
		</ul>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0 0 1rem;">
			<?php wp_nonce_field('lf_seo_export_keyword_map', 'lf_seo_export_nonce'); ?>
			<input type="hidden" name="action" value="lf_seo_export_keyword_map" />
			<button type="submit" class="button"><?php esc_html_e('Export keyword map (CSV)', 'leadsforward-core'); ?></button>
		</form>
	</div>

	<div class="card" style="max-width: 1100px;">
		<h2 style="margin-top:0;"><?php esc_html_e('Homepage keywords (manifest)', 'leadsforward-core'); ?></h2>
		<p><strong><?php esc_html_e('Primary', 'leadsforward-core'); ?></strong>: <?php echo esc_html($home_primary ?: __('(not set)', 'leadsforward-core')); ?></p>
		<p><strong><?php esc_html_e('Secondary', 'leadsforward-core'); ?></strong>: <?php echo esc_html($home_secondary ? implode(', ', array_slice($home_secondary, 0, 12)) : __('(not set)', 'leadsforward-core')); ?></p>
	</div>

	<div class="card" style="max-width: 1100px;">
		<h2 style="margin-top:0;"><?php esc_html_e('URLs', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Type', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Title', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Primary keyword', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Secondary keywords', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($items as $p) : ?>
					<?php
					$pk = trim((string) get_post_meta((int) $p->ID, '_lf_seo_primary_keyword', true));
					$sk_raw = (string) get_post_meta((int) $p->ID, '_lf_seo_secondary_keywords', true);
					$sk = $sk_raw !== '' ? preg_split('/\r\n|\r|\n|,/', $sk_raw) : [];
					$sk = is_array($sk) ? array_values(array_unique(array_filter(array_map('sanitize_text_field', $sk)))) : [];
					$is_dup = $pk !== '' && (($counts[strtolower($pk)] ?? 0) > 1);
					?>
					<tr>
						<td><?php echo esc_html(strtoupper((string) $p->post_type)); ?></td>
						<td><a href="<?php echo esc_url(get_edit_post_link((int) $p->ID) ?: ''); ?>"><?php echo esc_html((string) $p->post_title); ?></a></td>
						<td>
							<?php if ($pk === '') : ?>
								<span style="color:#b91c1c;font-weight:700;"><?php esc_html_e('(missing)', 'leadsforward-core'); ?></span>
							<?php else : ?>
								<span style="<?php echo $is_dup ? 'color:#b45309;font-weight:700;' : ''; ?>"><?php echo esc_html($pk); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html($sk ? implode(', ', array_slice($sk, 0, 6)) : ''); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
