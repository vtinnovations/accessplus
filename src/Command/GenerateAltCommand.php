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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use VTInnovations\AccessPlus\Alt\AltSuggestionService;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Batch-generates alt-text PROPOSALS for images with a missing alt
 * (`vendor/bin/contao-console accessplus:alt:generate --limit=25`). Proposals land
 * in review (pending) — never published automatically. Honours the egress
 * kill-switch; with it on, the run is a no-op with a clear message.
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
#[AsCommand(name: 'accessplus:alt:generate', description: 'Generate AI alt-text suggestions for images without alt text (review).')]
final class GenerateAltCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AltSuggestionService $service,
        private readonly SiteStatusProvider $siteStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of suggestions', '25');
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
        $limit = max(1, (int) $input->getOption('limit'));

        $summary = $this->service->generateForMissing($limit);

        if ($summary->blocked) {
            $io->warning($summary->message);

            return Command::SUCCESS;
        }

        $io->success($summary->message);

        return $summary->errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
