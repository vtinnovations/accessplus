<?php

declare(strict_types=1);

/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * Authoritative source strings. Contao loads this file first as the fallback
 * for every language, then overlays the requested one on top — see
 * src/I18n/Text.php. Every key used anywhere in the bundle must exist here;
 * a translation file may lag behind without breaking anything.
 */

$lang = &$GLOBALS['TL_LANG']['accessplus'];

// ── Shared ─────────────────────────────────────────────────────────────────
$lang['common']['back'] = 'Back';
$lang['common']['invalid_token'] = 'Invalid security token. Please try again.';
$lang['common']['save'] = 'Save';

// ── Hub (tab bar + licence gate) ─────────────────────────────────────────
$lang['hub']['tab_dashboard'] = 'Dashboard';
$lang['hub']['tab_report'] = 'Report';
$lang['hub']['tab_pdf'] = 'PDF';
$lang['hub']['tab_alt'] = 'AI Alt Text';
$lang['hub']['tab_aria'] = 'ARIA Names';
$lang['hub']['tab_subtitle'] = 'AI Subtitles';
$lang['hub']['tab_simple'] = 'Plain Language';
$lang['hub']['tab_overlay'] = 'Overlay';
$lang['hub']['tab_statement'] = 'Statement';
$lang['hub']['tab_audit'] = 'History';
$lang['hub']['tab_settings'] = 'Settings';
$lang['hub']['tab_reports_link'] = 'Reports';
$lang['hub']['domain_label'] = 'Domain';
$lang['hub']['domain_switch'] = 'Switch';
$lang['hub']['license_required_title'] = 'Accessibility';
$lang['hub']['license_required_body'] = 'This site root has no valid licence. This bundle\'s features are disabled for it; Contao behaves exactly as it would without it.';
$lang['hub']['license_manage_hint'] = 'The licence is stored per site root: <em>%link%</em>.';
$lang['hub']['license_manage_link'] = 'Page structure → Edit site root → AccessPlus Licence management';
$lang['hub']['no_root_pages'] = 'No site root has been created yet.';
$lang['hub']['choose_domain_title'] = 'Accessibility';
$lang['hub']['choose_domain_body'] = 'Please choose a licensed domain:';
$lang['hub']['selected_marker'] = '← selected';

// ── Licence section (root page, "AccessPlus Licence management") ──────────
$lang['license']['no_permission'] = 'You do not have permission to manage the licence.';
$lang['license']['not_a_root'] = 'This page is not a site root. The licence applies per site root.';
$lang['license']['remove_not_confirmed'] = 'Removal not confirmed.';
$lang['license']['removed_confirm'] = 'Licence removed. Contao\'s default behaviour applies again for this site root.';
$lang['license']['activated_confirm'] = 'Licence verified and activated.';
$lang['license']['accepted_but_invalid'] = 'Licence accepted, but not currently valid: %reason%';
$lang['license']['check_failed'] = 'The licence check could not be completed. Please try again later.';

$lang['license']['state_label'] = 'Status';
$lang['license']['domains_label'] = 'Domain(s) of this site root';
$lang['license']['no_domain_configured'] = '— (no domain configured)';
$lang['license']['masked_key_label'] = 'Key';
$lang['license']['package_label'] = 'Package';
$lang['license']['valid_from_label'] = 'Valid from';
$lang['license']['valid_until_label'] = 'Valid until';
$lang['license']['lifetime_label'] = 'unlimited';
$lang['license']['last_checked_label'] = 'Last verified';

$lang['license']['no_domain_warning'] = 'No domain is configured for this site root. Please enter a domain above in the "Routing" section — the licence is bound to this exact hostname (www.example.com and example.com are different hosts).';
$lang['license']['key_label'] = 'Licence key';
$lang['license']['key_stored_suffix'] = ' (stored — leave the field empty to keep it)';
$lang['license']['key_placeholder_kept'] = 'leave unchanged';
$lang['license']['key_placeholder_enter'] = 'Enter licence key';
$lang['license']['key_help'] = 'The key is used server-side only and is never shown in full again — the status above shows its first and last four characters so you can tell which licence is stored. The check contacts only the vendor\'s licence server.';
$lang['license']['activate_btn'] = 'Verify and activate licence';
$lang['license']['refresh_btn'] = 'Update licence';
$lang['license']['remove_btn'] = 'Remove licence';
$lang['license']['confirm_remove_label'] = 'Confirm removal';

$lang['license']['state_active'] = 'active';
$lang['license']['state_expired'] = 'expired — the features are disabled for this site root';
$lang['license']['state_invalid'] = 'not valid: %reason%';
$lang['license']['state_unlicensed'] = 'no licence stored';

