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
 * German translation. Contao loads contao/languages/en/accessplus.php first as
 * the fallback, then overlays this file on top — see src/I18n/Text.php. A key
 * missing here still resolves via the English fallback, so this file may lag
 * behind the authoritative English set without breaking anything.
 */

$lang = &$GLOBALS['TL_LANG']['accessplus'];

// ── Shared ─────────────────────────────────────────────────────────────────
$lang['common']['back'] = 'Zurück';
$lang['common']['invalid_token'] = 'Ungültiges Sicherheits-Token. Bitte erneut versuchen.';
$lang['common']['save'] = 'Speichern';

// ── Hub (Tab-Leiste + Lizenz-Sperre) ─────────────────────────────────────
$lang['hub']['tab_dashboard'] = 'Dashboard';
$lang['hub']['tab_report'] = 'Bericht';
$lang['hub']['tab_pdf'] = 'PDF';
$lang['hub']['tab_alt'] = 'KI-Alt-Texte';
$lang['hub']['tab_aria'] = 'ARIA-Namen';
$lang['hub']['tab_subtitle'] = 'KI-Untertitel';
$lang['hub']['tab_simple'] = 'Einfache Sprache';
$lang['hub']['tab_overlay'] = 'Overlay';
$lang['hub']['tab_statement'] = 'Erklärung';
$lang['hub']['tab_audit'] = 'Verlauf';
$lang['hub']['tab_settings'] = 'Einstellungen';
$lang['hub']['tab_reports_link'] = 'Meldungen';
$lang['hub']['domain_label'] = 'Domain';
$lang['hub']['domain_switch'] = 'Wechseln';
$lang['hub']['license_required_title'] = 'Barrierefreiheit';
$lang['hub']['license_required_body'] = 'Für diesen Startpunkt liegt keine gültige Lizenz vor. Die Funktionen dieses Bundles sind für ihn deaktiviert; Contao verhält sich unverändert.';
$lang['hub']['license_manage_hint'] = 'Die Lizenz wird je Startpunkt hinterlegt: <em>%link%</em>.';
$lang['hub']['license_manage_link'] = 'Seitenstruktur → Startpunkt bearbeiten → AccessPlus Licence management';
$lang['hub']['no_root_pages'] = 'Es ist noch kein Startpunkt angelegt.';
$lang['hub']['choose_domain_title'] = 'Barrierefreiheit';
$lang['hub']['choose_domain_body'] = 'Bitte eine lizenzierte Domain wählen:';
$lang['hub']['selected_marker'] = '← ausgewählt';

// ── Lizenz-Abschnitt (Startpunkt, "AccessPlus Licence management") ──────
$lang['license']['no_permission'] = 'Keine Berechtigung für die Lizenzverwaltung.';
$lang['license']['not_a_root'] = 'Diese Seite ist kein Startpunkt. Die Lizenz gilt je Startpunkt.';
$lang['license']['remove_not_confirmed'] = 'Entfernen nicht bestätigt.';
$lang['license']['removed_confirm'] = 'Lizenz entfernt. Für diesen Startpunkt gilt wieder das Contao-Standardverhalten.';
$lang['license']['activated_confirm'] = 'Lizenz geprüft und aktiviert.';
$lang['license']['accepted_but_invalid'] = 'Lizenz übernommen, aber derzeit nicht gültig: %reason%';
$lang['license']['check_failed'] = 'Die Lizenzprüfung konnte nicht abgeschlossen werden. Bitte später erneut versuchen.';

$lang['license']['state_label'] = 'Status';
$lang['license']['domains_label'] = 'Domain(s) dieses Startpunkts';
$lang['license']['no_domain_configured'] = '— (keine Domain konfiguriert)';
$lang['license']['masked_key_label'] = 'Schlüssel';
$lang['license']['package_label'] = 'Paket';
$lang['license']['valid_from_label'] = 'Gültig ab';
$lang['license']['valid_until_label'] = 'Gültig bis';
$lang['license']['lifetime_label'] = 'unbefristet';
$lang['license']['last_checked_label'] = 'Zuletzt geprüft';

$lang['license']['no_domain_warning'] = 'Für diesen Startpunkt ist keine Domain hinterlegt. Bitte oben im Abschnitt "Routing" eine Domain eintragen — die Lizenz wird exakt an diesen Hostnamen gebunden (www.example.com und example.com sind verschiedene Hosts).';
$lang['license']['key_label'] = 'Lizenzschlüssel';
$lang['license']['key_stored_suffix'] = ' (hinterlegt — Feld leer lassen, um ihn beizubehalten)';
$lang['license']['key_placeholder_kept'] = 'unverändert lassen';
$lang['license']['key_placeholder_enter'] = 'Lizenzschlüssel eingeben';
$lang['license']['key_help'] = 'Der Schlüssel wird ausschließlich serverseitig verwendet und nie wieder vollständig angezeigt — oben im Status stehen seine ersten und letzten vier Zeichen, damit erkennbar bleibt, welche Lizenz hinterlegt ist. Die Prüfung kontaktiert ausschließlich den Lizenzserver des Herstellers.';
$lang['license']['activate_btn'] = 'Lizenz prüfen und aktivieren';
$lang['license']['refresh_btn'] = 'Lizenz aktualisieren';
$lang['license']['remove_btn'] = 'Lizenz entfernen';
$lang['license']['confirm_remove_label'] = 'Entfernen bestätigen';

$lang['license']['state_active'] = 'aktiv';
$lang['license']['state_expired'] = 'abgelaufen — die Funktionen sind für diesen Startpunkt deaktiviert';
$lang['license']['state_invalid'] = 'nicht gültig: %reason%';
$lang['license']['state_unlicensed'] = 'keine Lizenz hinterlegt';

