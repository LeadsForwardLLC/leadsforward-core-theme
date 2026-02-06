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
		$show_title_h1 = apply_filters('lf_page_show_title_h1', true, get_the_ID());
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ($show_title_h1) : ?>
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
