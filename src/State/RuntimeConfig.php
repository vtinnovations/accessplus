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

namespace VTInnovations\AccessPlus\State;

/**
 * Site-wide, non-list configuration persisted as JSON at
 * var/accessplus/config.json. Singleton settings that do not belong in a DCA
 * table (provider choice, WCAG target, language list, the egress kill-switch).
 *
 * SECRETS DO NOT LIVE HERE. API keys go through SecretStore (encrypted, with a
 * separate key file). This file is safe to commit-ignore and to read without a
 * key. The only key-related state here is implicit: the UI asks SecretStore
 * whether a key is set.
 *
 * Concurrency: writes use LOCK_EX; admin edits are rare and single-writer in
 * practice. Reads are unlocked (worst case: previous value during a write).
 */
final class RuntimeConfig
{
    private const CONFIG_RELATIVE_PATH = 'var/accessplus/config.json';

    /** @var array<string, mixed>|null In-process cache, cleared on write. */
    private ?array $cache = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $path = $this->getConfigPath();
        if (!is_file($path)) {
            return $this->cache = $this->getDefaults();
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return $this->cache = $this->getDefaults();
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return $this->cache = $this->getDefaults();
        }

        // Merge over defaults so newly added keys appear on old config files.
        return $this->cache = array_replace($this->getDefaults(), $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Per-root value (Modell 2). Reads roots[$rootId][$key]; falls back to the
     * global value/default when this root has no override. rootId 0 = install-wide,
     * so it just reads the global key.
     */
    public function getForRoot(int $rootId, string $key, mixed $default = null): mixed
    {
        if ($rootId > 0) {
            $roots = $this->all()['roots'] ?? [];
            if (\is_array($roots) && isset($roots[$rootId]) && \is_array($roots[$rootId]) && \array_key_exists($key, $roots[$rootId])) {
                return $roots[$rootId][$key];
            }
        }

        return $this->get($key, $default);
    }

    /**
     * True when this root explicitly overrides $key (vs. inheriting the global).
     */
    public function hasRootOverride(int $rootId, string $key): bool
    {
        if ($rootId <= 0) {
            return false;
        }
        $roots = $this->all()['roots'] ?? [];

        return \is_array($roots) && isset($roots[$rootId]) && \is_array($roots[$rootId]) && \array_key_exists($key, $roots[$rootId]);
    }

    /**
     * Merge partial overrides for one root. Passing null as a value removes that
     * override (the root re-inherits the global). rootId <= 0 is ignored.
     *
     * @param array<string, mixed> $values
     */
    public function updateForRoot(int $rootId, array $values): void
    {
        if ($rootId <= 0) {
            return;
        }

        $all = $this->all();
        $roots = \is_array($all['roots'] ?? null) ? $all['roots'] : [];
        $entry = \is_array($roots[$rootId] ?? null) ? $roots[$rootId] : [];

        foreach ($values as $k => $v) {
            if ($v === null) {
                unset($entry[$k]);
            } else {
                $entry[$k] = $v;
            }
        }

        if ($entry === []) {
            unset($roots[$rootId]);
        } else {
            $roots[$rootId] = $entry;
        }

        $this->update(['roots' => $roots]);
    }

    /**
     * Default-on egress kill-switch. When true, NO external/AI call may leave
     * the server (the project guidelines §3.2). Every egress path must consult this.
     */
    public function externalCallsBlocked(): bool
    {
        return (bool) $this->get('no_external_calls', true);
    }

    /**
     * @param array<string, mixed> $values Partial update, merged into existing.
     */
    public function update(array $values): void
    {
        $next = array_replace($this->all(), $values);
        $this->writeAll($next);
    }

    public function set(string $key, mixed $value): void
    {
        $this->update([$key => $value]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeAll(array $values): void
    {
        $path = $this->getConfigPath();
        $dir = \dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create accessplus state directory.');
        }

        $json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode accessplus configuration.');
        }

        // Atomic-ish write: temp file + rename, so a crash mid-write cannot
        // leave a half-written config.json.
        $tmp = $path . '.' . substr(md5($json), 0, 8) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write accessplus configuration.');
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to commit accessplus configuration.');
        }

        $this->cache = $values;
    }

    private function getConfigPath(): string
    {
        return $this->projectDir . '/' . self::CONFIG_RELATIVE_PATH;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            // Per-site-root overrides (Modell 2). Shape: rootId => [key => value].
            // Any key absent here falls back to the global default via getForRoot().
            'roots' => [],

            // AI provider selection (key itself is in SecretStore).
            'ai_provider' => 'openai',     // openai|compatible
            'ai_base_url' => '',           // empty = provider default endpoint
            'ai_model'    => '',           // empty = client default

            // Accessibility target.
            'wcag_target' => 'AA',         // A|AA|AAA

            // Active content languages (BCP-47-ish short codes).
            'languages'   => ['de'],

            // Whisper model for subtitle transcription (Phase 10). Empty/this
            // default = whisper-1. Provider must be openai/compatible.
            'subtitle_model' => 'whisper-1',

            // Egress kill-switch — DEFAULT ON. No external calls until the
            // admin consciously turns this off.
            'no_external_calls' => true,

            // Monitoring (Phase 6). Re-scan after content edits + via cron,
            // throttled. No secrets/egress involved (DB checks only).
            'monitor_on_save'  => true,    // re-scan after backend saves
            'monitor_interval' => 120,     // throttle window in seconds
            'last_monitor_at'  => 0,       // internal throttle clock

            // Accessibility statement (Phase 8). NOT legal advice — the admin
            // fills these and is responsible for the content.
            'statement_org'           => '',
            'statement_url'           => '',
            'statement_status'        => 'partial', // conformant|partial|nonconformant
            'statement_nonaccessible' => '',        // free text: known barriers
            'statement_contact_name'  => '',
            'statement_contact_email' => '',
            'statement_contact_phone' => '',
            'statement_prepared'      => '',         // date the statement was prepared
            'statement_method'        => 'self',     // self|external
            'statement_enforcement'   => '',         // enforcement / conciliation body text
            'feedback_recipient'      => '',         // where barrier reports are emailed

            // Plain/Easy language (Phase 11). Opt-in, default OFF. AI drafts a
            // simplified version of each page's text; published only after human
            // review. Two registers selectable. The frontend switch swaps text
            // in place (cookie-driven, no page duplicates). NOT certified Leichte
            // Sprache — a human-review guardrail is shown.
            'simple_enabled'          => false,
            'simple_registers'        => ['einfach', 'leicht'], // offered registers
            'simple_default_register' => 'einfach',
            'simple_switch_overlay'   => true,   // expose toggle inside the comfort overlay
            'simple_switch_button'    => true,   // standalone floating button
            'simple_switch_nav'       => false,  // provide the nav-link frontend module

            // Comfort overlay (Phase 9). Opt-in display layer, default OFF;
            // all features available until the admin disables individual ones.
            'overlay_enabled' => false,
            'overlay_features' => \VTInnovations\AccessPlus\Overlay\OverlayFeatures::allIds(),
            'overlay_btn_color' => '#1d4ed8',
            'overlay_position' => 'bottomright',
        ];
    }
}
