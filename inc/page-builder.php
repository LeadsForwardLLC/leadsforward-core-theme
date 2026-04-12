<?php
/**
 * Page Builder Framework: shared registry, renderer, and admin UI for templates.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_PB_META_KEY = 'lf_pb_config';

add_action('add_meta_boxes', 'lf_pb_register_meta_box');
add_action('admin_enqueue_scripts', 'lf_pb_admin_assets');
add_action('save_post', 'lf_pb_handle_save', 10, 2);
add_action('admin_init', 'lf_pb_cleanup_templates_once');

function lf_pb_registry(): array {
	return [
		'service' => [
			'label' => __('Service Page', 'leadsforward-core'),
			'sections' => array_values(lf_sections_get_context_sections('service')),
		],
		'service_area' => [
			'label' => __('Service Area Page', 'leadsforward-core'),
			'sections' => array_values(lf_sections_get_context_sections('service_area')),
		],
		'page' => [
			'label' => __('Page', 'leadsforward-core'),
			'sections' => array_values(lf_sections_get_context_sections('page')),
		],
		'post' => [
			'label' => __('Post', 'leadsforward-core'),
			'sections' => array_values(lf_sections_get_context_sections('post')),
		],
	];
}

function lf_pb_get_context_for_post(\WP_Post $post): string {
	if ($post->post_type === 'lf_service') {
		return 'service';
	}
	if ($post->post_type === 'lf_service_area') {
		return 'service_area';
	}
	if ($post->post_type === 'lf_project') {
		return 'post';
	}
	if ($post->post_type === 'page') {
		if ($post->post_name === 'home') {
			return '';
		}
		return 'page';
	}
	if ($post->post_type === 'post') {
		return 'post';
	}
	return '';
}

function lf_pb_instance_id(string $type, int $index = 1): string {
	return $type . '-' . max(1, $index);
}

function lf_pb_basic_page_slugs(): array {
	return [
		'about-us',
		'reviews',
		'blog',
		'sitemap',
		'privacy-policy',
		'terms-of-service',
		'thank-you',
	];
}

function lf_pb_is_basic_page(\WP_Post $post): bool {
	return $post->post_type === 'page' && in_array($post->post_name, lf_pb_basic_page_slugs(), true);
}

function lf_pb_default_config(string $context): array {
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
	$order_types = function_exists('lf_niche_section_order')
		? lf_niche_section_order($context, $niche_slug)
		: lf_sections_default_order($context);
	$sections = [];
	$order = [];
	$counts = [];
	foreach ($order_types as $type) {
		$counts[$type] = ($counts[$type] ?? 0) + 1;
		$instance_id = lf_pb_instance_id($type, $counts[$type]);
		$settings = lf_sections_defaults_for($type);
		if ($type === 'hero' && in_array($context, ['page', 'post', 'service', 'service_area'], true)) {
			$settings['variant'] = 'internal';
		}
		$sections[$instance_id] = [
			'type' => $type,
			'enabled' => true,
			'deletable' => false,
			'settings' => $settings,
		];
		$order[] = $instance_id;
	}
	return [
		'order' => $order,
		'sections' => $sections,
		'seo' => [
			'title' => '',
			'description' => '',
		],
	];
}

function lf_pb_get_post_config(int $post_id, string $context): array {
	$default = lf_pb_default_config($context);
	
	// If this is the homepage, sync with homepage admin config
	if ($context === 'homepage' && function_exists('lf_get_homepage_section_config')) {
		$homepage_config = lf_get_homepage_section_config();
		if (is_array($homepage_config) && !empty($homepage_config)) {
			// Convert homepage config to page builder format
			$sections_out = [];
			$order_out = [];
			
			foreach ($homepage_config as $section_type => $section_config) {
				if (!is_array($section_config) || empty($section_config['enabled'])) {
					continue;
				}
				
				$instance_id = lf_pb_instance_id($section_type, 1);
				$sections_out[$instance_id] = [
					'type' => $section_type,
					'enabled' => true,
					'deletable' => true,
					'settings' => $section_config,
				];
				$order_out[] = $instance_id;
			}
			
			return [
				'order' => $order_out,
				'sections' => $sections_out,
				'seo' => [
					'title' => get_the_title($post_id),
					'description' => get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '',
				],
			];
		}
	}
	
	$stored = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (is_array($stored)) {
		$stored = wp_unslash($stored);
	}
	if (!is_array($stored) || empty($stored)) {
		return $default;
	}

	$allowed_types = array_keys(lf_sections_get_context_sections($context));
	$sections_out = [];
	$order_out = [];
	$seo_out = [
		'title' => '',
		'description' => '',
	];

	$is_legacy = false;
	if (isset($stored['sections']) && is_array($stored['sections'])) {
		$first = reset($stored['sections']);
		$is_legacy = is_array($first) && !array_key_exists('type', $first);
	}

	if ($is_legacy) {
		$legacy_sections = $stored['sections'] ?? [];
		$legacy_order = is_array($stored['order'] ?? null) ? $stored['order'] : array_keys($legacy_sections);
		$counts = [];
		foreach ($legacy_order as $type) {
			if (!in_array($type, $allowed_types, true)) {
				continue;
			}
			$counts[$type] = ($counts[$type] ?? 0) + 1;
			$instance_id = lf_pb_instance_id($type, $counts[$type]);
			$row = is_array($legacy_sections[$type] ?? null) ? $legacy_sections[$type] : [];
			$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
			if (function_exists('lf_sections_normalize_service_details_settings')) {
				$settings = lf_sections_normalize_service_details_settings($type, $settings);
			}
			if ($type === 'hero' && in_array($context, ['page', 'post', 'service', 'service_area'], true)) {
				$variant = $settings['variant'] ?? 'default';
				if ($variant === '' || $variant === 'default') {
					$settings['variant'] = 'internal';
				}
			}
			$sections_out[$instance_id] = [
				'type' => $type,
				'enabled' => !empty($row['enabled']),
				'deletable' => false,
				'settings' => $settings,
			];
			$order_out[] = $instance_id;
		}
	} else {
		$sections_in = is_array($stored['sections'] ?? null) ? $stored['sections'] : [];
		foreach ($sections_in as $instance_id => $row) {
			if (!is_array($row)) {
				continue;
			}
			$type = $row['type'] ?? '';
			if (!in_array($type, $allowed_types, true)) {
				continue;
			}
			$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
			if (function_exists('lf_sections_normalize_service_details_settings')) {
				$settings = lf_sections_normalize_service_details_settings($type, $settings);
			}
			if ($type === 'hero' && in_array($context, ['page', 'post', 'service', 'service_area'], true)) {
				$variant = $settings['variant'] ?? 'default';
				if ($variant === '' || $variant === 'default') {
					$settings['variant'] = 'internal';
				}
			}
			$sections_out[$instance_id] = [
				'type' => $type,
				'enabled' => !empty($row['enabled']),
				'deletable' => !empty($row['deletable']),
				'settings' => $settings,
			];
		}
		$order_out = is_array($stored['order'] ?? null) ? $stored['order'] : array_keys($sections_out);
	}

	if (isset($stored['seo']) && is_array($stored['seo'])) {
		$seo_out['title'] = sanitize_text_field((string) ($stored['seo']['title'] ?? ''));
		$seo_out['description'] = sanitize_textarea_field((string) ($stored['seo']['description'] ?? ''));
	}


	if (empty($sections_out)) {
		$default['seo'] = $seo_out;
		return $default;
	}

	foreach ($sections_out as $instance_id => $row) {
		$defaults = lf_sections_defaults_for($row['type']);
		$sections_out[$instance_id]['settings'] = array_merge($defaults, $row['settings'] ?? []);
	}

	if ($context === 'page') {
		$post = get_post($post_id);
		$post_slug = $post ? (string) $post->post_name : '';
		if ($post) {
			$slug = $post_slug;
			if (in_array($slug, ['contact', 'reviews'], true)) {
				if ($slug === 'contact') {
					$required_contact_sections = ['map_nap', 'cta'];
					foreach ($required_contact_sections as $required_type) {
						$has_required = false;
						foreach ($sections_out as $row) {
							if (($row['type'] ?? '') === $required_type) {
								$has_required = true;
								break;
							}
						}
						if ($has_required) {
							continue;
						}
						$settings = lf_sections_defaults_for($required_type);
						if ($required_type === 'trust_reviews') {
							$settings['trust_heading'] = __('Recent reviews', 'leadsforward-core');
							$settings['trust_layout'] = 'grid';
							$settings['trust_columns'] = 2;
							$settings['trust_max_items'] = 2;
							$settings['trust_show_summary'] = 0;
							$settings['trust_show_source'] = 0;
						}
						$instance_index = 1;
						$instance_id = lf_pb_instance_id($required_type, $instance_index);
						while (isset($sections_out[$instance_id])) {
							$instance_index++;
							$instance_id = lf_pb_instance_id($required_type, $instance_index);
						}
						$sections_out[$instance_id] = [
							'type' => $required_type,
							'enabled' => true,
							'deletable' => true,
							'settings' => $settings,
						];
						$order_out[] = $instance_id;
					}
				}
				if ($slug === 'reviews') {
					$has_reviews = false;
					foreach ($sections_out as $row) {
						if (($row['type'] ?? '') === 'trust_reviews') {
							$has_reviews = true;
							break;
						}
					}
					if (!$has_reviews) {
						$settings = lf_sections_defaults_for('trust_reviews');
						$settings['trust_heading'] = '';
						$settings['trust_layout'] = 'slider';
						$settings['trust_max_items'] = -1;
						$instance_index = 1;
						$instance_id = lf_pb_instance_id('trust_reviews', $instance_index);
						while (isset($sections_out[$instance_id])) {
							$instance_index++;
							$instance_id = lf_pb_instance_id('trust_reviews', $instance_index);
						}
						$sections_out[$instance_id] = [
							'type' => 'trust_reviews',
							'enabled' => true,
							'deletable' => true,
							'settings' => $settings,
						];
						$inserted = false;
						foreach ($order_out as $index => $id) {
							$type = $sections_out[$id]['type'] ?? '';
							if ($type === 'hero') {
								array_splice($order_out, $index + 1, 0, [$instance_id]);
								$inserted = true;
								break;
							}
						}
						if (!$inserted) {
							$order_out[] = $instance_id;
						}
					}
				}
				if ($slug === 'contact') {
					$desired_order = ['hero', 'map_nap', 'cta'];
					$placed_ids = [];
					$reordered = [];
					foreach ($desired_order as $desired_type) {
						foreach ($order_out as $id) {
							if (isset($placed_ids[$id])) {
								continue;
							}
							$type = $sections_out[$id]['type'] ?? '';
							if ($type === $desired_type) {
								$reordered[] = $id;
								$placed_ids[$id] = true;
								break;
							}
						}
					}
					foreach ($order_out as $id) {
						if (isset($placed_ids[$id])) {
							continue;
						}
						$reordered[] = $id;
					}
					$order_out = $reordered;
				}
			}
			if (in_array($slug, ['about-us', 'about'], true)) {
				$has_custom_sections = false;
				foreach ($sections_out as $row) {
					$type = $row['type'] ?? '';
					if (!in_array($type, ['hero', 'content'], true)) {
						$has_custom_sections = true;
						break;
					}
				}
				if (!$has_custom_sections) {
					$business = function_exists('lf_get_option') ? (string) lf_get_option('lf_business_name', 'option') : '';
					if ($business === '') {
						$business = get_bloginfo('name');
					}
					$cta_headline = $business ? 'Get a free estimate from ' . $business : __('Get a free estimate', 'leadsforward-core');
					$sections_to_add = [
						[
							'type' => 'content_image',
							'settings' => [
								'section_heading' => __('Our story', 'leadsforward-core'),
								'section_intro' => __('Built for homeowners who want clear pricing and reliable service.', 'leadsforward-core'),
								'service_details_body' => __('We started this company to make home services simpler and more dependable. Our team shows up on time, keeps you informed, and treats your home with care from start to finish.', 'leadsforward-core'),
								'service_details_checklist' => __('Clear communication' . "\n" . 'Respectful, clean crews' . "\n" . 'Work backed by warranty', 'leadsforward-core'),
							],
						],
						[
							'type' => 'benefits',
							'settings' => [
								'section_heading' => __('Why homeowners choose us', 'leadsforward-core'),
								'section_intro' => __('Honest pricing, reliable crews, and quality workmanship.', 'leadsforward-core'),
								'benefits_items' => __('Licensed and insured professionals' . "\n" . 'Upfront pricing before work starts' . "\n" . 'Respectful, clean crews', 'leadsforward-core'),
							],
						],
						[
							'type' => 'cta',
							'settings' => [
								'cta_headline' => $cta_headline,
								'cta_subheadline' => __('Request a free estimate and get a clear next step.', 'leadsforward-core'),
							],
						],
					];
					$new_ids = [];
					foreach ($sections_to_add as $section_row) {
						$type = $section_row['type'];
						$settings = lf_sections_defaults_for($type);
						$settings = array_merge($settings, $section_row['settings']);
						$instance_index = 1;
						$instance_id = lf_pb_instance_id($type, $instance_index);
						while (isset($sections_out[$instance_id])) {
							$instance_index++;
							$instance_id = lf_pb_instance_id($type, $instance_index);
						}
						$sections_out[$instance_id] = [
							'type' => $type,
							'enabled' => true,
							'deletable' => true,
							'settings' => $settings,
						];
						$new_ids[] = $instance_id;
					}
					$hero_index = null;
					foreach ($order_out as $index => $id) {
						$type = $sections_out[$id]['type'] ?? '';
						if ($type === 'hero') {
							$hero_index = $index;
							break;
						}
					}
					if ($hero_index === null) {
						$order_out = array_merge($order_out, $new_ids);
					} else {
						array_splice($order_out, $hero_index + 1, 0, $new_ids);
					}
				}
			}
		}
	}

	$order_out = lf_pb_sanitize_order($order_out, array_keys($sections_out));
	return ['order' => $order_out, 'sections' => $sections_out, 'seo' => $seo_out];
}

/**
 * Migrate legacy Page Builder SEO overrides to the SEO meta box.
 */
