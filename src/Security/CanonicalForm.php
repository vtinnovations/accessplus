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

namespace VTInnovations\AccessPlus\Security;

/**
 * Deterministic byte forms shared with the issuing service. Two independent
 * implementations (ours and theirs) must produce identical bytes, therefore this
 * class contains no options, no locale-dependent behaviour and no map ordering
 * that depends on PHP's insertion order.
 *
 * Two message shapes exist:
 *
 *  - canonical document form ("canonical-json-v1"): drop the top-level
 *    `signature` member, sort every object's members bytewise, keep list order,
 *    emit compact UTF-8 JSON without escaped slashes or escaped Unicode, and
 *    keep scalar types exactly (false is not "false", null is not 0).
 *  - canonical request form ("request-sig-v1"): six lines joined with \n and no
 *    trailing newline — method (uppercase), path as served, request id, decimal
 *    timestamp, nonce, lowercase hex SHA-256 of the raw body. The key-id header
 *    selects the key and is deliberately NOT part of the signed lines.
 *
 * Pure static helpers on purpose: no state, no service, nothing to swap out at
 * runtime, and callable from the tests without a container.
 */
final class CanonicalForm
{
    /**
     * Canonical bytes of a document/envelope for signature verification.
     *
     * @param array<string, mixed> $data
     */
    public static function document(array $data): string
    {
        unset($data['signature']);

        $json = json_encode(self::sorted($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!\is_string($json)) {
            throw new PackageRejected('canonical_form_failed');
        }

        return $json;
    }

    /**
     * The six-line message an inbound service callback signs.
     */
    public static function request(
        string $method,
        string $path,
        string $requestId,
        int $timestamp,
        string $nonce,
        string $bodyHashHex,
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $requestId,
            (string) $timestamp,
            $nonce,
            $bodyHashHex,
        ]);
    }

    /**
     * Strict Base64: rejects whitespace, non-canonical padding and alphabet
     * violations instead of silently decoding "close enough" input.
     */
    public static function fromBase64(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        if (!\is_string($decoded) || $decoded === '' || base64_encode($decoded) !== $value) {
            return null;
        }

        return $decoded;
    }

    /**
     * Lowercase hex SHA-256 of raw bytes (body fingerprints, never logged).
     */
    public static function bodyHash(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * Constant-time comparison for every digest/signature equality check.
     */
    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * Recursively sorts object members bytewise while preserving list order and
     * scalar types.
     */
    private static function sorted(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $out = [];
            foreach ($value as $item) {
                $out[] = self::sorted($item);
            }

            return $out;
        }

        ksort($value, SORT_STRING);

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = self::sorted($item);
        }

        return $out;
    }
}
