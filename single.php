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
		$has_hero = function_exists('lf_page_has_hero') ? lf_page_has_hero(get_the_ID()) : false;
		$show_title = !$use_builder || !$has_hero;
		$title_tag = function_exists('lf_should_output_h1')
			? (lf_should_output_h1(['location' => 'title', 'post_id' => get_the_ID(), 'has_hero' => $has_hero]) ? 'h1' : 'h2')
			: 'h1';
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ($show_title) : ?>
				<header class="entry-header">
					<<?php echo esc_html($title_tag); ?> class="entry-title"><?php the_title(); ?></<?php echo esc_html($title_tag); ?>>
				</header>
			<?php endif; ?>
			<?php if ($use_builder) : ?>
				<div class="entry-content entry-content--builder">
					<?php lf_pb_render_sections($post_obj); ?>
				</div>
			<?php else : ?>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