$lang['license']['reason_key_missing'] = 'Bitte einen Lizenzschlüssel eingeben.';
$lang['license']['reason_no_configured_domain'] = 'Für diesen Startpunkt ist keine Domain hinterlegt.';
$lang['license']['reason_domain_mismatch'] = 'Die Lizenz gilt nicht für die Domain dieses Startpunkts.';
$lang['license']['reason_package_not_permitted'] = 'Dieses Lizenzpaket ist für dieses Produkt nicht zugelassen.';
$lang['license']['reason_expired'] = 'Die Lizenz ist abgelaufen.';
$lang['license']['reason_not_yet_valid'] = 'Die Lizenz ist noch nicht gültig.';
$lang['license']['reason_refresh_required'] = 'Die hinterlegte Lizenz muss einmalig aktualisiert werden.';
$lang['license']['reason_version_rejected'] = 'Es liegt bereits ein neuerer Lizenzstand vor.';
$lang['license']['reason_service_unavailable'] = 'Der Lizenzserver ist derzeit nicht erreichbar. Der bisherige Stand bleibt erhalten.';
$lang['license']['reason_service_denied'] = 'Der Lizenzserver hat die Anfrage abgelehnt. Bitte Schlüssel und Domain prüfen.';
$lang['license']['reason_signature_runtime_unavailable'] = 'Auf diesem Server fehlt die benötigte Krypto-Erweiterung (libsodium).';
$lang['license']['reason_scope_invalid'] = 'Ungültiger Startpunkt.';
$lang['license']['reason_default'] = 'Die Lizenz konnte nicht bestätigt werden.';

// ── Gemeinsam über mehrere Backend-Bildschirme ────────────────────────────
$lang['common']['show_on_page'] = 'Auf der Seite zeigen →';
$lang['common']['fix_now'] = 'Jetzt fixen ›';
$lang['common']['best_practice_badge'] = 'Best Practice';
$lang['common']['best_practice_title'] = 'Empfehlung – kein WCAG/BFSG-Pflichtverstoß';
$lang['common']['occurrences_pages'] = ' × %count% Seiten';
$lang['common']['scan_word'] = 'Scan';
$lang['common']['apply_btn'] = 'Übernehmen';
$lang['common']['discard_btn'] = 'Verwerfen';
$lang['common']['suggestion_discarded'] = 'Vorschlag verworfen.';
$lang['common']['egress_blocked_title'] = 'Erst externe Aufrufe freigeben';

// ── Schweregrad-/Kategorie-Enum-Labels (gemeinsame UI-Badges) ────────────
$lang['severity']['critical'] = 'Kritisch';
$lang['severity']['serious'] = 'Schwer';
$lang['severity']['moderate'] = 'Mittel';
$lang['severity']['minor'] = 'Gering';
$lang['category']['done'] = 'Erledigt';
$lang['category']['oneclick'] = 'Ein-Klick';
$lang['category']['manual'] = 'Nur manuell';

// ── Datenbank-Check-Labels (Überschriften in Bericht/Dashboard, fester Katalog) ─
$lang['check']['heading_hierarchy'] = 'Überschriften-Hierarchie';
$lang['check']['image_alt_missing'] = 'Bilder ohne Alternativtext';
$lang['check']['page_language_missing'] = 'Seitensprache (lang) fehlt';
$lang['check']['link_text_vague'] = 'Wenig aussagekräftige Linktexte';
$lang['check']['form_field_no_label'] = 'Formularfelder ohne Beschriftung';

// ── Bericht ────────────────────────────────────────────────────────────────
$lang['report']['title'] = 'Barrierefreiheit &ndash; Bericht';
$lang['report']['scan_btn'] = 'Scan jetzt';
$lang['report']['scan_help'] = 'Liest die Contao-Inhalte (Datenbank) — verändert nichts.';
$lang['report']['scan_done_confirm'] = 'Scan fertig. Neu: %new% · Wieder offen: %reopened% · Behoben: %resolved% · Offen: %open% · Score: %score%';
$lang['report']['status_updated'] = 'Status aktualisiert.';
$lang['report']['no_open_findings'] = 'Keine offenen Befunde. Entweder noch nicht gescannt oder alles erledigt.';
$lang['report']['score_line'] = '<strong>Score:</strong> %score%/100 <span style="color:#999;">(%count% offene Befunde · Richtwert, keine Konformitätsaussage)</span>';
$lang['report']['confirmed_badge'] = '[bestätigt]';
$lang['report']['confirm_btn'] = 'Bestätigen';
$lang['report']['reopen_btn'] = 'Wieder offen';
$lang['report']['ignore_btn'] = 'Ignorieren';

// ── PDF ────────────────────────────────────────────────────────────────────
$lang['pdf']['title'] = 'Barrierefreiheit &ndash; PDF-Dokumente';
$lang['pdf']['scan_btn'] = 'PDFs prüfen';
$lang['pdf']['scan_help'] = 'Liest verlinkte PDFs (Titel, Sprache, Tags). Verändert die Dateien NICHT.';
$lang['pdf']['scan_done_confirm'] = 'PDF-Prüfung fertig. Geprüft: %checked% · Probleme: %issues% · Unklar (komprimiert): %unknown% · Unlesbar: %unreadable%';
$lang['pdf']['no_issues'] = 'Keine PDF-Probleme offen. Entweder noch nicht geprüft, alles ok, oder Status unklar (komprimierte PDFs).';
$lang['pdf']['open_issues_legend'] = 'Offene PDF-Befunde (%count%)';
$lang['pdf']['disclaimer'] = 'Hinweis: PDFs werden nicht automatisch repariert — echte Barrierefreiheit (Tags/Struktur) entsteht beim getaggten Export aus der Quelle. Das Tool zeigt, welche Dokumente betroffen sind.';

// ── KI-Alt-Texte ───────────────────────────────────────────────────────────
$lang['alt']['title'] = 'Barrierefreiheit &ndash; KI-Alt-Texte';
$lang['alt']['approved_confirm'] = 'Alt-Text übernommen (in tl_files.meta geschrieben).';
$lang['alt']['skipped_manual_error'] = 'Nicht übernommen: es existiert bereits ein manueller Alt-Text (wird nie überschrieben).';
$lang['alt']['file_not_found_error'] = 'Nicht übernommen: Datei nicht gefunden.';
$lang['alt']['limit_label'] = 'Anzahl:';
$lang['alt']['generate_btn'] = 'Vorschläge erzeugen';
$lang['alt']['egress_blocked_note'] = '"Keine externen Aufrufe" ist aktiv – Generierung gesperrt. In den Einstellungen freigeben.';
$lang['alt']['help'] = 'Erzeugt Alt-Text-Vorschläge nur für Bilder OHNE vorhandenen Alt-Text. Nichts wird automatisch veröffentlicht.';
$lang['alt']['no_pending'] = 'Keine offenen Vorschläge.';
$lang['alt']['pending_legend'] = 'Offene Vorschläge (%count%)';
$lang['alt']['decorative_badge'] = 'dekorativ → leeres alt';
$lang['alt']['alt_placeholder'] = 'Alt-Text – leer lassen = dekoratives (leeres) alt';

