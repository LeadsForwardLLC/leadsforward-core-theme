<?php
/**
 * Front page template. Renders homepage sections from ACF flexible content or defaults.
 * No hardcoded layout; section order and variants configured in Theme Options > Homepage.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$sections = lf_get_homepage_sections();
?>

<main id="main" class="site-main site-main--homepage" role="main">
	<?php if (!empty($sections)) : ?>
		<?php foreach ($sections as $i => $section) : ?>
			<?php lf_render_homepage_section($section, $i); ?>
		<?php endforeach; ?>
	<?php else : ?>
		<section class="lf-homepage-empty" aria-label="<?php esc_attr_e('Homepage', 'leadsforward-core'); ?>">
			<p><?php esc_html_e('Configure homepage sections in LeadsForward → Homepage.', 'leadsforward-core'); ?></p>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
