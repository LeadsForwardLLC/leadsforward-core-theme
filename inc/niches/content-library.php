<?php
/**
 * Per-niche reusable process steps and FAQs for About page generation.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_NICHE_CONTENT_LIBRARY_OPTION = 'lf_niche_content_libraries';
const LF_NICHE_ABOUT_PROCESS_GROUP = 'about-company';
const LF_NICHE_ABOUT_FAQ_CONTEXT = 'about_company';

/**
 * @return array<string, array{about: array{process: list<array{title:string,body:string}>, faqs: list<array{question:string,answer:string}>}}>
 */
function lf_niche_content_library_defaults(): array {
	return [
		'foundation-repair' => [
			'about' => [
				'process' => [
					[
						'title' => 'Free structural inspection',
						'body' => 'A certified evaluator documents cracks, settlement signs, moisture, and drainage patterns. You receive photos, measurements, and a plain-language explanation—not a pressure pitch.',
					],
					[
						'title' => 'Engineered repair plan',
						'body' => 'We size piers, wall anchors, carbon straps, or waterproofing to your soil and load conditions. Scope, timeline, and pricing are documented before any work begins.',
					],
					[
						'title' => 'Permits & engineering (when required)',
						'body' => '{business} coordinates engineering letters and municipal permits where your municipality requires them, so you are not chasing paperwork alone.',
					],
					[
						'title' => 'Protected installation',
						'body' => 'Crews stage materials, protect landscaping, and maintain clean access paths. Daily updates keep you informed while floors and walls are under repair.',
					],
					[
						'title' => 'Load transfer & stabilization',
						'body' => 'Piers are driven or helicals torqued to engineered depth; bowing walls are braced to stop inward movement. Each stage is verified before backfill.',
					],
					[
						'title' => 'Final walkthrough & warranty',
						'body' => 'We review monitoring points, drainage improvements, and transferable warranty coverage. You know what was fixed, what to watch, and who to call.',
					],
				],
				'faqs' => [
					[
						'question' => 'Are you licensed and insured for foundation work?',
						'answer' => 'Yes. {business} carries general liability and workers’ compensation coverage, and our crews follow local licensing requirements for structural repair{city_line}. We provide proof of insurance on request before work begins.',
					],
					[
						'question' => 'How long has {business} been serving homeowners{city_line}?',
						'answer' => 'We are a locally operated foundation repair company built on repeat referrals and long-term relationships. Our project managers live and work in the communities we serve, which keeps response times fast and accountability high.',
					],
					[
						'question' => 'Do you offer free foundation inspections?',
						'answer' => 'We provide complimentary structural evaluations for homeowners concerned about cracks, sticking doors, sloping floors, or basement moisture. You receive a clear findings summary and repair options—never a vague ballpark over the phone.',
					],
					[
						'question' => 'What warranty do you provide on foundation repairs?',
						'answer' => 'Warranty terms depend on the repair type—piers, wall stabilization, waterproofing, or crack repair—but every project includes written warranty documentation. We explain what is covered, for how long, and what maintenance keeps protection valid.',
					],
					[
						'question' => 'Will my landscaping be protected during pier installation?',
						'answer' => 'Yes. We plan access routes, use tarps and plywood where needed, and restore disturbed areas after installation. Our crews treat your yard like their own because most of our work comes from neighbors who can see the job site.',
					],
					[
						'question' => 'How do I know if I need piers versus crack injection?',
						'answer' => 'Crack injection addresses isolated wall cracks when the wall is still structurally sound. Piers or underpinning are recommended when floors are settling, gaps are widening, or load-bearing soil has failed. Your inspection explains which approach matches the root cause—not just the visible symptom.',
					],
				],
			],
		],
		'general' => [
			'about' => [
				'process' => [
					[
						'title' => 'Tell us what you need',
						'body' => 'Share photos, symptoms, and timing goals. {business} asks the right questions up front so the first visit is productive.',
					],
					[
						'title' => 'On-site evaluation & clear estimate',
						'body' => 'A qualified technician inspects the issue, explains options in plain language, and documents scope and pricing before work is scheduled.',
					],
					[
						'title' => 'Scheduled, protected work',
						'body' => 'Crews arrive on time, protect your property, and keep the job site clean throughout the project.',
					],
					[
						'title' => 'Quality check & follow-up',
						'body' => 'We walk through completed work with you, answer final questions, and stand behind the results with warranty coverage where applicable.',
					],
				],
				'faqs' => [
					[
						'question' => 'Is {business} licensed and insured?',
						'answer' => 'Yes. We maintain appropriate licensing and insurance for the work we perform{city_line}. Documentation is available before your project starts.',
					],
					[
						'question' => 'How quickly can you schedule service?',
						'answer' => 'Most inspections are scheduled within a few business days. Emergency situations are prioritized when safety or property damage is a concern.',
					],
					[
						'question' => 'Do you provide written estimates?',
						'answer' => 'Every project includes a documented scope and price before work begins. You will know what is included, what is optional, and what happens next.',
					],
					[
						'question' => 'Who will be on-site during the job?',
						'answer' => 'You will have a dedicated point of contact from {business} who coordinates scheduling, crew arrival, and the final walkthrough so you are never guessing who is responsible.',
					],
				],
			],
		],
	];
}

