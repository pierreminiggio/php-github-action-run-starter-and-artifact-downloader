<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\Service;

use Exception;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRun\GithubActionRun;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunDeleter\GithubActionRunDeleter;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderException;

/**
 * Turns an already-completed run into downloaded artifacts (and optionally
 * deletes the run afterwards). Shared by the synchronous and asynchronous
 * classes: both reach this exact same step once a run's status is COMPLETED.
 */
class RunCompletionResolver
{

    public function __construct(
        private GithubActionRunArtifactsLister $artifactLister,
        private GithubActionArtifactDownloader $artifactDownloader,
        private GithubActionRunDeleter $runDeleter
    )
    {
    }

    /**
     * @return string[] artifacts' file paths
     *
     * @throws GithubActionRunStarterAndArtifactDownloaderException
     */
    public function resolve(
        string $token,
        string $owner,
        string $repo,
        GithubActionRun $completedRun,
        bool $deleteAfterDownloading
    ): array
    {
        try {
            $artifacts = $this->artifactLister->list($owner, $repo, $completedRun->id, $token);
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        $files = [];

        foreach ($artifacts as $artifact) {
            try {
                $files = array_merge($files, $this->artifactDownloader->download(
                    $token,
                    $owner,
                    $repo,
                    $artifact->id
                ));
            } catch (Exception $e) {
                throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
            }
        }

        if ($deleteAfterDownloading) {
            try {
                $this->runDeleter->delete($token, $owner, $repo, $completedRun->id);
            } catch (Exception $e) {
                throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
            }
        }

        return $files;
    }
}