function lf_pb_migrate_legacy_seo(int $post_id, array $config): array {
	$seo = is_array($config['seo'] ?? null) ? $config['seo'] : [];
	if (empty($seo)) {
		return $config;
	}
	$title = trim((string) ($seo['title'] ?? ''));
	$description = trim((string) ($seo['description'] ?? ''));
	$noindex = !empty($seo['noindex']);
	if ($title !== '' && get_post_meta($post_id, '_lf_seo_meta_title', true) === '') {
		update_post_meta($post_id, '_lf_seo_meta_title', $title);
	}
	if ($description !== '' && get_post_meta($post_id, '_lf_seo_meta_description', true) === '') {
		update_post_meta($post_id, '_lf_seo_meta_description', $description);
	}
	if ($noindex && get_post_meta($post_id, '_lf_seo_noindex', true) !== '1') {
		update_post_meta($post_id, '_lf_seo_noindex', '1');
	}
	unset($config['seo']);
	update_post_meta($post_id, LF_PB_META_KEY, $config);
	return $config;
}

function lf_pb_sanitize_order(array $order, array $allowed): array {
	$clean = [];
	foreach ($order as $item) {
		if (!is_string($item)) {
			continue;
		}
		$item = trim($item);
		if ($item !== '' && in_array($item, $allowed, true) && !in_array($item, $clean, true)) {
			$clean[] = $item;
		}
	}
	foreach ($allowed as $id) {
		if (!in_array($id, $clean, true)) {
			$clean[] = $id;
		}
	}
	return $clean;
}

