<?php

namespace Domains\ProjectTemplate;

use Domains\Packagist\Models\Package;
use Domains\Packagist\PackagistApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpZip\ZipFile;

class LaravelDownloader
{
    public function __construct(
        private PackagistApiClient $packagistApiClient,
    ) { }

    /**
     * @return Package[]
     */
    public function laravelReleases(): array
    {
        return $this->packagistApiClient->packageReleases(
            'laravel',
            'laravel',
        );
    }

    public function latestRelease(): Package
    {
        return $this->laravelReleases()[0];
    }

    public function downloadLatest(): DownloadedLaravelRelease
    {
        return $this->download($this->latestRelease());
    }

    public function download(Package $package): DownloadedLaravelRelease
    {
        $response = Http::get($package->dist->url)->body();

        Log::info("Downloading $package->version...");

        $release = new DownloadedLaravelRelease(
            package: $package,
            archive: (new ZipFile())->openFromString($response),
        );

        Log::info("$package->version successfully downloaded!");

        return $release;
    }
}
