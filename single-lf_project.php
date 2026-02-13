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

<main id="main" class="site-main" role="main">
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
		$gallery_ids = function_exists('get_field') ? get_field('lf_project_gallery', $post_id) : get_post_meta($post_id, 'lf_project_gallery', true);
		if (!is_array($gallery_ids)) {
			$gallery_ids = [];
		}
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class('lf-project-single'); ?>>
			<header class="lf-project-hero">
				<div class="lf-project-hero__content">
					<?php if ($type_label) : ?><p class="lf-project-hero__type"><?php echo esc_html($type_label); ?></p><?php endif; ?>
					<h1 class="lf-project-hero__title"><?php the_title(); ?></h1>
					<?php if ($location_line) : ?><p class="lf-project-hero__meta"><?php echo esc_html($location_line); ?></p><?php endif; ?>
					<?php if ($excerpt) : ?><p class="lf-project-hero__excerpt"><?php echo esc_html($excerpt); ?></p><?php endif; ?>
					<?php if ($archive = get_post_type_archive_link('lf_project')) : ?>
						<a class="lf-project-hero__back" href="<?php echo esc_url($archive); ?>"><?php esc_html_e('Back to Projects', 'leadsforward-core'); ?></a>
					<?php endif; ?>
				</div>
				<div class="lf-project-hero__media">
					<?php if ($before_url && $after_url) : ?>
						<div class="lf-project-before-after" data-lf-project-before-after data-state="after">
							<img class="lf-project-before-after__image lf-project-before-after__image--after" src="<?php echo esc_url($after_url); ?>" alt="" loading="lazy" />
							<img class="lf-project-before-after__image lf-project-before-after__image--before" src="<?php echo esc_url($before_url); ?>" alt="" loading="lazy" />
							<button type="button" class="lf-project-before-after__toggle" data-lf-project-toggle data-before-label="<?php echo esc_attr__('Show before', 'leadsforward-core'); ?>" data-after-label="<?php echo esc_attr__('Show after', 'leadsforward-core'); ?>" aria-pressed="false"><?php esc_html_e('Show before', 'leadsforward-core'); ?></button>
						</div>
					<?php elseif ($hero_image) : ?>
						<img class="lf-project-hero__image" src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($hero_alt); ?>" loading="lazy" />
					<?php endif; ?>
				</div>
			</header>

			<div class="lf-project-body">
				<?php if (get_the_content()) : ?>
					<div class="lf-project-body__content">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>

				<?php if (!empty($gallery_ids)) : ?>
					<section class="lf-project-gallery">
						<h2><?php esc_html_e('Project Gallery', 'leadsforward-core'); ?></h2>
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
					</section>
				<?php endif; ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