/**
 * @return array<string, mixed>
 */
function lf_niche_content_library_fill_vars(array $vars): array {
	$city = trim((string) ($vars['city'] ?? ''));
	$vars['city_line'] = $city !== '' ? ' in ' . $city : '';
	$vars['niche'] = trim((string) ($vars['niche'] ?? ''));
	return $vars;
}

function lf_niche_content_library_fill_string(string $text, array $vars): string {
	$vars = lf_niche_content_library_fill_vars($vars);
	foreach ($vars as $key => $val) {
		if (!is_scalar($val)) {
			continue;
		}
		$text = str_replace('{' . $key . '}', (string) $val, $text);
	}
	return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function lf_niche_content_library_fill_row(array $row, array $vars): array {
	$out = [];
	foreach ($row as $key => $val) {
		$out[$key] = is_string($val) ? lf_niche_content_library_fill_string($val, $vars) : $val;
	}
	return $out;
}

function lf_niche_content_library_resolve_slug(string $niche_slug): string {
	$niche_slug = sanitize_title($niche_slug);
	if ($niche_slug === '') {
		return 'general';
	}
	$defaults = lf_niche_content_library_defaults();
	if (isset($defaults[$niche_slug]['about'])) {
		return $niche_slug;
	}
	return 'general';
}

/**
 * @return array{about: array{process: list<array{title:string,body:string}>, faqs: list<array{question:string,answer:string}>}}
 */
function lf_niche_content_library_get_for_niche(string $niche_slug): array {
	$resolved = lf_niche_content_library_resolve_slug($niche_slug);
	$stored = get_option(LF_NICHE_CONTENT_LIBRARY_OPTION, []);
	$stored = is_array($stored) ? $stored : [];
	$defaults = lf_niche_content_library_defaults();
	$base = $defaults[$resolved]['about'] ?? $defaults['general']['about'];
	if (!empty($stored[$resolved]['about']) && is_array($stored[$resolved]['about'])) {
		$custom = $stored[$resolved]['about'];
		if (!empty($custom['process']) && is_array($custom['process'])) {
			$base['process'] = $custom['process'];
		}
		if (!empty($custom['faqs']) && is_array($custom['faqs'])) {
			$base['faqs'] = $custom['faqs'];
		}
	}
	return $base;
}

/**
 * @param array<string, array{about?: array{process?: mixed, faqs?: mixed}}> $libraries
 */
function lf_niche_content_library_save(array $libraries): bool {
	$sanitized = [];
	foreach ($libraries as $slug => $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$slug = sanitize_title((string) $slug);
		if ($slug === '') {
			continue;
		}
		$about = is_array($entry['about'] ?? null) ? $entry['about'] : [];
		$process = [];
		foreach ((array) ($about['process'] ?? []) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$title = sanitize_text_field((string) ($row['title'] ?? ''));
			$body = sanitize_textarea_field((string) ($row['body'] ?? ''));
			if ($title === '' && $body === '') {
				continue;
			}
			$process[] = ['title' => $title, 'body' => $body];
		}
		$faqs = [];
		foreach ((array) ($about['faqs'] ?? []) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$question = sanitize_text_field((string) ($row['question'] ?? ''));
			$answer = wp_kses_post((string) ($row['answer'] ?? ''));
			if ($question === '' && $answer === '') {
				continue;
			}
			$faqs[] = ['question' => $question, 'answer' => $answer];
		}
		$sanitized[$slug] = ['about' => ['process' => $process, 'faqs' => $faqs]];
	}
	return update_option(LF_NICHE_CONTENT_LIBRARY_OPTION, $sanitized, false);
}

/**
 * @return array{process_ids: list<int>, faq_ids: list<int>}
 */
function lf_niche_seed_about_content(string $niche_slug, array $vars): array {
	$vars = lf_niche_content_library_fill_vars($vars);
	$vars['niche'] = $niche_slug;
	$library = lf_niche_content_library_get_for_niche($niche_slug);
	$process_steps = [];
	foreach ($library['process'] as $row) {
		if (!is_array($row)) {
			continue;
		}
		$filled = lf_niche_content_library_fill_row($row, $vars);
		$title = trim((string) ($filled['title'] ?? ''));
		$body = trim((string) ($filled['body'] ?? ''));
		if ($title === '') {
			continue;
		}
		$process_steps[] = ['title' => $title, 'body' => $body];
	}
	$process_ids = lf_niche_upsert_process_steps($process_steps, LF_NICHE_ABOUT_PROCESS_GROUP, $vars);

	$faq_rows = [];
	foreach ($library['faqs'] as $row) {
		if (is_array($row)) {
			$faq_rows[] = $row;
		}
	}
	$faq_ids = lf_niche_upsert_context_faqs($faq_rows, LF_NICHE_ABOUT_FAQ_CONTEXT, $vars);

	return [
		'process_ids' => array_values(array_filter(array_map('absint', $process_ids))),
		'faq_ids' => array_values(array_filter(array_map('absint', $faq_ids))),
	];
}

/**
 * Create/update lf_faq posts for a named context (e.g. about_company).
 *
 * @param list<array{question:string,answer:string}> $faqs
 * @return list<int>
 */
function lf_niche_upsert_context_faqs(array $faqs, string $context, array $vars = []): array {
	$context = sanitize_key($context);
	if ($context === '' || !post_type_exists('lf_faq')) {
		return [];
	}
	$vars = lf_niche_content_library_fill_vars($vars);
	$faq_ids = [];
	foreach ($faqs as $row) {
		if (!is_array($row)) {
			continue;
		}
		$filled = lf_niche_content_library_fill_row($row, $vars);
		$question = sanitize_text_field((string) ($filled['question'] ?? ''));
		$answer = wp_kses_post((string) ($filled['answer'] ?? ''));
		if ($question === '') {
			continue;
		}
		$existing = get_posts([
			'post_type' => 'lf_faq',
			'post_status' => ['publish', 'draft', 'pending', 'private'],
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => '_lf_faq_context',
					'value' => $context,
				],
				[
					'key' => 'lf_faq_question',
					'value' => $question,
				],
			],
		]);
		$faq_id = !empty($existing[0]) ? (int) $existing[0] : 0;
		if ($faq_id <= 0) {
			$faq_id = (int) wp_insert_post([
				'post_type' => 'lf_faq',
				'post_status' => 'publish',
				'post_title' => $question,
				'post_content' => $answer,
			], true);
			if (is_wp_error($faq_id) || $faq_id <= 0) {
				continue;
			}
		} else {
			wp_update_post(['ID' => $faq_id, 'post_title' => $question]);
			if ($answer !== '') {
				$current = trim((string) get_post_field('post_content', $faq_id));
				if ($current === '') {
					wp_update_post(['ID' => $faq_id, 'post_content' => $answer]);
				}
			}
		}
		if (function_exists('update_field')) {
			update_field('lf_faq_question', $question, $faq_id);
			if ($answer !== '') {
				$current_acf = trim((string) get_field('lf_faq_answer', $faq_id));
				if ($current_acf === '') {
					update_field('lf_faq_answer', $answer, $faq_id);
				}
			}
		} else {
			update_post_meta($faq_id, 'lf_faq_question', $question);
			if ($answer !== '') {
				$current_meta = trim((string) get_post_meta($faq_id, 'lf_faq_answer', true));
				if ($current_meta === '') {
					update_post_meta($faq_id, 'lf_faq_answer', $answer);
				}
			}
		}
		update_post_meta($faq_id, '_lf_faq_context', $context);
		$faq_ids[] = $faq_id;
	}
	return array_values(array_filter(array_map('absint', $faq_ids)));
}

