<?php
/**
 * Single Project. Full-width hero + before/after and gallery.
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

<main id="main" class="site-main site-main--projects" role="main">
	<?php while (have_posts()) : the_post(); ?>
		<?php
		$post_id = get_the_ID();
		$type_label = function_exists('lf_projects_get_primary_type') ? lf_projects_get_primary_type($post_id) : '';
		$location_line = function_exists('lf_projects_get_location_line') ? lf_projects_get_location_line($post_id) : '';
		$excerpt = has_excerpt() ? wp_strip_all_tags(get_the_excerpt()) : '';
		$before_after = function_exists('lf_projects_get_before_after_ids') ? lf_projects_get_before_after_ids($post_id) : ['before' => 0, 'after' => 0];
		$before_url = $before_after['before'] ? wp_get_attachment_image_url($before_after['before'], 'large') : '';
		$after_url = $before_after['after'] ? wp_get_attachment_image_url($before_after['after'], 'large') : '';
		$featured_id = get_post_thumbnail_id($post_id);
		$hero_image_id = $before_after['after'] ?: $featured_id ?: $before_after['before'];
		$hero_image = $hero_image_id ? wp_get_attachment_image_url($hero_image_id, 'large') : '';
		$hero_alt = $hero_image_id ? (string) get_post_meta($hero_image_id, '_wp_attachment_image_alt', true) : '';
		$archive_link = get_post_type_archive_link('lf_project');
		$has_media = ($before_url && $after_url) || $hero_image;
		$gallery_ids = function_exists('get_field') ? get_field('lf_project_gallery', $post_id) : get_post_meta($post_id, 'lf_project_gallery', true);
		if (!is_array($gallery_ids)) {
			$gallery_ids = [];
		}
		$terms = get_the_terms($post_id, 'lf_project_type');
		$term_ids = [];
		if (!empty($terms) && is_array($terms)) {
			foreach ($terms as $term) {
				if ($term instanceof \WP_Term) {
					$term_ids[] = (int) $term->term_id;
				}
			}
		}
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class('lf-project-single'); ?>>
			<section class="lf-section lf-section--project-hero">
				<div class="lf-section__inner">
					<div class="lf-blog-hero <?php echo $has_media ? 'lf-blog-hero--media' : 'lf-blog-hero--simple'; ?>">
						<div class="lf-blog-hero__content">
							<?php if ($archive_link) : ?>
								<a class="lf-blog-hero__back" href="<?php echo esc_url($archive_link); ?>">
									<?php esc_html_e('Back to Projects', 'leadsforward-core'); ?>
								</a>
							<?php endif; ?>
							<?php if ($type_label || $location_line) : ?>
								<div class="lf-blog-hero__meta">
									<?php if ($type_label) : ?>
										<span class="lf-blog-hero__pill"><?php echo esc_html($type_label); ?></span>
									<?php endif; ?>
									<?php if ($location_line) : ?>
										<span><?php echo esc_html($location_line); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<h1 class="lf-blog-hero__title"><?php the_title(); ?></h1>
							<?php if ($excerpt) : ?>
								<p class="lf-blog-hero__intro"><?php echo esc_html($excerpt); ?></p>
							<?php endif; ?>
						</div>
						<?php if ($has_media) : ?>
							<div class="lf-blog-hero__media">
								<?php if ($before_url && $after_url) : ?>
									<div class="lf-project-before-after" data-lf-project-before-after data-state="after">
										<img class="lf-project-before-after__image lf-project-before-after__image--after" src="<?php echo esc_url($after_url); ?>" alt="" loading="lazy" />
										<img class="lf-project-before-after__image lf-project-before-after__image--before" src="<?php echo esc_url($before_url); ?>" alt="" loading="lazy" />
										<button type="button" class="lf-project-before-after__toggle" data-lf-project-toggle data-before-label="<?php echo esc_attr__('Show before', 'leadsforward-core'); ?>" data-after-label="<?php echo esc_attr__('Show after', 'leadsforward-core'); ?>" aria-pressed="false"><?php esc_html_e('Show before', 'leadsforward-core'); ?></button>
									</div>
								<?php elseif ($hero_image) : ?>
									<img class="lf-blog-hero__image" src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($hero_alt); ?>" loading="lazy" />
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<?php if (get_the_content()) : ?>
				<section class="lf-section lf-section--project-content">
					<div class="lf-section__inner">
						<div class="lf-prose">
							<?php the_content(); ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if (!empty($gallery_ids)) : ?>
				<section class="lf-section lf-section--project-gallery">
					<div class="lf-section__inner">
						<header class="lf-section__header">
							<h2 class="lf-section__title"><?php esc_html_e('Project Gallery', 'leadsforward-core'); ?></h2>
						</header>
						<div class="lf-project-gallery__grid">
							<?php foreach ($gallery_ids as $image_id) :
								$image_id = absint($image_id);
								if (!$image_id) {
									continue;
								}
								$url = wp_get_attachment_image_url($image_id, 'large');
								$alt = (string) get_post_meta($image_id, '_wp_attachment_image_alt', true);
								if (!$url) {
									continue;
								}
								?>
								<figure class="lf-project-gallery__item">
									<img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" />
								</figure>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php
			$related_args = [
				'post_type' => 'lf_project',
				'post_status' => 'publish',
				'posts_per_page' => 3,
				'post__not_in' => [$post_id],
				'no_found_rows' => true,
			];
			if (!empty($term_ids)) {
				$related_args['tax_query'] = [
					[
						'taxonomy' => 'lf_project_type',
						'field' => 'term_id',
						'terms' => $term_ids,
					],
				];
			}
			$related = new \WP_Query($related_args);
			if ($related->have_posts()) :
			?>
				<section class="lf-section lf-section--project-related">
					<div class="lf-section__inner">
						<header class="lf-section__header">
							<h2 class="lf-section__title"><?php esc_html_e('Similar Projects', 'leadsforward-core'); ?></h2>
							<p class="lf-section__intro"><?php esc_html_e('Explore more work like this project.', 'leadsforward-core'); ?></p>
						</header>
						<div class="lf-project-grid">
							<?php while ($related->have_posts()) :
								$related->the_post();
								$related_post = get_post();
								if ($related_post instanceof \WP_Post && function_exists('lf_projects_render_card')) {
									lf_projects_render_card($related_post, ['show_before_after' => false]);
								}
							endwhile; ?>
						</div>
						<?php if ($archive_link) : ?>
							<a class="lf-project-card__action" href="<?php echo esc_url($archive_link); ?>"><?php esc_html_e('View all projects', 'leadsforward-core'); ?></a>
						<?php endif; ?>
					</div>
				</section>
				<?php
			endif;
			wp_reset_postdata();
			?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
