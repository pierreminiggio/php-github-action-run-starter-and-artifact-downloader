<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use PierreMiniggio\GithubActionRunsLister\GithubActionRun;

class MostRecentRunFinder
{

    /**
     * @param GithubActionRun[] $runs
     */
    public function find(array $runs): GithubActionRun
    {
        $sortedRuns = $runs;
        uasort($sortedRuns, function (GithubActionRun $a, GithubActionRun $b): int {
            if ($a->id === $b->id) {
                return 0;
            }
            return $a->id > $b->id ? 1 : -1;
        });

        return array_values($sortedRuns)[count($sortedRuns) - 1];
    }
}
