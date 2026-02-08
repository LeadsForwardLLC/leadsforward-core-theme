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
$heading  = !empty($section['section_heading']) ? $section['section_heading'] : __('Frequently Asked Questions', 'leadsforward-core');
$intro    = !empty($section['section_intro']) ? $section['section_intro'] : '';
$max_items = isset($section['faq_max_items']) ? (int) $section['faq_max_items'] : -1;
if ($max_items === 0) {
	$max_items = -1;
}
$query = new WP_Query([
	'post_type'      => 'lf_faq',
	'posts_per_page' => $max_items > 0 ? $max_items : -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-faq-accordion <?php echo esc_attr($bg_class ?: 'lf-surface-light'); ?> lf-block-faq-accordion--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" aria-label="<?php esc_attr_e('FAQs', 'leadsforward-core'); ?>">
	<div class="lf-block-faq-accordion__inner">
		<header class="lf-block-faq-accordion__header">
			<h2 class="lf-block-faq-accordion__title"><?php echo esc_html($heading); ?></h2>
			<?php if ($intro !== '') : ?>
				<p class="lf-block-faq-accordion__intro"><?php echo esc_html($intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($query->have_posts()) : ?>
			<div class="lf-block-faq-accordion__list" role="list">
				<?php while ($query->have_posts()) : $query->the_post();
					$q = function_exists('get_field') ? get_field('lf_faq_question') : '';
					$a = function_exists('get_field') ? get_field('lf_faq_answer') : '';
					if (!$q) {
						$q = get_the_title();
					}
					if (!$a) {
						$a = get_the_content();
					}
				?>
					<details class="lf-block-faq-accordion__item">
						<summary class="lf-block-faq-accordion__question"><?php echo esc_html($q); ?></summary>
						<div class="lf-block-faq-accordion__answer"><?php echo wp_kses_post($a); ?></div>
					</details>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-faq-accordion__empty"><?php esc_html_e('No FAQs yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
