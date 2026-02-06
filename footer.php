<?php
/**
 * Root footer. Loads template part so get_footer() works.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<?php get_template_part('templates/parts/footer'); ?>
<?php wp_footer(); ?>
</body>
</html>
