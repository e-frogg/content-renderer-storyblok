<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\NodeProvider;


use Efrogg\ContentRenderer\Connector\Storyblok\Asset\StoryBlokAsset;
use Efrogg\ContentRenderer\Connector\Storyblok\Exception\InvalidConfigurationException;
use Efrogg\ContentRenderer\Connector\Storyblok\Lib\Client;
use Efrogg\ContentRenderer\Converter\Keyword;
use Efrogg\ContentRenderer\Decorator\DecoratorAwareTrait;
use Efrogg\ContentRenderer\Exception\NodeNotFoundException;
use Efrogg\ContentRenderer\Node;
use Efrogg\ContentRenderer\NodeProvider\CacheableNodeProviderTrait;
use Efrogg\ContentRenderer\NodeProvider\NodeProviderInterface;
use Psr\Log\LoggerInterface;
use Storyblok\RichtextRender\Resolver;

class StoryBlokNodeProvider implements NodeProviderInterface, StoryBlokNodeProviderInterface
{
    use DecoratorAwareTrait;
    use CacheableNodeProviderTrait;

    public const KEY_EDITABLE = '_editable';
    public const KEY_UID = '_uid';
    public const KEY_FILENAME = 'filename';
    public const KEY_COMPONENT = 'component';
    public const KEY_IMAGE_ID = 'id';
    public const PROVIDER_IDENTIFIER = 'StoryBlok';

    public const MODE_PUBLIC = 'public';
    public const MODE_PREVIEW = 'preview';

    private int $maxRetries = 3;

    private const DECORATED_WHITELIST_PLUGINS = [
        'wysiwyg-tinymce',
    ];

    private const RAW_WHITELIST_PLUGINS = [
        'native-color-picker',
    ];

    /**
     * @var Client[]
     */
    private $clients;

    /**
     * @var string
     */
    private $clientMode = self::MODE_PUBLIC;

    /**
     * @var array<string,string>
     */
    private $apiKeys;

    /**
     * @var Resolver
     */
    private $textResolver;

    // acc0d372-11c5-426f-9786-6947004b745c
    private $uuidPattern = '/([\w]{8})-([\w]{4})-([\w]{4})-([\w]{4})-([\w]{12})/';

