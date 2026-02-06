<?php
/**
 * Archive: FAQs. One H1, semantic main + section (dl for FAQ list).
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
	<header class="archive-header">
		<h1 class="archive-title"><?php post_type_archive_title('', false); ?></h1>
	</header>
	<section class="archive-content" aria-label="<?php esc_attr_e('FAQs', 'leadsforward-core'); ?>">
		<?php if (have_posts()) : ?>
			<dl class="faq-archive-list">
				<?php while (have_posts()) : the_post();
					$q = function_exists('get_field') ? get_field('lf_faq_question') : '';
					if (!$q) $q = get_the_title();
					$a = function_exists('get_field') ? get_field('lf_faq_answer') : get_the_content();
				?>
					<div class="faq-archive-item">
						<dt><a href="<?php the_permalink(); ?>"><?php echo esc_html($q); ?></a></dt>
						<dd><?php echo wp_kses_post($a); ?></dd>
					</div>
				<?php endwhile; ?>
			</dl>
			<?php the_posts_navigation(); ?>
		<?php else : ?>
			<p><?php esc_html_e('No FAQs yet.', 'leadsforward-core'); ?></p>
		<?php endif; ?>
	</section>
</main>

<?php
get_footer();