// ── ARIA-Namen ─────────────────────────────────────────────────────────────
$lang['aria']['title'] = 'Barrierefreiheit &ndash; ARIA-Namen';
$lang['aria']['intro'] = 'Elemente ohne barrierefreien Namen (Links/Schaltflächen/iframes ohne Text) werden beim <strong>Frontend-Scan</strong> gesammelt. Prüfe den vorgeschlagenen Namen, passe ihn an und übernimm ihn — er wird dann zur Laufzeit als <code>aria-label</code> gesetzt, aber nur wo das Element noch keinen Namen hat (nie überschrieben). Der saubere Fix gehört langfristig ins Template.';
$lang['aria']['name_required_error'] = 'Bitte einen Namen eingeben.';
$lang['aria']['applied_confirm'] = 'Name übernommen und aktiv: „%name%".';
$lang['aria']['ai_blocked_error'] = 'Externe Aufrufe sind deaktiviert. Erst in den Einstellungen freigeben.';
$lang['aria']['ai_no_suggestion_error'] = 'KI lieferte keinen Vorschlag (oder Anbieter nicht erreichbar).';
$lang['aria']['ai_confirm'] = 'KI-Vorschlag eingetragen: „%name%". Bitte prüfen und übernehmen.';
$lang['aria']['rule_link_name'] = 'Link ohne erkennbaren Namen';
$lang['aria']['rule_button_name'] = 'Schaltfläche ohne Namen';
$lang['aria']['rule_frame_title'] = 'iframe ohne Titel';
$lang['aria']['rule_input_field_name'] = 'Eingabefeld ohne Namen';
$lang['aria']['rule_input_button_name'] = 'Button-Eingabe ohne Namen';
$lang['aria']['rule_command_name'] = 'Bedienelement ohne Namen';
$lang['aria']['rule_toggle_field_name'] = 'Umschalter ohne Namen';
$lang['aria']['rule_image_alt'] = 'Bild-Button ohne Alt-Text';
$lang['aria']['open_legend'] = 'Offen';
$lang['aria']['open_empty'] = 'Keine offenen Elemente. Führe im Dashboard einen Voll-Scan aus.';
$lang['aria']['active_legend'] = 'Aktiv';
$lang['aria']['selector_label'] = 'Selektor:';
$lang['aria']['name_placeholder'] = 'Barrierefreier Name (aria-label)';
$lang['aria']['ai_suggest_btn'] = 'KI-Vorschlag';
$lang['aria']['deactivate_btn'] = 'Deaktivieren';

// ── KI-Untertitel ──────────────────────────────────────────────────────────
$lang['subtitle']['title'] = 'Barrierefreiheit &ndash; KI-Untertitel';
$lang['subtitle']['help'] = 'Erzeugt WebVTT-Untertitel aus Video/Audio (Whisper). <strong>KI-Entwurf</strong> &ndash; muss geprüft und freigegeben werden, bevor er als Datei gespeichert wird. Limit 25&nbsp;MB pro Datei; nur Anbieter OpenAI / kompatibel.';
$lang['subtitle']['egress_blocked'] = '"Keine externen Aufrufe" ist aktiv – Transkription gesperrt. In den Einstellungen freigeben.';
$lang['subtitle']['generated_confirm'] = 'Untertitel-Entwurf erzeugt (%lang%, %ms% ms). Bitte prüfen und freigeben.';
$lang['subtitle']['generate_error_prefix'] = 'Transkription fehlgeschlagen: %message%';
$lang['subtitle']['savedraft_confirm'] = 'Entwurf gespeichert.';
$lang['subtitle']['approve_confirm'] = 'Untertitel freigegeben und gespeichert: %path%';
$lang['subtitle']['approve_error'] = 'Freigabe fehlgeschlagen (Datei konnte nicht geschrieben werden).';
$lang['subtitle']['reject_confirm'] = 'Entwurf verworfen.';
$lang['subtitle']['no_media'] = 'Keine Audio-/Videodateien in der Dateiverwaltung gefunden.';
$lang['subtitle']['media_legend'] = 'Medien (%count%)';
$lang['subtitle']['used_badge'] = 'verwendet';
$lang['subtitle']['too_large_error'] = 'Datei größer als 25 MB – bitte kürzen/komprimieren oder via Konsole verarbeiten.';
$lang['subtitle']['lang_label'] = 'Sprache:';
$lang['subtitle']['regenerate_btn'] = 'Neu erzeugen';
$lang['subtitle']['generate_btn'] = 'Untertitel erzeugen';
$lang['subtitle']['status_applied'] = 'freigegeben';
$lang['subtitle']['status_rejected'] = 'verworfen';
$lang['subtitle']['status_draft'] = 'Entwurf, ungeprüft';
$lang['subtitle']['status_label'] = 'Status:';
$lang['subtitle']['file_label'] = 'Datei:';
$lang['subtitle']['savedraft_btn'] = 'Entwurf speichern';
$lang['subtitle']['approve_btn'] = 'Freigeben & speichern';
$lang['subtitle']['track_help'] = 'Untertitel danach im Video-Element als <code>&lt;track&gt;</code> einbinden (Contao 5.4+ / zoglo). Whisper-Zeitstempel und Text bitte gegenlesen.';

