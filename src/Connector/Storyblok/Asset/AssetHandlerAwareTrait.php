<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


use Efrogg\ContentRenderer\Asset\AssetHandlerInterface;

trait AssetHandlerAwareTrait
{
    /**
     * @var AssetHandlerInterface
     */
    private $assetHandler;

    /**
     * @return AssetHandlerInterface
     */
    public function getAssetHandler(): AssetHandlerInterface
    {
        return $this->assetHandler;
    }

    /**
     * @param AssetHandlerInterface $assetHandler
     */
    public function setAssetHandler(AssetHandlerInterface $assetHandler): void
    {
        $this->assetHandler = $assetHandler;
    }


}