$lang['license']['reason_key_missing'] = 'Please enter a licence key.';
$lang['license']['reason_no_configured_domain'] = 'No domain is configured for this site root.';
$lang['license']['reason_domain_mismatch'] = 'The licence does not apply to this site root\'s domain.';
$lang['license']['reason_package_not_permitted'] = 'This licence package is not permitted for this product.';
$lang['license']['reason_expired'] = 'The licence has expired.';
$lang['license']['reason_not_yet_valid'] = 'The licence is not yet valid.';
$lang['license']['reason_refresh_required'] = 'The stored licence needs a one-time update.';
$lang['license']['reason_version_rejected'] = 'A newer licence state already exists.';
$lang['license']['reason_service_unavailable'] = 'The licence server is currently unreachable. The previous state is kept.';
$lang['license']['reason_service_denied'] = 'The licence server rejected the request. Please check the key and domain.';
$lang['license']['reason_signature_runtime_unavailable'] = 'This server is missing the required crypto extension (libsodium).';
$lang['license']['reason_scope_invalid'] = 'Invalid site root.';
$lang['license']['reason_default'] = 'The licence could not be confirmed.';

// ── Shared across several backend screens ─────────────────────────────────
$lang['common']['show_on_page'] = 'Show on page →';
$lang['common']['fix_now'] = 'Fix now ›';
$lang['common']['best_practice_badge'] = 'Best practice';
$lang['common']['best_practice_title'] = 'Recommendation – not a mandatory WCAG/BFSG violation';
$lang['common']['occurrences_pages'] = ' × %count% pages';
$lang['common']['scan_word'] = 'Scan';
$lang['common']['apply_btn'] = 'Apply';
$lang['common']['discard_btn'] = 'Discard';
$lang['common']['suggestion_discarded'] = 'Suggestion discarded.';
$lang['common']['egress_blocked_title'] = 'Enable external calls first';

// ── Severity / category enum labels (shared UI badges) ────────────────────
$lang['severity']['critical'] = 'Critical';
$lang['severity']['serious'] = 'Severe';
$lang['severity']['moderate'] = 'Moderate';
$lang['severity']['minor'] = 'Minor';
$lang['category']['done'] = 'Done';
$lang['category']['oneclick'] = 'One-click';
$lang['category']['manual'] = 'Manual only';

// ── Database check labels (report/dashboard headings, fixed catalogue) ────
$lang['check']['heading_hierarchy'] = 'Heading hierarchy';
$lang['check']['image_alt_missing'] = 'Images without alt text';
$lang['check']['page_language_missing'] = 'Page language (lang) missing';
$lang['check']['link_text_vague'] = 'Vague link text';
$lang['check']['form_field_no_label'] = 'Form fields without a label';

// ── Report ─────────────────────────────────────────────────────────────────
$lang['report']['title'] = 'Accessibility &ndash; Report';
$lang['report']['scan_btn'] = 'Scan now';
$lang['report']['scan_help'] = 'Reads the Contao content (database) — changes nothing.';
$lang['report']['scan_done_confirm'] = 'Scan done. New: %new% · Reopened: %reopened% · Resolved: %resolved% · Open: %open% · Score: %score%';
$lang['report']['status_updated'] = 'Status updated.';
$lang['report']['no_open_findings'] = 'No open findings. Either not scanned yet, or everything is done.';
$lang['report']['score_line'] = '<strong>Score:</strong> %score%/100 <span style="color:#999;">(%count% open findings · indicative value, not a conformance statement)</span>';
$lang['report']['confirmed_badge'] = '[confirmed]';
$lang['report']['confirm_btn'] = 'Confirm';
$lang['report']['reopen_btn'] = 'Reopen';
$lang['report']['ignore_btn'] = 'Ignore';

// ── PDF ────────────────────────────────────────────────────────────────────
$lang['pdf']['title'] = 'Accessibility &ndash; PDF documents';
$lang['pdf']['scan_btn'] = 'Check PDFs';
$lang['pdf']['scan_help'] = 'Reads linked PDFs (title, language, tags). Does NOT modify the files.';
$lang['pdf']['scan_done_confirm'] = 'PDF check done. Checked: %checked% · Issues: %issues% · Unclear (compressed): %unknown% · Unreadable: %unreadable%';
$lang['pdf']['no_issues'] = 'No open PDF issues. Either not checked yet, all fine, or status unclear (compressed PDFs).';
$lang['pdf']['open_issues_legend'] = 'Open PDF findings (%count%)';
$lang['pdf']['disclaimer'] = 'Note: PDFs are not repaired automatically – real accessibility (tags/structure) comes from a tagged export at the source. The tool shows which documents are affected.';

// ── AI Alt Text ────────────────────────────────────────────────────────────
$lang['alt']['title'] = 'Accessibility &ndash; AI Alt Text';
$lang['alt']['approved_confirm'] = 'Alt text applied (written to tl_files.meta).';
$lang['alt']['skipped_manual_error'] = 'Not applied: a manual alt text already exists (never overwritten).';
$lang['alt']['file_not_found_error'] = 'Not applied: file not found.';
$lang['alt']['limit_label'] = 'Count:';
$lang['alt']['generate_btn'] = 'Generate suggestions';
$lang['alt']['egress_blocked_note'] = '"No external calls" is active – generation is locked. Enable it in Settings.';
$lang['alt']['help'] = 'Generates alt-text suggestions only for images WITHOUT an existing alt text. Nothing is published automatically.';
$lang['alt']['no_pending'] = 'No open suggestions.';
$lang['alt']['pending_legend'] = 'Open suggestions (%count%)';
$lang['alt']['decorative_badge'] = 'decorative → empty alt';
$lang['alt']['alt_placeholder'] = 'Alt text – leave empty = decorative (empty) alt';