function lf_pb_cleanup_templates_once(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	if (get_option('lf_pb_template_cleanup_v2', '0') === '1') {
		return;
	}
	if (function_exists('lf_homepage_cleanup_sections_once')) {
		lf_homepage_cleanup_sections_once();
	}
	$updated = 0;
	$page_templates = [
		// About page should be as content-dense as Why Choose Us.
		'about-us' => ['hero', 'content_image', 'benefits', 'image_content', 'faq_accordion', 'cta'],
		'why-choose-us' => ['hero', 'benefits', 'content_image', 'image_content', 'faq_accordion', 'cta'],
		'our-services' => ['hero', 'service_intro', 'content_image', 'faq_accordion', 'cta'],
		'service-areas' => ['hero', 'service_areas', 'faq_accordion', 'cta'],
		'reviews' => ['hero', 'trust_reviews', 'cta'],
		'blog' => ['hero', 'blog_posts', 'cta'],
		'contact' => ['hero', 'map_nap', 'cta'],
		'sitemap' => ['hero', 'sitemap_links'],
		'privacy-policy' => ['hero', 'content'],
		'terms-of-service' => ['hero', 'content'],
		'thank-you' => ['hero', 'content'],
	];

	$page_ids = get_posts([
		'post_type' => 'page',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ($page_ids as $page_id) {
		$post = get_post((int) $page_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		if ($post->post_name === 'home') {
			continue;
		}
		$desired = $page_templates[$post->post_name] ?? ['hero', 'content'];
		if (lf_pb_cleanup_post_config((int) $page_id, 'page', $desired)) {
			$updated++;
		}
	}

	$service_ids = get_posts([
		'post_type' => 'lf_service',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ($service_ids as $service_id) {
		if (lf_pb_cleanup_post_config((int) $service_id, 'service', ['hero', 'trust_bar', 'service_details', 'benefits', 'process', 'faq_accordion', 'cta'])) {
			$updated++;
		}
	}

	$area_ids = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ($area_ids as $area_id) {
		if (lf_pb_cleanup_post_config((int) $area_id, 'service_area', ['hero', 'trust_bar', 'content_image', 'benefits', 'content_image', 'image_content', 'process', 'related_links', 'nearby_areas', 'faq_accordion', 'cta'])) {
			$updated++;
		}
	}

	$post_ids = get_posts([
		'post_type' => 'post',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ($post_ids as $post_id) {
		if (lf_pb_cleanup_post_config((int) $post_id, 'post', ['hero', 'content', 'related_links', 'cta'])) {
			$updated++;
		}
	}

	update_option('lf_pb_template_cleanup_v2', '1', true);
	update_option('lf_pb_template_cleanup_v2_count', (int) $updated, false);
}

function lf_pb_cleanup_post_config(int $post_id, string $context, array $desired_types): bool {
	$stored = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($stored) || empty($stored)) {
		return false;
	}
	$config = lf_pb_get_post_config($post_id, $context);
	if (!is_array($config) || empty($config['sections'])) {
		return false;
	}
	$sections_in = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$order_in = is_array($config['order'] ?? null) ? $config['order'] : array_keys($sections_in);
	$seo = is_array($config['seo'] ?? null) ? $config['seo'] : ['title' => '', 'description' => ''];

	$kept = [];
	$by_type = [];
	foreach ($order_in as $instance_id) {
		$row = $sections_in[$instance_id] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$type = (string) ($row['type'] ?? '');
		if ($type === '' || !in_array($type, $desired_types, true)) {
			continue;
		}
		if (isset($by_type[$type])) {
			continue;
		}
		$row['enabled'] = true;
		$kept[$instance_id] = $row;
		$by_type[$type] = $instance_id;
	}

	foreach ($desired_types as $type) {
		if (isset($by_type[$type])) {
			continue;
		}
		$instance_index = 1;
		$instance_id = lf_pb_instance_id($type, $instance_index);
		while (isset($kept[$instance_id])) {
			$instance_index++;
			$instance_id = lf_pb_instance_id($type, $instance_index);
		}
		$kept[$instance_id] = [
			'type' => $type,
			'enabled' => true,
			'deletable' => false,
			'settings' => lf_sections_defaults_for($type),
		];
		$by_type[$type] = $instance_id;
	}

	$new_order = [];
	foreach ($desired_types as $type) {
		if (isset($by_type[$type])) {
			$new_order[] = $by_type[$type];
		}
	}

	$new_config = [
		'order' => $new_order,
		'sections' => $kept,
		'seo' => $seo,
	];
	update_post_meta($post_id, LF_PB_META_KEY, $new_config);
	return true;
}

function lf_pb_register_meta_box(): void {
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, ['lf_service', 'lf_service_area', 'lf_project', 'page', 'post'], true)) {
		return;
	}
	if ($screen->post_type === 'page') {
		$post = get_post();
		if ($post && function_exists('lf_is_legal_page') && lf_is_legal_page($post)) {
			return;
		}
	}
	add_meta_box(
		'lf_page_builder',
		__('Page Builder', 'leadsforward-core'),
		'lf_pb_render_admin_box',
		$screen->post_type,
		'normal',
		'high'
	);
}

function lf_pb_admin_assets(string $hook): void {
	if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, ['lf_service', 'lf_service_area', 'lf_project', 'page', 'post'], true)) {
		return;
	}
	if ($screen->post_type === 'page') {
		$post = get_post();
		if ($post && function_exists('lf_is_legal_page') && lf_is_legal_page($post)) {
			return;
		}
	}
	wp_enqueue_script('jquery-ui-sortable');
	wp_enqueue_media();
	wp_enqueue_script(
		'lf-section-sortable',
		LF_THEME_URI . '/assets/js/lf-section-sortable.js',
		['jquery', 'jquery-ui-sortable'],
		LF_THEME_VERSION,
		true
	);
}

function lf_pb_render_section_item(string $instance_id, array $def, array $section, bool $is_template = false): void {
	$type = $def['id'] ?? '';
	$label = $def['label'] ?? $type;
	$enabled = $is_template ? true : !empty($section['enabled']);
	$deletable = $is_template ? true : !empty($section['deletable']);
	$settings = $is_template ? lf_sections_defaults_for($type) : ($section['settings'] ?? []);
	$disabled = $is_template ? ' disabled' : '';
	?>
	<li class="lf-pb-section" data-instance="<?php echo esc_attr($instance_id); ?>" data-type="<?php echo esc_attr($type); ?>">
		<div class="lf-pb-section-header">
			<span class="lf-pb-drag" aria-hidden="true">⋮⋮</span>
			<strong><?php echo esc_html($label); ?></strong>
			<label><input type="checkbox" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][enabled]" value="1" <?php checked($enabled); ?><?php echo $disabled; ?> /> <?php esc_html_e('Enabled', 'leadsforward-core'); ?></label>
			<button type="button" class="lf-pb-toggle" data-target="<?php echo esc_attr($instance_id); ?>" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
			<?php if ($deletable) : ?>
				<button type="button" class="lf-pb-remove" aria-label="<?php esc_attr_e('Remove section', 'leadsforward-core'); ?>">✕</button>
			<?php endif; ?>
			<input type="hidden" name="lf_pb_order[]" value="<?php echo esc_attr($instance_id); ?>"<?php echo $disabled; ?> />
			<input type="hidden" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][type]" value="<?php echo esc_attr($type); ?>"<?php echo $disabled; ?> />
			<input type="hidden" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][deletable]" value="<?php echo $deletable ? '1' : '0'; ?>"<?php echo $disabled; ?> />
		</div>
		<div class="lf-pb-section-body">
			<?php foreach ($def['fields'] as $field) :
				$key = $field['key'];
				$type_field = $field['type'];
				$value = $settings[$key] ?? ($field['default'] ?? '');
				?>
				<div class="lf-pb-field">
					<label><strong><?php echo esc_html($field['label']); ?></strong></label>
					<?php if ($type_field === 'textarea' || $type_field === 'list' || $type_field === 'richtext') : ?>
						<textarea class="widefat" rows="3" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][settings][<?php echo esc_attr($key); ?>]"<?php echo $disabled; ?>><?php echo esc_textarea((string) $value); ?></textarea>
					<?php elseif ($type_field === 'select') : ?>
						<select name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][settings][<?php echo esc_attr($key); ?>]"<?php echo $disabled; ?>>
							<?php foreach (($field['options'] ?? []) as $opt_val => $opt_label) : ?>
								<option value="<?php echo esc_attr($opt_val); ?>" <?php selected((string) $value, (string) $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ($type_field === 'image') : ?>
						<?php
						$img_id = (int) $value;
						$thumb = $img_id ? wp_get_attachment_image_src($img_id, 'thumbnail') : null;
						$img_html = $thumb ? '<img src="' . esc_url($thumb[0]) . '" alt="" />' : '';
						?>
						<div class="lf-media-field">
							<div class="lf-media-preview">
								<?php echo $img_html !== '' ? $img_html : '<div class="lf-media-preview__empty">' . esc_html__('No image selected', 'leadsforward-core') . '</div>'; ?>
							</div>
							<div class="lf-media-actions">
								<button type="button" class="button lf-media-upload"><?php esc_html_e('Select image', 'leadsforward-core'); ?></button>
								<button type="button" class="button lf-media-remove"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
							</div>
							<input type="hidden" class="lf-media-id" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][settings][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $img_id); ?>"<?php echo $disabled; ?> />
						</div>
					<?php else : ?>
						<input type="<?php echo esc_attr($type_field); ?>" class="widefat" name="lf_pb_sections[<?php echo esc_attr($instance_id); ?>][settings][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $value); ?>"<?php echo $disabled; ?> />
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if (empty($def['fields'])) : ?>
				<p class="description"><?php esc_html_e('No settings for this section.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
	</li>
	<?php
}

