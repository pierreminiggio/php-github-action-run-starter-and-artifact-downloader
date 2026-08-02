<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

final class GithubActionRunHandle
{

    private const CIPHER = 'aes-256-gcm';
    private const VERSION_PREFIX = 'v1:';
    private const TAG_LENGTH = 16; // bytes, openssl's default/standard GCM tag length

    private const REQUIRED_KEYS = ['token', 'owner', 'repo', 'runId', 'deleteAfterDownloading'];

    public function __construct(
        private string $token,
        private string $owner,
        private string $repo,
        private int $runId,
        private bool $deleteAfterDownloading
    )
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepo(): string
    {
        return $this->repo;
    }

    public function getRunId(): int
    {
        return $this->runId;
    }

    public function shouldDeleteAfterDownloading(): bool
    {
        return $this->deleteAfterDownloading;
    }

    /**
     * Encodes this handle into a single opaque, storable string (the "run ticket").
     */
    public function encode(string $encryptionKey): string
    {
        $payload = json_encode([
            'token' => $this->token,
            'owner' => $this->owner,
            'repo' => $this->repo,
            'runId' => $this->runId,
            'deleteAfterDownloading' => $this->deleteAfterDownloading,
        ]);

        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $payload,
            self::CIPHER,
            self::deriveKey($encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Could not encode run ticket');
        }

        return self::VERSION_PREFIX . rtrim(strtr(base64_encode($iv . $tag . $ciphertext), '+/', '-_'), '=');
    }

    /**
     * @throws GithubActionRunStarterAndArtifactDownloaderException if the ticket is
     *         malformed, tampered with, was encoded with a different key, or is
     *         missing one of the fields it's expected to carry
     */
    public static function decode(string $runTicket, string $encryptionKey): self
    {
        if (strpos($runTicket, self::VERSION_PREFIX) !== 0) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Unrecognized run ticket format');
        }

        $encoded = substr($runTicket, strlen(self::VERSION_PREFIX));
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($raw === false) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Malformed run ticket');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($raw) <= $ivLength + self::TAG_LENGTH) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Malformed run ticket');
        }

        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($raw, $ivLength + self::TAG_LENGTH);

        $payload = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::deriveKey($encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($payload === false) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Invalid or tampered run ticket');
        }

        $data = json_decode($payload, true);

        if (!is_array($data)) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Corrupt run ticket payload');
        }

        foreach (self::REQUIRED_KEYS as $requiredKey) {
            if (!array_key_exists($requiredKey, $data)) {
                throw new GithubActionRunStarterAndArtifactDownloaderException(
                    "Corrupt run ticket payload, missing \"{$requiredKey}\""
                );
            }
        }

        return new self(
            $data['token'],
            $data['owner'],
            $data['repo'],
            $data['runId'],
            $data['deleteAfterDownloading']
        );
    }

    private static function deriveKey(string $encryptionKey): string
    {
        return hash('sha256', $encryptionKey, true);
    }
}
