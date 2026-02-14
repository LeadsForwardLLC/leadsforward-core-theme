<?php
/**
 * Post card template for blog listings.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$variant = (string) get_query_var('lf_post_card_variant', 'standard');
$image_id = function_exists('lf_blog_get_featured_image_id')
	? lf_blog_get_featured_image_id(get_the_ID())
	: (int) get_post_thumbnail_id(get_the_ID());
$image_size = $variant === 'featured' ? 'large' : 'medium_large';
$image_html = $image_id ? wp_get_attachment_image($image_id, $image_size, false, [
	'class' => 'lf-post-card__image',
	'loading' => 'lazy',
	'decoding' => 'async',
]) : '';
$categories = get_the_category();
$primary = !empty($categories) ? $categories[0] : null;
$meta_date = get_the_date();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('lf-post-card lf-card lf-card--interactive lf-post-card--' . esc_attr($variant)); ?>>
	<a class="lf-post-card__media" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
		<?php if ($image_html) : ?>
			<?php echo $image_html; ?>
		<?php endif; ?>
	</a>
	<div class="lf-post-card__content">
		<div class="lf-post-card__meta">
			<?php if ($primary) : ?>
				<a class="lf-post-card__pill" href="<?php echo esc_url(get_category_link($primary->term_id)); ?>">
					<?php echo esc_html($primary->name); ?>
				</a>
			<?php endif; ?>
			<span class="lf-post-card__date"><?php echo esc_html($meta_date); ?></span>
		</div>
		<h2 class="lf-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="lf-post-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
		<div class="lf-post-card__footer">
			<span class="lf-post-card__author">
				<span class="lf-post-card__author-avatar" aria-hidden="true">
					<?php echo get_avatar(get_the_author_meta('ID'), 28, '', '', ['class' => 'lf-post-card__author-image']); ?>
				</span>
				<span class="lf-post-card__author-name"><?php the_author(); ?></span>
			</span>
			<a class="lf-post-card__cta" href="<?php the_permalink(); ?>">
				<?php esc_html_e('Read article', 'leadsforward-core'); ?>
				<span aria-hidden="true">→</span>
			</a>
		</div>
	</div>
</article>
