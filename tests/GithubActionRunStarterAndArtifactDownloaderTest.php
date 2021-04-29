<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloaderTest;

use PHPUnit\Framework\TestCase;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRun\GithubActionRun;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifact;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloader;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class GithubActionRunStarterAndArtifactDownloaderTest extends TestCase
{

    public function testIncrediblyFastAction(): void
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

        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::never())->method('find');

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $toto = 'toto.mp4';
        $tutu = 'tutu.mp4';
        $artifactLister->expects(self::once())->method('list')->willReturn(
            [
                new GithubActionRunArtifact(
                    1,
                    $toto,
                    false
                ),
                new GithubActionRunArtifact(
                    1,
                    $tutu,
                    false
                )
            ]
        );

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::exactly(2))->method('download')->willReturn(
            [$toto], [$tutu]
        );

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $runCreator,
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $files = $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml'
        );

        self::assertSame([$toto, $tutu], $files);
    }
}
