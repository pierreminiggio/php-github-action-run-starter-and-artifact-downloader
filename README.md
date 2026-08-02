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

## Async usage

`runActionAndGetArtifacts` is synchronous : it blocks your script until the run finishes, which can be minutes. If you'd rather start a run and check on it later (from a cron job, a queue worker, a webhook handler, ...), use `GithubActionAsyncRunStarterAndArtifactDownloaderFactory::makeAsync()` instead.

It needs a secret encryption key of your choosing, used to encrypt/decrypt the run tickets described below. Keep it out of source control (env var, secret manager, ...) : anyone who has it can decrypt a stored ticket back into a raw Github token.

```php
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$asyncActionRunner = (new GithubActionRunStarterAndArtifactDownloaderFactory())
    ->makeAsync($_ENV['RUN_TICKET_ENCRYPTION_KEY']);

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

// $runTicket is a plain string. Store it wherever you like (a database column,
// a queue message, ...) — it's all checkAction() needs, and it never changes.
```

`startAction` also accepts a `$ref` (defaults to `'main'`) and a `$deleteAfterDownloading` (defaults to `false`) parameter, with the exact same meaning as on `runActionAndGetArtifacts`.

`startAction` still blocks briefly (a handful of short polls, governed by the same two settings as the sync class, see below) while it locates the run it just dispatched on Github — it does **not** wait for the run to finish.

Later — from any process, as often as you like — check on the run using the stored ticket :

```php
$result = $asyncActionRunner->checkAction($runTicket);

if ($result->isPending()) {
    // not finished yet, check again later
} elseif ($result->isFailed()) {
    // something went wrong; getFailureReason() describes what
    echo $result->getFailureReason();
} else {
    // isSuccessful() — artifacts have already been downloaded
    $artifacts = $result->getArtifacts();
}
```

`checkAction` never sleeps and never blocks waiting on the run — it makes a single API call to check the run's status and returns immediately, whatever the outcome.

## Tuning the run-creation checks

Both the sync and the async class dispatch a run and then poll Github until they can tell which run was just created (Github's API doesn't hand back a run id directly when you dispatch a workflow). Two settings control that polling, with the same defaults on both classes :

```php
$actionRunner->setSleepTimeBetweenRunCreationChecks(5); // seconds, defaults to 10
$actionRunner->setNumberOfRunCreationChecksBeforeAssumingItsNotCreated(20); // defaults to 10
```