// ── ARIA Names ─────────────────────────────────────────────────────────────
$lang['aria']['title'] = 'Accessibility &ndash; ARIA Names';
$lang['aria']['intro'] = 'Elements without an accessible name (links/buttons/iframes without text) are collected during the <strong>frontend scan</strong>. Review the suggested name, adjust it and apply it &mdash; it is then set at runtime as <code>aria-label</code>, but only where the element still has no name (never overwritten). The clean fix belongs in the template long-term.';
$lang['aria']['name_required_error'] = 'Please enter a name.';
$lang['aria']['applied_confirm'] = 'Name applied and active: &bdquo;%name%&ldquo;.';
$lang['aria']['ai_blocked_error'] = 'External calls are disabled. Enable them in Settings first.';
$lang['aria']['ai_no_suggestion_error'] = 'AI returned no suggestion (or the provider is unreachable).';
$lang['aria']['ai_confirm'] = 'AI suggestion applied: &bdquo;%name%&ldquo;. Please review and apply.';
$lang['aria']['rule_link_name'] = 'Link without a recognizable name';
$lang['aria']['rule_button_name'] = 'Button without a name';
$lang['aria']['rule_frame_title'] = 'iframe without a title';
$lang['aria']['rule_input_field_name'] = 'Input field without a name';
$lang['aria']['rule_input_button_name'] = 'Button input without a name';
$lang['aria']['rule_command_name'] = 'Control without a name';
$lang['aria']['rule_toggle_field_name'] = 'Toggle without a name';
$lang['aria']['rule_image_alt'] = 'Image button without alt text';
$lang['aria']['open_legend'] = 'Open';
$lang['aria']['open_empty'] = 'No open elements. Run a full scan in the dashboard.';
$lang['aria']['active_legend'] = 'Active';
$lang['aria']['selector_label'] = 'Selector:';
$lang['aria']['name_placeholder'] = 'Accessible name (aria-label)';
$lang['aria']['ai_suggest_btn'] = 'AI suggestion';
$lang['aria']['deactivate_btn'] = 'Deactivate';

// ── AI Subtitles ───────────────────────────────────────────────────────────
$lang['subtitle']['title'] = 'Accessibility &ndash; AI Subtitles';
$lang['subtitle']['help'] = 'Generates WebVTT subtitles from video/audio (Whisper). <strong>AI draft</strong> &ndash; must be reviewed and approved before it is saved as a file. Limit 25&nbsp;MB per file; OpenAI / compatible provider only.';
$lang['subtitle']['egress_blocked'] = '"No external calls" is active – transcription is locked. Enable it in Settings.';
$lang['subtitle']['generated_confirm'] = 'Subtitle draft generated (%lang%, %ms% ms). Please review and approve.';
$lang['subtitle']['generate_error_prefix'] = 'Transcription failed: %message%';
$lang['subtitle']['savedraft_confirm'] = 'Draft saved.';
$lang['subtitle']['approve_confirm'] = 'Subtitles approved and saved: %path%';
$lang['subtitle']['approve_error'] = 'Approval failed (the file could not be written).';
$lang['subtitle']['reject_confirm'] = 'Draft discarded.';
$lang['subtitle']['no_media'] = 'No audio/video files found in the file manager.';
$lang['subtitle']['media_legend'] = 'Media (%count%)';
$lang['subtitle']['used_badge'] = 'used';
$lang['subtitle']['too_large_error'] = 'File larger than 25 MB – please shorten/compress it, or process it via the console.';
$lang['subtitle']['lang_label'] = 'Language:';
$lang['subtitle']['regenerate_btn'] = 'Regenerate';
$lang['subtitle']['generate_btn'] = 'Generate subtitles';
$lang['subtitle']['status_applied'] = 'approved';
$lang['subtitle']['status_rejected'] = 'discarded';
$lang['subtitle']['status_draft'] = 'Draft, unreviewed';
$lang['subtitle']['status_label'] = 'Status:';
$lang['subtitle']['file_label'] = 'File:';
$lang['subtitle']['savedraft_btn'] = 'Save draft';
$lang['subtitle']['approve_btn'] = 'Approve & save';
$lang['subtitle']['track_help'] = 'Then embed the subtitle in the video element as <code>&lt;track&gt;</code> (Contao 5.4+ / zoglo). Please proofread the Whisper timestamps and text.';

