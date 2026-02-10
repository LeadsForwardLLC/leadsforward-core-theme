<?php
/**
 * Author archive template.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$author = get_queried_object();
$author_id = $author instanceof \WP_User ? (int) $author->ID : 0;
$name = $author instanceof \WP_User ? $author->display_name : __('Author', 'leadsforward-core');
$bio = $author instanceof \WP_User ? $author->description : '';
$avatar = $author_id ? get_avatar_url($author_id, ['size' => 96]) : '';
$title = sprintf(__('Articles by %s', 'leadsforward-core'), $name);
$intro = $bio !== '' ? $bio : __('Insights and tips from our team.', 'leadsforward-core');

set_query_var('lf_blog_archive_title', $title);
set_query_var('lf_blog_archive_intro', $intro);
set_query_var('lf_blog_archive_label', __('Author', 'leadsforward-core'));
set_query_var('lf_blog_archive_author', [
	'name' => $name,
	'bio' => $bio,
	'avatar' => $avatar,
]);
?>
<main id="main" class="site-main site-main--blog" role="main">
	<?php get_template_part('templates/parts/blog-archive'); ?>
</main>

<?php
get_footer();