/**
 * @param list<array{title:string,body:string}> $steps
 * @return list<int>
 */
function lf_niche_upsert_process_steps(array $steps, string $group_slug, array $vars = []): array {
	if ($steps === [] || !function_exists('lf_ai_studio_upsert_process_steps')) {
		return [];
	}
	$vars = lf_niche_content_library_fill_vars($vars);
	$filled_steps = [];
	foreach ($steps as $row) {
		if (!is_array($row)) {
			continue;
		}
		$filled = lf_niche_content_library_fill_row($row, $vars);
		$title = trim((string) ($filled['title'] ?? ''));
		$body = trim((string) ($filled['body'] ?? ''));
		if ($title === '') {
			continue;
		}
		$filled_steps[] = ['title' => $title, 'body' => $body];
	}
	return lf_ai_studio_upsert_process_steps($filled_steps, $group_slug);
}

function lf_niche_ids_to_lines(array $ids): string {
	return implode("\n", array_map('strval', array_values(array_filter(array_map('absint', $ids)))));
}

/**
 * Build About page blueprint (order, overrides, seo) for wizard / manifest seeding.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $niche
 * @return array{order: list<string>, overrides: array<string, array<string, mixed>>, seo: array{title: string, description: string}}
 */
function lf_niche_build_about_page_blueprint(array $data, array $niche): array {
	$vars = function_exists('lf_wizard_template_vars') ? lf_wizard_template_vars($data) : [];
	$vars['niche'] = (string) ($niche['slug'] ?? $data['niche_slug'] ?? '');
	$vars = lf_niche_content_library_fill_vars($vars);
	$business = (string) ($vars['business'] ?? get_bloginfo('name'));
	$city = (string) ($vars['city'] ?? '');
	$city_line = (string) ($vars['city_line'] ?? '');
	$niche_slug = lf_niche_content_library_resolve_slug((string) ($niche['slug'] ?? $data['niche_slug'] ?? ''));
	$niche_name = trim((string) ($niche['name'] ?? ''));
	$is_foundation = $niche_slug === 'foundation-repair';

	$seeded = lf_niche_seed_about_content($niche_slug, $vars);
	$process_ids_line = lf_niche_ids_to_lines($seeded['process_ids']);
	$faq_ids_line = lf_niche_ids_to_lines($seeded['faq_ids']);

	$cta_headline = $business !== ''
		? ($is_foundation ? sprintf(__('Schedule a foundation inspection with %s', 'leadsforward-core'), $business) : sprintf(__('Get a free estimate from %s', 'leadsforward-core'), $business))
		: __('Get a free estimate', 'leadsforward-core');

	$hero_sub = $is_foundation
		? sprintf(
			__('Local foundation repair specialists%s — structural solutions backed by clear communication, engineered plans, and transferable warranties.', 'leadsforward-core'),
			$city_line
		)
		: sprintf(
			__('Local home-service professionals%s focused on quality, communication, and a clean job site.', 'leadsforward-core'),
			$city_line
		);

	$story_heading = $is_foundation ? __('Built on trust, not quick fixes', 'leadsforward-core') : __('Our story', 'leadsforward-core');
	$story_intro = $is_foundation
		? __('Foundation problems do not wait — and neither should your contractor.', 'leadsforward-core')
		: __('Built for homeowners who want clear pricing and reliable service.', 'leadsforward-core');
	$story_body = $is_foundation
		? sprintf(
			__('%1$s was founded to give homeowners an honest path through foundation repair%2$s. We combine structural evaluations, engineered solutions, and crews who respect your home. No scare tactics — just documented findings, clear pricing, and work you can stand behind when it is time to sell or refinance.', 'leadsforward-core'),
			$business,
			$city_line
		)
		: sprintf(
			__('We started %1$s to make home services simple and dependable. Our team shows up on time, keeps you informed, and treats your home with care from start to finish.', 'leadsforward-core'),
			$business
		);
	$story_checklist = $is_foundation
		? __("Licensed, insured structural crews\nEngineered repair plans before work starts\nTransferable warranty documentation\nClean, protected job sites")
		: __("Clear communication\nRespectful, clean crews\nWork backed by warranty");

	$benefits_items = $is_foundation
		? __("Engineered solutions || Piers, anchors, and waterproofing sized to your soil — not one-size-fits-all kits.\n")
			. __("Plain-language inspections || Photos, measurements, and options explained so you can make an informed decision.\n")
			. __("Local accountability || Project managers who answer the phone and stand behind the work{city_line}.\n")
			. __("Protected job sites || Landscaping, floors, and access paths treated with the same care we give our own homes.")
		: __("Licensed and insured professionals || Fully vetted crews with proper coverage and local reviews.\n")
			. __("Upfront pricing before work starts || Documented scopes so you always know the next step.\n")
			. __("Respectful, clean crews || Daily cleanup and clear communication throughout the project.");

	$benefits_items = lf_niche_content_library_fill_string($benefits_items, $vars);

	$team_heading = __('Meet the team behind your project', 'leadsforward-core');
	$team_intro = $is_foundation
		? __('Structural repair is a team sport — inspectors, engineers, and installers working from the same plan.', 'leadsforward-core')
		: __('Real people, accountable to your timeline and your property.', 'leadsforward-core');
	$team_body = $is_foundation
		? sprintf(
			__('At %1$s, your project is led by a dedicated manager who coordinates inspections, permits, and crew scheduling. Installers are trained on piering, wall stabilization, and waterproofing systems — not general handyman shortcuts. That specialization is why neighbors refer us when they see cracks, sticking doors, or basement moisture.', 'leadsforward-core'),
			$business
		)
		: sprintf(
			__('At %1$s, you work with a consistent point of contact from estimate to walkthrough. Our technicians are trained on the systems we install, follow documented standards on every job, and treat your home with the respect we expect in our own.', 'leadsforward-core'),
			$business
		);

	return [
		'order' => ['hero', 'content_image', 'benefits', 'image_content', 'process', 'faq_accordion', 'cta'],
		'overrides' => [
			'hero' => [
				'variant' => 'internal',
				'hero_headline' => sprintf(__('About %s', 'leadsforward-core'), $business),
				'hero_subheadline' => $hero_sub,
				'hero_eyebrow_text' => $is_foundation
					? __('Licensed • Insured • Engineered Repairs', 'leadsforward-core')
					: __('Licensed • Insured • Local', 'leadsforward-core'),
			],
			'content_image' => [
				'section_heading' => $story_heading,
				'section_intro' => $story_intro,
				'service_details_body' => $story_body,
				'service_details_media_mode' => 'image',
				'service_details_checklist' => $story_checklist,
				'content_media_show_checklist' => '1',
			],
			'benefits' => [
				'section_heading' => $is_foundation ? __('Why homeowners trust us with their foundation', 'leadsforward-core') : __('Why homeowners choose us', 'leadsforward-core'),
				'section_intro' => $is_foundation
					? __('Structural decisions deserve clarity, engineering, and a crew that shows up prepared.', 'leadsforward-core')
					: __('Clear communication, honest pricing, and consistent results.', 'leadsforward-core'),
				'benefits_items' => $benefits_items,
			],
			'image_content' => [
				'section_heading' => $team_heading,
				'section_intro' => $team_intro,
				'service_details_body' => $team_body,
				'service_details_media_mode' => 'image',
				'content_media_show_checklist' => '0',
			],
			'process' => [
				'section_heading' => $is_foundation ? __('How foundation repair works with us', 'leadsforward-core') : __('Our process', 'leadsforward-core'),
				'section_intro' => $is_foundation
					? __('A documented path from inspection to warranty — so you always know what happens next.', 'leadsforward-core')
					: __('Simple, clear steps from first call to completion.', 'leadsforward-core'),
				'process_selected_ids' => $process_ids_line,
			],
			'faq_accordion' => [
				'section_heading' => $is_foundation ? __('About our foundation repair team', 'leadsforward-core') : __('About us FAQs', 'leadsforward-core'),
				'section_intro' => $is_foundation
					? __('Honest answers about inspections, warranties, crews, and what to expect on your property.', 'leadsforward-core')
					: __('Quick answers about our team, process, and what to expect.', 'leadsforward-core'),
				'faq_selected_ids' => $faq_ids_line,
				'faq_max_items' => 8,
			],
			'cta' => [
				'cta_headline' => $cta_headline,
				'cta_subheadline' => $is_foundation
					? __('Request a free structural inspection and get a clear repair plan.', 'leadsforward-core')
					: __('Request a free estimate and get a clear next step.', 'leadsforward-core'),
			],
		],
		'seo' => [
			'title' => $business !== ''
				? sprintf(__('About %s%s', 'leadsforward-core'), $business, $city !== '' ? ' | ' . $city : '')
				: __('About Us', 'leadsforward-core'),
			'description' => $is_foundation
				? sprintf(__('Meet %1$s — local foundation repair specialists%2$s. Engineered solutions, clear inspections, and crews who protect your home.', 'leadsforward-core'), $business, $city_line)
				: sprintf(__('Learn about our team, process, and what makes us the trusted local choice%1$s.', 'leadsforward-core'), $city_line),
		],
	];
}
