<?php
/**
 * Single blog post template.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php while (have_posts()) : the_post();
		$post_id = get_the_ID();
		$categories = get_the_category();
		$primary = !empty($categories) ? $categories[0] : null;
		$reading_time = function_exists('lf_blog_reading_time') ? lf_blog_reading_time((string) get_post_field('post_content', $post_id)) : 0;
		$image_id = function_exists('lf_blog_get_featured_image_id') ? lf_blog_get_featured_image_id($post_id) : (int) get_post_thumbnail_id($post_id);
		$image_html = $image_id ? wp_get_attachment_image($image_id, 'large', false, [
			'class' => 'lf-blog-hero__image',
			'loading' => 'lazy',
			'decoding' => 'async',
		]) : '';
		$author_id = (int) get_the_author_meta('ID');
		$author_name = get_the_author();
		$author_url = get_author_posts_url($author_id);
		$author_bio = get_the_author_meta('description');
		$lf_hide_blog_author = (string) get_post_meta($post_id, 'lf_ai_generated', true) === '1';
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class('lf-blog-post'); ?>>
			<header class="lf-section lf-section--blog-hero">
				<div class="lf-section__inner">
					<div class="lf-blog-hero <?php echo $image_html ? 'lf-blog-hero--media' : 'lf-blog-hero--simple'; ?>">
						<div class="lf-blog-hero__content">
							<a class="lf-blog-hero__back" href="<?php echo esc_url(function_exists('lf_blog_base_url') ? lf_blog_base_url() : home_url('/')); ?>">
								<?php esc_html_e('Back to blog', 'leadsforward-core'); ?>
							</a>
							<div class="lf-blog-hero__meta">
								<?php if ($primary) : ?>
									<a class="lf-blog-hero__pill" href="<?php echo esc_url(get_category_link($primary->term_id)); ?>">
										<?php echo esc_html($primary->name); ?>
									</a>
								<?php endif; ?>
								<span><?php echo esc_html(get_the_date()); ?></span>
								<?php if ($reading_time) : ?>
									<span><?php echo esc_html(sprintf(_n('%d min read', '%d min read', $reading_time, 'leadsforward-core'), $reading_time)); ?></span>
								<?php endif; ?>
							</div>
							<h1 class="lf-blog-hero__title"><?php the_title(); ?></h1>
							<?php if (has_excerpt()) : ?>
								<p class="lf-blog-hero__intro"><?php echo esc_html(get_the_excerpt()); ?></p>
							<?php endif; ?>
							<?php if (!$lf_hide_blog_author) : ?>
								<div class="lf-blog-hero__author">
									<?php if ($author_id) : ?>
										<?php echo get_avatar($author_id, 48, '', '', ['class' => 'lf-blog-hero__avatar']); ?>
									<?php endif; ?>
									<div class="lf-blog-hero__author-meta">
										<span><?php esc_html_e('By', 'leadsforward-core'); ?></span>
										<a href="<?php echo esc_url($author_url); ?>"><?php echo esc_html($author_name); ?></a>
									</div>
								</div>
							<?php endif; ?>
						</div>
						<?php if ($image_html) : ?>
							<div class="lf-blog-hero__media">
								<?php echo $image_html; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</header>

			<section class="lf-section lf-section--blog-content">
				<div class="lf-section__inner">
					<div class="lf-blog-content lf-prose lf-prose--rich-section">
						<?php the_content(); ?>
					</div>
					<?php
					$tags = get_the_tags();
					if (!empty($tags)) :
					?>
						<div class="lf-blog-tags">
							<span><?php esc_html_e('Topics:', 'leadsforward-core'); ?></span>
							<?php foreach ($tags as $tag) : ?>
								<a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>"><?php echo esc_html($tag->name); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php
					$share_url = urlencode((string) get_permalink($post_id));
					$share_title = urlencode((string) get_the_title($post_id));
					?>
					<div class="lf-blog-share" aria-label="<?php esc_attr_e('Share this article', 'leadsforward-core'); ?>">
						<span class="lf-blog-share__label"><?php esc_html_e('Share:', 'leadsforward-core'); ?></span>
						<div class="lf-blog-share__links">
							<a class="lf-blog-share__link" href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . $share_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Facebook', 'leadsforward-core'); ?></a>
							<a class="lf-blog-share__link" href="<?php echo esc_url('https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('LinkedIn', 'leadsforward-core'); ?></a>
							<a class="lf-blog-share__link" href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . $share_url . '&text=' . $share_title); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('X', 'leadsforward-core'); ?></a>
							<a class="lf-blog-share__link" href="<?php echo esc_url('mailto:?subject=' . rawurlencode((string) get_the_title($post_id)) . '&body=' . $share_url); ?>"><?php esc_html_e('Email', 'leadsforward-core'); ?></a>
						</div>
					</div>
				</div>
			</section>

			<?php if (!$lf_hide_blog_author && $author_bio) : ?>
				<section class="lf-section lf-section--blog-author">
					<div class="lf-section__inner">
						<div class="lf-blog-author-card">
							<?php if ($author_id) : ?>
								<?php echo get_avatar($author_id, 72, '', '', ['class' => 'lf-blog-author-card__avatar']); ?>
							<?php endif; ?>
							<div class="lf-blog-author-card__content">
								<strong><?php echo esc_html($author_name); ?></strong>
								<p><?php echo esc_html($author_bio); ?></p>
							</div>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php
			$related_args = [
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page' => 3,
				'post__not_in' => [$post_id],
				'no_found_rows' => true,
			];
			if ($primary) {
				$related_args['cat'] = $primary->term_id;
			}
			$related = new WP_Query($related_args);
			if ($related->have_posts()) :
			?>
				<section class="lf-section lf-section--blog-related">
					<div class="lf-section__inner">
						<header class="lf-blog-related__header">
							<h2><?php esc_html_e('Related articles', 'leadsforward-core'); ?></h2>
						</header>
						<div class="lf-blog-grid">
							<?php
							while ($related->have_posts()) :
								$related->the_post();
								set_query_var('lf_post_card_variant', 'standard');
								get_template_part('templates/parts/content', 'post');
							endwhile;
							wp_reset_postdata();
							?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if (comments_open() || get_comments_number()) : ?>
				<section class="lf-section lf-section--blog-comments">
					<div class="lf-section__inner">
						<div class="lf-blog-comments">
							<?php comments_template(); ?>
						</div>
					</div>
				</section>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