// ── Plain Language ─────────────────────────────────────────────────────────
$lang['simple']['title'] = 'Accessibility &ndash; Plain / Easy Language';
$lang['simple']['disclaimer'] = 'AI creates <strong>drafts</strong> in simplified language. <strong>Not certified Easy Language</strong> &ndash; human review is required, nothing is published automatically.';
$lang['simple']['register_einfach'] = 'Plain language';
$lang['simple']['register_leicht'] = 'Easy language';
$lang['simple']['settings_legend'] = 'Settings';
$lang['simple']['settings_enabled_label'] = 'Feature active (show the switch in the frontend)';
$lang['simple']['settings_registers_label'] = 'Offered registers:';
$lang['simple']['settings_switch_label'] = 'Switch:';
$lang['simple']['settings_switch_overlay'] = 'in the comfort overlay';
$lang['simple']['settings_switch_button'] = 'floating button';
$lang['simple']['settings_switch_nav'] = 'nav-link module';
$lang['simple']['settings_save_btn'] = 'Save settings';
$lang['simple']['settings_saved_root'] = 'Settings saved (activation for this domain).';
$lang['simple']['settings_saved'] = 'Settings saved.';
$lang['simple']['page_select_legend'] = 'Choose page';
$lang['simple']['scope_label'] = 'Area:';
$lang['simple']['page_placeholder'] = '– please choose –';
$lang['simple']['page_all_option'] = '★ All content (entire site)';
$lang['simple']['register_label'] = 'Register:';
$lang['simple']['lang_label'] = 'Language:';
$lang['simple']['load_btn'] = 'Load';
$lang['simple']['no_items'] = 'No simplifiable text elements found.';
$lang['simple']['scope_all_hint'] = 'Scope: <strong>entire site</strong> &ndash; all content elements (including module/theme-embedded cards).';
$lang['simple']['generate_btn'] = 'Generate drafts (%count% elements)';
$lang['simple']['approveall_btn'] = 'Approve all drafts';
$lang['simple']['savedraft_confirm'] = 'Draft saved.';
$lang['simple']['approve_confirm'] = 'Draft approved (appears in the frontend when the switch is active).';
$lang['simple']['reject_confirm'] = 'Draft discarded.';
$lang['simple']['lock_confirm'] = 'Section locked &ndash; will no longer be regenerated and stays live.';
$lang['simple']['unlock_confirm'] = 'Lock removed.';
$lang['simple']['approveall_confirm'] = '%count% drafts approved.';
$lang['simple']['status_approved'] = 'approved';
$lang['simple']['status_rejected'] = 'discarded';
$lang['simple']['status_pending'] = 'Draft, unreviewed';
$lang['simple']['status_none'] = 'no draft';
$lang['simple']['locked_badge'] = '🔒 locked';
$lang['simple']['element_label'] = 'Element #%id%';
$lang['simple']['original_label'] = 'Original:';
$lang['simple']['draft_label'] = 'Draft:';
$lang['simple']['no_draft_hint'] = 'No draft yet – use “Generate drafts” above.';
$lang['simple']['unlock_btn'] = '🔓 Unlock';
$lang['simple']['savedraft_btn'] = 'Save';
$lang['simple']['approve_btn'] = 'Approve';
$lang['simple']['lock_btn'] = '🔒 Lock';
$lang['simple']['lock_btn_title'] = 'Approve + protect against regeneration';

// ── Comfort Overlay ─────────────────────────────────────────────────────────
$lang['overlay']['title'] = 'Accessibility &ndash; Comfort Overlay';
$lang['overlay']['disclaimer'] = 'Comfort/display options for the frontend. <strong>Not a substitute for accessible content</strong> and must not be marketed as an &bdquo;accessibility solution&ldquo;.';
$lang['overlay']['activation_legend'] = 'Activation';
$lang['overlay']['activation_domain_hint'] = 'This activation applies only to the <strong>selected domain</strong>. Design and features below apply install-wide for all domains.';
$lang['overlay']['activation_toggle'] = 'Show overlay in the frontend';
$lang['overlay']['design_legend'] = 'Design';
$lang['overlay']['color_label'] = 'Button colour';
$lang['overlay']['position_label'] = 'Button position';
$lang['overlay']['position_bottomright'] = 'Bottom right';
$lang['overlay']['position_bottomleft'] = 'Bottom left';
$lang['overlay']['position_middleright'] = 'Middle right';
$lang['overlay']['position_middleleft'] = 'Middle left';
$lang['overlay']['position_topright'] = 'Top right';
$lang['overlay']['position_topleft'] = 'Top left';
$lang['overlay']['saved_root'] = 'Overlay settings saved (activation for this domain).';
$lang['overlay']['saved'] = 'Overlay settings saved.';
$lang['overlay_group']['profiles'] = 'Modes';
$lang['overlay_group']['reading'] = 'Reading';
$lang['overlay_group']['orientation'] = 'Orientation';
$lang['overlay_group']['colors'] = 'Colours';
$lang['overlay_feature']['profile_epilepsy'] = 'Epilepsy-safe mode';
$lang['overlay_feature']['profile_lowvision'] = 'Low-vision mode';
$lang['overlay_feature']['profile_adhd'] = 'ADHD-friendly mode';
$lang['overlay_feature']['contentscale'] = 'Content scaling';
$lang['overlay_feature']['fontsize'] = 'Font size';
$lang['overlay_feature']['lineheight'] = 'Line height';
$lang['overlay_feature']['letterspacing'] = 'Letter spacing';
$lang['overlay_feature']['readablefont'] = 'Readable font';
$lang['overlay_feature']['dyslexiafont'] = 'Dyslexia-friendly font';
$lang['overlay_feature']['highlighttitles'] = 'Highlight titles';
$lang['overlay_feature']['highlightlinks'] = 'Highlight links';
$lang['overlay_feature']['bionic'] = 'Cognitive reading';
$lang['overlay_feature']['linknav'] = 'Link navigator';
$lang['overlay_feature']['darkcontrast'] = 'Dark contrast';
$lang['overlay_feature']['lightcontrast'] = 'Light contrast';
$lang['overlay_feature']['highcontrast'] = 'High contrast';
$lang['overlay_feature']['monochrome'] = 'Monochrome';
$lang['overlay_feature']['stopanim'] = 'Stop animations';
$lang['overlay_feature']['mutesound'] = 'Mute sounds';
$lang['overlay_feature']['bigcursor'] = 'Big cursor';
$lang['overlay_feature']['hideimages'] = 'Hide images';
$lang['overlay_feature']['readingguide'] = 'Reading guide (ruler)';
$lang['overlay_feature']['tts'] = 'Read aloud (text to speech)';
$lang['overlay_feature']['focushighlight'] = 'Highlight focus';
$lang['overlay_feature']['hoverhighlight'] = 'Highlight hover';
$lang['overlay_feature']['textalign'] = 'Text alignment';
$lang['overlay_feature']['color_text'] = 'Adjust text colour';
$lang['overlay_feature']['color_title'] = 'Adjust title colour';
$lang['overlay_feature']['color_link'] = 'Adjust link colour';
$lang['overlay_feature']['color_bg'] = 'Adjust background colour';

