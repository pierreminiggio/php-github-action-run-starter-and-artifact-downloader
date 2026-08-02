<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

final class GithubActionCheckResult
{

    /**
     * @param string[]|null $artifacts file paths, only set when status is SUCCESS
     */
    private function __construct(
        private string $status,
        private ?array $artifacts = null,
        private ?string $failureReason = null
    )
    {
    }

    public static function pending(): self
    {
        return new self(GithubActionCheckStatusEnum::PENDING);
    }

    public static function failed(string $failureReason): self
    {
        return new self(GithubActionCheckStatusEnum::FAILED, null, $failureReason);
    }

    /**
     * @param string[] $artifacts
     */
    public static function success(array $artifacts): self
    {
        return new self(GithubActionCheckStatusEnum::SUCCESS, $artifacts);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === GithubActionCheckStatusEnum::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === GithubActionCheckStatusEnum::FAILED;
    }

    public function isSuccessful(): bool
    {
        return $this->status === GithubActionCheckStatusEnum::SUCCESS;
    }

    /**
     * @return string[]|null null unless isSuccessful()
     */
    public function getArtifacts(): ?array
    {
        return $this->artifacts;
    }

    /**
     * null unless isFailed(). Carries as much detail as Github / the underlying
     * exception gave us: the run's conclusion when the run itself failed, or the
     * wrapped exception's class + message when artifact listing/downloading/
     * deletion broke after a successful run.
     */
    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }
}
