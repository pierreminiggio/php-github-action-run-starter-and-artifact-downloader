<?php

use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$asyncActionRunner = (new GithubActionRunStarterAndArtifactDownloaderFactory())
    ->makeAsync('some-secret-encryption-key-change-me');

$runTicket = $asyncActionRunner->startAction(
    'token',
    'pierreminiggio',
    'remotion-test-github-action',
    'render-video.yml',
    [
        'titleText' => 'Hello from PHP action runner',
        'titleColor' => 'orange'
    ]
);

var_dump($runTicket);

while (true) {
    sleep(10);

    $result = $asyncActionRunner->checkAction($runTicket);

    var_dump($result->getStatus());

    if ($result->isPending()) {
        continue;
    }

    if ($result->isFailed()) {
        var_dump($result->getFailureReason());
        break;
    }

    // isSuccessful()
    var_dump($result->getArtifacts());
    break;
}
