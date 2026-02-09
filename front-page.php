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
$front_id = (int) get_option('page_on_front');
$has_hero = $front_id && function_exists('lf_page_has_hero') ? lf_page_has_hero($front_id) : false;
$home_title = $front_id ? get_the_title($front_id) : get_bloginfo('name');
$home_title = is_string($home_title) ? $home_title : '';
$home_title_tag = function_exists('lf_should_output_h1')
	? (lf_should_output_h1(['location' => 'title', 'post_id' => $front_id, 'has_hero' => $has_hero]) ? 'h1' : 'h2')
	: 'h1';
?>

<main id="main" class="site-main site-main--homepage" role="main">
	<?php if (!$has_hero && $home_title !== '') : ?>
		<header class="entry-header">
			<<?php echo esc_html($home_title_tag); ?> class="entry-title"><?php echo esc_html($home_title); ?></<?php echo esc_html($home_title_tag); ?>>
		</header>
	<?php endif; ?>
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
