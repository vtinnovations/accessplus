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

namespace VTInnovations\AccessPlus\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Pdf\PdfScanService;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * CLI PDF accessibility scan: `vendor/bin/contao-console accessplus:pdf:scan`.
 *
 * The name, description and option help below are plain English literals,
 * not translated: Symfony Console reads them via configure() to build `list`
 * and `--help` output, which runs before Contao's framework is initialized
 * for this command (execute() is what calls $framework->initialize(), and
 * --help never reaches execute() at all). CLI metadata therefore cannot
 * depend on request-scoped services such as the language loader. The
 * command's actual runtime output below runs inside execute(), after the
 * framework is booted, and is fully translated.
 */
#[AsCommand(name: 'accessplus:pdf:scan', description: 'Check linked PDFs for title, language and tags.')]
final class PdfScanCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly PdfScanService $service,
        private readonly SiteStatusProvider $siteStatus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        // Scope gate: the CLI works across the installation, so it needs at least
        // one licensed site root. Content of unlicensed roots stays untouched
        // (the linter and the services enforce that per root as well).
        if (!$this->siteStatus->hasAnyActive()) {
            $output->writeln('<error>' . Text::get('command.no_license_error') . '</error>');

            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $summary = $this->service->scan();

        $io->success(Text::get('command.pdf.done_confirm', [
            'checked' => $summary->checked,
            'issues' => $summary->issues,
            'unknown' => $summary->unknown,
            'unreadable' => $summary->unreadable,
        ]));

        return Command::SUCCESS;
    }
}
