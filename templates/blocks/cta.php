<?php
/**
 * Block: CTA. Resolved CTA (section > homepage > global). Single GHL source; phone link when type=call.
 *
 * @var array $block
 * @var bool  $is_preview
 * @var array $block['context'] optional homepage section overrides
 * @package LeadsForward_Core
 */

if (!defined('ABSPATH')) {
	exit;
}

$block_id = $block['id'] ?? '';
$context = $block['context'] ?? [];
$section = $context['section'] ?? [];
$section_heading_tag = function_exists('lf_sections_sanitize_section_heading_tag') ? lf_sections_sanitize_section_heading_tag($section) : 'h2';
$variant = $block['variant'] ?? 'default';
$bg_class = function_exists('lf_sections_bg_class') ? lf_sections_bg_class($section['section_background'] ?? 'dark') : '';
$surface = function_exists('lf_sections_block_surface_attrs') ? lf_sections_block_surface_attrs($section) : ['class' => $bg_class, 'style' => ''];
$header_align = function_exists('lf_sections_sanitize_header_align') ? lf_sections_sanitize_header_align($section) : 'center';
$cta_surface_style = $surface['style'] !== '' ? ' style="' . esc_attr($surface['style']) . '"' : '';
$headline = !empty($section['cta_headline']) ? $section['cta_headline'] : '';
$subheadline = !empty($section['cta_subheadline']) ? $section['cta_subheadline'] : '';
$trust_strip_enabled = (string) ($section['cta_trust_strip_enabled'] ?? '0') !== '0';
$trust_rating = isset($section['cta_trust_rating']) ? (float) $section['cta_trust_rating'] : 0.0;
$trust_count = isset($section['cta_trust_review_count']) ? (int) $section['cta_trust_review_count'] : 0;
$trust_badges = [];
if (!empty($section['cta_trust_badges'])) {
	$trust_badges = preg_split('/[\r\n]+/', (string) $section['cta_trust_badges']);
	$trust_badges = array_values(array_filter(array_map('trim', is_array($trust_badges) ? $trust_badges : [])));
}
$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'cta', 'above', 'lf-heading-icon') : '';
$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($section, 'cta', 'left', 'lf-heading-icon') : '';
$eyebrow = '';
$offer_label = '';
$offer_items = [];
if ($variant === 'b') {
	$eyebrow = __('Get a fast response', 'leadsforward-core');
} elseif ($variant === 'c') {
	$eyebrow = __('Limited availability', 'leadsforward-core');
	$offer_label = __('Offer includes', 'leadsforward-core');
	$offer_items = [
		__('Fast, no-obligation estimate', 'leadsforward-core'),
		__('Priority scheduling window', 'leadsforward-core'),
		__('Clear next steps in one call', 'leadsforward-core'),
	];
}

