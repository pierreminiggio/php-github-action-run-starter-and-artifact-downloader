<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use Exception;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;

class GithubActionRunStarterAndArtifactDownloader
{

    public function __construct(
        private GithubActionRunsLister $runLister,
        private GithubActionRunCreator $runCreator,
        private MostRecentRunFinder $mostRecentRunFinder,
        private GithubActionRunDetailer $runDetailer,
        private GithubActionRunArtifactsLister $artifactLister,
        private GithubActionArtifactDownloader $artifactDownloader
    )
    {
    }

    /**
     * @param array<string, mixed> $inputs
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

        try {
            $artifacts = $this->artifactLister->list($owner, $repo, $currentRun->id);
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
        
        return $files;
    }
}