// ── Statement (editor) ─────────────────────────────────────────────────────
$lang['statement']['editor_title'] = 'Accessibility &ndash; Statement &amp; Feedback Channel';
$lang['statement']['legal_disclaimer'] = 'Note: this is <strong>not legal advice</strong>. The tool assembles the mandatory information; the operator is responsible for its legal correctness.';
$lang['statement']['domain_scope_hint'] = 'This statement applies to the <strong>selected domain</strong>. Each domain has its own statement; without its own entries, the install-wide template (&bdquo;All domains&ldquo;) applies.';
$lang['statement']['suggested_status'] = 'Status suggestion from current findings: <strong>%status%</strong> (recommendation only).';
$lang['statement']['section_details'] = 'Details';
$lang['statement']['org_label'] = 'Operator / organisation';
$lang['statement']['url_label'] = 'Website (URL)';
$lang['statement']['status_label'] = 'State of compliance';
$lang['statement']['nonaccessible_label'] = 'Non-accessible content (free text)';
$lang['statement']['section_contact'] = 'Contact / feedback channel';
$lang['statement']['contact_name_label'] = 'Contact name';
$lang['statement']['contact_email_label'] = 'Contact email';
$lang['statement']['contact_phone_label'] = 'Contact phone';
$lang['statement']['feedback_recipient_label'] = 'Email for reports (feedback channel)';
$lang['statement']['section_creation'] = 'Creation &amp; enforcement';
$lang['statement']['prepared_label'] = 'Prepared on (date)';
$lang['statement']['method_label'] = 'Method';
$lang['statement']['enforcement_label'] = 'Enforcement procedure / arbitration body (free text)';
$lang['statement']['saved_root'] = 'Accessibility statement saved for this domain.';
$lang['statement']['saved'] = 'Accessibility statement saved.';
$lang['statement']['invalid_recipient_error'] = 'Feedback channel email is invalid &mdash; not saved.';
$lang['statement']['status_conformant'] = 'fully conformant';
$lang['statement']['status_partial'] = 'partially conformant';
$lang['statement']['status_nonconformant'] = 'not conformant';
$lang['statement']['method_self'] = 'Self-assessment';
$lang['statement']['method_external'] = 'External review';
$lang['statement']['embed_hint'] = 'Embed in the frontend: place the module type <strong>Accessibility statement</strong> (shows this information) and <strong>Feedback channel</strong> (report form) on a page.';

// ── History / Undo ─────────────────────────────────────────────────────────
$lang['audit']['title'] = 'Accessibility &ndash; History / Undo';
$lang['audit']['undo_confirm'] = 'Change undone; suggestion reopened.';
$lang['audit']['undo_error'] = 'Undo not possible (changed meanwhile, or already undone).';
$lang['audit']['no_entries'] = 'No changes applied yet.';
$lang['audit']['col_time'] = 'Time';
$lang['audit']['col_action'] = 'Action';
$lang['audit']['col_target'] = 'Target';
$lang['audit']['col_before_after'] = 'Before → After';
$lang['audit']['col_user'] = 'User';
$lang['audit']['before_absent'] = '∅ (none before)';
$lang['audit']['undone_label'] = 'undone';
$lang['audit']['undo_btn'] = 'Undo';

// ── Frontend scan (standalone screen; superseded by the dashboard full scan) ─
$lang['frontend_scan']['title'] = 'Accessibility &ndash; Frontend Analysis (axe-core)';
$lang['frontend_scan']['no_pages'] = 'No published, public pages found.';
$lang['frontend_scan']['help'] = 'Scans <strong>%count%</strong> published page(s) directly in the browser (axe-core). <strong>Only legally mandatory rules</strong> (WCAG 2.x %target% = BITV 2.0 / BFSG) &mdash; no pure best-practice recommendations. Findings land as a <em>Frontend</em> source in the same report/dashboard.';
$lang['frontend_scan']['capped_note'] = ' <strong>Note:</strong> capped at %cap% pages.';
$lang['frontend_scan']['session_hint'] = 'Runs in your backend session; protected pages are skipped. Keep the backend tab open until &bdquo;Done&ldquo; appears.';
$lang['frontend_scan']['start_btn'] = 'Start frontend scan';

