<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

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

class GithubActionRunStarterAndArtifactDownloader
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
        GithubActionRunDeleter $runDeleter
    )
    {
        $this->runStarter = new ActionRunStarter($runLister, $runCreator, $mostRecentRunFinder);
        $this->runCompletionResolver = new RunCompletionResolver($artifactLister, $artifactDownloader, $runDeleter);
    }

    /**
     * @param array<string, mixed> $inputs
     * @param int $refreshTime in seconds
     *
     * @return string[] artifacts' file paths
     *
     * @throws GithubActionRunStarterAndArtifactDownloaderException
     */
    public function runActionAndGetArtifacts(
        string $token,
        string $owner,
        string $repo,
        string $workflowIdOrWorkflowFileName,
        int $refreshTime = 30,
        int $retries = 0,
        array $inputs = [],
        string $ref = 'main',
        bool $deleteAfterDownloading = false
    ): array
    {
        $currentRun = $this->runStarter->startAndLocateRun(
            $token,
            $owner,
            $repo,
            $workflowIdOrWorkflowFileName,
            $inputs,
            $ref
        );

        while ($currentRun->status !== GithubStatusesEnum::COMPLETED) {
            sleep($refreshTime);
            $currentRun = $this->runDetailer->find($owner, $repo, $currentRun->id, $token);
        }

        if ($currentRun->conclusion !== ConclusionsEnum::SUCCESS) {
            if ($retries === 0) {
                throw new GithubActionRunStarterAndArtifactDownloaderException("Run {$currentRun->id} failed");
            }

            return $this->runActionAndGetArtifacts(
                $token,
                $owner,
                $repo,
                $workflowIdOrWorkflowFileName,
                $refreshTime,
                $retries - 1,
                $inputs,
                $ref,
                $deleteAfterDownloading
            );
        }

        return $this->runCompletionResolver->resolve($token, $owner, $repo, $currentRun, $deleteAfterDownloading);
    }
}
