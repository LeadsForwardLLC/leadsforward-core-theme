<?php
/**
 * Register ACF blocks. Server-rendered via PHP templates.
 * Layout/style variants via block attributes.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('block_categories_all', 'lf_block_categories', 10, 2);

function lf_block_categories(array $categories, \WP_Block_Editor_Context $context): array {
	$categories[] = [
		'slug'  => 'leadsforward',
		'title' => __('LeadsForward', 'leadsforward-core'),
		'icon'  => null,
	];
	return $categories;
}

add_action('acf/init', 'lf_register_blocks');

function lf_register_blocks(): void {
	if (!function_exists('acf_register_block_type')) {
		return;
	}

	$blocks = [
		[
			'name'            => 'lf_hero',
			'title'           => __('Hero', 'leadsforward-core'),
			'description'     => __('Hero section with heading and optional CTA.', 'leadsforward-core'),
			'template'        => 'hero',
			'category'        => 'leadsforward',
			'icon'            => 'cover-image',
			'keywords'        => ['hero', 'banner'],
			'mode'            => 'preview',
			'supports'        => ['align' => ['full', 'wide'], 'customClassName' => true],
			'example'         => ['attributes' => ['mode' => 'preview']],
		],
		[
			'name'            => 'lf_trust_reviews',
			'title'           => __('Trust / Reviews', 'leadsforward-core'),
			'description'     => __('Display testimonials or review snippets.', 'leadsforward-core'),
			'template'        => 'trust-reviews',
			'category'        => 'leadsforward',
			'icon'            => 'format-quote',
			'keywords'        => ['testimonials', 'reviews', 'trust'],
			'mode'            => 'preview',
			'supports'        => ['align' => true, 'customClassName' => true],
		],
		[
			'name'            => 'lf_service_grid',
			'title'           => __('Service Grid', 'leadsforward-core'),
			'description'     => __('Grid of service links or cards.', 'leadsforward-core'),
			'template'        => 'service-grid',
			'category'        => 'leadsforward',
			'icon'            => 'grid-view',
			'keywords'        => ['services', 'grid'],
			'mode'            => 'preview',
			'supports'        => ['align' => true, 'customClassName' => true],
		],
		[
			'name'            => 'lf_cta',
			'title'           => __('CTA', 'leadsforward-core'),
			'description'     => __('Call-to-action with optional form embed.', 'leadsforward-core'),
			'template'        => 'cta',
			'category'        => 'leadsforward',
			'icon'            => 'megaphone',
			'keywords'        => ['cta', 'form', 'conversion'],
			'mode'            => 'preview',
			'supports'        => ['align' => true, 'customClassName' => true],
		],
		[
			'name'            => 'lf_faq_accordion',
			'title'           => __('FAQ Accordion', 'leadsforward-core'),
			'description'     => __('Accordion list of FAQs.', 'leadsforward-core'),
			'template'        => 'faq-accordion',
			'category'        => 'leadsforward',
			'icon'            => 'editor-help',
			'keywords'        => ['faq', 'accordion'],
			'mode'            => 'preview',
			'supports'        => ['align' => true, 'customClassName' => true],
		],
		[
			'name'            => 'lf_map_nap',
			'title'           => __('Map + NAP', 'leadsforward-core'),
			'description'     => __('Map embed with name, address, phone.', 'leadsforward-core'),
			'template'        => 'map-nap',
			'category'        => 'leadsforward',
			'icon'            => 'location-alt',
			'keywords'        => ['map', 'nap', 'address'],
			'mode'            => 'preview',
			'supports'        => ['align' => true, 'customClassName' => true],
		],
	];

	foreach ($blocks as $config) {
		$template = $config['template'];
		unset($config['template']);
		$config['render_callback'] = function (array $block, string $content = '', bool $is_preview = false) use ($template): void {
			lf_render_block_template($template, $block, $is_preview);
		};
		acf_register_block_type($config);
	}
}

/**
 * Load block PHP template. Passes $block and $is_preview. Fail gracefully if ACF off.
 */
function lf_render_block_template(string $name, array $block, bool $is_preview = false): void {
	$path = LF_THEME_DIR . '/templates/blocks/' . $name . '.php';
	if (!is_readable($path)) {
		if (current_user_can('edit_posts')) {
			echo '<p class="lf-block-placeholder">' . esc_html__('Block template missing:', 'leadsforward-core') . ' ' . esc_html($name) . '</p>';
		}
		return;
	}
	$block_id     = $block['id'] ?? '';
	$block_attrs  = $block['attributes'] ?? [];
	$variant      = $block_attrs['variant'] ?? $block_attrs['layout'] ?? 'default';
	$block['variant'] = $variant;
	include $path;
}
