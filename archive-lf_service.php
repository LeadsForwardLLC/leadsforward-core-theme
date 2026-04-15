<?php
/**
 * Archive: Services. One H1 (post type archive title), semantic main + section.
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
	<?php
	$archive_heading = post_type_archive_title('', false);
	$archive_intro = function_exists('term_description') ? (string) term_description() : '';
	$archive_intro = is_string($archive_intro) ? trim(wp_strip_all_tags($archive_intro)) : '';
	$variant = 'a';
	$card_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup([], 'service_grid', 'list', 'lf-block-service-grid__icon') : '';
	?>
	<section class="lf-block lf-block-service-grid lf-block-service-grid--<?php echo esc_attr($variant); ?>">
		<div class="lf-block-service-grid__inner">
			<header class="lf-block-service-grid__header lf-section__header lf-section__header--align-center">
				<h1 class="lf-block-service-grid__title"><?php echo esc_html($archive_heading !== '' ? $archive_heading : __('Our Services', 'leadsforward-core')); ?></h1>
				<?php if ($archive_intro !== '') : ?>
					<p class="lf-block-service-grid__intro"><?php echo esc_html($archive_intro); ?></p>
				<?php endif; ?>
			</header>
			<?php if (have_posts()) : ?>
				<ul class="lf-block-service-grid__list lf-cpt-driven-links" role="list">
					<?php $index = 0; ?>
					<?php while (have_posts()) : the_post(); $index++; ?>
						<li class="lf-block-service-grid__item">
							<a href="<?php the_permalink(); ?>" class="lf-block-service-grid__link">
								<?php if ($card_icon) : ?><span class="lf-block-service-grid__icon"><?php echo $card_icon; ?></span><?php endif; ?>
								<span class="lf-block-service-grid__card-index"><?php echo esc_html(str_pad((string) $index, 2, '0', STR_PAD_LEFT)); ?></span>
								<span class="lf-block-service-grid__card-title"><?php the_title(); ?></span>
								<span class="lf-block-service-grid__card-action" aria-hidden="true"><?php esc_html_e('View', 'leadsforward-core'); ?></span>
							</a>
						</li>
					<?php endwhile; ?>
				</ul>
				<?php the_posts_navigation(); ?>
			<?php else : ?>
				<p class="lf-block-service-grid__empty"><?php esc_html_e('No services yet.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
