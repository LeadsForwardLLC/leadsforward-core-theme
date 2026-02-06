<?php
/**
 * Single Service. One H1 (SEO H1 or title), semantic article, no duplicate headings.
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
	<?php while (have_posts()) : the_post();
		$h1 = function_exists('get_field') ? get_field('lf_service_seo_h1') : '';
		if (!$h1) {
			$h1 = get_the_title();
		}
		$short_desc = function_exists('get_field') ? get_field('lf_service_short_desc') : '';
		$long_content = function_exists('get_field') ? get_field('lf_service_long_content') : '';
		if (!$long_content) {
			$long_content = get_the_content();
		}
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html($h1); ?></h1>
			</header>
			<?php if ($short_desc) : ?>
				<p class="entry-summary"><?php echo esc_html($short_desc); ?></p>
			<?php endif; ?>
			<div class="entry-content">
				<?php echo wp_kses_post($long_content); ?>
			</div>
			<?php get_template_part('templates/parts/related-service-areas'); ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
