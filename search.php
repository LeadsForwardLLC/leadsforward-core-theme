<?php
/**
 * Search results template.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$query = get_search_query();
$title = $query !== '' ? sprintf(__('Search results for “%s”', 'leadsforward-core'), $query) : __('Search results', 'leadsforward-core');
$intro = __('Browse the most relevant articles below or refine your search.', 'leadsforward-core');
set_query_var('lf_blog_archive_title', $title);
set_query_var('lf_blog_archive_intro', $intro);
set_query_var('lf_blog_archive_label', __('Search', 'leadsforward-core'));
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php get_template_part('templates/parts/blog-archive'); ?>
</main>

<?php
get_footer();
