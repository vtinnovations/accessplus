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
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Simplify\SimplifyService;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Batch-generates plain/easy-language DRAFTS for every published page
 * (`vendor/bin/contao-console accessplus:simplify --register=einfach`). Useful for
 * "a simple version of every page" without hitting a web request's time limit.
 * Drafts land in review (pending) — never published automatically. Honours the
 * egress kill-switch.
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
#[AsCommand(name: 'accessplus:simplify', description: 'Generate AI drafts in plain/easy language for all pages (review).')]
final class GenerateSimpleCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SimplifyService $service,
        private readonly SiteStatusProvider $siteStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('register', null, InputOption::VALUE_REQUIRED, 'einfach|leicht', 'einfach');
        $this->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language code (ISO 639-1)', 'de');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of text snippets', '1000');
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
        $register = (string) $input->getOption('register');
        $lang = (string) $input->getOption('lang');
        $limit = max(1, (int) $input->getOption('limit'));

        // Site-wide (pageId 0): covers every content element, including theme/
        // module-included sections (product cards etc.) not attached to a page's
        // own articles.
        $summary = $this->service->generateForPage(0, $register, $lang, $limit);

        if ($summary->blocked) {
            $io->warning($summary->message);

            return Command::SUCCESS;
        }

        $io->success($summary->message . Text::get('command.review_suffix'));

        return $summary->errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
