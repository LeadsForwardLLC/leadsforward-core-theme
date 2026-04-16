<?php
/**
 * Archive: Services. One H1 (post type archive title), semantic main + section.
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

<main id="main" class="site-main" role="main">
	<header class="archive-header">
		<h1 class="archive-title"><?php post_type_archive_title('', false); ?></h1>
	</header>
	<section class="archive-content" aria-label="<?php esc_attr_e('Services list', 'leadsforward-core'); ?>">
		<?php if (have_posts()) : ?>
			<ul class="service-archive-list">
				<?php while (have_posts()) : the_post(); ?>
					<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
				<?php endwhile; ?>
			</ul>
			<?php the_posts_navigation(); ?>
		<?php else : ?>
			<p><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</section>
</main>

<?php
get_footer();
