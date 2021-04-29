<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloaderTest;

use PHPUnit\Framework\TestCase;
use PierreMiniggio\GithubActionRun\GithubActionRun;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\MostRecentRunFinder;
use PierreMiniggio\GithubStatusesEnum\ConclusionsEnum;
use PierreMiniggio\GithubStatusesEnum\GithubStatusesEnum;

class MostRecentRunFinderTest extends TestCase
{

    /**
     * @param GithubActionRun[] $pool
     * 
     * @dataProvider provideTests
     */
    public function testWorks(
        GithubActionRun $expected,
        array $pool
    ): void
    {
        $finder = new MostRecentRunFinder();
        $this->assertSameRun($expected, $finder->find($pool));
    }

    public function assertSameRun(GithubActionRun $expected, GithubActionRun $actual): void
    {
        self::assertSame($expected->id, $actual->id);
    }

    public function provideTests(): array
    {

        $expected = new GithubActionRun(3, GithubStatusesEnum::QUEUED, ConclusionsEnum::NEUTRAL);
        $el1 = new GithubActionRun(1, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS);
        $el2 = new GithubActionRun(2, GithubStatusesEnum::COMPLETED, ConclusionsEnum::SUCCESS);

        return [
            [$expected, [$el1, $el2, $expected]],
            [$expected, [$el1, $expected, $el2]],
            [$expected, [$el2, $el1, $expected]],
            [$expected, [$el2, $expected, $el1]],
            [$expected, [$expected, $el2, $el1]],
            [$expected, [$expected, $el1, $el2]]
        ];
    }
}
