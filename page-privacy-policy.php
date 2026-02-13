<?php
/**
 * Privacy Policy template. Fixed content with dynamic business details.
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
				<h1><?php esc_html_e('Privacy Policy', 'leadsforward-core'); ?></h1>
				<p><?php echo esc_html(sprintf(__('Last updated: %s', 'leadsforward-core'), $updated)); ?></p>

				<h2><?php esc_html_e('Overview', 'leadsforward-core'); ?></h2>
				<p><?php echo esc_html(sprintf(__('%s values your privacy. This policy explains what we collect, how we use it, and how we protect it when you visit our website or request service.', 'leadsforward-core'), $name)); ?></p>

				<h2><?php esc_html_e('Information we collect', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('Contact details you submit (name, phone, email, address).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Project details and service preferences you choose to share.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Usage data such as pages visited, device type, and browser information.', 'leadsforward-core'); ?></li>
				</ul>

				<h2><?php esc_html_e('How we use information', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('Respond to your requests and provide service updates.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Prepare estimates, scheduling, and customer support.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Improve site performance and user experience.', 'leadsforward-core'); ?></li>
				</ul>

				<h2><?php esc_html_e('Sharing and disclosure', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('We do not sell your personal information. We may share data with trusted service providers who help us operate the website or deliver services, or when required by law.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Cookies and analytics', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('We may use cookies or similar technologies to measure traffic and improve our services. You can disable cookies in your browser settings, but parts of the site may not function as intended.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Data security', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('We use reasonable safeguards to protect your information. No method of transmission is 100% secure, and we cannot guarantee absolute security.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Your choices', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('You may request access, correction, or deletion of your information by contacting us. We will respond within a reasonable timeframe.', 'leadsforward-core'); ?></p>

				<h2><?php esc_html_e('Contact', 'leadsforward-core'); ?></h2>
				<p><?php echo esc_html(sprintf(__('If you have questions about this policy, contact %s:', 'leadsforward-core'), $name)); ?></p>
				<?php if (!empty($contact_lines)) : ?>
					<p><?php echo esc_html(implode(' | ', $contact_lines)); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
