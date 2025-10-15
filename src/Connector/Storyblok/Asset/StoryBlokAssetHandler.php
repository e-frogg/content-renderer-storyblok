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
        $asset->updateSrc();
        $asset->dimensions = $this->computeDimensions($asset);
        return $asset;
    }

    /**
     * @param Asset $asset
     * @return array<string,mixed>
     */
    private function computeDimensions($asset): array
    {
        // https://a.storyblok.com/f/287370148876658/500x289/b17019ecec/filmage-e30c.png
        //     "src" => "https://img2.storyblok.com/500x500/filters:focal()/f/287370148876658/1074x557/f18c3d7e16/salon-de-la-photo.png"
        if (preg_match('/\/(\d+)x(\d+)\//i', $asset->src, $matches)) {
            return [
                'width' => (int)$matches[1],
                'height' => (int)$matches[2],
                'aspectRatio' => "$matches[1]/$matches[2]",
            ];
        }
        return [];
    }
}
