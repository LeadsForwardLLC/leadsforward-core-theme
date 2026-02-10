<?php
/**
 * 404 template.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$services_page = get_page_by_path('our-services');
$areas_page = get_page_by_path('our-service-areas');
$contact_page = get_page_by_path('contact');
?>
<main id="main" class="site-main site-main--blog" role="main">
	<section class="lf-section lf-section--error">
		<div class="lf-section__inner">
			<div class="lf-error">
				<p class="lf-error__kicker">404</p>
				<h1><?php esc_html_e('Page not found', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('That page doesn’t exist or has moved. Try searching the site or jump to a popular section below.', 'leadsforward-core'); ?></p>
				<form class="lf-error__search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
					<label class="screen-reader-text" for="lf-error-search"><?php esc_html_e('Search the site', 'leadsforward-core'); ?></label>
					<input type="search" id="lf-error-search" name="s" placeholder="<?php esc_attr_e('Search the site', 'leadsforward-core'); ?>" />
					<button type="submit" class="lf-btn lf-btn--primary"><?php esc_html_e('Search', 'leadsforward-core'); ?></button>
				</form>
				<div class="lf-error__actions">
					<a class="lf-btn lf-btn--secondary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'leadsforward-core'); ?></a>
					<?php if ($contact_page instanceof \WP_Post) : ?>
						<a class="lf-btn lf-btn--primary" href="<?php echo esc_url(get_permalink($contact_page)); ?>"><?php esc_html_e('Contact us', 'leadsforward-core'); ?></a>
					<?php endif; ?>
				</div>
				<div class="lf-error__links">
					<?php if ($services_page instanceof \WP_Post) : ?>
						<a href="<?php echo esc_url(get_permalink($services_page)); ?>"><?php esc_html_e('View services', 'leadsforward-core'); ?></a>
					<?php endif; ?>
					<?php if ($areas_page instanceof \WP_Post) : ?>
						<a href="<?php echo esc_url(get_permalink($areas_page)); ?>"><?php esc_html_e('Service areas', 'leadsforward-core'); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url(function_exists('lf_blog_base_url') ? lf_blog_base_url() : home_url('/')); ?>"><?php esc_html_e('Browse the blog', 'leadsforward-core'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
