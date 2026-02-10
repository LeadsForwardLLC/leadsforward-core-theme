<?php
/**
 * Blog archive filter bar (search + category).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$action = function_exists('lf_blog_base_url') ? lf_blog_base_url() : home_url('/');
$search_query = get_search_query();
$current_cat = (int) get_query_var('cat');
$categories = get_categories([
	'hide_empty' => false,
	'orderby' => 'name',
	'order' => 'ASC',
]);
$author_id = (int) get_query_var('author');
$tag = (string) get_query_var('tag');
$year = (int) get_query_var('year');
$month = (int) get_query_var('monthnum');
$day = (int) get_query_var('day');
?>
<form class="lf-blog-filters" role="search" method="get" action="<?php echo esc_url($action); ?>">
	<div class="lf-blog-filters__group">
		<label class="screen-reader-text" for="lf-blog-search"><?php esc_html_e('Search articles', 'leadsforward-core'); ?></label>
		<input type="search" id="lf-blog-search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search articles', 'leadsforward-core'); ?>" />
	</div>
	<div class="lf-blog-filters__group">
		<label class="screen-reader-text" for="lf-blog-category"><?php esc_html_e('Filter by topic', 'leadsforward-core'); ?></label>
		<select id="lf-blog-category" name="cat">
			<option value=""><?php esc_html_e('All topics', 'leadsforward-core'); ?></option>
			<?php foreach ($categories as $category) : ?>
				<option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($current_cat, (int) $category->term_id); ?>>
					<?php echo esc_html($category->name); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php if ($author_id) : ?>
		<input type="hidden" name="author" value="<?php echo esc_attr((string) $author_id); ?>" />
	<?php endif; ?>
	<?php if ($tag !== '') : ?>
		<input type="hidden" name="tag" value="<?php echo esc_attr($tag); ?>" />
	<?php endif; ?>
	<?php if ($year) : ?>
		<input type="hidden" name="year" value="<?php echo esc_attr((string) $year); ?>" />
	<?php endif; ?>
	<?php if ($month) : ?>
		<input type="hidden" name="monthnum" value="<?php echo esc_attr((string) $month); ?>" />
	<?php endif; ?>
	<?php if ($day) : ?>
		<input type="hidden" name="day" value="<?php echo esc_attr((string) $day); ?>" />
	<?php endif; ?>
	<button type="submit" class="lf-btn lf-btn--primary"><?php esc_html_e('Search', 'leadsforward-core'); ?></button>
	<?php if ($search_query !== '' || $current_cat || $tag !== '') : ?>
		<a class="lf-blog-filters__reset" href="<?php echo esc_url($action); ?>"><?php esc_html_e('View all', 'leadsforward-core'); ?></a>
	<?php endif; ?>
</form>