// ── Einfache / Leichte Sprache ────────────────────────────────────────────
$lang['simple']['title'] = 'Barrierefreiheit &ndash; Einfache / Leichte Sprache';
$lang['simple']['disclaimer'] = 'KI erstellt <strong>Entwürfe</strong> in vereinfachter Sprache. <strong>Keine zertifizierte Leichte Sprache</strong> &ndash; menschliche Prüfung nötig, nichts wird automatisch veröffentlicht.';
$lang['simple']['register_einfach'] = 'Einfache Sprache';
$lang['simple']['register_leicht'] = 'Leichte Sprache';
$lang['simple']['settings_legend'] = 'Einstellungen';
$lang['simple']['settings_enabled_label'] = 'Funktion aktiv (Umschalter im Frontend anzeigen)';
$lang['simple']['settings_registers_label'] = 'Angebotene Register:';
$lang['simple']['settings_switch_label'] = 'Umschalter:';
$lang['simple']['settings_switch_overlay'] = 'im Komfort-Overlay';
$lang['simple']['settings_switch_button'] = 'Floating-Button';
$lang['simple']['settings_switch_nav'] = 'Nav-Link-Modul';
$lang['simple']['settings_save_btn'] = 'Einstellungen speichern';
$lang['simple']['settings_saved_root'] = 'Einstellungen gespeichert (Aktivierung für diese Domain).';
$lang['simple']['settings_saved'] = 'Einstellungen gespeichert.';
$lang['simple']['page_select_legend'] = 'Seite wählen';
$lang['simple']['scope_label'] = 'Bereich:';
$lang['simple']['page_placeholder'] = '– bitte wählen –';
$lang['simple']['page_all_option'] = '★ Alle Inhalte (ganze Website)';
$lang['simple']['register_label'] = 'Register:';
$lang['simple']['lang_label'] = 'Sprache:';
$lang['simple']['load_btn'] = 'Laden';
$lang['simple']['no_items'] = 'Keine vereinfachbaren Textelemente gefunden.';
$lang['simple']['scope_all_hint'] = 'Bereich: <strong>ganze Website</strong> &ndash; alle Inhaltselemente (auch modul-/theme-eingebundene Karten).';
$lang['simple']['generate_btn'] = 'Entwürfe erzeugen (%count% Elemente)';
$lang['simple']['approveall_btn'] = 'Alle Entwürfe freigeben';
$lang['simple']['savedraft_confirm'] = 'Entwurf gespeichert.';
$lang['simple']['approve_confirm'] = 'Entwurf freigegeben (erscheint im Frontend bei aktivem Umschalter).';
$lang['simple']['reject_confirm'] = 'Entwurf verworfen.';
$lang['simple']['lock_confirm'] = 'Bereich gesperrt &ndash; wird nicht mehr neu generiert und bleibt live.';
$lang['simple']['unlock_confirm'] = 'Sperre aufgehoben.';
$lang['simple']['approveall_confirm'] = '%count% Entwürfe freigegeben.';
$lang['simple']['status_approved'] = 'freigegeben';
$lang['simple']['status_rejected'] = 'verworfen';
$lang['simple']['status_pending'] = 'Entwurf, ungeprüft';
$lang['simple']['status_none'] = 'kein Entwurf';
$lang['simple']['locked_badge'] = '🔒 gesperrt';
$lang['simple']['element_label'] = 'Element #%id%';
$lang['simple']['original_label'] = 'Original:';
$lang['simple']['draft_label'] = 'Entwurf:';
$lang['simple']['no_draft_hint'] = 'Noch kein Entwurf – oben „Entwürfe erzeugen“.';
$lang['simple']['unlock_btn'] = '🔓 Entsperren';
$lang['simple']['savedraft_btn'] = 'Speichern';
$lang['simple']['approve_btn'] = 'Freigeben';
$lang['simple']['lock_btn'] = '🔒 Sperren';
$lang['simple']['lock_btn_title'] = 'Freigeben + vor Neu-Generierung schützen';

// ── Komfort-Overlay ────────────────────────────────────────────────────────
$lang['overlay']['title'] = 'Barrierefreiheit &ndash; Komfort-Overlay';
$lang['overlay']['disclaimer'] = 'Komfort-/Anzeigeoptionen fürs Frontend. <strong>Kein Ersatz für barrierefreie Inhalte</strong> und nicht als „Barrierefreiheits-Lösung" zu vermarkten.';
$lang['overlay']['activation_legend'] = 'Aktivierung';
$lang['overlay']['activation_domain_hint'] = 'Diese Aktivierung gilt nur für die <strong>gewählte Domain</strong>. Design und Funktionen unten gelten install-weit für alle Domains.';
$lang['overlay']['activation_toggle'] = 'Overlay im Frontend anzeigen';
$lang['overlay']['design_legend'] = 'Design';
$lang['overlay']['color_label'] = 'Button-Farbe';
$lang['overlay']['position_label'] = 'Button-Position';
$lang['overlay']['position_bottomright'] = 'Unten rechts';
$lang['overlay']['position_bottomleft'] = 'Unten links';
$lang['overlay']['position_middleright'] = 'Mitte rechts';
$lang['overlay']['position_middleleft'] = 'Mitte links';
$lang['overlay']['position_topright'] = 'Oben rechts';
$lang['overlay']['position_topleft'] = 'Oben links';
$lang['overlay']['saved_root'] = 'Overlay-Einstellungen gespeichert (Aktivierung für diese Domain).';
$lang['overlay']['saved'] = 'Overlay-Einstellungen gespeichert.';
$lang['overlay_group']['profiles'] = 'Modi';
$lang['overlay_group']['reading'] = 'Lesen';
$lang['overlay_group']['orientation'] = 'Orientierung';
$lang['overlay_group']['colors'] = 'Farben';
$lang['overlay_feature']['profile_epilepsy'] = 'Epilepsie-sicherer Modus';
$lang['overlay_feature']['profile_lowvision'] = 'Sehbehinderten-Modus';
$lang['overlay_feature']['profile_adhd'] = 'ADHS-freundlicher Modus';
$lang['overlay_feature']['contentscale'] = 'Inhaltliche Skalierung';
$lang['overlay_feature']['fontsize'] = 'Schriftgröße';
$lang['overlay_feature']['lineheight'] = 'Zeilenhöhe';
$lang['overlay_feature']['letterspacing'] = 'Buchstabenabstand';
$lang['overlay_feature']['readablefont'] = 'Lesbare Schriftart';
$lang['overlay_feature']['dyslexiafont'] = 'Legasthenie-Schrift';
$lang['overlay_feature']['highlighttitles'] = 'Titel hervorheben';
$lang['overlay_feature']['highlightlinks'] = 'Links hervorheben';
$lang['overlay_feature']['bionic'] = 'Kognitives Lesen';
$lang['overlay_feature']['linknav'] = 'Link-Navigator';
$lang['overlay_feature']['darkcontrast'] = 'Dunkler Kontrast';
$lang['overlay_feature']['lightcontrast'] = 'Heller Kontrast';
$lang['overlay_feature']['highcontrast'] = 'Hoher Kontrast';
$lang['overlay_feature']['monochrome'] = 'Einfarbig';
$lang['overlay_feature']['stopanim'] = 'Animationen stoppen';
$lang['overlay_feature']['mutesound'] = 'Töne stummschalten';
$lang['overlay_feature']['bigcursor'] = 'Großer Cursor';
$lang['overlay_feature']['hideimages'] = 'Bilder ausblenden';
$lang['overlay_feature']['readingguide'] = 'Lesehilfe (Leselineal)';
$lang['overlay_feature']['tts'] = 'Vorlesen (Text in Sprache)';
$lang['overlay_feature']['focushighlight'] = 'Fokus hervorheben';
$lang['overlay_feature']['hoverhighlight'] = 'Hover hervorheben';
$lang['overlay_feature']['textalign'] = 'Textausrichtung';
$lang['overlay_feature']['color_text'] = 'Textfarbe anpassen';
$lang['overlay_feature']['color_title'] = 'Titelfarbe anpassen';
$lang['overlay_feature']['color_link'] = 'Linkfarbe anpassen';
$lang['overlay_feature']['color_bg'] = 'Hintergrundfarbe anpassen';