// ── Feedback inbox gate (backend) ──────────────────────────────────────────
$lang['feedback']['inbox_readonly_notice'] = 'No valid licence for a site root: reports are read-only. The licence is stored under Page structure → Edit site root → AccessPlus Licence management.';

// ── Highlight preview ───────────────────────────────────────────────────────
$lang['highlight']['no_preview'] = 'No preview available.';
$lang['highlight']['element_label'] = 'Element:';
$lang['highlight']['page_title'] = 'Accessibility &ndash; Preview';
$lang['highlight']['cross_origin_note'] = '(Cross-origin – cannot be marked)';
$lang['highlight']['element_not_found_note'] = '(Element not found – page may have changed)';
$lang['highlight']['marked_note'] = '✓ marked';
$lang['highlight']['preview_not_possible_note'] = '(Preview not possible)';

// ── Settings ────────────────────────────────────────────────────────────────
$lang['settings']['title'] = 'Accessibility &ndash; Settings';
$lang['settings']['provider_legend'] = 'AI provider';
$lang['settings']['provider_label'] = 'Provider';
$lang['settings']['provider_openai'] = 'OpenAI';
$lang['settings']['provider_compatible'] = 'OpenAI-compatible (own base URL)';
$lang['settings']['base_url_label'] = 'Base URL (optional, required for &bdquo;compatible&ldquo;)';
$lang['settings']['model_label'] = 'Model (optional, empty = default; a vision model for alt text)';
$lang['settings']['api_key_label'] = 'API key (%state%)';
$lang['settings']['api_key_state_set'] = 'set';
$lang['settings']['api_key_state_empty'] = 'empty';
$lang['settings']['api_key_placeholder_kept'] = 'leave unchanged';
$lang['settings']['api_key_placeholder_enter'] = 'Enter key';
$lang['settings']['api_key_help'] = 'Stored encrypted and never shown again. Leave the field empty to keep it unchanged.';
$lang['settings']['clear_key_label'] = 'Remove key';
$lang['settings']['privacy_legend'] = 'Privacy';
$lang['settings']['no_external_calls_label'] = 'No external calls';
$lang['settings']['no_external_calls_desc'] = ' &mdash; blocks every AI/egress call. Active by default. Disable it to use the AI features.';
$lang['settings']['no_external_calls_help'] = 'While active, no content/AI data leaves the server. The connection test below is then locked. This does not affect the licence check (only the vendor\'s licence server) &mdash; it is a prerequisite of the product and is managed per site root under &bdquo;AccessPlus Licence management&ldquo;.';
$lang['settings']['accessibility_legend'] = 'Accessibility';
$lang['settings']['wcag_target_label'] = 'WCAG target level';
$lang['settings']['wcag_aa_recommended'] = 'AA (recommended)';
$lang['settings']['languages_label'] = 'Active languages (comma-separated, e.g. &bdquo;de, en&ldquo;)';
$lang['settings']['monitor_legend'] = 'Monitoring';
$lang['settings']['monitor_on_save_label'] = 'Automatically re-check after saving';
$lang['settings']['monitor_on_save_desc'] = ' — re-scans the database checks after content changes (throttled). No external call.';
$lang['settings']['monitor_interval_label'] = 'Throttle interval (seconds, min. 30)';
$lang['settings']['monitor_interval_help'] = 'Prevents continuous scans during frequent saves. Also applies to the Contao cron.';
$lang['settings']['test_btn'] = 'Test connection';
$lang['settings']['key_cleared_confirm'] = 'API key removed.';
$lang['settings']['key_saved_confirm'] = 'API key encrypted and saved.';
$lang['settings']['saved_confirm'] = 'Settings saved.';

// ── Feedback (frontend form + backend wildcard) ────────────────────────────
$lang['feedback_form']['heading'] = 'Report a barrier';
$lang['feedback_form']['thank_you'] = 'Thank you &mdash; your report has been submitted.';
$lang['feedback_form']['honeypot_label'] = 'Website (please leave empty)';
$lang['feedback_form']['name_label'] = 'Name (optional)';
$lang['feedback_form']['email_label'] = 'Email (optional, for follow-up questions)';
$lang['feedback_form']['url_label'] = 'Affected page (URL, optional)';
$lang['feedback_form']['message_label'] = 'Your report';
$lang['feedback_form']['submit_btn'] = 'Send report';
$lang['feedback_form']['wildcard'] = '### Barrier feedback channel ###';
$lang['feedback']['error_invalid_token'] = 'Security token invalid. Please send again.';
$lang['feedback']['error_message_required'] = 'Please enter a message (max. 5000 characters).';
$lang['feedback']['error_email_invalid'] = 'Email address invalid.';
$lang['feedback']['error_url_invalid'] = 'Page URL must start with http(s)://.';
$lang['feedback']['error_name_too_long'] = 'Name too long.';

// ── Plain/Easy language switch (frontend) ──────────────────────────────────
$lang['simple_switch']['wildcard'] = '### Plain/Easy language switch ###';
$lang['simple_switch']['nav_label'] = 'Choose language';
$lang['simple']['register_none'] = 'Everyday language';

