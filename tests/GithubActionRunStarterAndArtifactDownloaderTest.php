<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloaderTest;

use PHPUnit\Framework\TestCase;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunsLister\GithubActionRun;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloader;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class GithubActionRunStarterAndArtifactDownloaderTest extends TestCase
{

    public function testBlabla(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = [
            new GithubActionRun(1, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
            new GithubActionRun(2, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
        ];
        $secondList = $firstList;
        $secondList[] = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $runLister->expects(self::exactly(2))->method('list')->willReturn($firstList, $secondList);

        $runCreator = $this->createMock(GithubActionRunCreator::class);
        $runCreator->expects(self::once())->method('create');

        $runFinder = $this->createMock(MostRecentRunFinder::class);
        $runFinder->expects(self::once())->method('find');

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $runCreator,
            $runFinder
        );

        $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml'
        );
    }
}
