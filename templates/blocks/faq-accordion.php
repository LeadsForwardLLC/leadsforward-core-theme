<?php
/**
 * Block: FAQ Accordion. Semantic list; expand/collapse can be added via JS later.
 *
 * @var array $block
 * @var bool  $is_preview
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$variant  = $block['variant'] ?? 'default';
$block_id = $block['id'] ?? '';
$context  = $block['context'] ?? [];
$section  = $context['section'] ?? [];
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'light') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$section_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$heading  = !empty($section['section_heading']) ? $section['section_heading'] : __('Frequently Asked Questions', 'leadsforward-core');
$intro    = !empty($section['section_intro']) ? $section['section_intro'] : '';
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'faq_accordion', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'faq_accordion', 'left', 'lf-heading-icon') : '';
$columns = 1;
if (isset($section['faq_columns'])) {
	$columns = (int) $section['faq_columns'];
} elseif (isset($block['attributes']['columns'])) {
	$columns = (int) $block['attributes']['columns'];
}
$columns = ($columns >= 2) ? 2 : 1;
$schema_enabled = (string) ($section['faq_schema_enabled'] ?? ($block['attributes']['schema'] ?? '1')) !== '0';
$max_items = isset($section['faq_max_items']) ? (int) $section['faq_max_items'] : -1;
if ($max_items === 0) {
	$max_items = -1;
}
$selected_faq_ids_raw = (string) ($section['faq_selected_ids'] ?? '');
$selected_faq_ids = preg_split('/[\r\n,]+/', $selected_faq_ids_raw);
$selected_faq_ids = array_values(array_filter(array_map(static function ($value): int {
	return (int) trim((string) $value);
}, is_array($selected_faq_ids) ? $selected_faq_ids : []), static function (int $id): bool {
	return $id > 0;
}));

/**
 * Lightweight FAQ auto-selector:
 * if a section has no manual selection, choose FAQs by overlap
 * between page/section intent text and FAQ question/answer tokens.
 */
$auto_select_faq_ids = static function (int $limit) use ($section): array {
	$query = new WP_Query([
		'post_type' => 'lf_faq',
		'posts_per_page' => 80,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'post_status' => 'publish',
		'no_found_rows' => true,
	]);
	if (!$query->have_posts()) {
		return [];
	}
	$page_title = get_the_title(get_queried_object_id());
	$intent_source = trim(implode(' ', array_filter([
		(string) ($section['section_heading'] ?? ''),
		(string) ($section['section_intro'] ?? ''),
		(string) $page_title,
		(string) get_post_field('post_excerpt', get_queried_object_id()),
	])));
	$terms = preg_split('/[^a-z0-9]+/i', strtolower($intent_source));
	$terms = array_values(array_unique(array_filter(array_map(static function ($term): string {
		return trim((string) $term);
	}, is_array($terms) ? $terms : []), static function (string $term): bool {
		return strlen($term) >= 4;
	})));
	$scores = [];
	while ($query->have_posts()) {
		$query->the_post();
		$faq_id = (int) get_the_ID();
		$question = function_exists('get_field') ? (string) get_field('lf_faq_question', $faq_id) : '';
		$answer = function_exists('get_field') ? (string) get_field('lf_faq_answer', $faq_id) : '';
		if ($question === '') {
			$question = (string) get_the_title($faq_id);
		}
		$haystack = strtolower(trim($question . ' ' . wp_strip_all_tags((string) $answer)));
		$score = 0;
		foreach ($terms as $term) {
			if ($term !== '' && strpos($haystack, $term) !== false) {
				$score += 3;
			}
		}
		$score += max(0, 10 - ((int) get_post_field('menu_order', $faq_id) / 10));
		$scores[] = ['id' => $faq_id, 'score' => $score];
	}
	wp_reset_postdata();
	usort($scores, static function (array $a, array $b): int {
		if ((int) $a['score'] === (int) $b['score']) {
			return 0;
		}
		return ((int) $a['score'] > (int) $b['score']) ? -1 : 1;
	});
	$ids = array_values(array_map(static function (array $row): int {
		return (int) ($row['id'] ?? 0);
	}, $scores));
	$ids = array_values(array_filter($ids, static function (int $id): bool {
		return $id > 0;
	}));
	if ($limit > 0) {
		$ids = array_slice($ids, 0, $limit);
	}
	return $ids;
};

