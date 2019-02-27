<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Exception;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\HttpClientInterface;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware_Components_Config;

class QueryBuilder
{
    const BASE_URL = 'https://service.findologic.com/ps/xml_2.0/';
    const ALIVE_ENDPOINT = 'alivetest.php';
    const SEARCH_ENDPOINT = 'index.php';
    const NAVIGATION_ENDPOINT = 'selector.php';

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    private $parameters;

    private $shopUrl;

    private $shopKey;

    /**
     * QueryBuilder constructor.
     *
     * @param HttpClientInterface $httpClient
     * @param InstallerService $installerService
     * @param Shopware_Components_Config $config
     *
     * @throws Exception
     */
    public function __construct(
        HttpClientInterface $httpClient,
        InstallerService $installerService,
        Shopware_Components_Config $config
    ) {
        $plugin = $installerService->getPluginByName('FinSearchUnified');

        $this->parameters = [];
        $this->httpClient = $httpClient;
        $this->shopKey = $config->offsetGet('ShopKey');
        $this->shopUrl = rtrim(Shopware()->Shop()->getHost(), '/') . '/';
        $this->parameters = [
            'userip' => $this->getClientIp(),
            'revision' => $plugin->getVersion(),
            'shopkey' => $this->shopKey
        ];
    }

    /**
     * @param bool $isSearch
     *
     * @return string|null
     */
    public function execute($isSearch = true)
    {
        if ($isSearch) {
            $endpoint = self::SEARCH_ENDPOINT;
        } else {
            $endpoint = self::NAVIGATION_ENDPOINT;
        }

        $url = sprintf(
            '%s%s%s?%s',
            self::BASE_URL,
            $this->shopUrl,
            $endpoint,
            http_build_query($this->parameters)
        );

        try {
            if ($this->isAlive()) {
                $response = $this->httpClient->get($url);

                return $response->getBody();
            } else {
                return null;
            }
        } catch (RequestException $exception) {
            return null;
        }
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    private function isSuccessful(Response $response)
    {
        $restype = (string)$response->getStatusCode();

        return $restype[0] === '2' || $restype[0] === '1';
    }

    /**
     * @return bool
     */
    private function isAlive()
    {
        $url = sprintf(
            '%s%s%s?shopkey=%s',
            self::BASE_URL,
            $this->shopUrl,
            self::ALIVE_ENDPOINT,
            $this->shopKey
        );

        try {
            $response = $this->httpClient->get($url);
            $isAlive = $this->isSuccessful($response) && strpos($response->getBody(), 'alive') !== false;
        } catch (RequestException $e) {
            $isAlive = false;
        }

        return $isAlive;
    }

    /**
     * @return bool|string
     */
    private function getClientIp()
    {
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            // Check for multiple IPs passing through proxy
            $position = strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',');

            // If multiple IPs are passed, extract the first one
            if ($position !== false) {
                $ipAddress = substr($_SERVER['HTTP_X_FORWARDED_FOR'], 0, $position);
            } else {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } elseif ($_SERVER['HTTP_X_FORWARDED']) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ($_SERVER['HTTP_FORWARDED_FOR']) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_FORWARDED']) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = 'UNKNOWN';
        }

        $ipAddress = implode(',', array_unique(array_map('trim', explode(',', $ipAddress))));

        return $ipAddress;
    }
}