// ── Erklärung (Editor) ─────────────────────────────────────────────────────
$lang['statement']['editor_title'] = 'Barrierefreiheit &ndash; Erklärung &amp; Meldekanal';
$lang['statement']['legal_disclaimer'] = 'Hinweis: Dies ist <strong>keine Rechtsberatung</strong>. Das Tool stellt die Pflichtangaben zusammen; für die rechtliche Richtigkeit ist der Betreiber verantwortlich.';
$lang['statement']['domain_scope_hint'] = 'Diese Erklärung gilt für die <strong>gewählte Domain</strong>. Jede Domain hat ihre eigene Erklärung; ohne eigene Angaben greift die install-weite Vorlage („Alle Domains").';
$lang['statement']['suggested_status'] = 'Status-Vorschlag aus aktuellen Befunden: <strong>%status%</strong> (nur Empfehlung).';
$lang['statement']['section_details'] = 'Angaben';
$lang['statement']['org_label'] = 'Betreiber / Organisation';
$lang['statement']['url_label'] = 'Website (URL)';
$lang['statement']['status_label'] = 'Stand der Vereinbarkeit';
$lang['statement']['nonaccessible_label'] = 'Nicht barrierefreie Inhalte (frei)';
$lang['statement']['section_contact'] = 'Kontakt / Meldekanal';
$lang['statement']['contact_name_label'] = 'Kontakt-Name';
$lang['statement']['contact_email_label'] = 'Kontakt-E-Mail';
$lang['statement']['contact_phone_label'] = 'Kontakt-Telefon';
$lang['statement']['feedback_recipient_label'] = 'E-Mail für Meldungen (Meldekanal)';
$lang['statement']['section_creation'] = 'Erstellung &amp; Durchsetzung';
$lang['statement']['prepared_label'] = 'Erstellt am (Datum)';
$lang['statement']['method_label'] = 'Methode';
$lang['statement']['enforcement_label'] = 'Durchsetzungsverfahren / Schlichtungsstelle (frei)';
$lang['statement']['saved_root'] = 'Barrierefreiheitserklärung für diese Domain gespeichert.';
$lang['statement']['saved'] = 'Barrierefreiheitserklärung gespeichert.';
$lang['statement']['invalid_recipient_error'] = 'Meldekanal-E-Mail ungültig — nicht gespeichert.';
$lang['statement']['status_conformant'] = 'vollständig konform';
$lang['statement']['status_partial'] = 'teilweise konform';
$lang['statement']['status_nonconformant'] = 'nicht konform';
$lang['statement']['method_self'] = 'Selbstbewertung';
$lang['statement']['method_external'] = 'Externe Prüfung';
$lang['statement']['embed_hint'] = 'Im Frontend einbinden: Modul-Typ <strong>Barrierefreiheitserklärung</strong> (zeigt diese Angaben) und <strong>Meldekanal</strong> (Meldeformular) auf eine Seite legen.';

// ── Verlauf / Undo ─────────────────────────────────────────────────────────
$lang['audit']['title'] = 'Barrierefreiheit &ndash; Verlauf / Undo';
$lang['audit']['undo_confirm'] = 'Änderung rückgängig gemacht; Vorschlag wieder offen.';
$lang['audit']['undo_error'] = 'Rückgängig nicht möglich (inzwischen geändert oder bereits rückgängig).';
$lang['audit']['no_entries'] = 'Noch keine angewendeten Änderungen.';
$lang['audit']['col_time'] = 'Zeit';
$lang['audit']['col_action'] = 'Aktion';
$lang['audit']['col_target'] = 'Ziel';
$lang['audit']['col_before_after'] = 'Vorher → Nachher';
$lang['audit']['col_user'] = 'Benutzer';
$lang['audit']['before_absent'] = '∅ (kein alt)';
$lang['audit']['undone_label'] = 'rückgängig';
$lang['audit']['undo_btn'] = 'Rückgängig';

// ── Frontend-Scan (Einzelbildschirm; abgelöst durch den Voll-Scan im Dashboard) ─
$lang['frontend_scan']['title'] = 'Barrierefreiheit &ndash; Frontend-Analyse (axe-core)';
$lang['frontend_scan']['no_pages'] = 'Keine veröffentlichten, öffentlichen Seiten gefunden.';
$lang['frontend_scan']['help'] = 'Scannt <strong>%count%</strong> veröffentlichte Seite(n) direkt im Browser (axe-core). <strong>Nur gesetzlich verpflichtende Regeln</strong> (WCAG 2.x %target% = BITV 2.0 / BFSG) — keine reinen Best-Practice-Empfehlungen. Befunde landen als <em>Frontend</em>-Quelle im selben Bericht/Dashboard.';
$lang['frontend_scan']['capped_note'] = ' <strong>Hinweis:</strong> auf %cap% Seiten begrenzt.';
$lang['frontend_scan']['session_hint'] = 'Läuft in deiner Backend-Sitzung; geschützte Seiten werden übersprungen. Lass das Backend-Tab offen, bis „Fertig" erscheint.';
$lang['frontend_scan']['start_btn'] = 'Frontend-Scan starten';

