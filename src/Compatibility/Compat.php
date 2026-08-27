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

namespace VTInnovations\AccessPlus\Compatibility;

use Contao\CoreBundle\ContaoCoreBundle;

/**
 * Single source of truth for "does the running Contao/Symfony stack support
 * feature X?". The rest of the bundle MUST ask this service instead of doing
 * scattered version_compare()/class_exists() checks (the project guidelines §2).
 *
 * Detection strategy, in order of preference:
 *   1. class_exists()/interface_exists() on a marker symbol — robust against
 *      backports (e.g. zoglo shipping a 5.6 feature on 5.3).
 *   2. Contao version range as a fallback signal, ONLY inside this class.
 *
 * Bias: when a marker is uncertain we return the CONSERVATIVE answer (false),
 * so later phases degrade to the safe, portable code path rather than calling
 * an API that might not exist on the host.
 *
 * The marker class names below are best-effort and flagged in the Phase-1
 * handoff log as "verify on a real 4.13 and 5.7 install".
 */
final class Compat
{
    /**
     * Candidate marker classes for the Twig front-end slot/block API that
     * landed in Contao 5.6. If any is present we assume the slot API is usable.
     *
     * @var list<class-string|string>
     */
    private const TWIG_SLOT_MARKERS = [
        'Contao\\CoreBundle\\Twig\\Slots\\SlotWidgetContext',
        'Contao\\CoreBundle\\Twig\\Slots\\SlotRenderer',
    ];

    /**
     * Candidate marker classes for the 5.6 background-task framework.
     *
     * @var list<class-string|string>
     */
    private const BACKGROUND_TASK_MARKERS = [
        'Contao\\CoreBundle\\BackgroundTask\\BackgroundTask',
        'Contao\\CoreBundle\\Job\\Job',
    ];

    private const HOOK_ATTRIBUTE = 'Contao\\CoreBundle\\DependencyInjection\\Attribute\\AsHook';

    private const CALLBACK_ATTRIBUTE = 'Contao\\CoreBundle\\DependencyInjection\\Attribute\\AsCallback';

    /** Cached "5.4", "4.13.49", … — resolved once per request. */
    private ?string $contaoVersion = null;

    /**
     * The Twig front-end slots/blocks API (Contao 5.6+). Phase 2+ optional
     * front-end building blocks gate on this.
     */
    public function hasTwigFrontendSlots(): bool
    {
        return $this->anyClassExists(self::TWIG_SLOT_MARKERS);
    }

    /**
     * The native background-task service (Contao 5.6+). Monitoring (Phase 6)
     * uses it when present and falls back to a plain command otherwise.
     */
    public function hasBackgroundTaskFramework(): bool
    {
        return $this->anyClassExists(self::BACKGROUND_TASK_MARKERS);
    }

    /**
     * Whether `#[AsHook]` / `#[AsCallback]` attribute registration is reliably
     * available. The attributes exist since 4.13; when this is false the bundle
     * registers hooks/callbacks via config.php/services.yaml instead.
     */
    public function hasAttributeRegistration(): bool
    {
        return class_exists(self::HOOK_ATTRIBUTE) && class_exists(self::CALLBACK_ATTRIBUTE);
    }

    /**
     * Whether the host already provides native or zoglo-backported a11y
     * templates (ARIA nav, video <track>, autocomplete). Used by the linter
     * (Phase 2) to avoid recommending what is already there. Best-effort:
     * present from Contao 5.6 in core, or when the zoglo bundle is installed.
     */
    public function hasNativeAccessibilityTemplates(): bool
    {
        if (class_exists('Zoglo\\ContaoAccessibilityBundle\\ContaoAccessibilityBundle')) {
            return true;
        }

        return $this->isContaoAtLeast(5, 6);
    }

    /**
     * Major.minor convenience flag for callers that genuinely need the Contao
     * generation (e.g. choosing the `invisible` vs `published` column).
     */
    public function isContao5(): bool
    {
        return $this->isContaoAtLeast(5, 0);
    }

    /**
     * The raw Contao version string, e.g. "5.3.10". Empty string if it cannot
     * be resolved (should not happen inside a booted Contao).
     */
    public function contaoVersion(): string
    {
        if ($this->contaoVersion !== null) {
            return $this->contaoVersion;
        }

        $version = '';

        if (method_exists(ContaoCoreBundle::class, 'getVersion')) {
            /** @var string $version */
            $version = (string) ContaoCoreBundle::getVersion();
        }

        return $this->contaoVersion = $version;
    }

    public function isContaoAtLeast(int $major, int $minor = 0): bool
    {
        $version = $this->contaoVersion();
        if ($version === '') {
            return false;
        }

        $parts = explode('.', $version);
        $haveMajor = (int) ($parts[0] ?? 0);
        $haveMinor = (int) ($parts[1] ?? 0);

        if ($haveMajor !== $major) {
            return $haveMajor > $major;
        }

        return $haveMinor >= $minor;
    }

    /**
     * @param list<class-string|string> $candidates
     */
    private function anyClassExists(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (class_exists($candidate) || interface_exists($candidate)) {
                return true;
            }
        }

        return false;
    }
}
