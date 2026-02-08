<?php
/**
 * Single post template using Page Builder sections.
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
		$post_obj = get_post();
		$use_builder = function_exists('lf_pb_get_context_for_post') && function_exists('lf_pb_render_sections')
			? (lf_pb_get_context_for_post($post_obj) === 'post')
			: false;
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ($use_builder) : ?>
				<div class="entry-content entry-content--builder">
					<?php lf_pb_render_sections($post_obj); ?>
				</div>
			<?php else : ?>
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
