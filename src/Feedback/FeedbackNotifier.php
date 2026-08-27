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

namespace VTInnovations\AccessPlus\Feedback;

use Contao\Email;
use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\FeedbackModel;

/**
 * Emails a new barrier report to the configured recipient. Uses Contao's Email
 * class (portable 4.13/5.x). A send failure is logged but never bubbles up — the
 * report is already saved in the DB, so notification is best-effort.
 *
 * The reporter's values only ever go into the mail BODY (plaintext), never into
 * headers, so there is no header-injection surface.
 */
final class FeedbackNotifier
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(string $recipient, FeedbackModel $entry): void
    {
        if ($recipient === '') {
            return;
        }

        try {
            $noValue = Text::get('feedback.email_no_value');

            $email = new Email();
            $email->subject = Text::get('feedback.email_subject');
            $email->text = Text::get('feedback.email_body', [
                'name' => (string) $entry->name !== '' ? (string) $entry->name : $noValue,
                'email' => (string) $entry->email !== '' ? (string) $entry->email : $noValue,
                'url' => (string) $entry->url !== '' ? (string) $entry->url : $noValue,
                'message' => (string) $entry->message,
            ]);
            $email->sendTo($recipient);
        } catch (\Throwable $e) {
            $this->logger->warning('a11y feedback notification failed', ['reason' => $e->getMessage()]);
        }
    }
}