$faq_ids_for_query = !empty($selected_faq_ids) ? $selected_faq_ids : $auto_select_faq_ids($max_items > 0 ? $max_items : 6);
$query_args = [
	'post_type' => 'lf_faq',
	'posts_per_page' => $max_items > 0 ? $max_items : -1,
	'post_status' => 'publish',
	'no_found_rows' => true,
];
if (!empty($faq_ids_for_query)) {
	$query_args['post__in'] = $faq_ids_for_query;
	$query_args['orderby'] = 'post__in';
} else {
	$query_args['orderby'] = 'menu_order title';
	$query_args['order'] = 'ASC';
}
$query = new WP_Query($query_args);
$has_faq_posts = $query->have_posts();
?>
<section class="lf-block lf-block-faq-accordion <?php echo esc_attr($surface['class'] ?: 'lf-surface-light'); ?> lf-block-faq-accordion--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" aria-label="<?php esc_attr_e('FAQs', 'leadsforward-core'); ?>"<?php echo $section_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-faq-accordion__inner">
		<header class="lf-block-faq-accordion__header lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($icon_left) : ?>
				<div class="lf-heading-row">
					<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
					<<?php echo esc_html($section_heading_tag); ?> class="lf-block-faq-accordion__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
				</div>
			<?php else : ?>
				<<?php echo esc_html($section_heading_tag); ?> class="lf-block-faq-accordion__title"><?php echo esc_html($heading); ?></<?php echo esc_html($section_heading_tag); ?>>
			<?php endif; ?>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-faq-accordion__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<div class="lf-block-faq-accordion__list lf-block-faq-accordion__list--cols-<?php echo esc_attr((string) $columns); ?>" role="list">
			<?php if ($has_faq_posts) : ?>
				<?php
				$schema_items = [];
				while ($query->have_posts()) : $query->the_post();
					$q = function_exists('get_field') ? get_field('lf_faq_question') : '';
					$a = function_exists('get_field') ? get_field('lf_faq_answer') : '';
					if (!is_string($q)) {
						$q = '';
					}
					if (!is_string($a)) {
						$a = '';
					}
					$q = trim($q);
					$a = trim($a);
					if (!$q) {
						$q = get_the_title();
					}
					if ($a === '') {
						$a = trim((string) get_post_field('post_content', get_the_ID()));
					}
					if ($a === '') {
						$a = trim((string) get_post_meta(get_the_ID(), 'lf_faq_answer', true));
					}
					if ($a !== '') {
						$a = wpautop($a);
					}
					if ($schema_enabled && $q !== '' && $a !== '') {
						$schema_items[] = [
							'@type' => 'Question',
							'name' => wp_strip_all_tags((string) $q),
							'acceptedAnswer' => [
								'@type' => 'Answer',
								'text' => wp_strip_all_tags((string) $a),
							],
						];
					}
				?>
					<details class="lf-block-faq-accordion__item" data-lf-faq-id="<?php echo esc_attr((string) get_the_ID()); ?>">
						<summary class="lf-block-faq-accordion__question"><?php echo esc_html($q); ?></summary>
						<div class="lf-block-faq-accordion__answer"><?php echo wp_kses_post($a); ?></div>
					</details>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php endif; ?>
		</div>
		<?php if ($schema_enabled && !empty($schema_items)) : ?>
			<script type="application/ld+json">
				<?php
				echo wp_json_encode(
					[
						'@context' => 'https://schema.org',
						'@type' => 'FAQPage',
						'mainEntity' => $schema_items,
					],
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
				?>
			</script>
		<?php endif; ?>
		<?php if (!$has_faq_posts) : ?>
			<p class="lf-block-faq-accordion__empty"><?php esc_html_e('No FAQs selected yet. Use editor controls to pick from your FAQ library.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
