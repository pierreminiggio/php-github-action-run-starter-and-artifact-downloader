<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
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
            new GithubActionArtifactDownloader()
        );
    }
}
