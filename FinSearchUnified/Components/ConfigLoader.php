<?php

namespace FinSearchUnified\Components;

use Shopware\Components\HttpClient\HttpClientInterface;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware_Components_Config;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class ConfigLoader
{
    const CDN_URL = 'https://cdn.findologic.com/static';
    const CONFIG_FILE = 'config.json';
    const CACHE_ID = 'fin_service_config';
    const CACHE_LIFETIME = 86400;
    const WHITE_LIST = [
        'isStagingShop' => null,
        'directIntegration' => ['enabled' => null],
        'blocks' => ['cat' => null, 'vendor' => null]
    ];

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var Zend_Cache_Core
     */
    private $cache;

    /**
     * @var mixed
     */
    private $shopkey;

    /**
     * @param Zend_Cache_Core $cache
     * @param HttpClientInterface $httpClient
     * @param Shopware_Components_Config $config
     */
    public function __construct(
        Zend_Cache_Core $cache,
        HttpClientInterface $httpClient,
        Shopware_Components_Config $config
    ) {
        $this->cache = $cache;
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->shopkey = $config->offsetGet('ShopKey');
    }

    /**
     * @return string
     */
    private function getUrl()
    {
        return sprintf('%s/%s/%s', self::CDN_URL, strtoupper(md5($this->shopkey)), self::CONFIG_FILE);
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    private function isSuccessful(Response $response)
    {
        $restype = (string)$response->getStatusCode();

        return strpos($restype, '2') === 0 || strpos($restype, '1') === 0;
    }

    /**
     * @return string|null
     */
    private function getConfigFile()
    {
        $payload = null;

        try {
            $response = $this->httpClient->get($this->getUrl());
            if ($this->isSuccessful($response)) {
                $payload = $response->getBody();
            }
        } catch (RequestException $exception) {
        }

        return $payload;
    }

    /**
     * @return string
     */
    private function getCacheKey()
    {
        return sprintf('%s_%s', self::CACHE_ID, $this->shopkey);
    }

    /**
     * @throws Zend_Cache_Exception
     */
    private function warmUpCache()
    {
        $config = json_decode($this->getConfigFile(), true);
        if ($config) {
            $data = $this->filterConfigs($config, self::WHITE_LIST);
            $this->cache->save($data, $this->getCacheKey(), ['FINDOLOGIC'], self::CACHE_LIFETIME);
        }
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     * @throws Zend_Cache_Exception
     */
    private function get($key, $default = null)
    {
        $id = $this->getCacheKey();
        if (($config = $this->cache->load($id)) === false) {
            $this->warmUpCache();
            $config = $this->cache->load($id);
        }

        if ($config === false) {
            return $default;
        }

        switch ($key) {
            case 'directIntegration':
                return $config[$key]['enabled'];
            case 'blocks':
            case 'isStagingShop':
                return $config[$key];
            default:
                return $default;
        }
    }

    /**
     * @param mixed[] $default
     *
     * @return mixed|null
     * @throws Zend_Cache_Exception
     */
    public function getSmartSuggestBlocks($default = [])
    {
        return $this->get('blocks', $default);
    }

    /**
     * @param mixed|null $default
     *
     * @return mixed|null
     * @throws Zend_Cache_Exception
     */
    public function directIntegrationEnabled($default = null)
    {
        return $this->get('directIntegration', $default);
    }

    /**
     * @param mixed|null $default
     *
     * @return mixed|null
     * @throws Zend_Cache_Exception
     */
    public function isStagingShop($default = null)
    {
        return $this->get('isStagingShop', $default);
    }

    /**
     * Recursively computes the intersection of arrays using keys for comparison.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array Contains all the entries of array1 which have keys that are present in array2.
     */
    private function filterConfigs(array $array1, array $array2)
    {
        $array1 = array_intersect_key($array1, $array2);

        foreach ($array1 as $key => &$value) {
            if (is_array($value) && is_array($array2[$key])) {
                $value = $this->filterConfigs($value, $array2[$key]);
            }
        }

        return $array1;
    }
}
