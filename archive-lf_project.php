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
$title = post_type_archive_title('', false);
$intro = get_the_archive_description();
if ($intro === '') {
	$intro = __('Browse real transformations and finished work from recent projects.', 'leadsforward-core');
}
?>

<main id="main" class="site-main site-main--projects" role="main">
	<section class="lf-section lf-section--project-hero">
		<div class="lf-section__inner">
			<div class="lf-blog-hero lf-blog-hero--simple">
				<div class="lf-blog-hero__content">
					<div class="lf-blog-hero__meta">
						<span class="lf-blog-hero__pill"><?php esc_html_e('Projects', 'leadsforward-core'); ?></span>
					</div>
					<h1 class="lf-blog-hero__title"><?php echo esc_html($title ?: __('Our Projects', 'leadsforward-core')); ?></h1>
					<?php if ($intro) : ?>
						<div class="lf-blog-hero__intro"><?php echo wp_kses_post($intro); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="lf-section lf-section--project-archive">
		<div class="lf-section__inner">
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
				<div class="lf-blog-pagination">
					<?php the_posts_navigation(); ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e('No projects yet.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
