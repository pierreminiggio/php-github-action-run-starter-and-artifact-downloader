<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use Exception;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDeleter\GithubActionRunDeleter;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\Service\ActionRunStarter;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\Service\RunCompletionResolver;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class GithubActionAsyncRunStarterAndArtifactDownloader
{
    use HasRunCreationCheckSettings;

    private RunCompletionResolver $runCompletionResolver;

    public function __construct(
        GithubActionRunsLister $runLister,
        GithubActionRunCreator $runCreator,
        MostRecentRunFinder $mostRecentRunFinder,
        private GithubActionRunDetailer $runDetailer,
        GithubActionRunArtifactsLister $artifactLister,
        GithubActionArtifactDownloader $artifactDownloader,
        GithubActionRunDeleter $runDeleter,
        private string $ticketEncryptionKey
    )
    {
        $this->runStarter = new ActionRunStarter($runLister, $runCreator, $mostRecentRunFinder);
        $this->runCompletionResolver = new RunCompletionResolver($artifactLister, $artifactDownloader, $runDeleter);
    }

    /**
     * Dispatches the workflow run and blocks only until the run is located on
     * Github (a handful of short polls, governed by the same two settings as
     * the synchronous class) — it does NOT wait for the run to finish.
     *
     * Returns a single opaque "run ticket" string with the run's id already
     * baked in. Persist it as-is (e.g. in a database column); it never changes
     * and it's all checkAction() needs.
     *
     * @param array<string, mixed> $inputs
     *
     * @throws GithubActionRunStarterAndArtifactDownloaderException if the API
     *         calls fail, or if the run can't be located after retrying
     */
    public function startAction(
        string $token,
        string $owner,
        string $repo,
        string $workflowIdOrWorkflowFileName,
        array $inputs = [],
        string $ref = 'main',
        bool $deleteAfterDownloading = false
    ): string
    {
        $run = $this->runStarter->startAndLocateRun(
            $token,
            $owner,
            $repo,
            $workflowIdOrWorkflowFileName,
            $inputs,
            $ref
        );

        $handle = new GithubActionRunHandle($token, $owner, $repo, $run->id, $deleteAfterDownloading);

        return $handle->encode($this->ticketEncryptionKey);
    }

    /**
     * Checks on a run previously started with startAction(), using only the
     * ticket it returned. Never sleeps, never blocks — safe to call from a
     * cron job, queue worker, or webhook handler as often as you like.
     *
     * @throws GithubActionRunStarterAndArtifactDownloaderException if the
     *         ticket can't be decoded, or if the Github API call to check the
     *         run's status fails. Does NOT throw when the run itself finished
     *         unsuccessfully, or when a post-success step (listing/downloading/
     *         deleting artifacts) fails — both are reported through the
     *         returned GithubActionCheckResult instead.
     */
    public function checkAction(string $runTicket): GithubActionCheckResult
    {
        $handle = GithubActionRunHandle::decode($runTicket, $this->ticketEncryptionKey);

        try {
            $currentRun = $this->runDetailer->find(
                $handle->getOwner(),
                $handle->getRepo(),
                $handle->getRunId(),
                $handle->getToken()
            );
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        if ($currentRun->status !== GithubStatusesEnum::COMPLETED) {
            return GithubActionCheckResult::pending();
        }

        if ($currentRun->conclusion !== ConclusionsEnum::SUCCESS) {
            return GithubActionCheckResult::failed(
                "Run {$handle->getRunId()} in {$handle->getOwner()}/{$handle->getRepo()} "
                . "completed with conclusion \"{$currentRun->conclusion}\" "
                . "(expected \"" . ConclusionsEnum::SUCCESS . "\")"
            );
        }

        try {
            $artifacts = $this->runCompletionResolver->resolve(
                $handle->getToken(),
                $handle->getOwner(),
                $handle->getRepo(),
                $currentRun,
                $handle->shouldDeleteAfterDownloading()
            );
        } catch (GithubActionRunStarterAndArtifactDownloaderException $e) {
            return GithubActionCheckResult::failed($e->getMessage());
        }

        return GithubActionCheckResult::success($artifacts);
    }
}
