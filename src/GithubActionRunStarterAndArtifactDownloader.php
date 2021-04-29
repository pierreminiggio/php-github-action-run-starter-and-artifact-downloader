<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use Exception;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

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
        int $refreshTime,
        int $retries = 0,
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

        sleep(10);

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

        while (true) {

            if ($currentRun->status === GithubStatusesEnum::COMPLETED) {
                break;
            }

            sleep($refreshTime);
            $currentRun = $this->runDetailer->find($owner, $repo, $currentRun->id);
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
                $ref
            );
        } 

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