function lf_pb_render_admin_box(\WP_Post $post): void {
	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		return;
	}
	$section_defs = lf_sections_get_context_sections($context);
	$section_list = array_values($section_defs);
	$config = lf_pb_get_post_config($post->ID, $context);
	$config = lf_pb_migrate_legacy_seo($post->ID, $config);
	$order = $config['order'] ?? [];
	$saved_sections = $config['sections'] ?? [];
	wp_nonce_field('lf_pb_save', 'lf_pb_nonce');
	?>
	<style>
		.lf-pb-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 1.5rem; align-items: start; }
		.lf-pb-main { min-width: 0; }
		.lf-pb-sections { margin: 0; padding: 0; min-height: 80px; }
		.lf-pb-section { list-style: none; margin: 0 0 1rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; }
		.lf-pb-section-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1rem; }
		.lf-pb-drag { cursor: grab; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #f1f5f9; color: #64748b; }
		.lf-pb-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
		.lf-pb-remove { margin-left: 0.35rem; border: none; background: #fee2e2; color: #b91c1c; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; }
		.lf-pb-section-body { padding: 0 1rem 1rem; }
		.lf-pb-section--collapsed .lf-pb-section-body { display: none; }
		.lf-pb-section--collapsed .lf-pb-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
		.lf-pb-field { border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; margin: 0.75rem 0; }
		.lf-pb-placeholder { border: 2px dashed #94a3b8; border-radius: 14px; height: 58px; margin-bottom: 1rem; background: #f8fafc; }
		.lf-pb-section--ghost { opacity: 0.85; border-style: dashed; }
		.lf-pb-library { position: sticky; top: 12px; background: #0f172a; color: #fff; border-radius: 16px; padding: 1rem; }
		.lf-pb-library__header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; }
		.lf-pb-library h4 { margin: 0; font-size: 14px; }
		.lf-pb-library__toggle { font-size: 11px; border-radius: 999px; padding: 0.2rem 0.6rem; border: 1px solid rgba(255,255,255,0.4); background: transparent; color: #fff; cursor: pointer; }
		.lf-pb-library__body p { margin: 0 0 0.75rem; color: #cbd5f5; font-size: 12px; }
		.lf-pb-library__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
		.lf-pb-library__item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.6rem 0.65rem; border-radius: 12px; background: rgba(255,255,255,0.08); cursor: grab; }
		.lf-pb-library__label { font-size: 12px; font-weight: 600; }
		.lf-pb-library__add { font-size: 11px; border-radius: 999px; padding: 0.15rem 0.6rem; border: 1px solid rgba(255,255,255,0.4); background: transparent; color: #fff; cursor: pointer; }
		.lf-pb-library__item:active { cursor: grabbing; }
		.lf-pb-library.is-collapsed .lf-pb-library__body { display: none; }
		.lf-pb-empty { border: 2px dashed #cbd5f5; border-radius: 16px; padding: 1rem; text-align: center; color: #64748b; background: #f8fafc; }
		.lf-media-field { display: grid; gap: 0.75rem; }
		.lf-media-preview { width: 160px; height: 100px; border: 1px dashed #cbd5e1; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f8fafc; }
		.lf-media-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
		.lf-media-preview__empty { font-size: 12px; color: #64748b; text-align: center; padding: 0 0.5rem; }
		.lf-media-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	</style>
	<div class="lf-pb-grid">
		<div class="lf-pb-main">
			<p class="description"><?php esc_html_e('Drag to reorder sections. Use the Add buttons on the right to insert new sections (duplicates allowed).', 'leadsforward-core'); ?></p>
			<ul class="lf-pb-sections">
				<?php if (empty($order)) : ?>
					<li class="lf-pb-empty"><?php esc_html_e('Drag sections here to start building.', 'leadsforward-core'); ?></li>
				<?php endif; ?>
				<?php foreach ($order as $instance_id) :
					$sec_cfg = $saved_sections[$instance_id] ?? [];
					$type = $sec_cfg['type'] ?? '';
					$def = $section_defs[$type] ?? null;
					if (!$def) { continue; }
					lf_pb_render_section_item($instance_id, $def, $sec_cfg);
				endforeach; ?>
			</ul>
		</div>
		<aside class="lf-pb-library" data-collapsed="0">
			<div class="lf-pb-library__header">
				<h4><?php esc_html_e('Section Library', 'leadsforward-core'); ?></h4>
				<button type="button" class="lf-pb-library__toggle" aria-expanded="true"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
			</div>
			<div class="lf-pb-library__body">
				<p><?php esc_html_e('Drag or click Add to insert a section.', 'leadsforward-core'); ?></p>
				<ul class="lf-pb-library__list">
					<?php foreach ($section_list as $def) : ?>
						<li class="lf-pb-library__item" data-section-type="<?php echo esc_attr($def['id']); ?>">
							<span class="lf-pb-library__label"><?php echo esc_html($def['label']); ?></span>
							<button type="button" class="lf-pb-library__add"><?php esc_html_e('Add', 'leadsforward-core'); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</aside>
	</div>
	<div class="lf-pb-templates" style="display:none;">
		<?php foreach ($section_list as $def) : ?>
			<?php lf_pb_render_section_item('__ID__', $def, [], true); ?>
		<?php endforeach; ?>
	</div>
	<script>
		jQuery(function ($) {
			var $list = $('.lf-pb-sections');
			var templates = {};
			$('.lf-pb-templates .lf-pb-section').each(function () {
				var type = $(this).data('type');
				if (type) {
					templates[type] = this.outerHTML;
				}
			});
			function makeId(type) {
				return 'pb_' + type + '_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
			}
			function addSection(type) {
				if (!templates[type]) return;
				$list.find('.lf-pb-empty').remove();
				var id = makeId(type);
				var html = templates[type].replace(/__ID__/g, id);
				var $item = $(html);
				$item.find('[disabled]').prop('disabled', false);
				$list.append($item);
				applyCollapse(id);
			}
			var mediaFrame = null;
			function openMediaFrame($field) {
				if (!window.wp || !wp.media) {
					return;
				}
				if (mediaFrame) {
					mediaFrame.off('select');
				}
				mediaFrame = wp.media({
					title: 'Select image',
					button: { text: 'Use image' },
					library: { type: 'image' },
					multiple: false
				});
				mediaFrame.on('select', function () {
					var attachment = mediaFrame.state().get('selection').first();
					if (!attachment) return;
					var data = attachment.toJSON();
					$field.find('.lf-media-id').val(data.id || '');
					var url = (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url;
					var html = url ? '<img src="' + url + '" alt="" />' : '';
					$field.find('.lf-media-preview').html(html || '<div class="lf-media-preview__empty">No image selected</div>');
				});
				mediaFrame.open();
			}
			function insertAtDrop($item, e) {
				var el = document.elementFromPoint(e.clientX, e.clientY);
				var $target = $(el).closest('.lf-pb-section');
				if ($target.length) {
					var midpoint = $target.offset().top + ($target.outerHeight() / 2);
					if (e.pageY > midpoint) {
						$target.after($item);
					} else {
						$target.before($item);
					}
				} else {
					$list.append($item);
				}
			}
			if (window.LFSectionSortable) {
				window.LFSectionSortable.initSortable($list, {
					items: '> li.lf-pb-section',
					handle: '.lf-pb-drag',
					placeholder: 'lf-pb-placeholder'
				});
			}
			$(document).on('click', '.lf-pb-library__add', function () {
				var type = $(this).closest('.lf-pb-library__item').data('sectionType');
				addSection(type);
			});
			$(document).on('click', '.lf-pb-remove', function () {
				$(this).closest('.lf-pb-section').remove();
			});
			$(document).on('click', '.lf-media-upload', function () {
				var $field = $(this).closest('.lf-media-field');
				openMediaFrame($field);
			});
			$(document).on('click', '.lf-media-remove', function () {
				var $field = $(this).closest('.lf-media-field');
				$field.find('.lf-media-id').val('');
				$field.find('.lf-media-preview').html('<div class="lf-media-preview__empty">No image selected</div>');
			});
			var libraryKey = 'lf_pb_library_collapsed';
			var libraryCollapsed = false;
			try { libraryCollapsed = window.localStorage.getItem(libraryKey) === '1'; } catch (e) { libraryCollapsed = false; }
			function applyLibraryCollapse() {
				var $library = $('.lf-pb-library');
				$library.toggleClass('is-collapsed', libraryCollapsed);
				var $toggle = $library.find('.lf-pb-library__toggle');
				$toggle.attr('aria-expanded', (!libraryCollapsed).toString());
				$toggle.text(libraryCollapsed ? '▸ <?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '▾ <?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>');
			}
			applyLibraryCollapse();
			$(document).on('click', '.lf-pb-library__toggle', function () {
				libraryCollapsed = !libraryCollapsed;
				try { window.localStorage.setItem(libraryKey, libraryCollapsed ? '1' : '0'); } catch (e) {}
				applyLibraryCollapse();
			});
			var key = 'lf_pb_collapsed';
			var collapsed = {};
			try { collapsed = JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (e) { collapsed = {}; }
			function applyCollapse(id) {
				var $panel = $('.lf-pb-section[data-instance="' + id + '"]');
				var isCollapsed = !!collapsed[id];
				$panel.toggleClass('lf-pb-section--collapsed', isCollapsed);
				var $toggle = $panel.find('.lf-pb-toggle');
				$toggle.attr('aria-expanded', (!isCollapsed).toString());
				$toggle.text((isCollapsed ? '▸ ' : '▾ ') + (isCollapsed ? '<?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '<?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>'));
			}
			$('.lf-pb-section').each(function () {
				var id = $(this).data('instance');
				if (id) applyCollapse(id);
			});
			$(document).on('click', '.lf-pb-toggle', function () {
				var id = $(this).data('target');
				if (!id) return;
				collapsed[id] = !collapsed[id];
				try { window.localStorage.setItem(key, JSON.stringify(collapsed)); } catch (e) {}
				applyCollapse(id);
			});
		});
	</script>
	<?php
}

function lf_pb_handle_save(int $post_id, \WP_Post $post): void {
	if (!in_array($post->post_type, ['lf_service', 'lf_service_area', 'lf_project', 'page', 'post'], true)) {
		return;
	}
	if (!isset($_POST['lf_pb_nonce']) || !wp_verify_nonce($_POST['lf_pb_nonce'], 'lf_pb_save')) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		return;
	}
	$section_defs = lf_sections_get_context_sections($context);
	$allowed_types = array_keys($section_defs);
	$input = $_POST['lf_pb_sections'] ?? [];
	$clean_sections = [];
	foreach ($input as $instance_id => $raw_section) {
		$instance_id = sanitize_text_field((string) $instance_id);
		if ($instance_id === '' || !is_array($raw_section)) {
			continue;
		}
		$type = sanitize_text_field($raw_section['type'] ?? '');
		if (!in_array($type, $allowed_types, true)) {
			continue;
		}
		$enabled = !empty($raw_section['enabled']);
		$deletable = !empty($raw_section['deletable']);
		$raw_settings = is_array($raw_section['settings'] ?? null) ? $raw_section['settings'] : [];
		$settings = lf_sections_sanitize_settings($type, $raw_settings);
		$clean_sections[$instance_id] = [
			'type' => $type,
			'enabled' => $enabled,
			'deletable' => $deletable,
			'settings' => $settings,
		];
	}
	$order_raw = isset($_POST['lf_pb_order']) ? (array) $_POST['lf_pb_order'] : [];
	$order = lf_pb_sanitize_order(array_map('sanitize_text_field', $order_raw), array_keys($clean_sections));
	$existing = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (is_array($existing) && !empty($existing['seo'])) {
		lf_pb_migrate_legacy_seo($post_id, $existing);
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $clean_sections]);
	
	// If this is the homepage, sync changes back to homepage config
	if ($context === 'homepage' && function_exists('lf_homepage_merge_config_with_defaults')) {
		$homepage_config = [];
		foreach ($clean_sections as $instance_id => $section) {
			$section_type = $section['type'];
			$section_settings = $section['settings'];
			$section_settings['enabled'] = $section['enabled'];
			$homepage_config[$section_type] = $section_settings;
		}
		
		// Merge with existing homepage config to preserve other settings
		$existing_config = lf_get_homepage_section_config();
		if (is_array($existing_config)) {
			$homepage_config = array_merge($existing_config, $homepage_config);
		}
		
		// Save to homepage config option
		update_option('lf_homepage_section_config', $homepage_config);
	}
}

function lf_pb_render_sections(\WP_Post $post): void {
	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		return;
	}
	$config = lf_pb_get_post_config($post->ID, $context);
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	foreach ($order as $section_id) {
		$sec_cfg = $sections[$section_id] ?? null;
		if (!$sec_cfg) {
			continue;
		}
		$enabled = !empty($sec_cfg['enabled']);
		$type = $sec_cfg['type'] ?? '';
		if ($type === '') {
			continue;
		}
		$sec_settings_pb = is_array($sec_cfg['settings'] ?? null) ? $sec_cfg['settings'] : [];
		$wrap_heading_tag_pb = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($sec_settings_pb) : 'h2';
		// If a section is hidden, keep a placeholder wrapper for front-end editors so it can be restored.
		if (!$enabled) {
			if (current_user_can('edit_theme_options')) {
				echo '<div class="lf-inline-section-wrap lf-inline-section-wrap--hidden" data-lf-section-wrap="1" data-lf-section-id="' . esc_attr((string) $section_id) . '" data-lf-section-type="' . esc_attr((string) $type) . '" data-lf-section-heading-tag="' . esc_attr($wrap_heading_tag_pb) . '" data-lf-section-visible="0">';
				echo '<div class="lf-ai-hidden-section-placeholder" aria-label="' . esc_attr__('Hidden section', 'leadsforward-core') . '">';
				echo esc_html__('Hidden section:', 'leadsforward-core') . ' ' . esc_html((string) ($type ?: $section_id));
				echo '</div>';
				echo '</div>';
			}
			continue;
		}
		if ($context === 'page' && $post->post_name === 'our-services' && $type === 'content_centered') {
			continue;
		}
		if ($context === 'page' && $post->post_name === 'contact' && $type === 'content_centered') {
			continue;
		}
		if ($context === 'service_area' && $type === 'map_nap') {
			continue;
		}
		if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
			$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
			$allowed = (isset($registry[$type]) && function_exists('lf_ai_studio_homepage_allowed_field_keys'))
				? lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type])
				: [];
			$settings = $sec_cfg['settings'] ?? [];
			$rendered = [];
			foreach ($allowed as $key) {
				$value = is_array($settings) ? ($settings[$key] ?? null) : null;
				if (is_array($value)) {
					if (!empty($value)) {
						$rendered[] = $key;
					}
				} elseif (is_string($value)) {
					if (trim($value) !== '') {
						$rendered[] = $key;
					}
				} elseif ($value !== null && $value !== '') {
					$rendered[] = $key;
				}
			}
			error_log(sprintf(
				'LF DEBUG: Page section=%s type=%s allowed=[%s] rendered=[%s]',
				$section_id,
				$type,
				implode(', ', $allowed),
				implode(', ', $rendered)
			));
		}
		echo '<div class="lf-inline-section-wrap" data-lf-section-wrap="1" data-lf-section-id="' . esc_attr((string) $section_id) . '" data-lf-section-type="' . esc_attr((string) $type) . '" data-lf-section-heading-tag="' . esc_attr($wrap_heading_tag_pb) . '" data-lf-section-visible="1">';
		lf_sections_render_section($type, $context, $sec_cfg['settings'] ?? [], $post);
		echo '</div>';
	}
}

/**
 * Page Builder context slug for a post type (used when no WP_Post exists yet).
 */
function lf_ai_pb_context_from_post_type(string $post_type): string {
	switch ($post_type) {
		case 'lf_service':
			return 'service';
		case 'lf_service_area':
			return 'service_area';
		case 'lf_project':
			return 'post';
		case 'page':
			return 'page';
		case 'post':
			return 'post';
		default:
			return '';
	}
}

/**
 * Ordered Page Builder section keys for the default template (hero, trust_bar, …; duplicates use type__2).
 *
 * @return string[]
 */
function lf_ai_pb_default_section_keys_for_context(string $context): array {
	if ($context === '' || !function_exists('lf_pb_default_config')) {
		return [];
	}
	$config = lf_pb_default_config($context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$counts = [];
	$keys = [];
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$t = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($t === '') {
			continue;
		}
		$counts[ $t ] = ($counts[ $t ] ?? 0) + 1;
		$n = (int) $counts[ $t ];
		$keys[] = $n === 1 ? $t : $t . '__' . $n;
	}
	return $keys;
}

/**
 * @param mixed $data
 */
function lf_ai_pb_nested_copy_length($data): int {
	if (!is_array($data)) {
		return strlen(wp_strip_all_tags((string) $data));
	}
	$sum = 0;
	foreach ($data as $v) {
		$sum += lf_ai_pb_nested_copy_length($v);
	}
	return $sum;
}

/**
 * @param mixed $raw
 */
function lf_ai_pb_filter_section_patch(string $section_type, $raw): array {
	if (!is_array($raw)) {
		return [];
	}
	if (!function_exists('lf_ai_pb_writable_field_keys_for_type')) {
		return [];
	}
	$allowed = array_flip(lf_ai_pb_writable_field_keys_for_type($section_type));
	$out = [];
	foreach ($raw as $k => $v) {
		$key = sanitize_text_field((string) $k);
		if ($key === '' || !isset($allowed[ $key ])) {
			continue;
		}
		$out[ $key ] = $v;
	}
	return $out;
}

/**
 * Merge AI creation output into default Page Builder config and save lf_pb_config.
 *
 * @param mixed $page_builder Decoded JSON object: keys like hero, trust_bar, service_details__2.
 */
function lf_ai_assistant_apply_creation_page_builder(int $post_id, string $post_type, $page_builder): bool {
	$post_id = max(0, $post_id);
	if ($post_id === 0 || !is_array($page_builder)) {
		return false;
	}
	if (function_exists('lf_ai_creation_interpolate_recursive')) {
		$interp = lf_ai_creation_interpolate_recursive($page_builder);
		$page_builder = is_array($interp) ? $interp : $page_builder;
	}
	$context = lf_ai_pb_context_from_post_type($post_type);
	if ($context === '' || !function_exists('lf_pb_default_config') || !function_exists('lf_sections_sanitize_settings')) {
		return false;
	}
	$config = lf_pb_default_config($context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if ($order === [] || $sections === []) {
		return false;
	}
	$counts = [];
	$changed = false;
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$t = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($t === '') {
			continue;
		}
		$counts[ $t ] = ($counts[ $t ] ?? 0) + 1;
		$n = (int) $counts[ $t ];
		$order_key = $n === 1 ? $t : $t . '__' . $n;
		$patch_raw = $page_builder[ $order_key ] ?? null;
		if ($patch_raw === null && $n === 1) {
			$patch_raw = $page_builder[ $t ] ?? null;
		}
		$patch = lf_ai_pb_filter_section_patch($t, $patch_raw);
		if ($patch === []) {
			continue;
		}
		$base = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		$sections[ $instance_id ]['settings'] = lf_sections_sanitize_settings($t, array_merge($base, $patch));
		$changed = true;
	}
	if (!$changed) {
		return false;
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $sections]);
	return true;
}

/**
 * New lf_service drafts: drop inline process_steps so the theme uses Process Step CPT + lf_process_group.
 */
function lf_pb_service_use_cpt_process_steps(int $post_id): bool {
	$post_id = max(0, $post_id);
	if ($post_id === 0) {
		return false;
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'lf_service') {
		return false;
	}
	$raw = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($raw)) {
		return false;
	}
	$order = is_array($raw['order'] ?? null) ? $raw['order'] : [];
	$sections = is_array($raw['sections'] ?? null) ? $raw['sections'] : [];
	if ($order === [] || $sections === []) {
		return false;
	}
	$changed = false;
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row) || ($row['type'] ?? '') !== 'process') {
			continue;
		}
		$base = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		$base['process_steps'] = '';
		$base['process_selected_ids'] = '';
		if (!function_exists('lf_sections_sanitize_settings')) {
			continue;
		}
		$sections[ $instance_id ]['settings'] = lf_sections_sanitize_settings('process', $base);
		$changed = true;
	}
	if (!$changed) {
		return false;
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $sections]);
	return true;
}

