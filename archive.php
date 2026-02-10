<?php
/**
 * Archive template (date archives, tags, etc).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$title = get_the_archive_title();
$intro = get_the_archive_description();
set_query_var('lf_blog_archive_title', $title ?: __('Blog', 'leadsforward-core'));
set_query_var('lf_blog_archive_intro', $intro ?: '');
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php get_template_part('templates/parts/blog-archive'); ?>
</main>

<?php
get_footer();
