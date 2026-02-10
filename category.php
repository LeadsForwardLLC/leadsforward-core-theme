<?php
/**
 * Category archive template.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$title = single_cat_title('', false);
$intro = category_description();
set_query_var('lf_blog_archive_title', $title ?: __('Blog category', 'leadsforward-core'));
set_query_var('lf_blog_archive_intro', $intro ?: '');
set_query_var('lf_blog_archive_label', __('Topic', 'leadsforward-core'));
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php get_template_part('templates/parts/blog-archive'); ?>
</main>

<?php
get_footer();
