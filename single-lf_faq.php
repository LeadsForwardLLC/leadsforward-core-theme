<?php
/**
 * Single FAQ. One H1 (question or title), semantic article.
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
		$question = function_exists('get_field') ? get_field('lf_faq_question') : '';
		if (!$question) {
			$question = get_the_title();
		}
		$answer = function_exists('get_field') ? get_field('lf_faq_answer') : '';
		if (!$answer) {
			$answer = get_the_content();
		}
	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html($question); ?></h1>
			</header>
			<div class="entry-content">
				<?php echo wp_kses_post($answer); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
