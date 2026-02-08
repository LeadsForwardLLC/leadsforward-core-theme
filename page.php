<?php
/**
 * Default template for pages. Clean semantic wrapper; no design yet.
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
			? (lf_pb_get_context_for_post($post_obj) === 'page')
			: false;
		$show_title_h1 = apply_filters('lf_page_show_title_h1', !$use_builder, get_the_ID());
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ($show_title_h1) : ?>
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>
			<div class="entry-content<?php echo $use_builder ? ' entry-content--builder' : ''; ?>">
				<?php
				if ($use_builder) {
					lf_pb_render_sections($post_obj);
				} else {
					the_content();
				}
				?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
