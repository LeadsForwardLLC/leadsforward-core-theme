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
	<?php while (have_posts()) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php
			if (function_exists('lf_pb_render_sections')) {
				lf_pb_render_sections(get_post());
			} else {
				the_content();
			}
			?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
