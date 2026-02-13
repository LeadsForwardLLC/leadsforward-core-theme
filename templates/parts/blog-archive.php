<?php
/**
 * Blog archive layout (used by home/category/tag/author/search).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$title = (string) get_query_var('lf_blog_archive_title', '');
$intro = (string) get_query_var('lf_blog_archive_intro', '');
$label = (string) get_query_var('lf_blog_archive_label', '');
$author = get_query_var('lf_blog_archive_author');
$blog_page = get_page_by_path('blog');
$blog_hero_title = '';
$blog_hero_intro = '';
if ($blog_page instanceof \WP_Post && function_exists('lf_pb_get_post_config')) {
	$config = lf_pb_get_post_config($blog_page->ID, 'page');
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	foreach ((array) $order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || ($section['type'] ?? '') !== 'hero') {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$blog_hero_title = (string) ($settings['hero_headline'] ?? '');
		$blog_hero_intro = (string) ($settings['hero_subheadline'] ?? ($settings['hero_supporting_text'] ?? ''));
		break;
	}
}

$archive_title = get_the_archive_title();
$archive_intro = get_the_archive_description();
if ($title === '') {
	$title = $blog_hero_title !== '' ? $blog_hero_title : ($archive_title ?: __('Blog', 'leadsforward-core'));
}
if ($intro === '') {
	$intro = $blog_hero_intro !== '' ? $blog_hero_intro : ($archive_intro ?: '');
}
if ($intro !== '' && stripos($intro, '<') === false) {
	$intro = wpautop($intro);
}
$intro = $intro !== '' ? wp_kses_post($intro) : '';
?>
<section class="lf-section lf-section--blog-hero">
	<div class="lf-section__inner">
		<div class="lf-blog-hero lf-blog-hero--simple">
			<div class="lf-blog-hero__content">
				<?php if ($label !== '') : ?>
					<div class="lf-blog-hero__meta">
						<span class="lf-blog-hero__pill"><?php echo esc_html($label); ?></span>
					</div>
				<?php endif; ?>
				<h1 class="lf-blog-hero__title"><?php echo esc_html($title); ?></h1>
				<?php if ($intro) : ?>
					<div class="lf-blog-hero__intro"><?php echo $intro; ?></div>
				<?php endif; ?>
				<?php if (is_array($author)) : ?>
					<div class="lf-blog-hero__author">
						<?php if (!empty($author['avatar'])) : ?>
							<img class="lf-blog-hero__avatar" src="<?php echo esc_url($author['avatar']); ?>" alt="<?php echo esc_attr($author['name'] ?? ''); ?>" />
						<?php endif; ?>
						<div class="lf-blog-hero__author-meta">
							<strong><?php echo esc_html($author['name'] ?? ''); ?></strong>
							<?php if (!empty($author['bio'])) : ?>
								<span><?php echo esc_html($author['bio']); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<section class="lf-section lf-section--blog-list">
	<div class="lf-section__inner">
		<?php get_template_part('templates/parts/blog-filters'); ?>
		<?php if (have_posts()) : ?>
			<div class="lf-blog-grid">
				<?php
				$index = 0;
				while (have_posts()) :
					the_post();
					$variant = (!is_paged() && $index === 0) ? 'featured' : 'standard';
					set_query_var('lf_post_card_variant', $variant);
					get_template_part('templates/parts/content', 'post');
					$index++;
				endwhile;
				?>
			</div>
			<div class="lf-blog-pagination">
				<?php
				the_posts_pagination([
					'mid_size' => 1,
					'prev_text' => __('Previous', 'leadsforward-core'),
					'next_text' => __('Next', 'leadsforward-core'),
				]);
				?>
			</div>
		<?php else : ?>
			<?php get_template_part('templates/parts/content', 'none'); ?>
		<?php endif; ?>
	</div>
</section>