// ── Meldungen-Posteingang (Backend-Sperre) ────────────────────────────────
$lang['feedback']['inbox_readonly_notice'] = 'Keine gültige Lizenz für einen Startpunkt: Die Meldungen sind nur lesbar. Die Lizenz wird unter Seitenstruktur → Startpunkt bearbeiten → AccessPlus Licence management hinterlegt.';

// ── Highlight-Vorschau ──────────────────────────────────────────────────────
$lang['highlight']['no_preview'] = 'Keine Vorschau verfügbar.';
$lang['highlight']['element_label'] = 'Element:';
$lang['highlight']['page_title'] = 'Barrierefreiheit &ndash; Vorschau';
$lang['highlight']['cross_origin_note'] = '(Cross-Origin – nicht markierbar)';
$lang['highlight']['element_not_found_note'] = '(Element nicht gefunden – Seite evtl. geändert)';
$lang['highlight']['marked_note'] = '✓ markiert';
$lang['highlight']['preview_not_possible_note'] = '(Vorschau nicht möglich)';

// ── Einstellungen ──────────────────────────────────────────────────────────
$lang['settings']['title'] = 'Barrierefreiheit &ndash; Einstellungen';
$lang['settings']['provider_legend'] = 'KI-Anbieter';
$lang['settings']['provider_label'] = 'Anbieter';
$lang['settings']['provider_openai'] = 'OpenAI';
$lang['settings']['provider_compatible'] = 'OpenAI-kompatibel (eigene Basis-URL)';
$lang['settings']['base_url_label'] = 'Basis-URL (optional, Pflicht für „kompatibel")';
$lang['settings']['model_label'] = 'Modell (optional, leer = Standard; für Alt-Texte ein Vision-Modell)';
$lang['settings']['api_key_label'] = 'API-Schlüssel (%state%)';
$lang['settings']['api_key_state_set'] = 'gesetzt';
$lang['settings']['api_key_state_empty'] = 'leer';
$lang['settings']['api_key_placeholder_kept'] = 'unverändert lassen';
$lang['settings']['api_key_placeholder_enter'] = 'Schlüssel eingeben';
$lang['settings']['api_key_help'] = 'Wird verschlüsselt gespeichert und nie angezeigt. Feld leer lassen = unverändert.';
$lang['settings']['clear_key_label'] = 'Schlüssel entfernen';
$lang['settings']['privacy_legend'] = 'Datenschutz';
$lang['settings']['no_external_calls_label'] = 'Keine externen Aufrufe';
$lang['settings']['no_external_calls_desc'] = ' — blockiert jeden KI-/Egress-Aufruf. Standardmäßig aktiv. Zum Nutzen der KI-Funktionen deaktivieren.';
$lang['settings']['no_external_calls_help'] = 'Ist dies aktiv, verlassen keine Inhalts-/KI-Daten den Server. Der Verbindungstest unten ist dann gesperrt. Nicht betroffen ist die Lizenzprüfung (nur der Lizenzserver des Herstellers) — sie ist Voraussetzung des Produkts und wird je Startpunkt unter „AccessPlus Licence management" verwaltet.';
$lang['settings']['accessibility_legend'] = 'Barrierefreiheit';
$lang['settings']['wcag_target_label'] = 'WCAG-Zielniveau';
$lang['settings']['wcag_aa_recommended'] = 'AA (empfohlen)';
$lang['settings']['languages_label'] = 'Aktive Sprachen (Komma-getrennt, z. B. „de, en")';
$lang['settings']['monitor_legend'] = 'Monitoring';
$lang['settings']['monitor_on_save_label'] = 'Nach Speichern automatisch neu prüfen';
$lang['settings']['monitor_on_save_desc'] = ' — re-scannt die Datenbank-Checks nach Inhalts-Änderungen (gedrosselt). Kein externer Aufruf.';
$lang['settings']['monitor_interval_label'] = 'Drossel-Intervall (Sekunden, min. 30)';
$lang['settings']['monitor_interval_help'] = 'Verhindert Dauer-Scans bei vielen Speichervorgängen. Gilt auch für den Contao-Cron.';
$lang['settings']['test_btn'] = 'Verbindung testen';
$lang['settings']['key_cleared_confirm'] = 'API-Schlüssel entfernt.';
$lang['settings']['key_saved_confirm'] = 'API-Schlüssel verschlüsselt gespeichert.';
$lang['settings']['saved_confirm'] = 'Einstellungen gespeichert.';

// ── Meldekanal (Frontend-Formular + Backend-Platzhalter) ─────────────────
$lang['feedback_form']['heading'] = 'Barriere melden';
$lang['feedback_form']['thank_you'] = 'Vielen Dank — Ihre Meldung wurde übermittelt.';
$lang['feedback_form']['honeypot_label'] = 'Website (bitte leer lassen)';
$lang['feedback_form']['name_label'] = 'Name (optional)';
$lang['feedback_form']['email_label'] = 'E-Mail (optional, für Rückfragen)';
$lang['feedback_form']['url_label'] = 'Betroffene Seite (URL, optional)';
$lang['feedback_form']['message_label'] = 'Ihre Meldung';
$lang['feedback_form']['submit_btn'] = 'Meldung absenden';
$lang['feedback_form']['wildcard'] = '### Barriere-Meldekanal ###';
$lang['feedback']['error_invalid_token'] = 'Sicherheits-Token ungültig. Bitte erneut senden.';
$lang['feedback']['error_message_required'] = 'Bitte eine Nachricht eingeben (max. 5000 Zeichen).';
$lang['feedback']['error_email_invalid'] = 'E-Mail-Adresse ungültig.';
$lang['feedback']['error_url_invalid'] = 'Seiten-URL muss mit http(s):// beginnen.';
$lang['feedback']['error_name_too_long'] = 'Name zu lang.';

// ── Umschalter Einfache/Leichte Sprache (Frontend) ────────────────────────
$lang['simple_switch']['wildcard'] = '### Einfache/Leichte Sprache – Umschalter ###';
$lang['simple_switch']['nav_label'] = 'Sprache wählen';
$lang['simple']['register_none'] = 'Alltagssprache';

