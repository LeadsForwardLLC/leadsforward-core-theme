<?php
/**
 * Block: Service Grid. Section heading + intro, links to lf_service posts (decision elements).
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context']['section'] homepage section overrides (section_heading, section_intro)
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$variant = $block['variant'] ?? 'default';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$heading = !empty($section['section_heading']) ? $section['section_heading'] : __('Our Services', 'leadsforward-core');
$intro   = !empty($section['section_intro']) ? $section['section_intro'] : '';
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$section_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_grid', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_grid', 'left', 'lf-heading-icon') : '';
$card_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'service_grid', 'list', 'lf-block-service-grid__icon') : '';

$query = new WP_Query([
	'post_type'      => 'lf_service',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => function_exists('lf_cpt_card_query_post_statuses') ? lf_cpt_card_query_post_statuses() : ['publish', 'future', 'draft', 'pending'],
	'no_found_rows'  => true,
]);

$desc_overrides_raw = (string) ($section['service_grid_card_desc_overrides'] ?? '');
$desc_overrides_map = [];
if ($desc_overrides_raw !== '') {
	foreach (preg_split('/\r\n|\r|\n/', $desc_overrides_raw) ?: [] as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}
		if (preg_match('/^(\d+)\s*[:|]\s*(.+)$/u', $line, $m)) {
			$sid = (int) ($m[1] ?? 0);
			$val = trim((string) ($m[2] ?? ''));
			if ($sid > 0 && $val !== '') {
				$desc_overrides_map[(string) $sid] = $val;
			}
		}
	}
}

$service_posts = is_array($query->posts) ? $query->posts : [];
$service_groups = function_exists('lf_services_group_posts_by_category')
	? lf_services_group_posts_by_category($service_posts)
	: ['grouped' => false, 'categories' => [['slug' => '', 'label' => '', 'posts' => $service_posts]]];
$use_categories = !empty($service_groups['grouped']) && !empty($service_groups['categories']);
$show_editor_status = !is_admin() && current_user_can('edit_theme_options');

/**
 * @param list<\WP_Post> $posts
 */
$render_service_grid_list = static function (array $posts, int $index_start = 0) use (
	$variant,
	$desc_overrides_map,
	$card_icon,
	$show_editor_status
): void {
	$index = $index_start;
	foreach ($posts as $post) {
		if (!$post instanceof \WP_Post) {
			continue;
		}
		++$index;
		$sid = (int) $post->ID;
		$excerpt = '';
		$card_url = function_exists('lf_cpt_card_permalink')
			? lf_cpt_card_permalink($post)
			: (string) get_permalink($sid);
		if ($variant === 'a') {
			if (!empty($desc_overrides_map[(string) $sid])) {
				$excerpt = (string) $desc_overrides_map[(string) $sid];
			} else {
				$short_desc = function_exists('get_field') ? (string) get_field('lf_service_short_desc', $sid) : '';
				if ($short_desc === '') {
					$short_desc = (string) get_post_meta($sid, 'lf_service_short_desc', true);
				}
				$excerpt = $short_desc !== '' ? wp_strip_all_tags($short_desc) : '';
			}
		}
		$status_meta = function_exists('lf_cpt_editor_status_meta')
			? lf_cpt_editor_status_meta($post)
			: ['status' => 'publish', 'status_label' => '', 'is_live' => true];
		?>
		<li class="lf-block-service-grid__item" data-lf-service-id="<?php echo esc_attr((string) $sid); ?>">
			<?php if ($show_editor_status && ($status_meta['status_label'] ?? '') !== '') : ?>
				<span class="lf-ai-service-status-badge lf-ai-faq-picker__status lf-ai-faq-picker__status--<?php echo esc_attr((string) ($status_meta['status'] ?? 'publish')); ?>"><?php echo esc_html((string) $status_meta['status_label']); ?></span>
			<?php endif; ?>
			<a href="<?php echo esc_url($card_url); ?>" class="lf-block-service-grid__link">
				<?php if ($card_icon) : ?><span class="lf-block-service-grid__icon"><?php echo $card_icon; ?></span><?php endif; ?>
				<?php if ($variant === 'a') : ?>
					<span class="lf-block-service-grid__card-index"><?php echo esc_html(str_pad((string) $index, 2, '0', STR_PAD_LEFT)); ?></span>
				<?php endif; ?>
				<span class="lf-block-service-grid__card-title"><?php echo esc_html((string) $post->post_title); ?></span>
				<?php if ($variant === 'a' && $excerpt !== '') : ?>
					<span class="lf-block-service-grid__card-desc"><?php echo esc_html($excerpt); ?></span>
				<?php endif; ?>
				<span class="lf-block-service-grid__card-action" aria-hidden="true"><?php esc_html_e('View', 'leadsforward-core'); ?></span>
			</a>
		</li>
		<?php
	}
};
?>
<section class="lf-block lf-block-service-grid <?php echo esc_attr($surface['class']); ?> lf-block-service-grid--<?php echo esc_attr($variant); ?><?php echo $use_categories ? ' lf-block-service-grid--categorized' : ''; ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>"<?php echo $section_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-service-grid__inner">
		<header class="lf-block-service-grid__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-grid__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
				</div>
			<?php else : ?>
				<<?php echo esc_html($section_heading_tag); ?> class="lf-block-service-grid__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
			<?php endif; ?>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-service-grid__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($service_posts !== []) : ?>
			<?php if ($use_categories) : ?>
				<div class="lf-block-service-grid__categories">
					<?php foreach ($service_groups['categories'] as $category) : ?>
						<?php
						$cat_slug = (string) ($category['slug'] ?? '');
						$cat_label = (string) ($category['label'] ?? '');
						$cat_posts = is_array($category['posts'] ?? null) ? $category['posts'] : [];
						if ($cat_posts === []) {
							continue;
						}
						?>
						<section class="lf-block-service-grid__category lf-block-service-grid__category--<?php echo esc_attr(sanitize_html_class($cat_slug)); ?>">
							<h3 class="lf-block-service-grid__category-heading"><?php echo esc_html($cat_label); ?></h3>
							<ul class="lf-block-service-grid__list lf-cpt-driven-links" role="list">
								<?php $render_service_grid_list($cat_posts); ?>
							</ul>
						</section>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<ul class="lf-block-service-grid__list lf-cpt-driven-links" role="list">
					<?php $render_service_grid_list($service_posts); ?>
				</ul>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-service-grid__empty"><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
