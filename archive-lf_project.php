<?php
/**
 * Archive: Projects. Gallery grid with filters and before/after.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$archive_link = get_post_type_archive_link('lf_project');
$current_type = sanitize_title((string) get_query_var('project_type', ''));
$terms = get_terms([
	'taxonomy' => 'lf_project_type',
	'hide_empty' => true,
]);
?>

<main id="main" class="site-main" role="main">
	<section class="lf-section lf-section--project-archive">
		<div class="lf-section__inner">
			<header class="lf-section__header">
				<h1 class="lf-section__title"><?php post_type_archive_title('', false); ?></h1>
				<?php if (get_the_archive_description()) : ?>
					<p class="lf-section__intro"><?php echo wp_kses_post(get_the_archive_description()); ?></p>
				<?php endif; ?>
			</header>

			<?php if (!is_wp_error($terms) && !empty($terms) && $archive_link) : ?>
				<div class="lf-project-filters" role="list">
					<a class="lf-project-filter <?php echo $current_type === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url($archive_link); ?>"><?php esc_html_e('All Projects', 'leadsforward-core'); ?></a>
					<?php foreach ($terms as $term) :
						if (!$term instanceof \WP_Term) {
							continue;
						}
						$link = add_query_arg('project_type', $term->slug, $archive_link);
						?>
						<a class="lf-project-filter <?php echo $current_type === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url($link); ?>"><?php echo esc_html($term->name); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (have_posts()) : ?>
				<div class="lf-project-grid">
					<?php while (have_posts()) : the_post(); ?>
						<?php
						$post = get_post();
						if ($post instanceof \WP_Post && function_exists('lf_projects_render_card')) {
							lf_projects_render_card($post, ['show_before_after' => true]);
						} elseif ($post instanceof \WP_Post) {
							?>
							<article class="lf-project-card lf-card">
								<h3 class="lf-project-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							</article>
							<?php
						}
						?>
					<?php endwhile; ?>
				</div>
				<?php the_posts_navigation(); ?>
			<?php else : ?>
				<p><?php esc_html_e('No projects yet.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