// ── Accessibility statement (frontend, public) ─────────────────────────────
$lang['statement_public']['wildcard'] = '### Accessibility statement ###';
$lang['statement_public']['heading'] = 'Accessibility statement';
$lang['statement_public']['intro'] = '%org% is committed to making its website%url_html% accessible in line with the requirements for accessibility.';
$lang['statement_public']['status_heading'] = 'State of compliance';
$lang['statement_public']['status_text'] = 'This website is <strong>%status%</strong> with the applicable requirements.';
$lang['statement_public']['nonaccessible_heading'] = 'Non-accessible content';
$lang['statement_public']['creation_heading'] = 'Creation of this statement';
$lang['statement_public']['creation_text'] = 'This statement was prepared on %date% based on a %method%.';
$lang['statement_public']['method_self'] = 'self-assessment';
$lang['statement_public']['method_external'] = 'external review';
$lang['statement_public']['feedback_heading'] = 'Feedback and contact details';
$lang['statement_public']['feedback_intro'] = 'Report barriers or request information:';
$lang['statement_public']['email_label'] = 'Email:';
$lang['statement_public']['phone_label'] = 'Phone:';
$lang['statement_public']['enforcement_heading'] = 'Enforcement procedure';

// ── CLI commands ────────────────────────────────────────────────────────────
$lang['command']['no_license_error'] = 'No valid licence for a site root.';
$lang['command']['lang_option'] = 'Language code (ISO 639-1)';
$lang['command']['review_suffix'] = ' Please review and approve in the backend.';
$lang['command']['alt']['description'] = 'Generate AI alt-text suggestions for images without alt text (review).';
$lang['command']['alt']['limit_option'] = 'Maximum number of suggestions';
$lang['command']['simple']['description'] = 'Generate AI drafts in plain/easy language for all pages (review).';
$lang['command']['simple']['register_option'] = 'einfach|leicht';
$lang['command']['simple']['limit_option'] = 'Maximum number of text snippets';
$lang['command']['subtitles']['description'] = 'Generate AI subtitle drafts (VTT) for media without subtitles (review).';
$lang['command']['subtitles']['limit_option'] = 'Maximum number of files';
$lang['command']['subtitles']['ok_tag'] = 'OK';
$lang['command']['subtitles']['error_tag'] = 'ERROR';
$lang['command']['subtitles']['skipped_tag'] = 'SKIPPED';
$lang['command']['subtitles']['fatal_abort'] = 'Unrecoverable error — aborting.';
$lang['command']['subtitles']['done_confirm'] = 'Subtitle drafts generated: %done% · Errors: %errors%.';
$lang['command']['monitor']['description'] = 'Re-check accessibility and show the change since the previous run.';
$lang['command']['monitor']['score_line'] = 'Score: %score% · Open: %open%';
$lang['command']['monitor']['since_last'] = 'Since the last run: +%new% new · -%resolved% resolved · Score %delta%';
$lang['command']['monitor']['first_run'] = 'First run — nothing to compare.';
$lang['command']['pdf']['description'] = 'Check linked PDFs for title, language and tags.';
$lang['command']['pdf']['done_confirm'] = 'Checked: %checked% · Issues: %issues% · Unclear: %unknown% · Unreadable: %unreadable%';
$lang['command']['scan']['description'] = 'Run the accessibility checks over the content.';
$lang['command']['scan']['done_confirm'] = 'Scan done. New: %new% · Reopened: %reopened% · Resolved: %resolved% · Total open: %open% · Score: %score%';

// ── AI provider / connection errors (surfaced via Settings, ARIA, Subtitles) ─
$lang['ai']['egress_blocked_error'] = 'External calls are disabled ("No external calls"). Please enable them in Settings first.';
$lang['ai']['no_api_key_error'] = 'No API key stored. Please set one in Settings.';
$lang['ai']['invalid_base_url_error'] = 'Invalid base URL. Only http(s) with a hostname is allowed.';
$lang['ai']['test_not_run'] = 'Test not run: "No external calls" is active. Enable it first to test.';
$lang['ai']['config_error_prefix'] = 'Configuration error: %message%';
$lang['ai']['connection_failed_prefix'] = 'Connection failed: %message%';
$lang['ai']['connection_ok'] = 'Connection OK (%provider% / %model%, %ms% ms).';
$lang['ai']['unsupported_image_format'] = 'Unsupported image format: %extension%';
$lang['ai']['file_not_found'] = 'File not found.';
$lang['ai']['path_outside_allowed_dir'] = 'Path outside the allowed directory.';
$lang['ai']['file_empty_or_unreadable'] = 'File is empty or unreadable.';
$lang['ai']['image_too_large'] = 'Image too large (%size% KB, max %max% KB).';
$lang['ai']['image_unreadable'] = 'Image could not be read.';
$lang['ai']['whisper_unsupported_provider'] = 'Subtitles need a Whisper-compatible provider (OpenAI or &bdquo;compatible&ldquo;). The current provider does not support audio transcription.';

