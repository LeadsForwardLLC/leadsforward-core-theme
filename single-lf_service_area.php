<?php
/**
 * Single Service Area. One H1 (city = title), semantic article.
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
		$state = function_exists('get_field') ? get_field('lf_service_area_state') : '';
		$intro = $state ? sprintf(/* translators: 1: city name, 2: state */ __('%1$s, %2$s', 'leadsforward-core'), get_the_title(), $state) : get_the_title();
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
				<?php if ($state) : ?>
					<p class="entry-meta entry-meta--state"><?php echo esc_html($state); ?></p>
				<?php endif; ?>
			</header>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
			<?php get_template_part('templates/parts/related-services'); ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
