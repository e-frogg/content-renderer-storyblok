<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


trait AssetDownloaderAwareTrait
{
    /**
     * @var AssetDownloaderInterface
     */
    private $assetDownloader;

    /**
     * @return AssetDownloaderInterface
     */
    public function getAssetDownloader(): AssetDownloaderInterface
    {
        return $this->assetDownloader;
    }

    /**
     * @param AssetDownloaderInterface $assetDownloader
     */
    public function setAssetDownloader(AssetDownloaderInterface $assetDownloader): void
    {
        $this->assetDownloader = $assetDownloader;
    }


}