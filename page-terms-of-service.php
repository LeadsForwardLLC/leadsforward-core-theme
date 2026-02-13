<?php
/**
 * Terms of Service template. Fixed content with dynamic business details.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$details = function_exists('lf_legal_business_details') ? lf_legal_business_details() : [
	'name' => get_bloginfo('name'),
	'address' => '',
	'phone' => '',
	'email' => '',
];
$name = $details['name'] ?? get_bloginfo('name');
$address = $details['address'] ?? '';
$phone = $details['phone'] ?? '';
$email = $details['email'] ?? '';
$updated = get_the_modified_date(get_option('date_format')) ?: date_i18n(get_option('date_format'));
$contact_lines = array_filter([
	$address,
	$phone ? __('Phone:', 'leadsforward-core') . ' ' . $phone : '',
	$email ? __('Email:', 'leadsforward-core') . ' ' . $email : '',
]);
?>

<main id="main" class="site-main" role="main">
	<section class="lf-section lf-section--legal">
		<div class="lf-section__inner">
			<div class="lf-prose">
				<h1><?php esc_html_e('Terms of Service', 'leadsforward-core'); ?></h1>
				<p><?php echo esc_html(sprintf(__('Last updated: %s', 'leadsforward-core'), $updated)); ?></p>

				<h2><?php esc_html_e('Acceptance of terms', 'leadsforward-core'); ?></h2>
				<p><?php echo esc_html(sprintf(__('By using this website or requesting services from %s, you agree to these terms and to provide accurate information.', 'leadsforward-core'), $name)); ?></p>

				<h2><?php esc_html_e('Services and estimates', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Service descriptions and estimates are provided for informational purposes. Final pricing and scope are confirmed in writing before work begins.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Scheduling and access', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('You agree to provide reasonable access to the property and to notify us of any conditions that may affect scheduling, safety, or scope.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Payments', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Payment terms, deposits, and accepted methods are confirmed before work begins. Late payments may be subject to fees as allowed by law.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Cancellations and changes', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Please provide advance notice for cancellations or rescheduling. Last‑minute changes may result in additional fees depending on staffing and materials.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Warranties and limitations', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Any warranties are stated in your service agreement. We are not responsible for delays or damages caused by conditions outside our control.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Website use', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('You may not misuse this website, interfere with its operation, or attempt unauthorized access. Content on this site is owned by the business and may not be reused without permission.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Changes to these terms', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('We may update these terms from time to time. Continued use of the site after updates constitutes acceptance of the new terms.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Contact', 'leadsforward-core'); ?></h2>
				<p><?php echo esc_html(sprintf(__('If you have questions about these terms, contact %s:', 'leadsforward-core'), $name)); ?></p>
				<?php if (!empty($contact_lines)) : ?>
					<p><?php echo esc_html(implode(' | ', $contact_lines)); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
