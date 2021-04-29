<?php

use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$actionRunner = (new GithubActionRunStarterAndArtifactDownloaderFactory())->make();
$artifacts = $actionRunner->runActionAndGetArtifacts(
    'token',
    'pierreminiggio',
    'remotion-test-github-action',
    'render-video.yml',
    3,
    0,
    [
        'titleText' => 'Hello from PHP action runner',
        'titleColor' => 'orange'
    ]
);

var_dump($artifacts);