// ── Barrierefreiheitserklärung (Frontend, öffentlich) ─────────────────────
$lang['statement_public']['wildcard'] = '### Barrierefreiheitserklärung ###';
$lang['statement_public']['heading'] = 'Erklärung zur Barrierefreiheit';
$lang['statement_public']['intro'] = '%org% ist bemüht, seine Website%url_html% im Einklang mit den Vorgaben zur Barrierefreiheit zugänglich zu machen.';
$lang['statement_public']['status_heading'] = 'Stand der Vereinbarkeit';
$lang['statement_public']['status_text'] = 'Diese Website ist <strong>%status%</strong> mit den einschlägigen Anforderungen.';
$lang['statement_public']['nonaccessible_heading'] = 'Nicht barrierefreie Inhalte';
$lang['statement_public']['creation_heading'] = 'Erstellung dieser Erklärung';
$lang['statement_public']['creation_text'] = 'Diese Erklärung wurde am %date% auf Grundlage einer %method% erstellt.';
$lang['statement_public']['method_self'] = 'Selbstbewertung';
$lang['statement_public']['method_external'] = 'externen Prüfung';
$lang['statement_public']['feedback_heading'] = 'Feedback und Kontaktangaben';
$lang['statement_public']['feedback_intro'] = 'Barrieren melden oder Informationen anfordern:';
$lang['statement_public']['email_label'] = 'E-Mail:';
$lang['statement_public']['phone_label'] = 'Telefon:';
$lang['statement_public']['enforcement_heading'] = 'Durchsetzungsverfahren';

// ── CLI-Kommandos ──────────────────────────────────────────────────────────
$lang['command']['no_license_error'] = 'Keine gültige Lizenz für einen Startpunkt hinterlegt.';
$lang['command']['lang_option'] = 'Sprachcode (ISO-639-1)';
$lang['command']['review_suffix'] = ' Bitte im Backend prüfen und freigeben.';
$lang['command']['alt']['description'] = 'KI-Alt-Text-Vorschläge für Bilder ohne Alt-Text erzeugen (Review).';
$lang['command']['alt']['limit_option'] = 'Maximale Anzahl Vorschläge';
$lang['command']['simple']['description'] = 'KI-Entwürfe in Einfacher/Leichter Sprache für alle Seiten erzeugen (Review).';
$lang['command']['simple']['register_option'] = 'einfach|leicht';
$lang['command']['simple']['limit_option'] = 'Maximale Anzahl Text-Snippets';
$lang['command']['subtitles']['description'] = 'KI-Untertitel-Entwürfe (VTT) für Medien ohne Untertitel erzeugen (Review).';
$lang['command']['subtitles']['limit_option'] = 'Maximale Anzahl Dateien';
$lang['command']['subtitles']['ok_tag'] = 'OK';
$lang['command']['subtitles']['error_tag'] = 'FEHLER';
$lang['command']['subtitles']['skipped_tag'] = 'ÜBERSPRUNGEN';
$lang['command']['subtitles']['fatal_abort'] = 'Nicht behebbarer Fehler — Abbruch.';
$lang['command']['subtitles']['done_confirm'] = 'Untertitel-Entwürfe erzeugt: %done% · Fehler: %errors%.';
$lang['command']['monitor']['description'] = 'Barrierefreiheit erneut prüfen und Veränderung zum Vorlauf zeigen.';
$lang['command']['monitor']['score_line'] = 'Score: %score% · Offen: %open%';
$lang['command']['monitor']['since_last'] = 'Seit letztem Lauf: +%new% neu · -%resolved% behoben · Score %delta%';
$lang['command']['monitor']['first_run'] = 'Erstlauf — kein Vergleich.';
$lang['command']['pdf']['description'] = 'Verlinkte PDFs auf Titel, Sprache und Tags prüfen.';
$lang['command']['pdf']['done_confirm'] = 'Geprüft: %checked% · Probleme: %issues% · Unklar: %unknown% · Unlesbar: %unreadable%';
$lang['command']['scan']['description'] = 'Barrierefreiheits-Checks über die Inhalte laufen lassen.';
$lang['command']['scan']['done_confirm'] = 'Scan fertig. Neu: %new% · Wieder offen: %reopened% · Behoben: %resolved% · Offen gesamt: %open% · Score: %score%';

// ── KI-Anbieter-/Verbindungsfehler (Einstellungen, ARIA, Untertitel) ──────
$lang['ai']['egress_blocked_error'] = 'Externe Aufrufe sind deaktiviert ("Keine externen Aufrufe"). Bitte erst in den Einstellungen freigeben.';
$lang['ai']['no_api_key_error'] = 'Kein API-Schlüssel hinterlegt. Bitte in den Einstellungen setzen.';
$lang['ai']['invalid_base_url_error'] = 'Ungültige Basis-URL. Nur http(s) mit Hostname erlaubt.';
$lang['ai']['test_not_run'] = 'Test nicht ausgeführt: "Keine externen Aufrufe" ist aktiv. Zum Testen erst freigeben.';
$lang['ai']['config_error_prefix'] = 'Konfigurationsfehler: %message%';
$lang['ai']['connection_failed_prefix'] = 'Verbindung fehlgeschlagen: %message%';
$lang['ai']['connection_ok'] = 'Verbindung ok (%provider% / %model%, %ms% ms).';
$lang['ai']['unsupported_image_format'] = 'Nicht unterstütztes Bildformat: %extension%';
$lang['ai']['file_not_found'] = 'Datei nicht gefunden.';
$lang['ai']['path_outside_allowed_dir'] = 'Pfad außerhalb des erlaubten Verzeichnisses.';
$lang['ai']['file_empty_or_unreadable'] = 'Datei ist leer oder nicht lesbar.';
$lang['ai']['image_too_large'] = 'Bild zu groß (%size% KB, max %max% KB).';
$lang['ai']['image_unreadable'] = 'Bild konnte nicht gelesen werden.';
$lang['ai']['whisper_unsupported_provider'] = 'Untertitel benötigen einen Whisper-kompatiblen Anbieter (OpenAI oder „kompatibel"). Aktueller Anbieter unterstützt keine Audio-Transkription.';