/**
 * When the model returns legacy HTML "content" only, map it into default Page Builder sections.
 */
function lf_ai_pb_apply_fallback_content(int $post_id, string $post_type, string $title, string $content_html): bool {
	$post_id = max(0, $post_id);
	if ($post_id === 0) {
		return false;
	}
	$content_html = trim($content_html);
	if ($content_html === '' || !function_exists('lf_pb_default_config') || !function_exists('lf_sections_sanitize_settings')) {
		return false;
	}
	$context = lf_ai_pb_context_from_post_type($post_type);
	if ($context === '') {
		return false;
	}
	$config = lf_pb_default_config($context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if ($order === [] || $sections === []) {
		return false;
	}
	$title = sanitize_text_field($title);
	$did = false;
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$t = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($t === 'hero' && $title !== '' && function_exists('lf_ai_pb_writable_field_keys_for_type')) {
			$keys = lf_ai_pb_writable_field_keys_for_type('hero');
			if (in_array('hero_headline', $keys, true)) {
				$base = is_array($row['settings'] ?? null) ? $row['settings'] : [];
				$patch = ['hero_headline' => $title];
				if (in_array('hero_subheadline', $keys, true) && strlen(wp_strip_all_tags($content_html)) > 20) {
					$patch['hero_subheadline'] = wp_trim_words(wp_strip_all_tags($content_html), 28, '…');
				}
				$sections[ $instance_id ]['settings'] = lf_sections_sanitize_settings('hero', array_merge($base, $patch));
				$did = true;
			}
		}
	}
	foreach ($order as $instance_id) {
		$row = $sections[ $instance_id ] ?? null;
		if (!is_array($row)) {
			continue;
		}
		$t = sanitize_text_field((string) ($row['type'] ?? ''));
		if ($t === '' || !function_exists('lf_ai_pb_writable_field_keys_for_type')) {
			continue;
		}
		$keys = lf_ai_pb_writable_field_keys_for_type($t);
		$base = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		$body_key = null;
		if (in_array('section_body', $keys, true)) {
			$body_key = 'section_body';
		} elseif (in_array('service_details_body', $keys, true)) {
			$body_key = 'service_details_body';
		}
		if ($body_key !== null) {
			$sections[ $instance_id ]['settings'] = lf_sections_sanitize_settings($t, array_merge($base, [ $body_key => $content_html ]));
			$did = true;
			break;
		}
		if (in_array('supporting_text', $keys, true)) {
			$sections[ $instance_id ]['settings'] = lf_sections_sanitize_settings($t, array_merge($base, ['supporting_text' => $content_html]));
			$did = true;
			break;
		}
	}
	if (!$did) {
		return false;
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $sections]);
	return true;
}

