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

namespace VTInnovations\AccessPlus\Controller;

use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Scope gate for the barrier-report inbox (do=accessplus_feedback).
 *
 * Without a licensed site root the module turns read-only and says why. Reports
 * already received stay visible on purpose: they are the site operator's legal
 * correspondence, not a bundle feature we may take away.
 *
 * Instantiated by Contao via `new` as a DCA onload_callback, so it has no
 * constructor arguments.
 */
final class FeedbackInboxGate
{
    public function onLoad(?DataContainer $dc = null): void
    {
        $provider = System::getContainer()->get(SiteStatusProvider::class);

        if ($provider->hasAnyActive()) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_accessplus_feedback'];
        $dca['config']['notEditable'] = true;
        $dca['config']['notDeletable'] = true;
        $dca['config']['notSortable'] = true;
        $dca['config']['closed'] = true;

        Message::addInfo(Text::get('feedback.inbox_readonly_notice'));
    }
}
