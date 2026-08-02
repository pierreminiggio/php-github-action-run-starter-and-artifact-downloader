<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

final class GithubActionCheckStatusEnum
{
    public const PENDING = 'pending'; // run not finished yet
    public const FAILED = 'failed';
    public const SUCCESS = 'success';
}
