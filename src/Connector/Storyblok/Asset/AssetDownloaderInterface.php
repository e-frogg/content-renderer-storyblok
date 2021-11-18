<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


use Efrogg\ContentRenderer\Asset\Asset;

interface AssetDownloaderInterface
{
    public function downloadAsset(Asset $asset);
}