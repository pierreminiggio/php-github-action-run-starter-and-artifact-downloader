Install using composer :
```
composer require pierreminiggio/github-action-run-starter-and-artifact-downloader
```

```php
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloader;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$lister = new GithubActionRunStarterAndArtifactDownloader();
$list = $lister->list(
    'pierreminiggio',
    'remotion-test-github-action',
    'render-video.yml'
);

var_dump($list);
```