// ── Alt-text / plain-language generation summaries (Alt / Plain Language tabs) ─
$lang['common']['aborted_prefix'] = 'Aborted: %message%';
$lang['alt']['generation_blocked_message'] = 'Generation locked: "No external calls" is active.';
$lang['alt']['no_used_images_message'] = 'No used images without alt text found.';
$lang['alt']['generated_summary'] = 'Generated: %generated% · Re-surfaced: %skipped% · Errors: %errors%';
$lang['simple']['generation_blocked_message'] = 'Generation locked: "No external calls" is active.';
$lang['simple']['generated_summary'] = 'Generated: %generated% · Skipped: %skipped% · Errors: %errors%';

// ── Subtitle generation errors ─────────────────────────────────────────────
$lang['subtitle']['media_not_found_error'] = 'Media file not found in the file manager.';
$lang['subtitle']['invalid_file_path_error'] = 'Invalid file path.';
$lang['subtitle']['media_file_unreadable_error'] = 'Media file not found or unreadable: %file%';
$lang['subtitle']['file_too_large_for_transcription_error'] = 'File too large for transcription (%size% MB > 25 MB limit). Please shorten or compress it.';

// ── Feedback notification email ────────────────────────────────────────────
$lang['feedback']['email_subject'] = 'New accessibility report';
$lang['feedback']['email_no_value'] = '(not provided)';
$lang['feedback']['email_body'] = "A new report has come in through the barrier feedback channel.\n\nName: %name%\nEmail: %email%\nPage: %url%\n\nMessage:\n%message%\n";

// ── Dashboard ──────────────────────────────────────────────────────────────
$lang['dashboard']['title'] = 'Accessibility &ndash; Dashboard';
$lang['dashboard']['fullscan_legend'] = 'Full scan';
$lang['dashboard']['fullscan_help'] = 'Checks the <strong>database content</strong> first, then the <strong>rendered pages</strong> (axe-core: contrast, DOM, landmarks). Changes nothing. Keep the backend tab open until &bdquo;Done&ldquo; appears.';
$lang['dashboard']['fullscan_start_btn'] = 'Start full scan';
$lang['dashboard']['db_only_btn'] = 'Database analysis only (no frontend)';
$lang['dashboard']['multi_domain_hint'] = 'This installation serves several domains. Frontend features (comfort overlay, plain language, subtitles) and the <strong>frontend scan</strong> apply per domain &ndash; run the scan on each one. The <strong>database analysis</strong> is install-wide and independent of that.';
$lang['dashboard']['multi_domain_scanned_here'] = 'scanned here';
$lang['dashboard']['multi_domain_open_backend'] = 'open backend there ›';
$lang['dashboard']['multi_domain_pages_count'] = '%count% page(s)';
$lang['dashboard']['db_analysis_done'] = 'Database analysis done. Score: %score% · One-click: %oneclick% · Manual: %manual% · Done: %done%';
$lang['dashboard']['reset_frontend_confirm'] = '%count% frontend findings reset. The next full scan will re-check the pages.';
$lang['dashboard']['score_optimized'] = '%score%% optimized';
$lang['dashboard']['score_disclaimer'] = 'Indicative value based on the issues found &ndash; <strong>not</strong> a conformance statement.';
$lang['dashboard']['live_hint'] = ' (live · no run stored yet)';
$lang['dashboard']['stat_open'] = 'Open findings';
$lang['dashboard']['stat_critical'] = 'Critical';
$lang['dashboard']['stat_serious'] = 'Serious';
$lang['dashboard']['stat_oneclick'] = 'One-click fixable';
$lang['dashboard']['stat_manual'] = 'Manual only';
$lang['dashboard']['stat_done'] = 'Done';
$lang['dashboard']['stat_frontend'] = 'Frontend (axe)';
$lang['dashboard']['stat_image_alt'] = 'Images without alt';
$lang['dashboard']['trend_since'] = ' (since last run: +%new% new, &minus;%resolved% resolved)';
$lang['dashboard']['category_hint'] = 'Automatically fixable &mdash; generate &amp; apply in the <strong>AI Alt Text</strong> module.';
$lang['dashboard']['score_ring_aria'] = 'Accessibility score %score% of 100';
$lang['dashboard']['score_ring_caption'] = 'A11Y SCORE';
$lang['dashboard']['frontend_section_legend'] = 'Frontend analysis (axe) &ndash; %count%';
$lang['dashboard']['frontend_section_empty'] = 'No open frontend findings. (Start a full scan to check rendered pages.)';
$lang['dashboard']['frontend_section_help'] = 'Rendered pages (contrast, DOM, landmarks). Triage &amp; details in the <a href="contao?do=accessplus&amp;tab=report">Report</a>. Reset and rescan on obvious false positives (e.g. from animations).';
$lang['dashboard']['reset_frontend_btn'] = 'Reset frontend findings';
$lang['dashboard']['column_nothing'] = 'Nothing.';
$lang['dashboard']['column_more'] = '&hellip; %count% more (truncated).';
$lang['dashboard']['column_more_report'] = '&hellip; %count% more (truncated) &ndash; see report.';
$lang['dashboard']['run_history_title'] = 'Run history';
$lang['dashboard']['run_history_time'] = 'Time';
$lang['dashboard']['run_history_score'] = 'Score';
