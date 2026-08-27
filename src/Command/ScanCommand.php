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
use VTInnovations\AccessPlus\Check\LintRunner;
use VTInnovations\AccessPlus\Check\Severity;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Runs the database linter from the CLI: `vendor/bin/contao-console accessplus:scan`.
 * Same engine as the backend "Scan jetzt" button, so it fits cron OR manual
 * use — no hard cron dependency (the project guidelines §2).
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
#[AsCommand(name: 'accessplus:scan', description: 'Run the accessibility checks over the content.')]
final class ScanCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LintRunner $runner,
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
        $summary = $this->runner->run();

        $io->success(Text::get('command.scan.done_confirm', [
            'new' => $summary->created,
            'reopened' => $summary->reopened,
            'resolved' => $summary->resolved,
            'open' => $summary->openTotal,
            'score' => $summary->score,
        ]));

        foreach ($summary->bySeverity as $severity => $count) {
            $label = Severity::tryFrom($severity)?->label() ?? $severity;
            $io->writeln(sprintf('  %s: %d', $label, $count));
        }

        return Command::SUCCESS;
    }
}
