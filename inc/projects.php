<?php
/**
 * Project gallery helpers: archive filters, cards, and assets.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('query_vars', 'lf_projects_register_query_vars');
add_action('pre_get_posts', 'lf_projects_filter_archive');
add_action('wp_enqueue_scripts', 'lf_projects_enqueue_assets');

function lf_projects_register_query_vars(array $vars): array {
	$vars[] = 'project_type';
	return $vars;
}

function lf_projects_filter_archive(\WP_Query $query): void {
	if (is_admin() || !$query->is_main_query()) {
		return;
	}
	if (!$query->is_post_type_archive('lf_project')) {
		return;
	}
	$project_type = (string) $query->get('project_type', '');
	$project_type = sanitize_title($project_type);
	if ($project_type !== '') {
		$query->set('tax_query', [
			[
				'taxonomy' => 'lf_project_type',
				'field' => 'slug',
				'terms' => $project_type,
			],
		]);
	}
}

function lf_projects_enqueue_assets(): void {
	if (!lf_projects_should_enqueue_assets()) {
		return;
	}
	$style_path = LF_THEME_DIR . '/assets/css/projects.css';
	if (is_readable($style_path)) {
		wp_enqueue_style(
			'lf-projects',
			LF_THEME_URI . '/assets/css/projects.css',
			[],
			(string) filemtime($style_path)
		);
	}
	$script_path = LF_THEME_DIR . '/assets/js/project-gallery.js';
	if (is_readable($script_path)) {
		wp_enqueue_script(
			'lf-projects',
			LF_THEME_URI . '/assets/js/project-gallery.js',
			[],
			(string) filemtime($script_path),
			true
		);
	}
}

function lf_projects_should_enqueue_assets(): bool {
	if (is_post_type_archive('lf_project') || is_singular('lf_project')) {
		return true;
	}
	if (is_front_page() && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		if (!empty($config['project_gallery']['enabled'])) {
			return true;
		}
	}
	if (is_singular() && defined('LF_PB_META_KEY')) {
		$post_id = get_the_ID();
		if ($post_id) {
			$config = get_post_meta($post_id, LF_PB_META_KEY, true);
			if (is_array($config) && !empty($config['sections'])) {
				foreach ($config['sections'] as $section) {
					if (($section['type'] ?? '') === 'project_gallery' && !empty($section['enabled'])) {
						return true;
					}
				}
			}
		}
	}
	return false;
}

/**
 * Normalize image ID from ACF or post meta.
 */
function lf_projects_normalize_image_id($value): int {
	if (is_array($value)) {
		return absint($value['ID'] ?? $value['id'] ?? 0);
	}
	return absint($value);
}

/**
 * Return before/after image IDs for a project.
 *
 * @return array{before:int,after:int}
 */
function lf_projects_get_before_after_ids(int $post_id): array {
	$before = function_exists('get_field') ? get_field('lf_project_before_image', $post_id) : get_post_meta($post_id, 'lf_project_before_image', true);
	$after = function_exists('get_field') ? get_field('lf_project_after_image', $post_id) : get_post_meta($post_id, 'lf_project_after_image', true);
	return [
		'before' => lf_projects_normalize_image_id($before),
		'after' => lf_projects_normalize_image_id($after),
	];
}

function lf_projects_get_location_line(int $post_id): string {
	$city = function_exists('get_field') ? get_field('lf_project_city', $post_id) : get_post_meta($post_id, 'lf_project_city', true);
	$state = function_exists('get_field') ? get_field('lf_project_state', $post_id) : get_post_meta($post_id, 'lf_project_state', true);
	$year = function_exists('get_field') ? get_field('lf_project_year', $post_id) : get_post_meta($post_id, 'lf_project_year', true);
	$city = sanitize_text_field((string) $city);
	$state = sanitize_text_field((string) $state);
	$year = sanitize_text_field((string) $year);
	$parts = array_filter([
		$city !== '' && $state !== '' ? $city . ', ' . $state : $city,
		$year,
	]);
	return implode(' · ', $parts);
}

function lf_projects_get_primary_type(int $post_id): string {
	$terms = get_the_terms($post_id, 'lf_project_type');
	if (empty($terms) || !is_array($terms)) {
		return '';
	}
	$term = $terms[0];
	return $term instanceof \WP_Term ? $term->name : '';
}

/**
 * Render a project card.
 *
 * @param array{show_before_after?:bool} $args
 */
