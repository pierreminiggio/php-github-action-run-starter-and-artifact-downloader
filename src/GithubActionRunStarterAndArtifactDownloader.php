<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use Exception;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;

class GithubActionRunStarterAndArtifactDownloader
{

    public function __construct(
        private GithubActionRunsLister $runLister,
        private GithubActionRunCreator $runCreator,
        private MostRecentRunFinder $mostRecentRunFinder
    )
    {
    }

    /**
     * @param array<string, mixed> $inputs
     * 
     * @throws GithubActionRunStarterAndArtifactDownloaderException
     */
    public function runActionAndGetArtifacts(
        string $token,
        string $owner,
        string $repo,
        string $workflowIdOrWorkflowFileName,
        array $inputs = [],
        string $ref = 'main'
    ): array
    {

        $runListerArgs = [$owner, $repo, $workflowIdOrWorkflowFileName];

        try {
            $previousRuns = $this->runLister->list(...$runListerArgs);
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        try {
            $this->runCreator->create(
                $token,
                $owner,
                $repo,
                $workflowIdOrWorkflowFileName,
                $inputs,
                $ref
            );
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        try {
            $newRuns = $this->runLister->list(...$runListerArgs);
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        $previousRunsCount = count($previousRuns);
        $newRunsCount = count($newRuns);

        if ($previousRunsCount >= $newRunsCount) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Run was not created ?');
        }

        if ($previousRunsCount + 1 < $newRunsCount) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('More than 1 run was not created ?');
        }

        $currentRun = $this->mostRecentRunFinder->find($newRuns);
        
        return [];
    }
}
