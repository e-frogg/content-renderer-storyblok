<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


use Efrogg\ContentRenderer\Asset\Asset;
use Efrogg\ContentRenderer\Asset\AssetHandlerInterface;
use Efrogg\ContentRenderer\Asset\AssetResolver;
use Efrogg\ContentRenderer\Core\Resolver\SolverInterface;

class StoryBlokAssetHandler implements AssetHandlerInterface, SolverInterface
{
    use AssetDownloaderAwareTrait;

    public function canResolve($solvable, string $resolverName): bool
    {
        return AssetResolver::RESOLVER_NAME === $resolverName &&
            $solvable instanceof StoryBlokAsset;
    }

    /**
     * @param StoryBlokAsset $asset
     * @param array $parameters
     * @return Asset
     */
    public function getAsset($asset, $parameters = []): Asset
    {
        $asset->setParameters($parameters);
        $asset->setSrc($asset->filename);
        return $asset;
    }
}