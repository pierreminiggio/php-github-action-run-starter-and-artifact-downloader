<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloaderTest;

use PHPUnit\Framework\TestCase;
use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;
use PierreMiniggio\GithubActionRun\GithubActionRun;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifact;
use PierreMiniggio\GithubActionRunArtifactsLister\GithubActionRunArtifactsLister;
use PierreMiniggio\GithubActionRunCreator\GithubActionRunCreator;
use PierreMiniggio\GithubActionRunDeleter\GithubActionRunDeleter;
use PierreMiniggio\GithubActionRunDetailer\GithubActionRunDetailer;
use PierreMiniggio\GithubActionRunsLister\GithubActionRunsLister;
use PierreMiniggio\GithubActionRunsLister\RunListerResponse;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionAsyncRunStarterAndArtifactDownloader;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunHandle;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderException;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class GithubActionAsyncRunStarterAndArtifactDownloaderTest extends TestCase
{
    private const TICKET_ENCRYPTION_KEY = 'test-ticket-encryption-key';

    public function testStartActionReturnsATicketAndDispatchesOnce(): void
    {
        $runLister = $this->createMock(GithubActionRunsLister::class);
        $firstList = $this->provideFirstList();
        $secondList = $firstList;
        $secondList[] = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $runLister->expects(self::exactly(2))->method('list')->willReturn(
            new RunListerResponse($firstList, count($firstList)),
            new RunListerResponse($secondList, count($secondList))
        );

        $runCreator = $this->createMock(GithubActionRunCreator::class);
        $runCreator->expects(self::once())->method('create');

        $asyncRunner = $this->makeAsyncRunner(
            $runLister,
            $runCreator,
            $this->createMock(GithubActionRunDetailer::class),
            $this->createMock(GithubActionRunArtifactsLister::class),
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );
        $asyncRunner->setSleepTimeBetweenRunCreationChecks(0);

        $runTicket = $asyncRunner->startAction(
            'token',
            'pierreminiggio',
            'remotion-test-github-action',
            'render-video.yml'
        );

        self::assertIsString($runTicket);
        self::assertStringStartsWith('v1:', $runTicket);

        $handle = GithubActionRunHandle::decode($runTicket, self::TICKET_ENCRYPTION_KEY);
        self::assertSame('token', $handle->getToken());
        self::assertSame('pierreminiggio', $handle->getOwner());
        self::assertSame('remotion-test-github-action', $handle->getRepo());
        self::assertSame(3, $handle->getRunId());
        self::assertFalse($handle->shouldDeleteAfterDownloading());
    }

    public function testCheckActionReportsPendingWhileRunIsNotCompleted(): void
    {
        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::once())->method('find')->willReturn(
            new GithubActionRun(3, GithubStatusesEnum::IN_PROGRESS, ConclusionsEnum::NEUTRAL)
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::never())->method('list');

        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $runDetailer,
            $artifactLister,
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );

        $runTicket = $this->makeTicket('token', 'owner', 'repo', 3, false);

        $result = $asyncRunner->checkAction($runTicket);

        self::assertTrue($result->isPending());
        self::assertNull($result->getArtifacts());
        self::assertNull($result->getFailureReason());
    }

    public function testCheckActionReportsSuccessAndDownloadsArtifactsWhenRunCompletedSuccessfully(): void
    {
        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::once())->method('find')->willReturn(
            new GithubActionRun(3, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS)
        );

        $toto = 'toto.mp4';
        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::once())->method('list')->willReturn([
            new GithubActionRunArtifact(1, $toto, false),
        ]);

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::once())->method('download')->willReturn([$toto]);

        $runDeleter = $this->createMock(GithubActionRunDeleter::class);
        $runDeleter->expects(self::never())->method('delete');

        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $runDetailer,
            $artifactLister,
            $artifactDownloader,
            $runDeleter
        );

        $runTicket = $this->makeTicket('token', 'owner', 'repo', 3, false);

        $result = $asyncRunner->checkAction($runTicket);

        self::assertTrue($result->isSuccessful());
        self::assertSame([$toto], $result->getArtifacts());
        self::assertNull($result->getFailureReason());
    }

    public function testCheckActionDeletesRunAfterDownloadingWhenTicketAsksForIt(): void
    {
        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::once())->method('find')->willReturn(
            new GithubActionRun(3, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS)
        );

        $toto = 'toto.mp4';
        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::once())->method('list')->willReturn([
            new GithubActionRunArtifact(1, $toto, false),
        ]);

        $artifactDownloader = $this->createMock(GithubActionArtifactDownloader::class);
        $artifactDownloader->expects(self::once())->method('download')->willReturn([$toto]);

        $runDeleter = $this->createMock(GithubActionRunDeleter::class);
        $runDeleter->expects(self::once())->method('delete');

        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $runDetailer,
            $artifactLister,
            $artifactDownloader,
            $runDeleter
        );

        $runTicket = $this->makeTicket('token', 'owner', 'repo', 3, true);

        $result = $asyncRunner->checkAction($runTicket);

        self::assertTrue($result->isSuccessful());
    }

    public function testCheckActionReportsFailedWithConclusionDetailWhenRunFailed(): void
    {
        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::once())->method('find')->willReturn(
            new GithubActionRun(3, GithubStatusesEnum::COMPLETED, ConclusionsEnum::FAILURE)
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::never())->method('list');

        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $runDetailer,
            $artifactLister,
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );

        $runTicket = $this->makeTicket('token', 'owner', 'repo', 3, false);

        $result = $asyncRunner->checkAction($runTicket);

        self::assertTrue($result->isFailed());
        self::assertNull($result->getArtifacts());
        self::assertStringContainsString('3', $result->getFailureReason());
        self::assertStringContainsString(ConclusionsEnum::FAILURE, $result->getFailureReason());
    }

    public function testCheckActionReportsFailedWhenArtifactResolutionThrows(): void
    {
        $runDetailer = $this->createMock(GithubActionRunDetailer::class);
        $runDetailer->expects(self::once())->method('find')->willReturn(
            new GithubActionRun(3, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS)
        );

        $artifactLister = $this->createMock(GithubActionRunArtifactsLister::class);
        $artifactLister->expects(self::once())->method('list')->willThrowException(
            new \RuntimeException('Github API is down')
        );

        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $runDetailer,
            $artifactLister,
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );

        $runTicket = $this->makeTicket('token', 'owner', 'repo', 3, false);

        $result = $asyncRunner->checkAction($runTicket);

        self::assertTrue($result->isFailed());
        self::assertStringContainsString('Github API is down', $result->getFailureReason());
    }

    public function testCheckActionThrowsOnAnUndecodableTicket(): void
    {
        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $this->createMock(GithubActionRunDetailer::class),
            $this->createMock(GithubActionRunArtifactsLister::class),
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );

        $this->expectException(GithubActionRunStarterAndArtifactDownloaderException::class);
        $asyncRunner->checkAction('not-a-real-ticket');
    }

    public function testCheckActionThrowsWhenTicketWasEncodedWithADifferentKey(): void
    {
        $asyncRunner = $this->makeAsyncRunner(
            $this->createMock(GithubActionRunsLister::class),
            $this->createMock(GithubActionRunCreator::class),
            $this->createMock(GithubActionRunDetailer::class),
            $this->createMock(GithubActionRunArtifactsLister::class),
            $this->createMock(GithubActionArtifactDownloader::class),
            $this->createMock(GithubActionRunDeleter::class)
        );

        $ticketFromAnotherKey = $this->makeTicket('token', 'owner', 'repo', 3, false, 'a-different-key');

        $this->expectException(GithubActionRunStarterAndArtifactDownloaderException::class);
        $asyncRunner->checkAction($ticketFromAnotherKey);
    }

    /**
     * @return GithubActionRun[]
     */
    private function provideFirstList(): array
    {
        return [
            new GithubActionRun(1, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
            new GithubActionRun(2, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS),
        ];
    }

    private function makeTicket(
        string $token,
        string $owner,
        string $repo,
        int $runId,
        bool $deleteAfterDownloading,
        ?string $encryptionKey = null
    ): string {
        return (new GithubActionRunHandle($token, $owner, $repo, $runId, $deleteAfterDownloading))
            ->encode($encryptionKey ?? self::TICKET_ENCRYPTION_KEY);
    }

    private function makeAsyncRunner(
        GithubActionRunsLister $runLister,
        GithubActionRunCreator $runCreator,
        GithubActionRunDetailer $runDetailer,
        GithubActionRunArtifactsLister $artifactLister,
        GithubActionArtifactDownloader $artifactDownloader,
        GithubActionRunDeleter $runDeleter
    ): GithubActionAsyncRunStarterAndArtifactDownloader {
        return new GithubActionAsyncRunStarterAndArtifactDownloader(
            $runLister,
            $runCreator,
            new MostRecentRunFinder(),
            $runDetailer,
            $artifactLister,
            $artifactDownloader,
            $runDeleter,
            self::TICKET_ENCRYPTION_KEY
        );
    }
}
