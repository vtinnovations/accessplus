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
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\SiteStatusProvider;
use VTInnovations\AccessPlus\Subtitle\MediaLoadException;
use VTInnovations\AccessPlus\Subtitle\SubtitleService;

/**
 * Batch-generates subtitle DRAFTS (WebVTT) for media files lacking one
 * (`vendor/bin/contao-console accessplus:subtitles:generate --lang=de --limit=10`).
 * Useful for long files that would exceed a web request's time limit. Drafts
 * land in review (pending) — never published automatically. Honours the egress
 * kill-switch.
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
#[AsCommand(name: 'accessplus:subtitles:generate', description: 'Generate AI subtitle drafts (VTT) for media without subtitles (review).')]
final class GenerateSubtitlesCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SubtitleService $service,
        private readonly SiteStatusProvider $siteStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language code (ISO 639-1)', 'de');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of files', '10');
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
        $lang = (string) $input->getOption('lang');
        $limit = max(1, (int) $input->getOption('limit'));

        $done = 0;
        $errors = 0;

        foreach ($this->service->listCandidates() as $candidate) {
            if ($done >= $limit) {
                break;
            }

            // Skip already-processed and oversized files.
            if ($candidate->tooLarge() || ($candidate->track !== null && (string) $candidate->track->status !== 'rejected')) {
                continue;
            }

            try {
                $track = $this->service->generate($candidate->uuid, $lang);
                $io->writeln(sprintf('<info>%s</info> %s (%d ms)', Text::get('command.subtitles.ok_tag'), $track->sourcePath, (int) $track->durationMs));
                ++$done;
            } catch (AiException $e) {
                $io->writeln(sprintf('<error>%s</error> %s: %s', Text::get('command.subtitles.error_tag'), $candidate->path, $e->getMessage()));
                ++$errors;

                if (!$e->isRetryable()) {
                    $io->warning(Text::get('command.subtitles.fatal_abort'));
                    break;
                }
            } catch (MediaLoadException $e) {
                $io->writeln(sprintf('<comment>%s</comment> %s: %s', Text::get('command.subtitles.skipped_tag'), $candidate->path, $e->getMessage()));
            }
        }

        $io->success(Text::get('command.subtitles.done_confirm', ['done' => $done, 'errors' => $errors]) . Text::get('command.review_suffix'));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