function lf_projects_render_card(\WP_Post $post, array $args = []): void {
	$show_before_after = $args['show_before_after'] ?? true;
	$before_after = lf_projects_get_before_after_ids($post->ID);
	$featured_id = get_post_thumbnail_id($post->ID);
	$image_id = $before_after['after'] ?: $featured_id ?: $before_after['before'];
	if ($image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
		$image_id = (int) lf_get_placeholder_image_id();
	}
	$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
	$image_alt = $image_id ? (string) get_post_meta($image_id, '_wp_attachment_image_alt', true) : '';
	$type_label = lf_projects_get_primary_type($post->ID);
	$location_line = lf_projects_get_location_line($post->ID);
	$excerpt = has_excerpt($post) ? wp_strip_all_tags(get_the_excerpt($post)) : wp_trim_words(wp_strip_all_tags($post->post_content), 18);
	$before_url = $before_after['before'] ? wp_get_attachment_image_url($before_after['before'], 'large') : '';
	$after_url = $before_after['after'] ? wp_get_attachment_image_url($before_after['after'], 'large') : '';
	$has_toggle = $show_before_after && $before_url && $after_url;
	?>
	<article class="lf-project-card lf-card lf-card--interactive">
		<div class="lf-project-card__media <?php echo $has_toggle ? 'has-before-after' : ''; ?>" <?php if ($has_toggle) : ?>data-lf-project-before-after data-state="after"<?php endif; ?>>
			<?php if ($has_toggle) : ?>
				<img class="lf-project-card__image lf-project-card__image--after" src="<?php echo esc_url($after_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="lazy" />
				<img class="lf-project-card__image lf-project-card__image--before" src="<?php echo esc_url($before_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="lazy" />
				<button type="button" class="lf-project-card__toggle" data-lf-project-toggle data-before-label="<?php echo esc_attr__('Show before', 'leadsforward-core'); ?>" data-after-label="<?php echo esc_attr__('Show after', 'leadsforward-core'); ?>" aria-pressed="false"><?php esc_html_e('Show before', 'leadsforward-core'); ?></button>
			<?php elseif ($image_url) : ?>
				<img class="lf-project-card__image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="lazy" />
			<?php endif; ?>
		</div>
		<div class="lf-project-card__content">
			<?php if ($type_label || $location_line) : ?>
				<div class="lf-project-card__meta">
					<?php if ($type_label) : ?><span class="lf-project-card__type"><?php echo esc_html($type_label); ?></span><?php endif; ?>
					<?php if ($location_line) : ?><span class="lf-project-card__location"><?php echo esc_html($location_line); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<h3 class="lf-project-card__title"><a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></h3>
			<?php if ($excerpt) : ?><p class="lf-project-card__excerpt"><?php echo esc_html($excerpt); ?></p><?php endif; ?>
			<a class="lf-project-card__action" href="<?php echo esc_url(get_permalink($post)); ?>"><?php esc_html_e('View Project', 'leadsforward-core'); ?></a>
		</div>
	</article>
	<?php
}

/**
 * Render a gallery grid of projects.
 *
 * @param array{count?:int,show_filters?:bool,show_before_after?:bool,layout?:string,show_controls?:bool} $args
 */
function lf_projects_render_gallery(array $args = []): void {
	$args = wp_parse_args($args, [
		'count' => 6,
		'show_filters' => false,
		'show_before_after' => true,
		'layout' => 'grid',
		'show_controls' => true,
	]);
	$count = max(3, min(12, (int) $args['count']));
	$show_filters = (bool) $args['show_filters'];
	$show_before_after = (bool) $args['show_before_after'];
	$layout = (string) $args['layout'];
	if (!in_array($layout, ['grid', 'masonry', 'slider'], true)) {
		$layout = 'grid';
	}
	$show_controls = (bool) $args['show_controls'];
	$archive_link = get_post_type_archive_link('lf_project');

	if ($show_filters && $archive_link) {
		$terms = get_terms([
			'taxonomy' => 'lf_project_type',
			'hide_empty' => true,
		]);
		if (!is_wp_error($terms) && !empty($terms)) {
			echo '<div class="lf-project-filters" role="list">';
			echo '<a class="lf-project-filter is-active" href="' . esc_url($archive_link) . '">' . esc_html__('All Projects', 'leadsforward-core') . '</a>';
			foreach ($terms as $term) {
				if (!$term instanceof \WP_Term) {
					continue;
				}
				$link = add_query_arg('project_type', $term->slug, $archive_link);
				echo '<a class="lf-project-filter" href="' . esc_url($link) . '">' . esc_html($term->name) . '</a>';
			}
			echo '</div>';
		}
	}

	$query = new \WP_Query([
		'post_type'      => 'lf_project',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	if ($query->have_posts()) {
		$grid_class = 'lf-project-grid lf-project-grid--' . $layout;
		if ($layout === 'slider') {
			echo '<div class="lf-slider" data-lf-slider>';
			if ($show_controls) {
				echo '<button type="button" class="lf-slider__nav lf-slider__prev" data-lf-slider-prev aria-label="' . esc_attr__('Previous projects', 'leadsforward-core') . '">‹</button>';
			}
			echo '<div class="' . esc_attr($grid_class) . ' lf-slider__track" data-lf-slider-track>';
		} else {
			echo '<div class="' . esc_attr($grid_class) . '">';
		}
		while ($query->have_posts()) {
			$query->the_post();
			$post = get_post();
			if ($post instanceof \WP_Post) {
				lf_projects_render_card($post, ['show_before_after' => $show_before_after]);
			}
		}
		echo '</div>';
		if ($layout === 'slider') {
			if ($show_controls) {
				echo '<button type="button" class="lf-slider__nav lf-slider__next" data-lf-slider-next aria-label="' . esc_attr__('Next projects', 'leadsforward-core') . '">›</button>';
			}
			echo '</div>';
		}
		wp_reset_postdata();
	} else {
		echo '<p>' . esc_html__('No projects yet.', 'leadsforward-core') . '</p>';
	}
}
