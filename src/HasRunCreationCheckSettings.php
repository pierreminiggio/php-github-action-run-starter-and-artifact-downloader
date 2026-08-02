<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\Service\ActionRunStarter;

/**
 * Exposes explicit setters for ActionRunStarter's two tunable settings, so both
 * GithubActionRunStarterAndArtifactDownloader and
 * GithubActionAsyncRunStarterAndArtifactDownloader can offer the same public API
 * without duplicating these two methods.
 *
 * The class using this trait is expected to assign its ActionRunStarter instance
 * to $this->runStarter in its constructor.
 */
trait HasRunCreationCheckSettings
{

    private ActionRunStarter $runStarter;

    public function setSleepTimeBetweenRunCreationChecks(int $seconds): void
    {
        $this->runStarter->sleepTimeBetweenRunCreationChecks = $seconds;
    }

    public function setNumberOfRunCreationChecksBeforeAssumingItsNotCreated(int $tries): void
    {
        $this->runStarter->numberOfRunCreationChecksBeforeAssumingItsNotCreated = $tries;
    }
}
