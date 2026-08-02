<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\Service;

use Exception;
use PierreMiniggio\GithubActionRun\GithubActionRun;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderException;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;

/**
 * Dispatches a workflow run and locates it on Github. Shared by the synchronous
 * and asynchronous run starters, since both need the exact same "how do we tell
 * which run we just created" logic. Does NOT wait for the run to finish.
 */
class ActionRunStarter
{

    public int $sleepTimeBetweenRunCreationChecks = 10; // seconds
    public int $numberOfRunCreationChecksBeforeAssumingItsNotCreated = 10;

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
    public function startAndLocateRun(
        string $token,
        string $owner,
        string $repo,
        string $workflowIdOrWorkflowFileName,
        array $inputs = [],
        string $ref = 'main'
    ): GithubActionRun
    {
        $runListerArgs = [$owner, $repo, $workflowIdOrWorkflowFileName, $token];

        try {
            $previousRunsCount = $this->runLister->list(...$runListerArgs)->totalCount;
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

        return $this->getCreatedRun(
            $runListerArgs,
            $previousRunsCount,
            $this->numberOfRunCreationChecksBeforeAssumingItsNotCreated
        );
    }

    /**
     * @param string[] $runListerArgs [$owner, $repo, $workflowIdOrWorkflowFileName, ?$token]
     *
     * @throws GithubActionRunStarterAndArtifactDownloaderException
     */
    private function getCreatedRun(array $runListerArgs, int $previousRunsCount, int $triesLeft): GithubActionRun
    {
        if ($triesLeft <= 0) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('Run was not created ?');
        }

        sleep($this->sleepTimeBetweenRunCreationChecks);

        try {
            $newRunsResponse = $this->runLister->list(...$runListerArgs);
        } catch (Exception $e) {
            throw GithubActionRunStarterAndArtifactDownloaderException::makeFromException($e);
        }

        $newRunsCount = $newRunsResponse->totalCount;

        if ($previousRunsCount >= $newRunsCount) {
            return $this->getCreatedRun($runListerArgs, $previousRunsCount, $triesLeft - 1);
        }

        if ($previousRunsCount + 1 < $newRunsCount) {
            throw new GithubActionRunStarterAndArtifactDownloaderException('More than 1 run was not created ?');
        }

        return $this->mostRecentRunFinder->find($newRunsResponse->runs);
    }
}
