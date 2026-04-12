<?php
/**
 * Quote Builder: full-screen multi-step modal with safe admin config.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_QUOTE_BUILDER_OPTION = 'lf_quote_builder_config';
const LF_QUOTE_BUILDER_MANUAL_OPTION = 'lf_quote_builder_manual_override';
const LF_QUOTE_BUILDER_SUBMISSIONS = 'lf_quote_builder_submissions';
const LF_QUOTE_BUILDER_INTEGRATIONS = 'lf_quote_builder_integrations';
const LF_QUOTE_BUILDER_GHL_ERRORS = 'lf_quote_builder_ghl_errors';
const LF_QUOTE_BUILDER_ANALYTICS_TABLE = 'lf_quote_builder_analytics';
const LF_QUOTE_BUILDER_GHL_RETRY_QUEUE = 'lf_quote_builder_ghl_retry_queue';
const LF_QUOTE_BUILDER_GHL_RETRY_HOOK = 'lf_quote_builder_process_ghl_retries';

add_action('admin_init', 'lf_quote_builder_handle_save');
add_action('admin_init', 'lf_quote_builder_integrations_handle_save');
add_action('admin_init', 'lf_quote_builder_handle_resync_from_niche');
add_action('admin_init', 'lf_quote_builder_maybe_create_analytics_table');
add_action('admin_init', 'lf_quote_builder_redirect_legacy_pages');
add_action('admin_init', 'lf_quote_builder_handle_reset_analytics');
add_action('wp_enqueue_scripts', 'lf_quote_builder_enqueue_assets');
add_action('wp_footer', 'lf_quote_builder_render_modal', 20);
add_action('wp_ajax_lf_quote_builder_submit', 'lf_quote_builder_handle_submit');
add_action('wp_ajax_nopriv_lf_quote_builder_submit', 'lf_quote_builder_handle_submit');
add_action('wp_ajax_lf_quote_builder_event', 'lf_quote_builder_handle_event');
add_action('wp_ajax_nopriv_lf_quote_builder_event', 'lf_quote_builder_handle_event');
add_action(LF_QUOTE_BUILDER_GHL_RETRY_HOOK, 'lf_quote_builder_process_ghl_retries');

function lf_quote_builder_default_config(?string $niche_slug = null): array {
	$service_options = lf_quote_builder_service_options($niche_slug);
	$project_extra_fields = lf_quote_builder_niche_fields($niche_slug);
	$config = [
		'version' => 1,
		'steps' => [
			[
				'id'      => 'service_type',
				'title'   => __('What can we help you with?', 'leadsforward-core'),
				'helper'  => __('Choose the service that best matches your need.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'      => 'service_type',
						'label'    => __('Service type', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => true,
						'options'  => $service_options,
						'default' => '',
					],
				],
			],
			[
				'id'      => 'project_details',
				'title'   => __('Tell us about your project', 'leadsforward-core'),
				'helper'  => __('A little detail helps us send the right expert.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => array_merge([
					[
						'key'         => 'project_details',
						'label'       => __('Project details (optional)', 'leadsforward-core'),
						'type'        => 'textarea',
						'required'    => false,
						'placeholder' => __('Briefly describe what you need help with…', 'leadsforward-core'),
						'default'     => '',
					],
				], $project_extra_fields, [
					[
						'key'      => 'project_timeline',
						'label'    => __('When do you need help?', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => false,
						'options'  => [
							__('As soon as possible', 'leadsforward-core'),
							__('This week', 'leadsforward-core'),
							__('Next 2-4 weeks', 'leadsforward-core'),
							__('Just researching', 'leadsforward-core'),
						],
						'default' => '',
					],
				]),
			],
			[
				'id'      => 'location',
				'title'   => __('Where should we send help?', 'leadsforward-core'),
				'helper'  => __('We only use this to plan your estimate and arrival.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'         => 'address_street',
						'label'       => __('Street address', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('123 Main Street', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'address_city',
						'label'       => __('City', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('City', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'address_zip',
						'label'       => __('ZIP code', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('ZIP', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'contact',
				'title'   => __('How can we reach you?', 'leadsforward-core'),
				'helper'  => __('We respond quickly and never share your information.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'         => 'full_name',
						'label'       => __('Full name', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __('Your name', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'phone',
						'label'       => __('Phone', 'leadsforward-core'),
						'type'        => 'tel',
						'required'    => true,
						'placeholder' => __('(555) 123-4567', 'leadsforward-core'),
						'default'     => '',
					],
					[
						'key'         => 'email',
						'label'       => __('Email', 'leadsforward-core'),
						'type'        => 'email',
						'required'    => true,
						'placeholder' => __('you@email.com', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'schedule',
				'title'   => __('Scheduling preference', 'leadsforward-core'),
				'helper'  => __('Tell us how and when you prefer to connect.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'standard',
				'fields'  => [
					[
						'key'      => 'contact_method',
						'label'    => __('Preferred contact method', 'leadsforward-core'),
						'type'     => 'choice',
						'required' => true,
						'options'  => [
							__('Call me', 'leadsforward-core'),
							__('Text me', 'leadsforward-core'),
							__('Email me', 'leadsforward-core'),
						],
						'default' => '',
					],
					[
						'key'         => 'preferred_time',
						'label'       => __('Preferred time (optional)', 'leadsforward-core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __('e.g. Weekdays after 4pm', 'leadsforward-core'),
						'default'     => '',
					],
				],
			],
			[
				'id'      => 'confirmation',
				'title'   => __('Request received', 'leadsforward-core'),
				'helper'  => __('Thanks for the details. We’ll follow up shortly.', 'leadsforward-core'),
				'enabled' => true,
				'type'    => 'confirmation',
				'confirmation_title' => __('Thanks! Your request is on the way.', 'leadsforward-core'),
				'confirmation_body'  => __('A local specialist will review your details and contact you shortly to confirm next steps.', 'leadsforward-core'),
				'fields'  => [],
			],
		],
	];
	return apply_filters('lf_quote_builder_default_config', $config, $niche_slug);
}

function lf_quote_builder_service_options(?string $niche_slug = null): array {
	$options = [];
	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 100,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($services as $service) {
		if ($service instanceof \WP_Post) {
			$options[] = $service->post_title;
		}
	}
	if (empty($options) && function_exists('lf_get_niche') && $niche_slug) {
		$niche = lf_get_niche($niche_slug);
		if (!empty($niche['services']) && is_array($niche['services'])) {
			$options = array_values(array_filter(array_map('strval', $niche['services'])));
		}
	}
	if (empty($options)) {
		$options = [
			__('General Service', 'leadsforward-core'),
			__('Repair', 'leadsforward-core'),
			__('Installation', 'leadsforward-core'),
			__('Maintenance', 'leadsforward-core'),
		];
	}
	return apply_filters('lf_quote_builder_service_options', $options, $niche_slug);
}

function lf_quote_builder_niche_fields(?string $niche_slug = null): array {
	$fields = [];
	$niche = $niche_slug
		? (string) $niche_slug
		: (string) get_option('lf_homepage_niche_slug', function_exists('lf_default_niche_slug') ? lf_default_niche_slug() : 'foundation-repair');
	$niche_data = function_exists('lf_get_niche') ? lf_get_niche($niche) : null;
	$layout_profile = is_array($niche_data) ? (string) ($niche_data['layout_profile'] ?? '') : '';
	if ($niche === 'general') {
		return apply_filters('lf_quote_builder_niche_fields', [], $niche);
	}
	$project_fields = [
		[
			'key' => 'project_scope',
			'label' => __('Project scope', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Full project', 'leadsforward-core'),
				__('Partial update', 'leadsforward-core'),
				__('Repair', 'leadsforward-core'),
				__('New build', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'project_timeline_priority',
			'label' => __('Timeline priority', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('As soon as possible', 'leadsforward-core'),
				__('1-3 months', 'leadsforward-core'),
				__('3-6 months', 'leadsforward-core'),
				__('Just researching', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$cleaning_fields = [
		[
			'key' => 'property_type',
			'label' => __('Property type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Residential', 'leadsforward-core'),
				__('Commercial', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'area_size',
			'label' => __('Area size', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Small', 'leadsforward-core'),
				__('Medium', 'leadsforward-core'),
				__('Large', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'last_service',
			'label' => __('When was it last serviced?', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Within 6 months', 'leadsforward-core'),
				__('1-2 years ago', 'leadsforward-core'),
				__('3+ years ago', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$pw_light = [
		[
			'key' => 'property_type',
			'label' => __('Property type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Residential', 'leadsforward-core'),
				__('Commercial', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$duct_fields = [
		[
			'key' => 'property_type',
			'label' => __('Property type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Residential', 'leadsforward-core'),
				__('Commercial', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'duct_concern',
			'label' => __('Main concern', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Allergies / dust', 'leadsforward-core'),
				__('Musty or stale air', 'leadsforward-core'),
				__('New home / first cleaning', 'leadsforward-core'),
				__('Routine maintenance', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$carpet_light = [
		[
			'key' => 'carpet_area',
			'label' => __('How much carpet?', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('1 room', 'leadsforward-core'),
				__('2–3 rooms', 'leadsforward-core'),
				__('4+ rooms', 'leadsforward-core'),
				__('Stairs or hallways', 'leadsforward-core'),
				__('Whole home', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$tree_quote_fields = [
		[
			'key' => 'tree_job_type',
			'label' => __('What do you need?', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Removal', 'leadsforward-core'),
				__('Trimming / pruning', 'leadsforward-core'),
				__('Health or hazard check', 'leadsforward-core'),
				__('Storm cleanup', 'leadsforward-core'),
				__('Stump work', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$water_fields = [
		[
			'key' => 'water_issue',
			'label' => __('Issue type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Leak or seepage', 'leadsforward-core'),
				__('Flooding', 'leadsforward-core'),
				__('Foundation cracks', 'leadsforward-core'),
				__('Moisture or mold', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'water_urgency',
			'label' => __('Urgency', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Emergency', 'leadsforward-core'),
				__('Within 1-2 weeks', 'leadsforward-core'),
				__('Within a month', 'leadsforward-core'),
				__('Just researching', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$electrical_fields = [
		[
			'key' => 'electrical_service',
			'label' => __('Service type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Repair', 'leadsforward-core'),
				__('Installation', 'leadsforward-core'),
				__('Upgrade', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'electrical_issue',
			'label' => __('Issue type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Outlets or switches', 'leadsforward-core'),
				__('Lighting', 'leadsforward-core'),
				__('Panel or breaker', 'leadsforward-core'),
				__('Wiring', 'leadsforward-core'),
				__('Other', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$pest_fields = [
		[
			'key' => 'pest_type',
			'label' => __('Pest type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Ants', 'leadsforward-core'),
				__('Roaches', 'leadsforward-core'),
				__('Rodents', 'leadsforward-core'),
				__('Termites', 'leadsforward-core'),
				__('Other', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'pest_frequency',
			'label' => __('Service preference', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('One-time treatment', 'leadsforward-core'),
				__('Ongoing prevention', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$pool_build_fields = [
		[
			'key' => 'pool_type',
			'label' => __('Pool type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('In-ground', 'leadsforward-core'),
				__('Above-ground', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'pool_scope',
			'label' => __('Project scope', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('New build', 'leadsforward-core'),
				__('Resurface/renovate', 'leadsforward-core'),
				__('Equipment upgrade', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$pool_service_fields = [
		[
			'key' => 'pool_service_need',
			'label' => __('What do you need help with?', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Routine care / chemicals', 'leadsforward-core'),
				__('Equipment repair', 'leadsforward-core'),
				__('Open / close', 'leadsforward-core'),
				__('Something else', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$cleanup_fields = [
		[
			'key' => 'load_size',
			'label' => __('Load size', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('A few items', 'leadsforward-core'),
				__('One room', 'leadsforward-core'),
				__('Multiple rooms', 'leadsforward-core'),
				__('Whole property', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'material_type',
			'label' => __('Material type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Household items', 'leadsforward-core'),
				__('Construction debris', 'leadsforward-core'),
				__('Yard waste', 'leadsforward-core'),
				__('Mixed', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$vehicle_fields = [
		[
			'key' => 'vehicle_type',
			'label' => __('Vehicle type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('RV', 'leadsforward-core'),
				__('Boat', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'vehicle_service',
			'label' => __('Service type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Inspection', 'leadsforward-core'),
				__('Repair', 'leadsforward-core'),
				__('Detailing', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$snow_fields = [
		[
			'key' => 'snow_property_type',
			'label' => __('Property type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Residential', 'leadsforward-core'),
				__('Commercial', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'snow_frequency',
			'label' => __('Service frequency', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Per snowfall', 'leadsforward-core'),
				__('Seasonal contract', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$solar_fields = [
		[
			'key' => 'solar_service',
			'label' => __('Service type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('New installation', 'leadsforward-core'),
				__('System upgrade', 'leadsforward-core'),
				__('Maintenance', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'roof_type',
			'label' => __('Roof type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Asphalt shingle', 'leadsforward-core'),
				__('Metal', 'leadsforward-core'),
				__('Tile', 'leadsforward-core'),
				__('Flat/low-slope', 'leadsforward-core'),
				__('Not sure', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$window_fields = [
		[
			'key' => 'window_project_type',
			'label' => __('Project type', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('Replacement', 'leadsforward-core'),
				__('Repair', 'leadsforward-core'),
				__('New install', 'leadsforward-core'),
			],
			'default' => '',
		],
		[
			'key' => 'opening_count',
			'label' => __('How many openings?', 'leadsforward-core'),
			'type' => 'choice',
			'required' => false,
			'options' => [
				__('1-3', 'leadsforward-core'),
				__('4-8', 'leadsforward-core'),
				__('9+', 'leadsforward-core'),
			],
			'default' => '',
		],
	];
	$map = [
		'remodeling' => $project_fields,
		'pressure-washing' => $pw_light,
		'power-washing' => $pw_light,
		'gutter-services' => $pw_light,
		'window-cleaning' => $window_fields,
		'carpet-cleaning' => $carpet_light,
		'air-duct-cleaning' => $duct_fields,
		'tree-service' => $tree_quote_fields,
		'roofing' => [
			[
				'key' => 'roof_type',
				'label' => __('Roof type', 'leadsforward-core'),
				'type' => 'choice',
				'required' => false,
				'options' => [
					__('Asphalt shingle', 'leadsforward-core'),
					__('Metal', 'leadsforward-core'),
					__('Tile', 'leadsforward-core'),
					__('Flat/low-slope', 'leadsforward-core'),
					__('Not sure', 'leadsforward-core'),
				],
				'default' => '',
			],
			[
				'key' => 'roof_issue',
				'label' => __('What issue are you seeing?', 'leadsforward-core'),
				'type' => 'choice',
				'required' => false,
				'options' => [
					__('Leak', 'leadsforward-core'),
					__('Storm damage', 'leadsforward-core'),
					__('Aging roof', 'leadsforward-core'),
					__('Missing shingles', 'leadsforward-core'),
					__('Just need an inspection', 'leadsforward-core'),
				],
				'default' => '',
			],
		],
		'plumbing' => [
			[
				'key' => 'plumbing_issue',
				'label' => __('Plumbing issue', 'leadsforward-core'),
				'type' => 'choice',
				'required' => false,
				'options' => [
					__('Leak', 'leadsforward-core'),
					__('Clog/backup', 'leadsforward-core'),
					__('Water heater', 'leadsforward-core'),
					__('Fixture install', 'leadsforward-core'),
					__('Other', 'leadsforward-core'),
				],
				'default' => '',
			],
		],
		'hvac' => [
			[
				'key' => 'hvac_system',
				'label' => __('System type', 'leadsforward-core'),
				'type' => 'choice',
				'required' => false,
				'options' => [
					__('Heating', 'leadsforward-core'),
					__('Cooling', 'leadsforward-core'),
					__('Both', 'leadsforward-core'),
					__('Not sure', 'leadsforward-core'),
				],
				'default' => '',
			],
			[
				'key' => 'hvac_issue',
				'label' => __('Issue type', 'leadsforward-core'),
				'type' => 'choice',
				'required' => false,
				'options' => [
					__('Not heating/cooling', 'leadsforward-core'),
					__('Strange noise', 'leadsforward-core'),
					__('Poor airflow', 'leadsforward-core'),
					__('Maintenance/tune-up', 'leadsforward-core'),
				],
				'default' => '',
			],
		],
		'electrical' => $electrical_fields,
		'pest-control' => $pest_fields,
		'pool-service' => $pool_service_fields,
		'pool-building' => $pool_build_fields,
		'pool-resurfacing' => $pool_build_fields,
		'junk-removal' => $cleanup_fields,
		'dumpster-rental' => $cleanup_fields,
		'rv-repair' => $vehicle_fields,
		'boat-detailing' => $vehicle_fields,
		'snow-removal' => $snow_fields,
		'water-damage' => $water_fields,
		'waterproofing' => $water_fields,
		'foundation-repair' => $water_fields,
		'solar' => $solar_fields,
		'windows-doors' => $window_fields,
		'shower-doors' => $window_fields,
		'siding' => $window_fields,
	];
	if (!empty($map[$niche])) {
		$fields = $map[$niche];
	} elseif (in_array($niche, ['excavation', 'concrete-cutting', 'stamped-concrete', 'paving', 'masonry', 'landscaping', 'fencing', 'deck-building', 'lanais-patios'], true)) {
		$fields = $project_fields;
	} elseif ($layout_profile === 'project-heavy') {
		$fields = $project_fields;
	}
	return apply_filters('lf_quote_builder_niche_fields', $fields, $niche);
}

function lf_quote_builder_get_config(): array {
	$stored = get_option(LF_QUOTE_BUILDER_OPTION, null);
	$manual = (bool) get_option(LF_QUOTE_BUILDER_MANUAL_OPTION, false);
	$default = lf_quote_builder_default_config(get_option('lf_homepage_niche_slug', ''));
	if (is_array($stored) && !empty($stored)) {
		return lf_quote_builder_merge_config($stored, $default);
	}
	if (!$manual) {
		update_option(LF_QUOTE_BUILDER_OPTION, $default, true);
	}
	return $default;
}

function lf_quote_builder_merge_config(array $stored, array $default): array {
	$out = $default;
	$out['version'] = $default['version'] ?? 1;
	$stored_steps = $stored['steps'] ?? [];
	if (!is_array($stored_steps)) {
		return $out;
	}
	foreach ($out['steps'] as $index => $step) {
		$match = null;
		foreach ($stored_steps as $candidate) {
			if (!empty($candidate['id']) && $candidate['id'] === $step['id']) {
				$match = $candidate;
				break;
			}
		}
		if (is_array($match)) {
			$out['steps'][$index] = array_merge($step, $match);
		}
	}
	return $out;
}

function lf_quote_builder_apply_niche_config(string $niche_slug): void {
	$config = lf_quote_builder_default_config($niche_slug);
	update_option(LF_QUOTE_BUILDER_OPTION, $config, true);
	update_option(LF_QUOTE_BUILDER_MANUAL_OPTION, false, true);
}

function lf_quote_builder_get_integrations(): array {
	$defaults = [
		'ghl_enabled' => false,
		'ghl_webhook' => '',
		'ghl_pipeline' => '',
		'ghl_tags' => '',
		'ghl_source' => __('Website Quote', 'leadsforward-core'),
	];
	$stored = get_option(LF_QUOTE_BUILDER_INTEGRATIONS, []);
	if (!is_array($stored)) {
		return $defaults;
	}
	return array_merge($defaults, $stored);
}

function lf_quote_builder_validate_webhook_url(string $url): string {
	$url = esc_url_raw($url);
	if ($url === '' || !wp_http_validate_url($url)) {
		return '';
	}
	$scheme = wp_parse_url($url, PHP_URL_SCHEME);
	if (!in_array($scheme, ['http', 'https'], true)) {
		return '';
	}
	return $url;
}

function lf_quote_builder_integrations_handle_save(): void {
	if (!isset($_POST['lf_quote_builder_integrations_nonce'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_quote_builder_integrations_nonce'], 'lf_quote_builder_integrations_save')) {
		return;
	}
	$enabled = !empty($_POST['lf_qb_ghl_enabled']);
	$webhook = isset($_POST['lf_qb_ghl_webhook']) ? lf_quote_builder_validate_webhook_url(wp_unslash((string) $_POST['lf_qb_ghl_webhook'])) : '';
	$pipeline = isset($_POST['lf_qb_ghl_pipeline']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_qb_ghl_pipeline'])) : '';
	$tags = isset($_POST['lf_qb_ghl_tags']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_qb_ghl_tags'])) : '';
	$source = isset($_POST['lf_qb_ghl_source']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_qb_ghl_source'])) : '';
	if ($source === '') {
		$source = __('Website Quote', 'leadsforward-core');
	}

	if ($enabled && $webhook === '') {
		update_option('lf_quote_builder_integrations_error', __('Please provide a valid GHL webhook URL.', 'leadsforward-core'), true);
		wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&section=integrations&saved=0'));
		exit;
	}

	$settings = [
		'ghl_enabled' => (bool) $enabled,
		'ghl_webhook' => $webhook,
		'ghl_pipeline' => $pipeline,
		'ghl_tags' => $tags,
		'ghl_source' => $source,
	];
	update_option(LF_QUOTE_BUILDER_INTEGRATIONS, $settings, true);
	delete_option('lf_quote_builder_integrations_error');
	wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&section=integrations&saved=1'));
	exit;
}

function lf_quote_builder_redirect_legacy_pages(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	if (empty($_GET['page'])) {
		return;
	}
	$page = sanitize_key((string) $_GET['page']);
	if (in_array($page, ['lf-quote-builder-integrations', 'lf-quote-builder-analytics'], true)) {
		wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&section=' . ($page === 'lf-quote-builder-integrations' ? 'integrations' : 'analytics')));
		exit;
	}
}

function lf_quote_builder_handle_resync_from_niche(): void {
	if (!isset($_POST['lf_qb_resync_from_niche'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!isset($_POST['lf_qb_resync_from_niche_nonce']) || !wp_verify_nonce($_POST['lf_qb_resync_from_niche_nonce'], 'lf_quote_builder_resync_from_niche')) {
		return;
	}
	$slug = (string) get_option('lf_homepage_niche_slug', '');
	if ($slug === '' && function_exists('lf_default_niche_slug')) {
		$slug = lf_default_niche_slug();
	}
	$slug = sanitize_title($slug);
	if ($slug === '') {
		$slug = 'general';
	}
	lf_quote_builder_apply_niche_config($slug);
	wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&resynced=1'));
	exit;
}

function lf_quote_builder_handle_reset_analytics(): void {
	if (!isset($_POST['lf_qb_reset_analytics'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!isset($_POST['lf_qb_reset_analytics_nonce']) || !wp_verify_nonce($_POST['lf_qb_reset_analytics_nonce'], 'lf_qb_reset_analytics')) {
		return;
	}
	lf_quote_builder_maybe_create_analytics_table();
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	if ($exists === $table) {
		$wpdb->query("TRUNCATE TABLE $table");
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&section=analytics&reset=1'));
	exit;
}

function lf_quote_builder_get_page_context_data(): array {
	$context = 'page';
	if (is_front_page()) {
		$context = 'homepage';
	} elseif (is_singular('lf_service')) {
		$context = 'service';
	} elseif (is_singular('lf_service_area')) {
		$context = 'service_area';
	}
	$page_id = (int) get_queried_object_id();
	$page_title = $page_id ? get_the_title($page_id) : '';
	$page_url = $page_id ? get_permalink($page_id) : '';
	return [
		'context' => $context,
		'page_id' => $page_id,
		'page_title' => is_string($page_title) ? $page_title : '',
		'page_url' => is_string($page_url) ? $page_url : '',
	];
}

function lf_quote_builder_page_label_from_context(array $context): string {
	$title = isset($context['page_title']) ? (string) $context['page_title'] : '';
	$id = isset($context['page_id']) ? (int) $context['page_id'] : 0;
	$title = $title !== '' ? $title : __('Page', 'leadsforward-core');
	return $id > 0 ? ($title . ' (#' . $id . ')') : $title;
}

function lf_quote_builder_page_label_from_clean(array $clean): string {
	$title = isset($clean['page_title']) ? (string) $clean['page_title'] : '';
	$id = isset($clean['page_id']) ? (int) $clean['page_id'] : 0;
	$title = $title !== '' ? $title : __('Page', 'leadsforward-core');
	return $id > 0 ? ($title . ' (#' . $id . ')') : $title;
}

function lf_quote_builder_get_form_variant(): string {
	$config = lf_quote_builder_get_config();
	$version = isset($config['version']) ? (int) $config['version'] : 1;
	return 'v' . $version;
}

function lf_quote_builder_handle_save(): void {
	if (!isset($_POST['lf_quote_builder_nonce'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_quote_builder_nonce'], 'lf_quote_builder_save')) {
		return;
	}
	$defaults = lf_quote_builder_default_config(get_option('lf_homepage_niche_slug', ''));
	$input = $_POST['lf_qb_steps'] ?? [];
	$config = lf_quote_builder_sanitize_config($input, $defaults);
	update_option(LF_QUOTE_BUILDER_OPTION, $config, true);
	update_option(LF_QUOTE_BUILDER_MANUAL_OPTION, true, true);
	wp_safe_redirect(admin_url('admin.php?page=lf-quote-builder&saved=1'));
	exit;
}

function lf_quote_builder_sanitize_config($input, array $defaults): array {
	$out = $defaults;
	$raw_steps = is_array($input) ? $input : [];
	foreach ($out['steps'] as $index => $step) {
		$step_id = $step['id'];
		$raw = $raw_steps[$step_id] ?? [];
		$out['steps'][$index]['enabled'] = !empty($raw['enabled']);
		$title = isset($raw['title']) ? sanitize_text_field(wp_unslash($raw['title'])) : $step['title'];
		$helper = isset($raw['helper']) ? sanitize_textarea_field(wp_unslash($raw['helper'])) : $step['helper'];
		$out['steps'][$index]['title'] = $title;
		$out['steps'][$index]['helper'] = $helper;
		if (($step['type'] ?? '') === 'confirmation') {
			$confirm_title = isset($raw['confirmation_title']) ? sanitize_text_field(wp_unslash($raw['confirmation_title'])) : ($step['confirmation_title'] ?? '');
			$confirm_body = isset($raw['confirmation_body']) ? sanitize_textarea_field(wp_unslash($raw['confirmation_body'])) : ($step['confirmation_body'] ?? '');
			$out['steps'][$index]['confirmation_title'] = $confirm_title;
			$out['steps'][$index]['confirmation_body'] = $confirm_body;
			continue;
		}
		$fields = $step['fields'] ?? [];
		foreach ($fields as $field_index => $field) {
			$key = $field['key'];
			$raw_field = $raw['fields'][$key] ?? [];
			$out_field = $field;
			$out_field['label'] = isset($raw_field['label']) ? sanitize_text_field(wp_unslash($raw_field['label'])) : $field['label'];
			$out_field['required'] = !empty($raw_field['required']);
			$out_field['default'] = isset($raw_field['default']) ? sanitize_text_field(wp_unslash($raw_field['default'])) : ($field['default'] ?? '');
			$out_field['placeholder'] = isset($raw_field['placeholder']) ? sanitize_text_field(wp_unslash($raw_field['placeholder'])) : ($field['placeholder'] ?? '');
			if (($field['type'] ?? '') === 'choice') {
				$options_raw = isset($raw_field['options']) ? wp_unslash($raw_field['options']) : '';
				$options = array_filter(array_map('sanitize_text_field', array_map('trim', explode("\n", (string) $options_raw))));
				$out_field['options'] = !empty($options) ? array_values($options) : ($field['options'] ?? []);
			}
			$out['steps'][$index]['fields'][$field_index] = $out_field;
		}
	}
	return $out;
}

function lf_quote_builder_maybe_create_analytics_table(): void {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_date date NOT NULL,
		event_type varchar(32) NOT NULL,
		step_id varchar(64) NOT NULL DEFAULT '',
		context varchar(32) NOT NULL DEFAULT '',
		niche varchar(32) NOT NULL DEFAULT '',
		form_variant varchar(32) NOT NULL DEFAULT '',
		meta_key varchar(32) NOT NULL DEFAULT '',
		meta_value varchar(128) NOT NULL DEFAULT '',
		device varchar(16) NOT NULL DEFAULT '',
		returning tinyint(1) NOT NULL DEFAULT 0,
		count bigint(20) unsigned NOT NULL DEFAULT 0,
		total_time bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY lf_qb_unique (event_date, event_type, step_id, context, niche, form_variant, meta_key, meta_value, device, returning)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	if ($exists !== $table) {
		return;
	}
	$columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
	$column_names = array_map(function ($row) {
		return $row['Field'] ?? '';
	}, $columns);
	$add_columns = [
		'meta_key' => "ALTER TABLE $table ADD COLUMN meta_key varchar(32) NOT NULL DEFAULT ''",
		'meta_value' => "ALTER TABLE $table ADD COLUMN meta_value varchar(128) NOT NULL DEFAULT ''",
		'device' => "ALTER TABLE $table ADD COLUMN device varchar(16) NOT NULL DEFAULT ''",
		'returning' => "ALTER TABLE $table ADD COLUMN returning tinyint(1) NOT NULL DEFAULT 0",
	];
	foreach ($add_columns as $col => $sql_add) {
		if (!in_array($col, $column_names, true)) {
			$wpdb->query($sql_add);
		}
	}
	$indexes = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'lf_qb_unique'", ARRAY_A);
	$index_cols = [];
	foreach ($indexes as $index) {
		if (!empty($index['Column_name'])) {
			$index_cols[] = $index['Column_name'];
		}
	}
	$desired = ['event_date', 'event_type', 'step_id', 'context', 'niche', 'form_variant', 'meta_key', 'meta_value', 'device', 'returning'];
	sort($index_cols);
	$sorted_desired = $desired;
	sort($sorted_desired);
	if (empty($index_cols)) {
		$wpdb->query("ALTER TABLE $table ADD UNIQUE KEY lf_qb_unique (" . implode(',', $desired) . ")");
	} elseif ($index_cols !== $sorted_desired) {
		$wpdb->query("ALTER TABLE $table DROP INDEX lf_qb_unique");
		$wpdb->query("ALTER TABLE $table ADD UNIQUE KEY lf_qb_unique (" . implode(',', $desired) . ")");
	}
	update_option('lf_qb_analytics_ready', 1, true);
}

function lf_quote_builder_record_event(string $event, string $step_id, string $context, int $duration = 0, string $niche = '', string $variant = '', string $meta_key = '', string $meta_value = '', string $device = '', int $returning = 0): void {
	global $wpdb;
	$event = sanitize_text_field($event);
	$step_id = sanitize_text_field($step_id);
	$context = sanitize_text_field($context);
	$niche = sanitize_text_field($niche);
	$variant = sanitize_text_field($variant);
	$meta_key = sanitize_text_field($meta_key);
	$meta_value = sanitize_text_field($meta_value);
	$device = sanitize_text_field($device);
	$returning = $returning ? 1 : 0;
	$duration = max(0, (int) $duration);
	if ($event === '') {
		return;
	}
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$date = wp_date('Y-m-d');
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO $table (event_date, event_type, step_id, context, niche, form_variant, meta_key, meta_value, device, returning, count, total_time)
			 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %d, 1, %d)
			 ON DUPLICATE KEY UPDATE count = count + 1, total_time = total_time + %d",
			$date,
			$event,
			$step_id,
			$context,
			$niche,
			$variant,
			$meta_key,
			$meta_value,
			$device,
			$returning,
			$duration,
			$duration
		)
	);
}

function lf_quote_builder_handle_event(): void {
	check_ajax_referer('lf_quote_builder', 'nonce');
	if (function_exists('lf_security_rate_limit_allow') && !lf_security_rate_limit_allow('quote_builder_event', 90, 300)) {
		wp_send_json_success(['ok' => true]);
	}
	lf_quote_builder_maybe_create_analytics_table();
	$event = isset($_POST['event']) ? sanitize_text_field(wp_unslash((string) $_POST['event'])) : '';
	$allowed = ['open', 'step_view', 'step_complete', 'abandon', 'complete', 'validation_error'];
	if (!in_array($event, $allowed, true)) {
		wp_send_json_success(['ok' => true]);
	}
	$step_id = isset($_POST['step_id']) ? sanitize_text_field(wp_unslash((string) $_POST['step_id'])) : '';
	$context = isset($_POST['context']) ? sanitize_text_field(wp_unslash((string) $_POST['context'])) : '';
	$niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash((string) $_POST['niche'])) : '';
	$variant = isset($_POST['variant']) ? sanitize_text_field(wp_unslash((string) $_POST['variant'])) : '';
	$duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 0;
	$meta_key = isset($_POST['meta_key']) ? sanitize_key(wp_unslash((string) $_POST['meta_key'])) : '';
	$meta_value = isset($_POST['meta_value']) ? sanitize_text_field(wp_unslash((string) $_POST['meta_value'])) : '';
	$device = isset($_POST['device']) ? sanitize_text_field(wp_unslash((string) $_POST['device'])) : '';
	$returning = isset($_POST['returning']) ? (int) $_POST['returning'] : 0;
	lf_quote_builder_record_event($event, $step_id, $context, $duration, $niche, $variant, $meta_key, $meta_value, $device, $returning);
	wp_send_json_success(['ok' => true]);
}

function lf_quote_builder_enqueue_assets(): void {
	if (is_admin()) {
		return;
	}
	$handle = 'lf-quote-builder';
	$src = LF_THEME_URI . '/assets/js/quote-builder.js';
	wp_enqueue_script($handle, $src, [], LF_THEME_VERSION, true);
	$context = lf_quote_builder_get_page_context_data();
	wp_localize_script($handle, 'lfQuoteBuilder', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_quote_builder'),
		'context'  => [
			'page_context' => $context['context'],
			'page_id' => $context['page_id'],
			'page_title' => $context['page_title'],
			'page_url' => $context['page_url'],
			'niche' => (string) get_option('lf_homepage_niche_slug', ''),
			'form_variant' => lf_quote_builder_get_form_variant(),
		],
	]);
}

function lf_quote_builder_get_review_previews(int $limit = 3): array {
	if (!post_type_exists('lf_testimonial')) {
		return [];
	}
	$limit = max(1, min(5, $limit));
	$posts = get_posts([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => $limit * 2,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	$out = [];
	foreach ($posts as $post) {
		$name = function_exists('get_field') ? (string) get_field('lf_testimonial_reviewer_name', $post->ID) : '';
		$text = function_exists('get_field') ? (string) get_field('lf_testimonial_review_text', $post->ID) : '';
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $post->ID) : 5;
		if ($rating <= 1 || $text === '') {
			continue;
		}
		$text = wp_trim_words($text, 18, '…');
		if ($name === '') {
			$name = $post->post_title;
		}
		$out[] = [
			'name' => $name,
			'text' => $text,
			'rating' => max(1, min(5, $rating ?: 5)),
		];
		if (count($out) >= $limit) {
			break;
		}
	}
	return $out;
}

function lf_quote_builder_render_review_preview(array $review, bool $compact = false): void {
	if (empty($review)) {
		return;
	}
	$wrap_classes = 'lf-quote-reviews lf-quote-reviews--single';
	if ($compact) {
		$wrap_classes .= ' lf-quote-reviews--compact';
	}
	?>
	<div class="<?php echo esc_attr($wrap_classes); ?>" role="note" aria-label="<?php esc_attr_e('Recent review', 'leadsforward-core'); ?>">
		<p class="lf-quote-reviews__eyebrow"><?php esc_html_e('Recent review', 'leadsforward-core'); ?></p>
		<div class="lf-quote-reviews__list">
			<div class="lf-quote-reviews__card">
				<p class="lf-quote-reviews__text"><?php echo esc_html($review['text']); ?></p>
				<div class="lf-quote-reviews__meta">
					<span class="lf-quote-reviews__name"><?php echo esc_html($review['name']); ?></span>
					<span class="lf-quote-reviews__stars" aria-label="<?php echo esc_attr(sprintf(__('%d stars', 'leadsforward-core'), $review['rating'])); ?>">
						<?php for ($s = 1; $s <= 5; $s++) : ?>
							<svg class="lf-quote-reviews__star<?php echo $s <= $review['rating'] ? ' is-filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php endfor; ?>
					</span>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function lf_quote_builder_render_modal(): void {
	if (is_admin()) {
		return;
	}
	$config = lf_quote_builder_get_config();
	$steps = array_values(array_filter($config['steps'] ?? [], function ($step) {
		return !empty($step['enabled']);
	}));
	if (empty($steps)) {
		return;
	}
	$total = count($steps);
	$modal_id = 'lf-quote-builder';
	$first_title_id = 'lf-quote-title-0';
	$review_previews = lf_quote_builder_get_review_previews(3);
	$review_steps = [
		'confirmation' => 0,
	];
	?>
	<div class="lf-quote-modal" id="<?php echo esc_attr($modal_id); ?>" aria-hidden="true">
		<div class="lf-quote-modal__overlay" data-lf-quote-close></div>
		<div class="lf-quote-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($first_title_id); ?>" tabindex="-1">
			<button type="button" class="lf-quote-modal__close" data-lf-quote-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
			<div class="lf-quote-modal__progress">
				<span class="lf-quote-modal__step" id="lf-quote-step-label"><?php echo esc_html(sprintf(__('Step %d of %d', 'leadsforward-core'), 1, $total)); ?></span>
				<div class="lf-quote-modal__bar"><span class="lf-quote-modal__bar-fill" style="width:<?php echo esc_attr((string) (100 / max(1, $total))); ?>%"></span></div>
			</div>
			<form class="lf-quote-form" autocomplete="on">
				<?php
				$context = lf_quote_builder_get_page_context_data();
				?>
				<input type="hidden" name="lf_quote[page_context]" value="<?php echo esc_attr($context['context']); ?>" />
				<input type="hidden" name="lf_quote[page_id]" value="<?php echo esc_attr((string) $context['page_id']); ?>" />
				<input type="hidden" name="lf_quote[page_title]" value="<?php echo esc_attr($context['page_title']); ?>" />
				<input type="hidden" name="lf_quote[page_url]" value="<?php echo esc_attr($context['page_url']); ?>" />
				<input type="hidden" name="lf_quote[device]" value="" />
				<input type="hidden" name="lf_quote[returning]" value="0" />
				<input type="hidden" name="lf_quote[submission_id]" value="" />
				<input type="hidden" name="lf_quote[pages_path]" value="[]" />
				<input type="text" name="lf_quote[website]" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true" />
				<?php foreach ($steps as $index => $step) :
					$step_id = $step['id'] ?? 'step-' . $index;
					$step_type = $step['type'] ?? 'standard';
					$fields = $step['fields'] ?? [];
					$is_confirm = $step_type === 'confirmation';
					?>
					<section class="lf-quote-step<?php echo $index === 0 ? ' is-active' : ''; ?>" data-step-index="<?php echo esc_attr((string) $index); ?>" data-step-id="<?php echo esc_attr($step_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
						<h2 class="lf-quote-step__title" id="<?php echo esc_attr('lf-quote-title-' . $index); ?>"><?php echo esc_html($step['title'] ?? ''); ?></h2>
						<?php if (!empty($step['helper'])) : ?>
							<p class="lf-quote-step__helper"><?php echo esc_html($step['helper']); ?></p>
						<?php endif; ?>
					<?php
					$review_index = array_key_exists($step_id, $review_steps) ? $review_steps[$step_id] : null;
					$review_for_step = is_int($review_index) && isset($review_previews[$review_index]) ? $review_previews[$review_index] : null;
					?>
						<?php if ($is_confirm) : ?>
							<div class="lf-quote-step__confirmation">
								<p class="lf-quote-step__confirm-title"><?php echo esc_html($step['confirmation_title'] ?? ''); ?></p>
								<p class="lf-quote-step__confirm-body"><?php echo esc_html($step['confirmation_body'] ?? ''); ?></p>
							</div>
						<?php if (!empty($review_for_step)) : ?>
							<?php lf_quote_builder_render_review_preview($review_for_step, true); ?>
						<?php endif; ?>
						<?php else : ?>
							<div class="lf-quote-fields">
								<?php foreach ($fields as $field) :
									$key = $field['key'];
									$type = $field['type'];
									$label = $field['label'] ?? '';
									$required = !empty($field['required']);
									$default = $field['default'] ?? '';
									$placeholder = $field['placeholder'] ?? '';
									$name = 'lf_quote[' . $key . ']';
									?>
									<div class="lf-quote-field lf-quote-field--<?php echo esc_attr($type); ?>">
										<label class="lf-quote-field__label">
											<?php echo esc_html($label); ?>
											<?php if ($required) : ?><span class="lf-quote-field__required">*</span><?php endif; ?>
										</label>
										<?php if ($type === 'choice') : ?>
											<div class="lf-quote-choice">
												<?php foreach (($field['options'] ?? []) as $option_index => $option) :
													$option_value = is_string($option) ? $option : '';
													$input_id = 'lf-quote-' . $key . '-' . $option_index;
													?>
													<label class="lf-quote-choice__card" for="<?php echo esc_attr($input_id); ?>">
														<input type="radio" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($option_value); ?>" <?php checked($default, $option_value); ?> <?php echo $required ? 'required' : ''; ?> />
														<span><?php echo esc_html($option_value); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										<?php elseif ($type === 'textarea') : ?>
											<textarea name="<?php echo esc_attr($name); ?>" rows="3" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea($default); ?></textarea>
										<?php else : ?>
											<input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($default); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> />
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
				<div class="lf-quote-modal__actions">
					<button type="button" class="lf-quote-btn lf-quote-btn--ghost" data-lf-quote-back><?php esc_html_e('Back', 'leadsforward-core'); ?></button>
					<button type="button" class="lf-quote-btn lf-quote-btn--primary" data-lf-quote-next><?php esc_html_e('Continue', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-quote-modal__status" role="status" aria-live="polite"></div>
			</form>
		</div>
	</div>
	<?php
}

function lf_quote_builder_log_ghl_error(string $message, array $context = []): void {
	$log = get_option(LF_QUOTE_BUILDER_GHL_ERRORS, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'time' => time(),
		'message' => $message,
		'context' => $context,
	]);
	$log = array_slice($log, 0, 20);
	update_option(LF_QUOTE_BUILDER_GHL_ERRORS, $log, false);
}

function lf_quote_builder_send_ghl(array $clean): void {
	$settings = lf_quote_builder_get_integrations();
	if (empty($settings['ghl_enabled'])) {
		return;
	}
	$webhook = $settings['ghl_webhook'] ?? '';
	if ($webhook === '') {
		return;
	}

	$full_name = $clean['full_name'] ?? '';
	$first = '';
	$last = '';
	if ($full_name) {
		$parts = preg_split('/\s+/', (string) $full_name);
		$first = $parts[0] ?? '';
		$last = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
	}
	$address = trim(
		implode(', ', array_filter([
			$clean['address_street'] ?? '',
			$clean['address_city'] ?? '',
			$clean['address_zip'] ?? '',
		]))
	);

	$payload = [
		'firstName' => $first,
		'lastName'  => $last,
		'name'      => $full_name,
		'email'     => $clean['email'] ?? '',
		'phone'     => $clean['phone'] ?? '',
		'address1'  => $clean['address_street'] ?? '',
		'city'      => $clean['address_city'] ?? '',
		'postalCode'=> $clean['address_zip'] ?? '',
		'source'    => $settings['ghl_source'] ?? __('Website Quote', 'leadsforward-core'),
		'pipelineId'=> $settings['ghl_pipeline'] ?? '',
		'tags'      => array_values(array_filter(array_map('trim', explode(',', (string) ($settings['ghl_tags'] ?? ''))))),
		'customData'=> [
			'service_type' => $clean['service_type'] ?? '',
			'address' => $address,
			'page_context' => $clean['page_context'] ?? '',
			'page_title' => $clean['page_title'] ?? '',
			'page_url' => $clean['page_url'] ?? '',
			'timestamp' => wp_date('c'),
			'site_identifier' => home_url('/'),
		],
	];

	$args = [
		'method'  => 'POST',
		'timeout' => 10,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8',
		],
		'body'    => wp_json_encode($payload),
	];
	$response = wp_remote_post($webhook, $args);
	if (is_wp_error($response)) {
		lf_quote_builder_log_ghl_error($response->get_error_message(), ['payload' => $payload]);
		lf_quote_builder_record_event('ghl_fail', 'ghl', $clean['page_context'] ?? '', 0, (string) get_option('lf_homepage_niche_slug', ''), lf_quote_builder_get_form_variant(), 'error', $response->get_error_message());
		lf_quote_builder_enqueue_ghl_retry($payload, ['reason' => 'wp_error', 'message' => $response->get_error_message()]);
		return;
	}
	$code = wp_remote_retrieve_response_code($response);
	if ($code < 200 || $code >= 300) {
		lf_quote_builder_log_ghl_error('GHL webhook error: ' . $code, ['payload' => $payload]);
		lf_quote_builder_record_event('ghl_fail', 'ghl', $clean['page_context'] ?? '', 0, (string) get_option('lf_homepage_niche_slug', ''), lf_quote_builder_get_form_variant(), 'http', (string) $code);
		lf_quote_builder_enqueue_ghl_retry($payload, ['reason' => 'http_error', 'code' => $code]);
		return;
	}
	lf_quote_builder_record_event('ghl_success', 'ghl', $clean['page_context'] ?? '', 0, (string) get_option('lf_homepage_niche_slug', ''), lf_quote_builder_get_form_variant());
}

function lf_quote_builder_enqueue_ghl_retry(array $payload, array $context = []): void {
	$queue = get_option(LF_QUOTE_BUILDER_GHL_RETRY_QUEUE, []);
	if (!is_array($queue)) {
		$queue = [];
	}
	$queue[] = [
		'payload' => $payload,
		'attempts' => 0,
		'next_try' => time() + 300,
		'context' => $context,
		'created_at' => time(),
	];
	$queue = array_slice($queue, -200);
	update_option(LF_QUOTE_BUILDER_GHL_RETRY_QUEUE, $queue, false);
	if (!wp_next_scheduled(LF_QUOTE_BUILDER_GHL_RETRY_HOOK)) {
		wp_schedule_single_event(time() + 60, LF_QUOTE_BUILDER_GHL_RETRY_HOOK);
	}
}

function lf_quote_builder_process_ghl_retries(): void {
	$settings = lf_quote_builder_get_integrations();
	if (empty($settings['ghl_enabled']) || empty($settings['ghl_webhook'])) {
		return;
	}
	$queue = get_option(LF_QUOTE_BUILDER_GHL_RETRY_QUEUE, []);
	if (!is_array($queue) || empty($queue)) {
		return;
	}
	$now = time();
	$remaining = [];
	$processed = 0;
	foreach ($queue as $job) {
		if (!is_array($job) || empty($job['payload']) || !is_array($job['payload'])) {
			continue;
		}
		if ($processed >= 20) {
			$remaining[] = $job;
			continue;
		}
		$next_try = isset($job['next_try']) ? (int) $job['next_try'] : 0;
		if ($next_try > $now) {
			$remaining[] = $job;
			continue;
		}
		$processed++;
		$attempts = isset($job['attempts']) ? (int) $job['attempts'] : 0;
		$response = wp_remote_post((string) $settings['ghl_webhook'], [
			'method' => 'POST',
			'timeout' => 10,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'body' => wp_json_encode($job['payload']),
		]);
		$success = !is_wp_error($response);
		if ($success) {
			$code = (int) wp_remote_retrieve_response_code($response);
			$success = $code >= 200 && $code < 300;
		}
		if ($success) {
			continue;
		}
		$attempts++;
		if ($attempts >= 5) {
			lf_quote_builder_log_ghl_error('GHL retry exhausted after 5 attempts.', ['payload' => $job['payload']]);
			continue;
		}
		$backoff = min(6 * HOUR_IN_SECONDS, (int) pow(2, $attempts) * 300);
		$job['attempts'] = $attempts;
		$job['next_try'] = $now + $backoff;
		$remaining[] = $job;
	}
	update_option(LF_QUOTE_BUILDER_GHL_RETRY_QUEUE, $remaining, false);
	if (!empty($remaining) && !wp_next_scheduled(LF_QUOTE_BUILDER_GHL_RETRY_HOOK)) {
		wp_schedule_single_event(time() + 300, LF_QUOTE_BUILDER_GHL_RETRY_HOOK);
	}
}

function lf_quote_builder_handle_submit(): void {
	check_ajax_referer('lf_quote_builder', 'nonce');
	if (function_exists('lf_security_rate_limit_allow') && !lf_security_rate_limit_allow('quote_builder_submit', 10, 300)) {
		wp_send_json_error(['message' => __('Too many attempts. Please wait a few minutes and try again.', 'leadsforward-core')], 429);
	}
	$payload = $_POST['lf_quote'] ?? [];
	if (!is_array($payload)) {
		wp_send_json_error(['message' => __('Invalid submission.', 'leadsforward-core')]);
	}
	$honeypot = isset($payload['website']) ? trim((string) wp_unslash($payload['website'])) : '';
	if ($honeypot !== '') {
		// Silent success reduces bot feedback loops.
		wp_send_json_success(['ok' => true]);
	}
	$config = lf_quote_builder_get_config();
	$allowed = [];
	$required = [];
	$step_ids = [];
	foreach ($config['steps'] as $step) {
		if (empty($step['enabled'])) {
			continue;
		}
		if (!empty($step['id']) && ($step['type'] ?? '') !== 'confirmation') {
			$step_ids[] = $step['id'];
		}
		foreach ($step['fields'] ?? [] as $field) {
			if (!empty($field['key'])) {
				$allowed[] = $field['key'];
				if (!empty($field['required'])) {
					$required[] = $field['key'];
				}
			}
		}
	}
	$allowed = array_unique($allowed);
	$required = array_unique($required);
	$meta_keys = ['page_context', 'page_id', 'page_title', 'page_url', 'device', 'returning', 'submission_id', 'pages_path'];
	$allowed = array_unique(array_merge($allowed, $meta_keys));
	$meta_keys = ['page_context', 'page_id', 'page_title', 'page_url'];
	$allowed = array_unique(array_merge($allowed, $meta_keys));
	$clean = [];
	foreach ($allowed as $key) {
		if (!isset($payload[$key])) {
			continue;
		}
		$val = $payload[$key];
		if (is_array($val)) {
			$val = wp_json_encode($val);
		}
		if ($key === 'page_id') {
			$clean[$key] = (string) absint($val);
			continue;
		}
		if ($key === 'page_url') {
			$clean[$key] = esc_url_raw(wp_unslash((string) $val));
			continue;
		}
		if ($key === 'returning') {
			$clean[$key] = absint($val) > 0 ? '1' : '0';
			continue;
		}
		if ($key === 'submission_id') {
			$clean[$key] = sanitize_key(wp_unslash((string) $val));
			continue;
		}
		if ($key === 'pages_path') {
			$clean[$key] = wp_unslash((string) $val);
			continue;
		}
		$clean[$key] = sanitize_text_field(wp_unslash((string) $val));
	}
	if (empty($clean)) {
		wp_send_json_error(['message' => __('Please complete the required fields.', 'leadsforward-core')]);
	}
	foreach ($required as $key) {
		if (empty($clean[$key])) {
			wp_send_json_error(['message' => __('Please complete the required fields.', 'leadsforward-core')]);
		}
	}
	$submission_id = $clean['submission_id'] ?? '';
	if ($submission_id !== '') {
		$submission_key = 'lf_qb_sub_' . $submission_id;
		if (get_transient($submission_key)) {
			wp_send_json_success(['ok' => true]);
		}
		set_transient($submission_key, 1, 10 * MINUTE_IN_SECONDS);
	}

	$log = get_option(LF_QUOTE_BUILDER_SUBMISSIONS, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'time' => time(),
		'data' => $clean,
		'ip'   => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
	]);
	$log = array_slice($log, 0, 50);
	update_option(LF_QUOTE_BUILDER_SUBMISSIONS, $log, false);
	$page_path_label = '';
	if (!empty($clean['pages_path'])) {
		$decoded = json_decode((string) $clean['pages_path'], true);
		if (is_array($decoded)) {
			$labels = [];
			foreach ($decoded as $label) {
				$label = sanitize_text_field((string) $label);
				if ($label !== '') {
					$labels[] = $label;
				}
			}
			if (!empty($labels)) {
				$labels = array_slice($labels, -6);
				$page_path_label = implode(' > ', $labels);
			}
		}
	}

	lf_quote_builder_maybe_create_analytics_table();
	$page_label = lf_quote_builder_page_label_from_clean($clean);
	lf_quote_builder_record_event(
		'complete',
		'form',
		$clean['page_context'] ?? '',
		0,
		(string) get_option('lf_homepage_niche_slug', ''),
		lf_quote_builder_get_form_variant(),
		'page',
		$page_label,
		$clean['device'] ?? '',
		isset($clean['returning']) && $clean['returning'] === '1' ? 1 : 0
	);
	if ($page_path_label !== '') {
		lf_quote_builder_record_event(
			'pre_path',
			'path',
			$clean['page_context'] ?? '',
			0,
			(string) get_option('lf_homepage_niche_slug', ''),
			lf_quote_builder_get_form_variant(),
			'path',
			$page_path_label
		);
	}
	lf_quote_builder_send_ghl($clean);
	do_action('lf_quote_builder_submission', $clean);
	wp_send_json_success(['ok' => true]);
}

function lf_quote_builder_render_integrations(bool $embedded = false): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$settings = lf_quote_builder_get_integrations();
	$section = isset($_GET['section']) ? sanitize_key((string) $_GET['section']) : '';
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1' && $section === 'integrations';
	$error = ($section === 'integrations') ? get_option('lf_quote_builder_integrations_error', '') : '';
	$errors = get_option(LF_QUOTE_BUILDER_GHL_ERRORS, []);
	if (!is_array($errors)) {
		$errors = [];
	}
	if (!$embedded) {
		echo '<div class="wrap"><h1>' . esc_html__('Quote Builder — Integrations', 'leadsforward-core') . '</h1>';
		echo '<p class="description">' . esc_html__('Configure lead delivery integrations. Webhook errors are logged for admins only.', 'leadsforward-core') . '</p>';
	}
	if ($saved) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Integration settings saved.', 'leadsforward-core'); ?></p></div>
	<?php elseif ($error) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
	<?php endif; ?>
	<form method="post">
		<?php wp_nonce_field('lf_quote_builder_integrations_save', 'lf_quote_builder_integrations_nonce'); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e('Enable GHL integration', 'leadsforward-core'); ?></th>
				<td><label><input type="checkbox" name="lf_qb_ghl_enabled" value="1" <?php checked(!empty($settings['ghl_enabled'])); ?> /> <?php esc_html_e('Send completed quotes to GoHighLevel', 'leadsforward-core'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="lf_qb_ghl_webhook"><?php esc_html_e('GHL Webhook URL', 'leadsforward-core'); ?></label></th>
				<td><input type="url" class="large-text" id="lf_qb_ghl_webhook" name="lf_qb_ghl_webhook" value="<?php echo esc_attr($settings['ghl_webhook'] ?? ''); ?>" placeholder="https://hooks.leadconnectorhq.com/..." /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lf_qb_ghl_pipeline"><?php esc_html_e('Pipeline ID (optional)', 'leadsforward-core'); ?></label></th>
				<td><input type="text" class="regular-text" id="lf_qb_ghl_pipeline" name="lf_qb_ghl_pipeline" value="<?php echo esc_attr($settings['ghl_pipeline'] ?? ''); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lf_qb_ghl_tags"><?php esc_html_e('Tag(s) (optional)', 'leadsforward-core'); ?></label></th>
				<td><input type="text" class="large-text" id="lf_qb_ghl_tags" name="lf_qb_ghl_tags" value="<?php echo esc_attr($settings['ghl_tags'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Quote Lead, Website', 'leadsforward-core'); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="lf_qb_ghl_source"><?php esc_html_e('Source label', 'leadsforward-core'); ?></label></th>
				<td><input type="text" class="regular-text" id="lf_qb_ghl_source" name="lf_qb_ghl_source" value="<?php echo esc_attr($settings['ghl_source'] ?? __('Website Quote', 'leadsforward-core')); ?>" /></td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Integrations', 'leadsforward-core'); ?></button></p>
	</form>
	<?php if (!empty($errors)) : ?>
		<h3><?php esc_html_e('Recent webhook errors', 'leadsforward-core'); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e('Time', 'leadsforward-core'); ?></th><th><?php esc_html_e('Message', 'leadsforward-core'); ?></th></tr></thead>
			<tbody>
			<?php foreach ($errors as $entry) : ?>
				<tr>
					<td><?php echo esc_html(wp_date('Y-m-d H:i', (int) ($entry['time'] ?? time()))); ?></td>
					<td><?php echo esc_html((string) ($entry['message'] ?? '')); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif;
	if (!$embedded) {
		echo '</div>';
	}
}

function lf_quote_builder_get_range_totals(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT event_type, SUM(count) AS total_count FROM $table WHERE event_date >= %s GROUP BY event_type", $since),
		ARRAY_A
	);
	$totals = ['open' => 0, 'complete' => 0];
	foreach ($rows as $row) {
		$type = $row['event_type'] ?? '';
		if (isset($totals[$type])) {
			$totals[$type] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $totals;
}

function lf_quote_builder_get_step_stats(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT event_type, step_id, SUM(count) AS total_count, SUM(total_time) AS total_time FROM $table WHERE event_date >= %s GROUP BY event_type, step_id", $since),
		ARRAY_A
	);
	$stats = [];
	foreach ($rows as $row) {
		$type = $row['event_type'] ?? '';
		$step = $row['step_id'] ?? '';
		if (!isset($stats[$step])) {
			$stats[$step] = ['views' => 0, 'completes' => 0, 'abandons' => 0, 'time' => 0];
		}
		if ($type === 'step_view') {
			$stats[$step]['views'] = (int) ($row['total_count'] ?? 0);
		} elseif ($type === 'step_complete') {
			$stats[$step]['completes'] = (int) ($row['total_count'] ?? 0);
			$stats[$step]['time'] = (int) ($row['total_time'] ?? 0);
		} elseif ($type === 'abandon') {
			$stats[$step]['abandons'] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $stats;
}

function lf_quote_builder_get_validation_errors(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT step_id, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type = 'validation_error' GROUP BY step_id", $since),
		ARRAY_A
	);
	$stats = [];
	foreach ($rows as $row) {
		$step = $row['step_id'] ?? '';
		$stats[$step] = (int) ($row['total_count'] ?? 0);
	}
	return $stats;
}

function lf_quote_builder_get_page_totals(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT meta_value, event_type, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type IN ('open','complete') AND meta_key = 'page' GROUP BY meta_value, event_type", $since),
		ARRAY_A
	);
	$out = [];
	foreach ($rows as $row) {
		$page = $row['meta_value'] ?? 'Unknown';
		if (!isset($out[$page])) {
			$out[$page] = ['open' => 0, 'complete' => 0];
		}
		$type = $row['event_type'] ?? '';
		if (isset($out[$page][$type])) {
			$out[$page][$type] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $out;
}

function lf_quote_builder_get_device_totals(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT device, event_type, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type IN ('open','complete') GROUP BY device, event_type", $since),
		ARRAY_A
	);
	$out = [];
	foreach ($rows as $row) {
		$device = $row['device'] ?? 'unknown';
		$device = $device !== '' ? $device : 'unknown';
		if (!isset($out[$device])) {
			$out[$device] = ['open' => 0, 'complete' => 0];
		}
		$type = $row['event_type'] ?? '';
		if (isset($out[$device][$type])) {
			$out[$device][$type] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $out;
}

function lf_quote_builder_get_returning_totals(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT event_type, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type IN ('open','complete') AND returning = 1 GROUP BY event_type", $since),
		ARRAY_A
	);
	$out = ['open' => 0, 'complete' => 0];
	foreach ($rows as $row) {
		$type = $row['event_type'] ?? '';
		if (isset($out[$type])) {
			$out[$type] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $out;
}

function lf_quote_builder_get_top_paths(int $days, int $limit = 10): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT meta_value, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type = 'pre_path' AND meta_key = 'path' GROUP BY meta_value ORDER BY total_count DESC LIMIT %d", $since, $limit),
		ARRAY_A
	);
	$out = [];
	foreach ($rows as $row) {
		$out[] = [
			'path' => $row['meta_value'] ?? '',
			'count' => (int) ($row['total_count'] ?? 0),
		];
	}
	return $out;
}

function lf_quote_builder_get_ghl_stats(int $days): array {
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$since = wp_date('Y-m-d', strtotime('-' . $days . ' days'));
	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT event_type, SUM(count) AS total_count FROM $table WHERE event_date >= %s AND event_type IN ('ghl_success','ghl_fail') GROUP BY event_type", $since),
		ARRAY_A
	);
	$out = ['ghl_success' => 0, 'ghl_fail' => 0];
	foreach ($rows as $row) {
		$type = $row['event_type'] ?? '';
		if (isset($out[$type])) {
			$out[$type] = (int) ($row['total_count'] ?? 0);
		}
	}
	return $out;
}

function lf_quote_builder_render_analytics(bool $embedded = false): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	lf_quote_builder_maybe_create_analytics_table();
	global $wpdb;
	$table = $wpdb->prefix . LF_QUOTE_BUILDER_ANALYTICS_TABLE;
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	$section = isset($_GET['section']) ? sanitize_key((string) $_GET['section']) : '';
	$reset = isset($_GET['reset']) && $_GET['reset'] === '1' && $section === 'analytics';
	$range_days = [7, 30, 90];
	$totals = [];
	foreach ($range_days as $days) {
		$totals[$days] = lf_quote_builder_get_range_totals($days);
	}
	$config = lf_quote_builder_get_config();
	$steps = array_values(array_filter($config['steps'] ?? [], function ($step) {
		return !empty($step['enabled']) && ($step['type'] ?? '') !== 'confirmation';
	}));
	$stats = lf_quote_builder_get_step_stats(30);
	$errors = lf_quote_builder_get_validation_errors(30);
	$page_totals = lf_quote_builder_get_page_totals(30);
	$device_totals = lf_quote_builder_get_device_totals(30);
	$return_totals = lf_quote_builder_get_returning_totals(30);
	$ghl_stats = lf_quote_builder_get_ghl_stats(30);
	$top_paths = lf_quote_builder_get_top_paths(30, 10);
	$ghl_errors = get_option(LF_QUOTE_BUILDER_GHL_ERRORS, []);
	if (!is_array($ghl_errors)) {
		$ghl_errors = [];
	}
	$weakest = '';
	$weakest_rate = -1;
	$most_exit = '';
	$most_exit_count = -1;
	$step_labels = [];
	foreach ($steps as $step) {
		$step_id = $step['id'] ?? '';
		$step_labels[$step_id] = $step['title'] ?? $step_id;
		$view = $stats[$step_id]['views'] ?? 0;
		$complete = $stats[$step_id]['completes'] ?? 0;
		$drop = max(0, $view - $complete);
		$rate = $view > 0 ? $drop / $view : 0;
		if ($rate > $weakest_rate) {
			$weakest_rate = $rate;
			$weakest = $step_id;
		}
		$abandon = $stats[$step_id]['abandons'] ?? 0;
		if ($abandon > $most_exit_count) {
			$most_exit_count = $abandon;
			$most_exit = $step_id;
		}
	}
	?>
	<?php if (!$embedded) : ?>
		<div class="wrap">
			<h1><?php esc_html_e('Quote Builder — Analytics', 'leadsforward-core'); ?></h1>
			<p class="description"><?php esc_html_e('Aggregated, first-party analytics for Quote Builder performance. No PII is stored or shown.', 'leadsforward-core'); ?></p>
	<?php endif; ?>
		<?php if ($reset) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Analytics data has been reset.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($exists !== $table) : ?>
			<div class="notice notice-error"><p><?php esc_html_e('Analytics storage table is missing. Try reloading this page or re-saving the theme settings.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<h2><?php esc_html_e('Totals', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Range', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Opens', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Completions', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Conversion', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($range_days as $days) :
					$open = $totals[$days]['open'] ?? 0;
					$complete = $totals[$days]['complete'] ?? 0;
					$rate = $open > 0 ? round(($complete / $open) * 100, 1) : 0;
					?>
					<tr>
						<td><?php echo esc_html(sprintf(__('%d days', 'leadsforward-core'), $days)); ?></td>
						<td><?php echo esc_html((string) $open); ?></td>
						<td><?php echo esc_html((string) $complete); ?></td>
						<td><?php echo esc_html($rate . '%'); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e('Returning users (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Returning opens', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Returning completions', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Return rate', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$total_open = $totals[30]['open'] ?? 0;
				$return_open = $return_totals['open'] ?? 0;
				$return_complete = $return_totals['complete'] ?? 0;
				$return_rate = $total_open > 0 ? round(($return_open / $total_open) * 100, 1) : 0;
				?>
				<tr>
					<td><?php echo esc_html((string) $return_open); ?></td>
					<td><?php echo esc_html((string) $return_complete); ?></td>
					<td><?php echo esc_html($return_rate . '%'); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e('By page (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Page', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Opens', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Completions', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Conversion', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($page_totals as $page_label => $counts) :
					$open = $counts['open'] ?? 0;
					$complete = $counts['complete'] ?? 0;
					$rate = $open > 0 ? round(($complete / $open) * 100, 1) : 0;
					?>
					<tr>
						<td><?php echo esc_html($page_label); ?></td>
						<td><?php echo esc_html((string) $open); ?></td>
						<td><?php echo esc_html((string) $complete); ?></td>
						<td><?php echo esc_html($rate . '%'); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e('By device (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Device', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Opens', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Completions', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Conversion', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($device_totals as $device => $counts) :
					$open = $counts['open'] ?? 0;
					$complete = $counts['complete'] ?? 0;
					$rate = $open > 0 ? round(($complete / $open) * 100, 1) : 0;
					?>
					<tr>
						<td><?php echo esc_html(ucfirst($device)); ?></td>
						<td><?php echo esc_html((string) $open); ?></td>
						<td><?php echo esc_html((string) $complete); ?></td>
						<td><?php echo esc_html($rate . '%'); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e('Funnel (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Step', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Viewed', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Completed', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Drop-off', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Errors', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Avg time', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($steps as $step) :
					$step_id = $step['id'] ?? '';
					$label = $step['title'] ?? $step_id;
					$view = $stats[$step_id]['views'] ?? 0;
					$complete = $stats[$step_id]['completes'] ?? 0;
					$drop = max(0, $view - $complete);
					$drop_rate = $view > 0 ? round(($drop / $view) * 100, 1) : 0;
					$avg_time = $complete > 0 ? round(($stats[$step_id]['time'] ?? 0) / $complete / 1000, 1) : 0;
					$error_count = $errors[$step_id] ?? 0;
					$error_rate = $view > 0 ? round(($error_count / $view) * 100, 1) : 0;
					$row_style = ($step_id === $weakest && $view > 0) ? ' style="background:#fef9c3;"' : '';
					?>
					<tr<?php echo $row_style; ?>>
						<td><?php echo esc_html($label); ?></td>
						<td><?php echo esc_html((string) $view); ?></td>
						<td><?php echo esc_html((string) $complete); ?></td>
						<td><?php echo esc_html($drop_rate . '%'); ?></td>
						<td><?php echo esc_html((string) $error_count . ' (' . $error_rate . '%)'); ?></td>
						<td><?php echo esc_html($avg_time . 's'); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ($most_exit !== '') : ?>
			<p class="description"><?php echo esc_html(sprintf(__('Most common exit step: %s', 'leadsforward-core'), $step_labels[$most_exit] ?? $most_exit)); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e('Pre-conversion page paths (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Path', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Conversions', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($top_paths)) : ?>
					<tr><td colspan="2"><?php esc_html_e('No paths recorded yet.', 'leadsforward-core'); ?></td></tr>
				<?php else : ?>
					<?php foreach ($top_paths as $row) : ?>
						<tr>
							<td><?php echo esc_html($row['path']); ?></td>
							<td><?php echo esc_html((string) $row['count']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e('GHL delivery health (last 30 days)', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Success', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Failures', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Last error', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php $last_error = $ghl_errors[0]['message'] ?? __('None', 'leadsforward-core'); ?>
				<tr>
					<td><?php echo esc_html((string) ($ghl_stats['ghl_success'] ?? 0)); ?></td>
					<td><?php echo esc_html((string) ($ghl_stats['ghl_fail'] ?? 0)); ?></td>
					<td><?php echo esc_html((string) $last_error); ?></td>
				</tr>
			</tbody>
		</table>
		<form method="post" class="lf-qb-reset-form">
			<?php wp_nonce_field('lf_qb_reset_analytics', 'lf_qb_reset_analytics_nonce'); ?>
			<input type="hidden" name="lf_qb_reset_analytics" value="1" />
			<p class="description"><?php esc_html_e('Resetting clears all quote builder analytics data. This cannot be undone.', 'leadsforward-core'); ?></p>
			<p><button type="submit" class="button lf-qb-danger"><?php esc_html_e('Reset Analytics Data', 'leadsforward-core'); ?></button></p>
		</form>
	<?php if (!$embedded) : ?>
		</div>
	<?php endif; ?>
	<?php
}

function lf_quote_builder_render_admin(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$config = lf_quote_builder_get_config();
	$steps = $config['steps'] ?? [];
	$section = isset($_GET['section']) ? sanitize_key((string) $_GET['section']) : '';
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1' && $section !== 'integrations';
	$resynced = isset($_GET['resynced']) && $_GET['resynced'] === '1';
	$niche_slug_for_display = (string) get_option('lf_homepage_niche_slug', '');
	if ($niche_slug_for_display === '' && function_exists('lf_default_niche_slug')) {
		$niche_slug_for_display = lf_default_niche_slug();
	}
	$niche_label_for_display = $niche_slug_for_display;
	if (function_exists('lf_get_niche')) {
		$niche_row = lf_get_niche($niche_slug_for_display);
		if (is_array($niche_row) && !empty($niche_row['name'])) {
			$niche_label_for_display = (string) $niche_row['name'];
		}
	}
	echo '<div class="wrap"><h1>' . esc_html__('Quote Builder', 'leadsforward-core') . '</h1>';
	echo '<p class="description">' . esc_html__('Configure the multi-step Quote Builder. This is a structured, safe editor—no HTML, no layout changes.', 'leadsforward-core') . '</p>';
	?>
	<style>
		.lf-qb-panel { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin:1.25rem 0; overflow:hidden; }
		.lf-qb-panel-header { display:flex; align-items:center; gap:0.75rem; padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; }
		.lf-qb-panel-header h2 { margin:0; font-size:1.1rem; }
		.lf-qb-panel-toggle { margin-left:auto; font-size:12px; text-decoration:none; padding:0.35rem 0.65rem; border-radius:999px; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; }
		.lf-qb-panel-toggle:hover { background:#e2e8f0; }
		.lf-qb-panel--collapsed .lf-qb-panel-body { display:none; }
		.lf-qb-panel--collapsed .lf-qb-panel-toggle { background:#0f172a; color:#fff; border-color:#0f172a; }
		.lf-qb-panel-body { padding:1rem 1.25rem; }
		.lf-qb-step { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; margin:1rem 0; }
		.lf-qb-step-header { display:flex; align-items:center; gap:0.75rem; }
		.lf-qb-step-header h2 { margin:0; font-size:1.1rem; }
		.lf-qb-toggle { margin-left:auto; font-size:12px; text-decoration:none; padding:0.35rem 0.65rem; border-radius:999px; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; }
		.lf-qb-toggle:hover { background:#e2e8f0; }
		.lf-qb-step--collapsed .lf-qb-step-body { display:none; }
		.lf-qb-step--collapsed .lf-qb-toggle { background:#0f172a; color:#fff; border-color:#0f172a; }
		.lf-qb-field { border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem 1rem; margin:0.75rem 0; }
		.lf-qb-field h4 { margin:0 0 0.5rem; }
		.lf-qb-danger { background:#dc2626; border-color:#b91c1c; color:#fff; }
		.lf-qb-danger:hover { background:#b91c1c; border-color:#991b1b; color:#fff; }
	</style>
	<div class="lf-qb-panel" data-panel="builder">
		<div class="lf-qb-panel-header">
			<h2><?php esc_html_e('Builder', 'leadsforward-core'); ?></h2>
			<button type="button" class="lf-qb-panel-toggle" data-target="builder" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
		</div>
		<div class="lf-qb-panel-body">
			<?php if ($resynced) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Quote form was reset from the current niche (%s). Manual override is cleared.', 'leadsforward-core'), $niche_label_for_display)); ?></p></div>
			<?php endif; ?>
			<?php if ($saved) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Quote Builder settings saved.', 'leadsforward-core'); ?></p></div>
			<?php endif; ?>
			<div class="lf-qb-resync" style="margin:0 0 1.25rem;padding:1rem 1.1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
				<p style="margin:0 0 0.65rem;"><strong><?php esc_html_e('Resync from niche', 'leadsforward-core'); ?></strong></p>
				<p class="description" style="margin:0 0 0.85rem;">
					<?php
					echo esc_html(sprintf(
						/* translators: 1: niche name, 2: Global Settings screen name */
						__('Reload steps and default fields from the theme preset for your Global Settings niche (%1$s). This removes the “manual override” flag and replaces any edits you made on this screen. Change the niche under %2$s if needed.', 'leadsforward-core'),
						$niche_label_for_display,
						__('Global Settings', 'leadsforward-core')
					));
					?>
					<?php
					$global_url = admin_url('admin.php?page=lf-ops');
					echo ' <a href="' . esc_url($global_url) . '">' . esc_html__('Open Global Settings', 'leadsforward-core') . '</a>';
					?>
				</p>
				<form method="post" style="margin:0;" onsubmit="return window.confirm(<?php echo esc_js(__('Replace the entire Quote Builder configuration with the default for your current niche? Custom labels and choices you saved here will be lost.', 'leadsforward-core')); ?>); ?>">
					<?php wp_nonce_field('lf_quote_builder_resync_from_niche', 'lf_qb_resync_from_niche_nonce'); ?>
					<input type="hidden" name="lf_qb_resync_from_niche" value="1" />
					<button type="submit" class="button button-secondary"><?php esc_html_e('Resync quote form from niche', 'leadsforward-core'); ?></button>
				</form>
			</div>
			<form method="post">
				<?php wp_nonce_field('lf_quote_builder_save', 'lf_quote_builder_nonce'); ?>
				<?php foreach ($steps as $index => $step) :
					$step_id = $step['id'];
					$enabled = !empty($step['enabled']);
					?>
					<div class="lf-qb-step" data-step="<?php echo esc_attr($step_id); ?>">
						<div class="lf-qb-step-header">
							<h2><?php echo esc_html(sprintf(__('Step %d', 'leadsforward-core'), $index + 1)); ?> — <?php echo esc_html($step['title'] ?? ''); ?></h2>
							<label><input type="checkbox" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][enabled]" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Enabled', 'leadsforward-core'); ?></label>
							<button type="button" class="lf-qb-toggle" data-target="<?php echo esc_attr($step_id); ?>" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
						</div>
						<div class="lf-qb-step-body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label><?php esc_html_e('Step title', 'leadsforward-core'); ?></label></th>
									<td><input type="text" class="large-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][title]" value="<?php echo esc_attr($step['title'] ?? ''); ?>" /></td>
								</tr>
								<tr>
									<th scope="row"><label><?php esc_html_e('Helper text', 'leadsforward-core'); ?></label></th>
									<td><textarea class="large-text" rows="2" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][helper]"><?php echo esc_textarea($step['helper'] ?? ''); ?></textarea></td>
								</tr>
								<?php if (($step['type'] ?? '') === 'confirmation') : ?>
									<tr>
										<th scope="row"><label><?php esc_html_e('Confirmation title', 'leadsforward-core'); ?></label></th>
										<td><input type="text" class="large-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][confirmation_title]" value="<?php echo esc_attr($step['confirmation_title'] ?? ''); ?>" /></td>
									</tr>
									<tr>
										<th scope="row"><label><?php esc_html_e('Confirmation message', 'leadsforward-core'); ?></label></th>
										<td><textarea class="large-text" rows="2" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][confirmation_body]"><?php echo esc_textarea($step['confirmation_body'] ?? ''); ?></textarea></td>
									</tr>
								<?php endif; ?>
							</table>
							<?php if (($step['type'] ?? '') !== 'confirmation') : ?>
								<?php foreach (($step['fields'] ?? []) as $field) :
									$key = $field['key'];
									$type = $field['type'];
									?>
									<div class="lf-qb-field">
										<h4><?php echo esc_html($field['label'] ?? $key); ?> <span class="description">(<?php echo esc_html($type); ?>)</span></h4>
										<table class="form-table" role="presentation">
											<tr>
												<th scope="row"><label><?php esc_html_e('Label', 'leadsforward-core'); ?></label></th>
												<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($field['label'] ?? ''); ?>" /></td>
											</tr>
											<tr>
												<th scope="row"><?php esc_html_e('Required', 'leadsforward-core'); ?></th>
												<td><label><input type="checkbox" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?> /> <?php esc_html_e('Yes', 'leadsforward-core'); ?></label></td>
											</tr>
											<tr>
												<th scope="row"><label><?php esc_html_e('Default value', 'leadsforward-core'); ?></label></th>
												<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][default]" value="<?php echo esc_attr($field['default'] ?? ''); ?>" /></td>
											</tr>
											<?php if ($type !== 'choice') : ?>
												<tr>
													<th scope="row"><label><?php esc_html_e('Placeholder', 'leadsforward-core'); ?></label></th>
													<td><input type="text" class="regular-text" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][placeholder]" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" /></td>
												</tr>
											<?php else : ?>
												<tr>
													<th scope="row"><label><?php esc_html_e('Choices (one per line)', 'leadsforward-core'); ?></label></th>
													<td><textarea class="large-text" rows="3" name="lf_qb_steps[<?php echo esc_attr($step_id); ?>][fields][<?php echo esc_attr($key); ?>][options]"><?php echo esc_textarea(implode("\n", $field['options'] ?? [])); ?></textarea></td>
												</tr>
											<?php endif; ?>
										</table>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Quote Builder', 'leadsforward-core'); ?></button></p>
			</form>
		</div>
	</div>

	<div class="lf-qb-panel" data-panel="integrations">
		<div class="lf-qb-panel-header">
			<h2><?php esc_html_e('Integrations', 'leadsforward-core'); ?></h2>
			<button type="button" class="lf-qb-panel-toggle" data-target="integrations" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
		</div>
		<div class="lf-qb-panel-body">
			<?php lf_quote_builder_render_integrations(true); ?>
		</div>
	</div>

	<div class="lf-qb-panel" data-panel="analytics">
		<div class="lf-qb-panel-header">
			<h2><?php esc_html_e('Analytics', 'leadsforward-core'); ?></h2>
			<button type="button" class="lf-qb-panel-toggle" data-target="analytics" aria-expanded="true">▾ <?php esc_html_e('Collapse', 'leadsforward-core'); ?></button>
		</div>
		<div class="lf-qb-panel-body">
			<?php lf_quote_builder_render_analytics(true); ?>
		</div>
	</div>
	<script>
		(function () {
			var storageKey = 'lf_quote_builder_collapsed';
			var collapsed = {};
			try { collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (e) { collapsed = {}; }
			function applyCollapse(type) {
				var panel = document.querySelector('.lf-qb-step[data-step="' + type + '"]');
				if (!panel) return;
				var isCollapsed = !!collapsed[type];
				panel.classList.toggle('lf-qb-step--collapsed', isCollapsed);
				var toggle = panel.querySelector('.lf-qb-toggle');
				if (toggle) {
					toggle.setAttribute('aria-expanded', (!isCollapsed).toString());
					toggle.textContent = (isCollapsed ? '▸ ' : '▾ ') + (isCollapsed ? '<?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '<?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>');
				}
			}
			document.querySelectorAll('.lf-qb-step').forEach(function (panel) {
				var type = panel.getAttribute('data-step');
				if (type) applyCollapse(type);
			});
			document.addEventListener('click', function (e) {
				if (e.target && e.target.classList.contains('lf-qb-toggle')) {
					var type = e.target.getAttribute('data-target');
					if (!type) return;
					collapsed[type] = !collapsed[type];
					try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
					applyCollapse(type);
				}
			});
		})();
		(function () {
			var storageKey = 'lf_quote_builder_panels';
			var collapsed = {};
			try { collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (e) { collapsed = {}; }
			var forceOpen = '<?php echo esc_js($section); ?>';
			if (forceOpen) {
				collapsed[forceOpen] = false;
				try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
			}
			function applyPanel(type) {
				var panel = document.querySelector('.lf-qb-panel[data-panel="' + type + '"]');
				if (!panel) return;
				var isCollapsed = !!collapsed[type];
				panel.classList.toggle('lf-qb-panel--collapsed', isCollapsed);
				var toggle = panel.querySelector('.lf-qb-panel-toggle');
				if (toggle) {
					toggle.setAttribute('aria-expanded', (!isCollapsed).toString());
					toggle.textContent = (isCollapsed ? '▸ ' : '▾ ') + (isCollapsed ? '<?php echo esc_js(__('Expand', 'leadsforward-core')); ?>' : '<?php echo esc_js(__('Collapse', 'leadsforward-core')); ?>');
				}
			}
			document.querySelectorAll('.lf-qb-panel').forEach(function (panel) {
				var type = panel.getAttribute('data-panel');
				if (type) applyPanel(type);
			});
			document.addEventListener('click', function (e) {
				if (!e.target || !e.target.classList.contains('lf-qb-panel-toggle')) return;
				var type = e.target.getAttribute('data-target');
				if (!type) return;
				collapsed[type] = !collapsed[type];
				try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
				applyPanel(type);
			});
		})();
		(function () {
			var resetForm = document.querySelector('.lf-qb-reset-form');
			if (!resetForm) return;
			resetForm.addEventListener('submit', function (e) {
				if (!window.confirm('<?php echo esc_js(__('This will permanently delete all quote builder analytics. Continue?', 'leadsforward-core')); ?>')) {
					e.preventDefault();
					return;
				}
				if (!window.confirm('<?php echo esc_js(__('Are you absolutely sure? This cannot be undone.', 'leadsforward-core')); ?>')) {
					e.preventDefault();
				}
			});
		})();
	</script>
	<?php
	echo '</div>';
}
