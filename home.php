<?php
/**
 * Blog home (posts page).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$page_for_posts = (int) get_option('page_for_posts');
$title = $page_for_posts ? get_the_title($page_for_posts) : __('Blog', 'leadsforward-core');
$intro = '';
if ($page_for_posts) {
	$intro = (string) get_the_excerpt($page_for_posts);
	if ($intro === '') {
		$content = (string) get_post_field('post_content', $page_for_posts);
		$intro = wp_trim_words(wp_strip_all_tags($content), 28);
	}
}
if ($intro === '') {
	$intro = __('Practical tips, seasonal checklists, and homeowner-friendly advice from our team.', 'leadsforward-core');
}

set_query_var('lf_blog_archive_title', $title);
set_query_var('lf_blog_archive_intro', $intro);
set_query_var('lf_blog_archive_label', __('Latest updates', 'leadsforward-core'));
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php get_template_part('templates/parts/blog-archive'); ?>
</main>

<?php
get_footer();