    public function __construct(array $apiKeys, ?LoggerInterface $logger = null)
    {
        $this->apiKeys = $apiKeys;
        // pour le rendu  des RichText
        $this->textResolver = new Resolver();
        $this->initLogger($logger);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws NodeNotFoundException
     */
    public function fetchNodeById(string $nodeId): Node
    {
        $this->info(
            'load ' . $nodeId,
            ['title' => 'StoryBlokNodeProvider']
        );
        try {
            if ($this->isUUID($nodeId)) {
                $this->getClient()->getStoryByUuid($nodeId);
            } else {
                $this->getClient()->getStoryBySlug($nodeId);
            }
        } catch (\Exception) {
            throw new NodeNotFoundException(sprintf('node %s was not found on storyblok', $nodeId));
        }

        return $this->convertStoryDataToNode($this->getClient()->responseBody['story']);
    }

    public function canResolve($solvable, string $resolverName): bool
    {
       return true;
    }

    private function convertStoryDataToNode(array $storyData): Node
    {
        $this->info('convert data', ['data' => $storyData, 'title' => 'StoryBlokNodeProvider']);

        return $this->convertDataToNode($storyData['content']);
    }

    private function convertDataToNode(array $content): Node
    {
        $context = [];
        $nodeData = [
            '__cmsProvider__'        => self::PROVIDER_IDENTIFIER,
            '__storyBlokHotReload__' => isset($_GET['_storyblok_version']),
        ];
//        dd($content);
        foreach ($content as $key => $value) {
            switch ($key) {
                case self::KEY_EDITABLE:
                    $nodeData[Keyword::EDITABLE] = $this->extractEditable($content[self::KEY_UID], $value);
                    $nodeData[Keyword::PREVIEW] = true;
                    break;
                case self::KEY_UID:
                    $nodeData[Keyword::NODE_ID] = $value;
                    break;
                case self::KEY_COMPONENT:
                    $nodeData[Keyword::NODE_TYPE] = $value;
                    break;
                default:
                    $nodeData[$key] = $this->convertValue($value);
            }
        }

        return new Node($nodeData, $context);
    }

    private function extractEditable(string $nodeId, string $_editable): string
    {
        if (0 === strpos($_editable, '<!--#storyblok#')) {
            return sprintf("data-blok-uid='%s' data-blok-c='%s'", $nodeId, substr($_editable, 15, -3));
        }

        return '';
    }

    /**
     * retourne true si on a affaire à une liste de nodes imbriqués
     *
     * @param $nested
     *
     * @return bool
     */
    private function isNestedNodeArray($nested): bool
    {
        if (!is_array($nested) || empty($nested)) {
            return false;
        }

        foreach ($nested as $key => $value) {
            if (!is_numeric($key) || !is_array($value) || !isset($value[self::KEY_UID])) {
                return false;
            }
        }

        return true;
    }

    private function isAssetArray($nested): bool
    {
        return is_array($nested) && isset($nested[0][self::KEY_IMAGE_ID], $nested[0][self::KEY_FILENAME]);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function convertValue($value)
    {
        if ($this->isNestedNodeArray($value)) {
            $newArray = [];
            foreach ($value as $nodeKey => $nodeData) {
                $newArray[$nodeKey] = $this->convertDataToNode($nodeData);
            }

            return $newArray;
        }

        if ($this->isAssetArray($value)) {
            $assets = [];
            foreach ($value as $nodeKey => $assetData) {
                $assets [$nodeKey] = new StoryBlokAsset($assetData);
            }

            return $assets;
        }

        if (is_array($value)) {
            if (isset($value['type']) && $value['type'] === 'doc') {
                return $this->decorate($this->textResolver->render($value));
            }
            if (isset($value['plugin']) && in_array($value['plugin'], self::DECORATED_WHITELIST_PLUGINS, true)) {
                return $this->decorate($value['content']);
            }
            if (isset($value['plugin']) && !in_array($value['plugin'], self::RAW_WHITELIST_PLUGINS, true)) {
                return sprintf('[plugin : %s] : %s', $value['plugin'], var_export($value, true));
            }

            if (isset($value[self::KEY_FILENAME], $value[self::KEY_IMAGE_ID])) {
                $value[Keyword::NODE_ID] = $value['id'];

                return new StoryBlokAsset($value);
            }
        }

        if (is_string($value)) {
            return $this->decorate($value);
        }

        // autre ?
        return $value;
    }

    // acc0d372-11c5-426f-9786-6947004b745c
    private function isUUID($nodeId): bool
    {
        return preg_match($this->uuidPattern, $nodeId);
    }

    /**
     * @return Client
     * @throws InvalidConfigurationException
     */
    public function getClient(string $mode = null): Client
    {
        $mode ??= $this->getClientMode();

        if (!isset($this->clients[$mode])) {
            if (!isset($this->apiKeys[$mode])) {
                throw new InvalidConfigurationException('no api keys for mode '.$mode);
            }
            $client = new Client($this->apiKeys['preview']);
            $client->setMaxRetries($this->maxRetries);
            $client->setTimeout(5);
            $this->clients[$mode] = $client;
        }
        return $this->clients[$mode];
    }

    /**
     * @return string
     */
    public function getClientMode(): string
    {
        return $this->clientMode;
    }

    /**
     * @param string $clientMode
     */
    public function setClientMode(string $clientMode): void
    {
        $this->logger->info(sprintf('set mode "%s"',$clientMode));
        $this->clientMode = $clientMode;
    }

    public function getCacheKeyPrefix(): string
    {
        return '';
    }

    /**
     * @param int $maxRetries
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

}
