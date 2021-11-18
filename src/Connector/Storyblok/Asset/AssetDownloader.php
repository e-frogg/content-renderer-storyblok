<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


use Efrogg\ContentRenderer\Asset\Asset;
use Efrogg\ContentRenderer\Asset\AssetHandlerInterface;
use Efrogg\ContentRenderer\Core\Resolver\SolverInterface;
use Efrogg\ContentRenderer\Log\LoggerProxy;
use RuntimeException;

class AssetDownloader implements AssetHandlerInterface, SolverInterface
{
    use AssetHandlerAwareTrait;
    use LoggerProxy;

    public const STORAGE_HASH = 'hash';
    public const STORAGE_RAW = 'raw';

    /**
     * @var string
     */
    protected $baseStoragePath;
    /**
     * @var int
     * on refresh l'image tous les 30 jours au minimum (utile ?)
     */
    private $maxAge = 86400*30;
    /**
     * @var string
     */
    private $basePublicPath;
    /**
     * @var string
     */
    private $storageMethod=self::STORAGE_HASH;

    public function __construct(string $baseStoragePath, string $basePublicPath)
    {
        $this->baseStoragePath = rtrim($baseStoragePath,'/');
        $this->basePublicPath = rtrim($basePublicPath,'/');
    }

    /**
     * @return int
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * @param int $maxAge
     * @return AssetDownloader
     */
    public function setMaxAge(int $maxAge): AssetDownloader
    {
        $this->maxAge = $maxAge;
        return $this;
    }


    public function getAsset($asset, $parameters = []): Asset
    {
        $asset = $this->getAssetHandler()->getAsset($asset, $parameters);

        try {
            $newSrc = $this->downloadAsset($asset);
            $asset->setSrc($newSrc);
        } catch (RuntimeException $e) {
            // on ne remplace pas ...
        }

        return $asset;
    }

    public function canResolve($solvable, string $resolverName): bool
    {
        return $this->getAssetHandler()->canResolve($solvable, $resolverName);
    }

    /**
     * @param Asset $asset
     * @return string
     * @throws RuntimeException
     */
    private function downloadAsset(Asset $asset): string
    {
        $originalSource = $asset->getSrc();
        $storageFilename = $this->computeFilename($originalSource);

        $targetStorageFilename = $this->baseStoragePath . '/' . $storageFilename;
        $targetPublicFilename = $this->basePublicPath . '/' . $storageFilename;

        if ($this->isFresh($targetStorageFilename)) {
            $this->info('asset '.$originalSource.' is fresh',['title'=>'AssetDownloader']);
            return $targetPublicFilename;
        }

        $dir = dirname($targetStorageFilename);
        if(!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $content = file_get_contents($originalSource);
        if(false === $content) {
            throw new RuntimeException('file '.$originalSource.' could not be read');
        }
        file_put_contents($targetStorageFilename,$content);

        return $targetPublicFilename;
    }

    private function isFresh(string $targetFilename): bool
    {
        if (!file_exists($targetFilename)) {
            $this->warning('asset '.$targetFilename.' does not exist');
            return false;
        }

        $age = time() - filemtime($targetFilename);
        if($age>$this->maxAge) {
            $this->warning('asset '.$targetFilename.' is TOO old ('.$age.')');
            return false;
        }

        return true;
    }

    private function computeFilename(string $originalSource): string
    {
        if($this->storageMethod === self::STORAGE_HASH) {
            $extension = pathinfo($originalSource,PATHINFO_EXTENSION);
            return md5($originalSource).'.'.$extension;
        }

        return ltrim(parse_url($originalSource,PHP_URL_PATH),'/');
    }
}