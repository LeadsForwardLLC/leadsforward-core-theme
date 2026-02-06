<?php
/**
 * Fallback template. Used when no more specific template matches.
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
	<?php if (have_posts()) : ?>
		<?php if (is_home()) : ?>
			<header class="archive-header">
				<h1 class="archive-title"><?php single_post_title('', false) ?: esc_html_e('Blog', 'leadsforward-core'); ?></h1>
			</header>
		<?php endif; ?>
		<section class="posts-list" aria-label="<?php esc_attr_e('Posts', 'leadsforward-core'); ?>">
			<?php while (have_posts()) : the_post(); ?>
				<?php get_template_part('templates/parts/content', get_post_type()); ?>
			<?php endwhile; ?>
		</section>
		<?php the_posts_navigation(); ?>
	<?php else : ?>
		<?php
		$no_results_title = is_search()
			? sprintf(/* translators: search query */ __('Search: %s', 'leadsforward-core'), get_search_query())
			: __('No results', 'leadsforward-core');
		?>
		<header class="archive-header">
			<h1 class="archive-title"><?php echo esc_html($no_results_title); ?></h1>
		</header>
		<section class="no-results" aria-label="<?php esc_attr_e('No results', 'leadsforward-core'); ?>">
			<?php get_template_part('templates/parts/content', 'none'); ?>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