/**
 * Post types that use the Section Library / lf_pb_config.
 *
 * @return string[]
 */
function lf_pb_menu_assist_post_types(): array {
	return ['lf_service', 'lf_service_area', 'lf_project', 'page', 'post'];
}

/**
 * @return array{0: int, 1: string} Parent nav_menu_item db ID (0 = top-level) and human-readable strategy label.
 */
function lf_pb_nav_smart_parent_for_post(\WP_Post $post): array {
	$menu_id = lf_pb_header_menu_term_id();
	if ($menu_id <= 0) {
		return [0, ''];
	}
	if ($post->post_type === 'lf_service') {
		$pid = lf_pb_nav_find_item_id_by_class($menu_id, 'lf-menu-services-parent');
		return [$pid, 'services'];
	}
	if ($post->post_type === 'lf_service_area') {
		$pid = lf_pb_nav_find_item_id_by_class($menu_id, 'lf-menu-areas-parent');
		return [$pid, 'areas'];
	}
	if ($post->post_type === 'page' && (int) $post->post_parent > 0) {
		$pid = lf_pb_nav_find_item_for_object($menu_id, (int) $post->post_parent, 'page');
		if ($pid > 0) {
			return [$pid, 'page_parent'];
		}
	}
	if ($post->post_type === 'post') {
		$posts_page = (int) get_option('page_for_posts', 0);
		if ($posts_page > 0) {
			$pid = lf_pb_nav_find_item_for_object($menu_id, $posts_page, 'page');
			if ($pid > 0) {
				return [$pid, 'blog_index'];
			}
		}
	}
	return [0, 'top'];
}