// ── Zusammenfassungen Alt-Text-/Sprach-Generierung (Alt-Texte / Einfache Sprache) ─
$lang['common']['aborted_prefix'] = 'Abgebrochen: %message%';
$lang['alt']['generation_blocked_message'] = 'Generierung gesperrt: "Keine externen Aufrufe" ist aktiv.';
$lang['alt']['no_used_images_message'] = 'Keine verwendeten Bilder ohne Alt-Text gefunden.';
$lang['alt']['generated_summary'] = 'Erzeugt: %generated% · Erneut vorgelegt: %skipped% · Fehler: %errors%';
$lang['simple']['generation_blocked_message'] = 'Generierung gesperrt: "Keine externen Aufrufe" ist aktiv.';
$lang['simple']['generated_summary'] = 'Erzeugt: %generated% · Übersprungen: %skipped% · Fehler: %errors%';

// ── Fehler bei der Untertitel-Erzeugung ───────────────────────────────────
$lang['subtitle']['media_not_found_error'] = 'Mediendatei nicht in der Dateiverwaltung gefunden.';
$lang['subtitle']['invalid_file_path_error'] = 'Ungültiger Dateipfad.';
$lang['subtitle']['media_file_unreadable_error'] = 'Mediendatei nicht gefunden oder nicht lesbar: %file%';
$lang['subtitle']['file_too_large_for_transcription_error'] = 'Datei zu groß für die Transkription (%size% MB > 25 MB Limit). Bitte kürzen oder komprimieren.';

// ── Benachrichtigungs-E-Mail (Meldekanal) ─────────────────────────────────
$lang['feedback']['email_subject'] = 'Neue Barrierefreiheits-Meldung';
$lang['feedback']['email_no_value'] = '(keine Angabe)';
$lang['feedback']['email_body'] = "Eine neue Meldung über den Barriere-Meldekanal ist eingegangen.\n\nName: %name%\nE-Mail: %email%\nSeite: %url%\n\nNachricht:\n%message%\n";

// ── Dashboard ──────────────────────────────────────────────────────────────
$lang['dashboard']['title'] = 'Barrierefreiheit &ndash; Dashboard';
$lang['dashboard']['fullscan_legend'] = 'Voll-Scan';
$lang['dashboard']['fullscan_help'] = 'Prüft erst die <strong>Datenbank-Inhalte</strong>, dann die <strong>gerenderten Seiten</strong> (axe-core: Kontrast, DOM, Landmarks). Verändert nichts. Lass das Backend-Tab offen, bis „Fertig" erscheint.';
$lang['dashboard']['fullscan_start_btn'] = 'Voll-Scan starten';
$lang['dashboard']['db_only_btn'] = 'Nur Datenbank-Analyse (ohne Frontend)';
$lang['dashboard']['multi_domain_hint'] = 'Diese Installation bedient mehrere Domains. Frontend-Funktionen (Komfort-Overlay, Einfache Sprache, Untertitel) und der <strong>Frontend-Scan</strong> gelten je Domain – den Scan jeweils dort ausführen. Die <strong>Datenbank-Analyse</strong> arbeitet install-weit und ist davon unabhängig.';
$lang['dashboard']['multi_domain_scanned_here'] = 'wird hier gescannt';
$lang['dashboard']['multi_domain_open_backend'] = 'dortiges Backend öffnen ›';
$lang['dashboard']['multi_domain_pages_count'] = '%count% Seite(n)';
$lang['dashboard']['db_analysis_done'] = 'Datenbank-Analyse fertig. Score: %score% · Ein-Klick: %oneclick% · Manuell: %manual% · Erledigt: %done%';
$lang['dashboard']['reset_frontend_confirm'] = '%count% Frontend-Befunde zurückgesetzt. Nächster Voll-Scan prüft die Seiten neu.';
$lang['dashboard']['score_optimized'] = '%score%% optimiert';
$lang['dashboard']['score_disclaimer'] = 'Richtwert auf Basis der gefundenen Probleme – <strong>keine</strong> Konformitätsaussage.';
$lang['dashboard']['live_hint'] = ' (live · noch kein Lauf gespeichert)';
$lang['dashboard']['stat_open'] = 'Offene Befunde';
$lang['dashboard']['stat_critical'] = 'Kritisch';
$lang['dashboard']['stat_serious'] = 'Ernst';
$lang['dashboard']['stat_oneclick'] = 'Ein-Klick lösbar';
$lang['dashboard']['stat_manual'] = 'Nur manuell';
$lang['dashboard']['stat_done'] = 'Erledigt';
$lang['dashboard']['stat_frontend'] = 'Frontend (axe)';
$lang['dashboard']['stat_image_alt'] = 'Bilder ohne Alt';
$lang['dashboard']['trend_since'] = ' (seit letztem Lauf: +%new% neu, −%resolved% behoben)';
$lang['dashboard']['category_hint'] = 'Automatisch lösbar — im Modul <strong>KI-Alt-Texte</strong> erzeugen &amp; übernehmen.';
$lang['dashboard']['score_ring_aria'] = 'A11y-Score %score% von 100';
$lang['dashboard']['score_ring_caption'] = 'A11Y-SCORE';
$lang['dashboard']['frontend_section_legend'] = 'Frontend-Analyse (axe) – %count%';
$lang['dashboard']['frontend_section_empty'] = 'Keine offenen Frontend-Befunde. (Voll-Scan starten, um gerenderte Seiten zu prüfen.)';
$lang['dashboard']['frontend_section_help'] = 'Gerenderte Seiten (Kontrast, DOM, Landmarks). Triage &amp; Details im <a href="contao?do=accessplus&amp;tab=report">Bericht</a>. Bei offensichtlichen Falschmeldungen (z. B. von Animationen) zurücksetzen und neu scannen.';
$lang['dashboard']['reset_frontend_btn'] = 'Frontend-Befunde zurücksetzen';
$lang['dashboard']['column_nothing'] = 'Nichts.';
$lang['dashboard']['column_more'] = '… %count% weitere (gekürzt).';
$lang['dashboard']['column_more_report'] = '… %count% weitere (gekürzt) – siehe Bericht.';
$lang['dashboard']['run_history_title'] = 'Lauf-Verlauf';
$lang['dashboard']['run_history_time'] = 'Zeitpunkt';
$lang['dashboard']['run_history_score'] = 'Score';