$cta = function_exists('lf_resolve_cta') ? lf_resolve_cta($context, $section, []) : [
	'primary_text'   => lf_get_option('lf_cta_primary_text', 'option'),
	'secondary_text' => lf_get_option('lf_cta_secondary_text', 'option'),
	'ghl_embed'     => lf_get_option('lf_cta_ghl_embed', 'option'),
	'primary_type'   => 'text',
];
$primary   = $cta['primary_text'] ?? '';
$secondary = $cta['secondary_text'] ?? '';
if (!empty($context['homepage']) && function_exists('lf_copy_template')) {
	$primary = lf_copy_template('cta_microcopy', $primary, []);
	if ($primary === '') {
		$primary = $cta['primary_text'] ?? '';
	}
}
$ghl_embed = $cta['ghl_embed'] ?? '';
$cta_type  = $cta['primary_type'] ?? 'text';
$cta_action = $cta['primary_action'] ?? 'link';
$cta_url = $cta['primary_url'] ?? '';
$cta_secondary_action = $cta['secondary_action'] ?? 'call';
$cta_secondary_url = $cta['secondary_url'] ?? '';
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$use_phone_link = $cta_type === 'call' && $cta_phone && $primary;
$show_form = ($cta_type === 'form' && $ghl_embed) || ($cta_type !== 'call' && $ghl_embed);
?>
<section class="lf-block lf-block-cta <?php echo esc_attr($surface['class'] ?: 'lf-surface-dark'); ?> lf-block-cta--<?php echo esc_attr($variant); ?>" id="<?php echo esc_attr($block_id ?: 'block-' . uniqid()); ?>" data-variant="<?php echo esc_attr($variant); ?>" aria-label="<?php esc_attr_e('Call to action', 'leadsforward-core'); ?>"<?php echo $cta_surface_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="lf-block-cta__inner">
		<div class="lf-block-cta__content lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($eyebrow !== '') : ?>
				<p class="lf-block-cta__eyebrow"><?php echo esc_html($eyebrow); ?></p>
			<?php endif; ?>
			<?php if ($headline !== '') : ?>
				<?php if ($icon_left) : ?>
					<div class="lf-heading-row">
						<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
						<<?php echo esc_html($section_heading_tag); ?> class="lf-block-cta__headline"><?php echo esc_html($headline); ?></<?php echo esc_html($section_heading_tag); ?>>
					</div>
				<?php else : ?>
					<<?php echo esc_html($section_heading_tag); ?> class="lf-block-cta__headline"><?php echo esc_html($headline); ?></<?php echo esc_html($section_heading_tag); ?>>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ($subheadline !== '') : ?>
				<p class="lf-block-cta__subheadline"><?php echo esc_html($subheadline); ?></p>
			<?php endif; ?>
			<?php if ($trust_strip_enabled && ($trust_rating > 0 || $trust_count > 0 || !empty($trust_badges))) : ?>
				<div class="lf-block-cta__trust" role="note" aria-label="<?php esc_attr_e('Trust and credibility', 'leadsforward-core'); ?>">
					<div class="lf-block-cta__trust-summary">
						<span class="lf-block-cta__trust-stars" aria-hidden="true">
							<?php for ($i = 0; $i < 5; $i++) : ?>
								<svg class="lf-block-cta__trust-star" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							<?php endfor; ?>
						</span>
						<?php if ($trust_rating > 0) : ?><span class="lf-block-cta__trust-score"><?php echo esc_html(number_format($trust_rating, 1)); ?></span><?php endif; ?>
						<?php if ($trust_count > 0) : ?><span class="lf-block-cta__trust-count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $trust_count, 'leadsforward-core'), $trust_count)); ?></span><?php endif; ?>
					</div>
					<?php if (!empty($trust_badges)) : ?>
						<div class="lf-block-cta__trust-badges">
							<?php foreach ($trust_badges as $badge) : ?>
								<span class="lf-block-cta__trust-badge"><?php echo esc_html($badge); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($primary || $secondary) : ?>
				<div class="lf-block-cta__buttons">
					<?php if ($primary) : ?>
						<div class="lf-block-cta__primary">
							<?php if ($use_phone_link) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-cta__primary-link lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
							<?php elseif ($cta_action === 'quote') : ?>
								<button type="button" class="lf-block-cta__primary-text lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="cta"><?php echo esc_html($primary); ?></button>
							<?php elseif ($cta_url !== '') : ?>
								<a href="<?php echo esc_url($cta_url); ?>" class="lf-block-cta__primary-link lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></a>
							<?php else : ?>
								<span class="lf-block-cta__primary-text lf-btn lf-btn--primary"><?php echo esc_html($primary); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ($secondary) : ?>
						<div class="lf-block-cta__secondary">
							<?php if ($cta_secondary_action === 'quote') : ?>
								<button type="button" class="lf-block-cta__secondary-link lf-btn lf-btn--secondary" data-lf-quote-trigger="1" data-lf-quote-source="cta-secondary"><?php echo esc_html($secondary); ?></button>
							<?php elseif ($cta_secondary_action === 'call' && $cta_phone) : ?>
								<a href="tel:<?php echo esc_attr($cta_phone); ?>" class="lf-block-cta__secondary-link lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
							<?php elseif ($cta_secondary_action === 'link' && $cta_secondary_url !== '') : ?>
								<a href="<?php echo esc_url($cta_secondary_url); ?>" class="lf-block-cta__secondary-link lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></a>
							<?php else : ?>
								<span class="lf-block-cta__secondary-link lf-btn lf-btn--secondary"><?php echo esc_html($secondary); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($variant === 'c' && !empty($offer_items)) : ?>
				<div class="lf-block-cta__offer" role="note" aria-label="<?php esc_attr_e('Offer details', 'leadsforward-core'); ?>">
					<span class="lf-block-cta__offer-label"><?php echo esc_html($offer_label); ?></span>
					<ul class="lf-block-cta__offer-list" role="list">
						<?php foreach ($offer_items as $item) : ?>
							<li class="lf-block-cta__offer-item"><?php echo esc_html($item); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php if ($show_form) : ?>
			<div class="lf-block-cta__form">
				<div class="lf-block-cta__embed">
					<?php echo wp_kses_post($ghl_embed); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
