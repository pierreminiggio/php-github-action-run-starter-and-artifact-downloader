<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDeleter\GithubActionRunDeleter;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;

class GithubActionRunStarterAndArtifactDownloaderFactory
{
    public function make(): GithubActionRunStarterAndArtifactDownloader
    {
        return new GithubActionRunStarterAndArtifactDownloader(
            new GithubActionRunsLister(),
            new GithubActionRunCreator(),
            new MostRecentRunFinder(),
            new GithubActionRunDetailer(),
            new GithubActionRunArtifactsLister(),
            new GithubActionArtifactDownloader(),
            new GithubActionRunDeleter()
        );
    }

    /**
     * @param string $ticketEncryptionKey secret key used to encrypt/decrypt run
     *        tickets. Keep it out of source control (env var, secret manager, ...);
     *        anyone who has it can decrypt a stored ticket back into a raw Github token.
     */
    public function makeAsync(string $ticketEncryptionKey): GithubActionAsyncRunStarterAndArtifactDownloader
    {
        return new GithubActionAsyncRunStarterAndArtifactDownloader(
            new GithubActionRunsLister(),
            new GithubActionRunCreator(),
            new MostRecentRunFinder(),
            new GithubActionRunDetailer(),
            new GithubActionRunArtifactsLister(),
            new GithubActionArtifactDownloader(),
            new GithubActionRunDeleter(),
            $ticketEncryptionKey
        );
    }
}
