<?php
/**
 * Shared section library: registry, defaults, sanitizers, renderers.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_sections_registry(): array {
	return [
		'hero' => [
			'label' => __('Hero', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'hero_headline', 'label' => __('Headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'hero_subheadline', 'label' => __('Subheadline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'call'  => __('Call now', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
				['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'call', 'options' => [
					'call'  => __('Call now', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
			],
			'render' => 'lf_sections_render_hero',
		],
		'trust_bar' => [
			'label' => __('Trust Bar', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'trust_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Trusted by local homeowners', 'leadsforward-core')],
				['key' => 'trust_badges', 'label' => __('Badges (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Licensed & Insured' . "\n" . '5-Star Rated' . "\n" . 'Fast Response', 'leadsforward-core')],
				['key' => 'trust_rating', 'label' => __('Rating override (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
				['key' => 'trust_review_count', 'label' => __('Review count override (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
			],
			'render' => 'lf_sections_render_trust_bar',
		],
		'benefits' => [
			'label' => __('Benefits / Why Choose Us', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Why Homeowners Choose Us', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear pricing, fast response, and workmanship you can trust.', 'leadsforward-core')],
				['key' => 'benefits_items', 'label' => __('Benefits (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Fast response windows' . "\n" . 'Licensed, insured professionals' . "\n" . 'Upfront pricing before work starts', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_benefits',
		],
		'service_details' => [
			'label' => __('Service Details', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service Details', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Everything you need to know before scheduling.', 'leadsforward-core')],
				['key' => 'service_details_body', 'label' => __('Body copy', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'service_details_checklist', 'label' => __('Checklist (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Transparent scope and pricing' . "\n" . 'Clean, respectful crews' . "\n" . 'Work backed by warranty', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_details',
		],
		'process' => [
			'label' => __('Process', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Our Process', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Simple, clear steps from first call to completion.', 'leadsforward-core')],
				['key' => 'process_steps', 'label' => __('Steps (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Tell us what you need' . "\n" . 'Get a fast, clear estimate' . "\n" . 'Schedule and complete the work', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_process',
		],
		'faq_accordion' => [
			'label' => __('FAQ', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Frequently Asked Questions', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Answers to common questions about scheduling and service.', 'leadsforward-core')],
				['key' => 'faq_max_items', 'label' => __('Max items', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_faq',
		],
		'cta' => [
			'label' => __('CTA Band', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'cta_headline', 'label' => __('CTA headline', 'leadsforward-core'), 'type' => 'text', 'default' => __('Get a fast, no-obligation estimate', 'leadsforward-core')],
				['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
					''      => __('Use global', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'call'  => __('Call now', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
				['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
					''      => __('Use global', 'leadsforward-core'),
					'call'  => __('Call now', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
			],
			'render' => 'lf_sections_render_cta_band',
		],
		'related_links' => [
			'label' => __('Related Links', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Explore More', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Browse related services and areas we serve.', 'leadsforward-core')],
				['key' => 'related_links_mode', 'label' => __('Links to show', 'leadsforward-core'), 'type' => 'select', 'default' => 'both', 'options' => [
					'services' => __('Services', 'leadsforward-core'),
					'areas'    => __('Service Areas', 'leadsforward-core'),
					'both'     => __('Both', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_related_links',
		],
		'service_areas_served' => [
			'label' => __('Service Areas Served', 'leadsforward-core'),
			'contexts' => ['service'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service Areas', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Nearby cities and neighborhoods we serve.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_areas_served',
		],
		'services_offered_here' => [
			'label' => __('Services Offered Here', 'leadsforward-core'),
			'contexts' => ['service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Services in this area', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore the services available in your area.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_services_offered',
		],
		'nearby_areas' => [
			'label' => __('Nearby Areas', 'leadsforward-core'),
			'contexts' => ['service_area'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Nearby service areas', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('We also serve these nearby locations.', 'leadsforward-core')],
				['key' => 'nearby_areas_max', 'label' => __('Max areas', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_nearby_areas',
		],
		'map_nap' => [
			'label' => __('Service Areas + Map', 'leadsforward-core'),
			'contexts' => ['homepage'],
			'fields' => [
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Areas We Serve', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Find us on the map and explore nearby neighborhoods.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_map_nap',
		],
	];
}

function lf_sections_default_order(string $context): array {
	$base = ['hero', 'trust_bar', 'benefits', 'service_details', 'process', 'faq_accordion', 'cta', 'related_links'];
	if ($context === 'service') {
		$base[] = 'service_areas_served';
	}
	if ($context === 'service_area') {
		$base[] = 'services_offered_here';
		$base[] = 'nearby_areas';
	}
	if ($context === 'homepage') {
		$base[] = 'map_nap';
	}
	return $base;
}

function lf_sections_get_context_sections(string $context): array {
	$registry = lf_sections_registry();
	$out = [];
	foreach ($registry as $id => $section) {
		$contexts = $section['contexts'] ?? [];
		if (in_array($context, $contexts, true)) {
			$section['id'] = $id;
			$out[$id] = $section;
		}
	}
	return $out;
}

function lf_sections_defaults_for(string $section_id): array {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return [];
	}
	$defaults = [];
	foreach ($section['fields'] as $field) {
		$defaults[$field['key']] = $field['default'] ?? '';
	}
	return $defaults;
}

function lf_sections_sanitize_settings(string $section_id, array $input): array {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return [];
	}
	$out = [];
	foreach ($section['fields'] as $field) {
		$key = $field['key'];
		$raw = $input[$key] ?? ($field['default'] ?? '');
		switch ($field['type']) {
			case 'textarea':
				$out[$key] = sanitize_textarea_field(wp_unslash((string) $raw));
				break;
			case 'url':
				$out[$key] = esc_url_raw(wp_unslash((string) $raw));
				break;
			case 'number':
				$val = trim(wp_unslash((string) $raw));
				$val = preg_replace('/[^0-9.]/', '', $val);
				$out[$key] = $val;
				break;
			case 'select':
				$val = sanitize_text_field(wp_unslash((string) $raw));
				$options = $field['options'] ?? [];
				$out[$key] = array_key_exists($val, $options) ? $val : ($field['default'] ?? '');
				break;
			case 'list':
				$out[$key] = sanitize_textarea_field(wp_unslash((string) $raw));
				break;
			default:
				$out[$key] = sanitize_text_field(wp_unslash((string) $raw));
				break;
		}
	}
	return $out;
}

function lf_sections_parse_lines(string $value): array {
	$lines = array_filter(array_map('trim', explode("\n", $value)));
	return array_values(array_map('sanitize_text_field', $lines));
}

function lf_sections_render_section(string $section_id, string $context, array $settings, \WP_Post $post): void {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return;
	}
	$callback = $section['render'] ?? '';
	if (is_callable($callback)) {
		call_user_func($callback, $context, $settings, $post);
	}
}

function lf_sections_render_shell_open(string $id, string $title = '', string $intro = ''): void {
	?>
	<section class="lf-section lf-section--<?php echo esc_attr($id); ?>">
		<div class="lf-section__inner">
			<?php if ($title || $intro) : ?>
				<header class="lf-section__header">
					<?php if ($title) : ?><h2 class="lf-section__title"><?php echo esc_html($title); ?></h2><?php endif; ?>
					<?php if ($intro) : ?><p class="lf-section__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
				</header>
			<?php endif; ?>
			<div class="lf-section__body">
	<?php
}

function lf_sections_render_shell_close(): void {
	?>
			</div>
		</div>
	</section>
	<?php
}

function lf_sections_render_hero(string $context, array $settings, \WP_Post $post): void {
	if ($context === 'homepage' && function_exists('lf_render_block_template')) {
		$section = [
			'hero_headline' => $settings['hero_headline'] ?? '',
			'hero_subheadline' => $settings['hero_subheadline'] ?? '',
			'hero_cta_override' => $settings['cta_primary_override'] ?? '',
			'hero_cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
			'hero_cta_action' => $settings['cta_primary_action'] ?? '',
			'hero_cta_url' => $settings['cta_primary_url'] ?? '',
			'hero_cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
			'hero_cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
		];
		$block = [
			'id'         => 'lf-hero',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => true, 'section' => $section],
		];
		lf_render_block_template('hero', $block, false, $block['context']);
		return;
	}
	$heading = $settings['hero_headline'] ?? '';
	$sub = $settings['hero_subheadline'] ?? '';
	if ($heading === '') {
		if ($post->post_type === 'lf_service' && function_exists('get_field')) {
			$seo_h1 = get_field('lf_service_seo_h1', $post->ID);
			$heading = $seo_h1 ?: get_the_title($post);
		} else {
			$heading = get_the_title($post);
		}
	}
	if ($sub === '') {
		if ($post->post_type === 'lf_service' && function_exists('get_field')) {
			$short_desc = get_field('lf_service_short_desc', $post->ID);
			$sub = $short_desc ?: '';
		}
		if ($post->post_type === 'lf_service_area' && function_exists('get_field')) {
			$state = get_field('lf_service_area_state', $post->ID);
			if ($state) {
				$sub = sprintf(__('Serving %1$s, %2$s', 'leadsforward-core'), get_the_title($post), $state);
			}
		}
	}
	$cta = [
		'cta_primary_override' => $settings['cta_primary_override'] ?? ($settings['hero_cta_override'] ?? ''),
		'cta_secondary_override' => $settings['cta_secondary_override'] ?? ($settings['hero_cta_secondary_override'] ?? ''),
		'cta_primary_action' => $settings['cta_primary_action'] ?? ($settings['hero_cta_action'] ?? ''),
		'cta_primary_url' => $settings['cta_primary_url'] ?? ($settings['hero_cta_url'] ?? ''),
		'cta_secondary_action' => $settings['cta_secondary_action'] ?? ($settings['hero_cta_secondary_action'] ?? ''),
		'cta_secondary_url' => $settings['cta_secondary_url'] ?? ($settings['hero_cta_secondary_url'] ?? ''),
	];
	$resolved = function_exists('lf_get_resolved_cta') ? lf_get_resolved_cta(['section' => $cta, 'homepage' => false]) : [];
	$primary = $resolved['primary_text'] ?? '';
	$secondary = $resolved['secondary_text'] ?? '';
	$action = $resolved['primary_action'] ?? 'quote';
	$secondary_action = $resolved['secondary_action'] ?? 'call';
	$primary_url = $resolved['primary_url'] ?? '';
	$secondary_url = $resolved['secondary_url'] ?? '';
	$phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
	?>
	<section class="lf-section lf-section--hero">
		<div class="lf-section__inner">
			<h1 class="lf-section__title"><?php echo esc_html($heading); ?></h1>
			<?php if ($sub) : ?><p class="lf-section__intro"><?php echo esc_html($sub); ?></p><?php endif; ?>
			<div class="lf-section__buttons">
				<?php if ($primary) : ?>
					<?php if ($action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="pb-hero"><?php echo esc_html($primary); ?></button>
					<?php elseif ($action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php elseif ($action === 'link' && $primary_url) : ?>
						<a href="<?php echo esc_url($primary_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($secondary) : ?>
					<?php if ($secondary_action === 'call' && $phone) : ?>
						<a href="tel:<?php echo esc_attr($phone); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php elseif ($secondary_action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="pb-hero-secondary"><?php echo esc_html($secondary); ?></button>
					<?php elseif ($secondary_action === 'link' && $secondary_url) : ?>
						<a href="<?php echo esc_url($secondary_url); ?>" class="lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function lf_sections_render_trust_bar(string $context, array $settings, \WP_Post $post): void {
	$rating = (float) ($settings['trust_rating'] ?? 0);
	$count = (int) ($settings['trust_review_count'] ?? 0);
	if ($rating <= 0 || $count <= 0) {
		$query = new WP_Query([
			'post_type'      => 'lf_testimonial',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'post_status'    => 'publish',
		]);
		$ratings_total = 0;
		$ratings_count = 0;
		foreach ($query->posts as $p) {
			$r = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $p->ID) : 5;
			if ($r > 0) {
				$ratings_total += $r;
				$ratings_count++;
			}
		}
		$computed_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 5.0;
		$computed_count = $ratings_count > 0 ? $ratings_count : 0;
		if ($rating <= 0) {
			$rating = $computed_rating;
		}
		if ($count <= 0) {
			$count = $computed_count;
		}
	}
	$badges = lf_sections_parse_lines((string) ($settings['trust_badges'] ?? ''));
	if (empty($badges)) {
		$badges = [__('Licensed & Insured', 'leadsforward-core'), __('5-Star Rated', 'leadsforward-core')];
	}
	$title = $settings['trust_heading'] ?? '';
	lf_sections_render_shell_open('trust-bar', $title, '');
	?>
	<div class="lf-trust-bar">
		<div class="lf-trust-bar__rating">
			<span class="lf-trust-bar__stars" aria-hidden="true">
				<?php for ($i = 0; $i < 5; $i++) : ?>
					<svg class="lf-trust-bar__star" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php endfor; ?>
			</span>
			<?php if ($rating) : ?><span class="lf-trust-bar__score"><?php echo esc_html(number_format($rating, 1)); ?></span><?php endif; ?>
			<?php if ($count) : ?><span class="lf-trust-bar__count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $count, 'leadsforward-core'), $count)); ?></span><?php endif; ?>
		</div>
		<div class="lf-trust-bar__badges">
			<?php foreach ($badges as $badge) : ?>
				<span class="lf-trust-bar__badge"><?php echo esc_html($badge); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_benefits(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$items = lf_sections_parse_lines((string) ($settings['benefits_items'] ?? ''));
	lf_sections_render_shell_open('benefits', $title, $intro);
	?>
	<div class="lf-benefits">
		<?php foreach ($items as $item) : ?>
			<div class="lf-benefits__card"><?php echo esc_html($item); ?></div>
		<?php endforeach; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_service_details(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$body = $settings['service_details_body'] ?? '';
	$body_from_settings = $body !== '';
	if ($body === '' && $context !== 'homepage') {
		$body = apply_filters('the_content', $post->post_content);
	}
	if ($body_from_settings) {
		$body = wpautop($body);
	}
	$checklist = lf_sections_parse_lines((string) ($settings['service_details_checklist'] ?? ''));
	lf_sections_render_shell_open('service-details', $title, $intro);
	?>
	<div class="lf-service-details">
		<?php if ($body) : ?>
			<div class="lf-service-details__body"><?php echo wp_kses_post($body); ?></div>
		<?php endif; ?>
		<?php if (!empty($checklist)) : ?>
			<ul class="lf-service-details__checklist" role="list">
				<?php foreach ($checklist as $item) : ?>
					<li><?php echo esc_html($item); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_process(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$steps = lf_sections_parse_lines((string) ($settings['process_steps'] ?? ''));
	lf_sections_render_shell_open('process', $title, $intro);
	?>
	<ol class="lf-process">
		<?php foreach ($steps as $step) : ?>
			<li class="lf-process__step"><?php echo esc_html($step); ?></li>
		<?php endforeach; ?>
	</ol>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_faq(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'section_heading' => $settings['section_heading'] ?? '',
			'section_intro' => $settings['section_intro'] ?? '',
			'faq_max_items' => $settings['faq_max_items'] ?? '',
		];
		$block = [
			'id'         => 'lf-faq',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
		];
		lf_render_block_template('faq-accordion', $block, false, $block['context']);
	}
}

function lf_sections_render_cta_band(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'cta_headline' => $settings['cta_headline'] ?? '',
			'cta_primary_override' => $settings['cta_primary_override'] ?? '',
			'cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
			'cta_primary_action' => $settings['cta_primary_action'] ?? '',
			'cta_primary_url' => $settings['cta_primary_url'] ?? '',
			'cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
			'cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
		];
		$block = [
			'id'         => 'lf-cta-band',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
		];
		lf_render_block_template('cta', $block, false, $block['context']);
	}
}

function lf_sections_render_related_links(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$mode = $settings['related_links_mode'] ?? 'both';
	if (!in_array($mode, ['services', 'areas', 'both'], true)) {
		$mode = 'both';
	}
	$links = [];
	if ($mode === 'services' || $mode === 'both') {
		$services = get_posts([
			'post_type'      => 'lf_service',
			'posts_per_page' => 6,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		]);
		foreach ($services as $svc) {
			$links[] = ['label' => get_the_title($svc), 'url' => get_permalink($svc)];
		}
	}
	if ($mode === 'areas' || $mode === 'both') {
		$areas = get_posts([
			'post_type'      => 'lf_service_area',
			'posts_per_page' => 6,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		]);
		foreach ($areas as $area) {
			$links[] = ['label' => get_the_title($area), 'url' => get_permalink($area)];
		}
	}
	if (empty($links)) {
		return;
	}
	lf_sections_render_shell_open('related-links', $title, $intro);
	?>
	<ul class="lf-related-links" role="list">
		<?php foreach ($links as $link) : ?>
			<li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_service_areas_served(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	lf_sections_render_shell_open('service-areas-served', $title, $intro);
	get_template_part('templates/parts/related-service-areas');
	lf_sections_render_shell_close();
}

function lf_sections_render_services_offered(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	lf_sections_render_shell_open('services-offered', $title, $intro);
	get_template_part('templates/parts/related-services');
	lf_sections_render_shell_close();
}

function lf_sections_render_nearby_areas(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$max = max(1, (int) ($settings['nearby_areas_max'] ?? 6));
	$query = new WP_Query([
		'post_type'      => 'lf_service_area',
		'posts_per_page' => $max,
		'post__not_in'   => [$post->ID],
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	if (!$query->have_posts()) {
		return;
	}
	lf_sections_render_shell_open('nearby-areas', $title, $intro);
	?>
	<ul class="lf-related-links" role="list">
		<?php while ($query->have_posts()) : $query->the_post(); ?>
			<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
		<?php endwhile; ?>
	</ul>
	<?php
	wp_reset_postdata();
	lf_sections_render_shell_close();
}

function lf_sections_render_map_nap(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'section_heading' => $settings['section_heading'] ?? '',
			'section_intro'   => $settings['section_intro'] ?? '',
		];
		$block = [
			'id'         => 'lf-map-nap',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => true, 'section' => $section],
		];
		lf_render_block_template('map-nap', $block, false, $block['context']);
	}
}
