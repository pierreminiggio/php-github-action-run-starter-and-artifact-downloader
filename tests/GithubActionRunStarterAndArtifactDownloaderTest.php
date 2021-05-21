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
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderException;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class GithubActionRunStarterAndArtifactDownloaderTest extends TestCase
{

    public function testIncrediblyFastAction(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $secondList[] = new GithubActionRun(3, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS);
        $runLister->expects(self::exactly(2))->method('list')->willReturn($firstList, $secondList);

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
            $this->makeCalledOnceCreatorMock(),
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $files = $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml',
            0,
            0
        );

        self::assertSame([$toto, $tutu], $files);
    }

    public function testNormalSuccessAction(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $queuedCurrentRun = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $secondList[] = $queuedCurrentRun;
        $runLister->expects(self::exactly(2))->method('list')->willReturn($firstList, $secondList);

        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $loadingCurrentRun = clone $queuedCurrentRun;
        $loadingCurrentRun->status = GithubStatusesEnum::IN_PROGRESS;
        $completedCurrentRun = clone $queuedCurrentRun;
        $completedCurrentRun->status = GithubStatusesEnum::COMPLETED;
        $completedCurrentRun->conclusion = ConclusionsEnum::SUCCESS;
        $runDetailer->expects(self::exactly(3))->method('find')->willReturn(
            $queuedCurrentRun,
            $loadingCurrentRun,
            $completedCurrentRun
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $toto = 'toto.mp4';
        $artifactLister->expects(self::once())->method('list')->willReturn(
            [
                new GithubActionRunArtifact(
                    1,
                    $toto,
                    false
                )
            ]
        );

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::once())->method('download')->willReturn(
            [$toto]
        );

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $this->makeCalledOnceCreatorMock(),
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $files = $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml',
            0,
            0
        );

        self::assertSame([$toto], $files);
    }

    public function testNormalFailedAction(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $queuedCurrentRun = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $secondList[] = $queuedCurrentRun;
        $runLister->expects(self::exactly(2))->method('list')->willReturn($firstList, $secondList);

        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $loadingCurrentRun = clone $queuedCurrentRun;
        $loadingCurrentRun->status = GithubStatusesEnum::IN_PROGRESS;
        $completedCurrentRun = clone $queuedCurrentRun;
        $completedCurrentRun->status = GithubStatusesEnum::COMPLETED;
        $completedCurrentRun->conclusion = ConclusionsEnum::FAILURE;
        $runDetailer->expects(self::exactly(3))->method('find')->willReturn(
            $queuedCurrentRun,
            $loadingCurrentRun,
            $completedCurrentRun
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::never())->method('list');

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::never())->method('download');

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $this->makeCalledOnceCreatorMock(),
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $this->expectException(GithubActionRunStarterAndArtifactDownloaderException::class);
        $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml',
            0,
            0
        );
    }

    public function testFailedActionThenSuccess(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $queuedCurrentRun = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $secondList[] = $queuedCurrentRun;
        $runLister->expects(self::exactly(4))->method('list')->willReturn(
            $firstList,
            $secondList,
            $firstList,
            $secondList
        );

        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $loadingCurrentRun = clone $queuedCurrentRun;
        $loadingCurrentRun->status = GithubStatusesEnum::IN_PROGRESS;
        $failedCurrentRun = clone $queuedCurrentRun;
        $failedCurrentRun->status = GithubStatusesEnum::COMPLETED;
        $failedCurrentRun->conclusion = ConclusionsEnum::FAILURE;
        $succeededCurrentRun = clone $failedCurrentRun;
        $succeededCurrentRun->conclusion = ConclusionsEnum::SUCCESS;
        $runDetailer->expects(self::exactly(6))->method('find')->willReturn(
            $queuedCurrentRun,
            $loadingCurrentRun,
            $failedCurrentRun,
            $queuedCurrentRun,
            $loadingCurrentRun,
            $succeededCurrentRun
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $toto = 'toto.mp4';
        $artifactLister->expects(self::once())->method('list')->willReturn(
            [
                new GithubActionRunArtifact(
                    1,
                    $toto,
                    false
                )
            ]
        );

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::once())->method('download')->willReturn(
            [$toto]
        );

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $this->makeCalledTwiceCreatorMock(),
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $files = $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml',
            0,
            1
        );

        self::assertSame([$toto], $files);
    }

    public function testNormalSuccessActionButTook3TriesToSeeItselfCreated(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $queuedCurrentRun = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $secondList[] = $queuedCurrentRun;
        $runLister->expects(self::exactly(4))->method('list')->willReturn(
            $firstList,
            $firstList,
            $firstList,
            $secondList
        );

        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $loadingCurrentRun = clone $queuedCurrentRun;
        $loadingCurrentRun->status = GithubStatusesEnum::IN_PROGRESS;
        $completedCurrentRun = clone $queuedCurrentRun;
        $completedCurrentRun->status = GithubStatusesEnum::COMPLETED;
        $completedCurrentRun->conclusion = ConclusionsEnum::SUCCESS;
        $runDetailer->expects(self::exactly(3))->method('find')->willReturn(
            $queuedCurrentRun,
            $loadingCurrentRun,
            $completedCurrentRun
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $toto = 'toto.mp4';
        $artifactLister->expects(self::once())->method('list')->willReturn(
            [
                new GithubActionRunArtifact(
                    1,
                    $toto,
                    false
                )
            ]
        );

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::once())->method('download')->willReturn(
            [$toto]
        );

        $actionRunStarterAndArtifactDownloader = new GithubActionRunStarterAndArtifactDownloader(
            $runLister,
            $this->makeCalledOnceCreatorMock(),
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader
        );

        $files = $actionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml',
            0,
            0
        );

        self::assertSame([$toto], $files);
    }

    /**
     * @return GithubActionRun[]
     */
    protected function provideFirstList(): array
    {
        return [
            new GithubActionRun(1, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
            new GithubActionRun(2, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
        ];
    }

    protected function makeCalledOnceCreatorMock(): GithubActionRunCreator
    {
        $mock = $this->createMock(GithubActionRunCreator::class);
        $mock->expects(self::once())->method('create');

        return $mock;
    }

    protected function makeCalledTwiceCreatorMock(): GithubActionRunCreator
    {
        $mock = $this->createMock(GithubActionRunCreator::class);
        $mock->expects(self::exactly(2))->method('create');

        return $mock;
    }
}
