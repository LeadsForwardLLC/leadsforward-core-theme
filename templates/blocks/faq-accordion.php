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

$variant = $block['variant'] ?? 'default';
$query = new WP_Query([
	'post_type'      => 'lf_faq',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
	'no_found_rows'  => true,
]);
?>
<section class="lf-block lf-block-faq-accordion lf-block-faq-accordion--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" aria-label="<?php esc_attr_e('FAQs', 'leadsforward-core'); ?>">
	<div class="lf-block-faq-accordion__inner">
		<?php if ($query->have_posts()) : ?>
			<dl class="lf-block-faq-accordion__list">
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
					<div class="lf-block-faq-accordion__item">
						<dt class="lf-block-faq-accordion__question"><?php echo esc_html($q); ?></dt>
						<dd class="lf-block-faq-accordion__answer"><?php echo wp_kses_post($a); ?></dd>
					</div>
				<?php endwhile; ?>
			</dl>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p class="lf-block-faq-accordion__empty"><?php esc_html_e('No FAQs yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</div>
</section>
