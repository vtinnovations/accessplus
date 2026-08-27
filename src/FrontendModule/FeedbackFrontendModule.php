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

namespace VTInnovations\AccessPlus\FrontendModule;

use Contao\BackendTemplate;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Module;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use VTInnovations\AccessPlus\Feedback\FeedbackNotifier;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\FeedbackModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Frontend barrier-report form (BFSG feedback channel). No symfony/form — a
 * hand-built form validated server-side with the Contao request token plus a
 * honeypot. On success it stores a tl_accessplus_feedback row and emails the
 * configured recipient. User input is stored as plaintext and escaped on output.
 */
final class FeedbackFrontendModule extends Module
{
    protected $strTemplate = 'mod_accessplus_feedback';

    public function generate(): string
    {
        if ($this->isBackendRequest()) {
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = Text::get('feedback_form.wildcard');

            return $template->parse();
        }

        if (!$this->isLicensedRoot()) {
            return '';
        }

        return parent::generate();
    }

    protected function compile(): void
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();

        $errors = [];
        $confirmed = false;
        $values = ['name' => '', 'email' => '', 'url' => '', 'message' => ''];

        if ($request instanceof Request && $request->isMethod('POST') && $request->request->has('accessplus_feedback')) {
            [$confirmed, $errors, $values] = $this->process($request, $container);
        }

        // The form's REQUEST_TOKEN is emitted via the {{request_token}} insert
        // tag in the template, so it survives the page cache.
        $this->Template->confirmed = $confirmed;
        $this->Template->errors = $errors;
        $this->Template->values = $values;
    }

    /**
     * @return array{0: bool, 1: list<string>, 2: array<string, string>}
     */
    private function process(Request $request, object $container): array
    {
        $values = [
            'name' => trim((string) $request->request->get('name', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'url' => trim((string) $request->request->get('url', '')),
            'message' => trim((string) $request->request->get('message', '')),
        ];

        // Honeypot: real users leave this hidden field empty.
        if (trim((string) $request->request->get('website', '')) !== '') {
            return [true, [], $values]; // silently accept-and-drop bots
        }

        $tokenName = (string) $container->getParameter('contao.csrf_token_name');
        /** @var ContaoCsrfTokenManager $csrf */
        $csrf = $container->get('contao.csrf.token_manager');
        if (!$csrf->isTokenValid(new CsrfToken($tokenName, (string) $request->request->get('REQUEST_TOKEN', '')))) {
            return [false, [Text::get('feedback.error_invalid_token')], $values];
        }

        $errors = [];
        if ($values['message'] === '' || mb_strlen($values['message']) > 5000) {
            $errors[] = Text::get('feedback.error_message_required');
        }
        if ($values['email'] !== '' && !filter_var($values['email'], \FILTER_VALIDATE_EMAIL)) {
            $errors[] = Text::get('feedback.error_email_invalid');
        }
        if ($values['url'] !== '' && !preg_match('#^https?://#i', $values['url'])) {
            $errors[] = Text::get('feedback.error_url_invalid');
        }
        if (mb_strlen($values['name']) > 128) {
            $errors[] = Text::get('feedback.error_name_too_long');
        }

        if ($errors !== []) {
            return [false, $errors, $values];
        }

        $now = time();
        $entry = new FeedbackModel();
        $entry->tstamp = $now;
        $entry->createdAt = $now;
        $entry->name = mb_substr($values['name'], 0, 128);
        $entry->email = mb_substr($values['email'], 0, 255);
        $entry->url = mb_substr($values['url'], 0, 2048);
        $entry->message = $values['message'];
        $entry->status = 'new';
        $entry->save();

        /** @var RuntimeConfig $config */
        $config = $container->get(RuntimeConfig::class);
        // Modell 2: reports go to the recipient configured for THIS domain's root.
        $rootId = isset($GLOBALS['objPage']) ? (int) ($GLOBALS['objPage']->rootId ?? 0) : 0;
        $recipient = (string) $config->getForRoot($rootId, 'feedback_recipient', '');
        if ($recipient === '') {
            $recipient = (string) $config->getForRoot($rootId, 'statement_contact_email', '');
        }

        /** @var FeedbackNotifier $notifier */
        $notifier = $container->get(FeedbackNotifier::class);
        $notifier->notify($recipient, $entry);

        return [true, [], ['name' => '', 'email' => '', 'url' => '', 'message' => '']];
    }

    private function isBackendRequest(): bool
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        return $request !== null
            && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request);
    }

    /**
     * Scope gate. An unlicensed site root must behave as if this bundle were not
     * installed, so the module renders nothing at all there.
     */
    private function isLicensedRoot(): bool
    {
        $rootId = isset($GLOBALS['objPage']) ? (int) ($GLOBALS['objPage']->rootId ?? 0) : 0;

        return System::getContainer()->get(SiteStatusProvider::class)->isActive($rootId);
    }
}