function lf_pb_header_menu_term_id(): int {
	$locations = get_nav_menu_locations();
	$mid = isset($locations['header_menu']) ? (int) $locations['header_menu'] : 0;
	return $mid > 0 ? $mid : 0;
}

function lf_pb_nav_find_item_id_by_class(int $menu_id, string $class): int {
	$class = sanitize_text_field($class);
	if ($class === '') {
		return 0;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return 0;
	}
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$classes = $item->classes ?? [];
		if (is_array($classes) && in_array($class, $classes, true)) {
			return (int) $item->ID;
		}
	}
	return 0;
}

function lf_pb_nav_find_item_for_object(int $menu_id, int $object_id, string $object_type): int {
	$object_id = max(0, $object_id);
	if ($object_id === 0) {
		return 0;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return 0;
	}
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) $item->object_id === $object_id && $item->object === $object_type) {
			return (int) $item->ID;
		}
	}
	return 0;
}

function lf_pb_nav_post_already_in_menu(int $menu_id, \WP_Post $post): bool {
	return lf_pb_nav_find_item_for_object($menu_id, (int) $post->ID, $post->post_type) > 0;
}

function lf_pb_nav_next_menu_order_under_parent(int $menu_id, int $parent_db_id): int {
	$max = 0;
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return 1;
	}
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) $item->menu_item_parent === $parent_db_id) {
			$max = max($max, (int) $item->menu_order);
		}
	}
	return $max + 1;
}

