Install using composer :
```
composer require pierreminiggio/github-action-run-starter-and-artifact-downloader
```

```php
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
```

`runActionAndGetArtifacts` also accepts a `$ref` (defaults to `'main'`) and a `$deleteAfterDownloading` (defaults to `false`) parameter. When `$deleteAfterDownloading` is set to `true`, the run (and, as a consequence, its artifacts) is deleted from Github once the artifacts have been successfully downloaded :

```php
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
    ],
    'main',
    true
);
```
