<?php

namespace PierreMiniggio\GithubActionRunStarterAndArtifactDownloader;

use Exception;

class GithubActionRunStarterAndArtifactDownloaderException extends Exception
{
    public static function makeFromException(Exception $e): self
    {
        return new self(get_class($e) . ' : ' . $e->getMessage(), 0, $e);
    }
}