/**
 * Add a post to the header menu if missing. Requires edit_theme_options.
 */
function lf_pb_try_add_post_to_header_menu(\WP_Post $post): bool {
	if (!current_user_can('edit_theme_options')) {
		return false;
	}
	$menu_id = lf_pb_header_menu_term_id();
	if ($menu_id <= 0) {
		set_transient(
			'lf_pb_nav_notice_' . get_current_user_id(),
			__('No menu is assigned to the Header Menu location. Assign one under Appearance → Menus.', 'leadsforward-core'),
			45
		);
		return false;
	}
	if (lf_pb_nav_post_already_in_menu($menu_id, $post)) {
		return true;
	}
	[$parent_db_id] = lf_pb_nav_smart_parent_for_post($post);
	$parent_db_id = max(0, $parent_db_id);
	$position = lf_pb_nav_next_menu_order_under_parent($menu_id, $parent_db_id);
	$new_id = wp_update_nav_menu_item(
		$menu_id,
		0,
		[
			'menu-item-title'     => $post->post_title,
			'menu-item-object-id' => (int) $post->ID,
			'menu-item-object'    => $post->post_type,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent_db_id,
			'menu-item-position'  => $position,
		]
	);
	return !is_wp_error($new_id) && $new_id > 0;
}

/**
 * Markup for “add to header menu” (classic Publish box + block editor side meta box).
 */
function lf_pb_render_add_to_header_menu_markup(\WP_Post $post): void {
	if (!in_array($post->post_type, lf_pb_menu_assist_post_types(), true)) {
		return;
	}
	if ($post->post_type === 'page' && $post->post_name === 'home') {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		echo '<p class="description">' . esc_html__('You need permission to manage menus to use this option.', 'leadsforward-core') . '</p>';
		return;
	}
	$menu_id = lf_pb_header_menu_term_id();
	if ($menu_id <= 0) {
		echo '<p class="description">' . esc_html__('Assign a menu to the “Header Menu” location under Appearance → Menus, then save this post again to see the checkbox.', 'leadsforward-core') . '</p>';
		return;
	}
	if (lf_pb_nav_post_already_in_menu($menu_id, $post)) {
		echo '<p class="description">' . esc_html__('This item is already in the menu assigned to Header Menu.', 'leadsforward-core') . '</p>';
		return;
	}
	[$parent_id, $strategy] = lf_pb_nav_smart_parent_for_post($post);
	$hint = '';
	if ($strategy === 'services') {
		$hint = __('Places the link under the Services group in the header menu.', 'leadsforward-core');
	} elseif ($strategy === 'areas') {
		$hint = __('Places the link under the Service Areas group in the header menu.', 'leadsforward-core');
	} elseif ($strategy === 'blog_index') {
		$hint = __('Places the link under the blog index item.', 'leadsforward-core');
	} elseif ($strategy === 'page_parent') {
		$hint = __('Places the link under the parent page’s menu item, if that page is already in the menu.', 'leadsforward-core');
	}
	wp_nonce_field('lf_pb_nav_menu', 'lf_pb_nav_menu_nonce');
	echo '<div class="misc-pub-section lf-pb-add-to-menu"><label><input type="checkbox" name="lf_pb_add_to_header_menu" value="1" /> ';
	echo esc_html__('Add to header menu on save', 'leadsforward-core');
	echo '</label>';
	if ($hint !== '') {
		echo '<p class="description" style="margin:4px 0 0;">' . esc_html($hint) . '</p>';
	}
	if ($parent_id === 0 && in_array($post->post_type, ['lf_service', 'lf_service_area'], true)) {
		echo '<p class="description" style="margin:4px 0 0;">';
		echo esc_html__('If the Services or Areas dropdown is missing from the menu, add it from the setup wizard or Menus, then use this again.', 'leadsforward-core');
		echo '</p>';
	}
	echo '</div>';
}

function lf_pb_render_header_menu_checkbox(): void {
	global $post;
	if (!$post instanceof \WP_Post) {
		return;
	}
	lf_pb_render_add_to_header_menu_markup($post);
}

function lf_pb_register_header_menu_metabox(): void {
	foreach (lf_pb_menu_assist_post_types() as $pt) {
		add_meta_box(
			'lf_pb_header_menu_add',
			__('Header menu', 'leadsforward-core'),
			static function (\WP_Post $post): void {
				lf_pb_render_add_to_header_menu_markup($post);
			},
			$pt,
			'side',
			'default'
		);
	}
}

function lf_pb_save_header_menu_checkbox(int $post_id, \WP_Post $post): void {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}
	if (!isset($_POST['lf_pb_nav_menu_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['lf_pb_nav_menu_nonce'])), 'lf_pb_nav_menu')) {
		return;
	}
	if (empty($_POST['lf_pb_add_to_header_menu'])) {
		return;
	}
	if (!in_array($post->post_type, lf_pb_menu_assist_post_types(), true)) {
		return;
	}
	if (!current_user_can('edit_post', $post_id) || !current_user_can('edit_theme_options')) {
		return;
	}
	lf_pb_try_add_post_to_header_menu($post);
}

function lf_pb_admin_nav_notice(): void {
	if (!is_admin()) {
		return;
	}
	$uid = get_current_user_id();
	if ($uid <= 0) {
		return;
	}
	$msg = get_transient('lf_pb_nav_notice_' . $uid);
	if (!is_string($msg) || $msg === '') {
		return;
	}
	delete_transient('lf_pb_nav_notice_' . $uid);
	echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($msg) . '</p></div>';
}

add_action('add_meta_boxes', 'lf_pb_register_header_menu_metabox', 20);
add_action('post_submitbox_misc_actions', 'lf_pb_render_header_menu_checkbox', 11);
add_action('save_post', 'lf_pb_save_header_menu_checkbox', 55, 2);
add_action('admin_notices', 'lf_pb_admin_nav_notice');

