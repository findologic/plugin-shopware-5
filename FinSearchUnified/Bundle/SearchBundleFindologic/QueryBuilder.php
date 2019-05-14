<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\HttpClientInterface;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware_Components_Config;

abstract class QueryBuilder
{
    const BASE_URL = 'https://service.findologic.com/ps';
    const ALIVE_ENDPOINT = 'alivetest.php';
    const ENDPOINT = 'index.php';
    const PARAMETER_KEY_ATTRIB = 'attrib';

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var string
     */
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

        $this->httpClient = $httpClient;
        $this->shopKey = $config->offsetGet('ShopKey');
        $this->shopUrl = rtrim(Shopware()->Shop()->getHost(), '/');
        $this->parameters = [
            'outputAdapter' => 'XML_2.0',
            'userip' => $this->getClientIp(),
            'revision' => $plugin->getVersion(),
            'shopkey' => $this->shopKey
        ];
    }

    /**
     * @return string|null
     */
    public function execute()
    {
        if (isset($_GET[Constants::SDYM_PARAM_FORCE_QUERY])) {
            $this->parameters[Constants::SDYM_PARAM_FORCE_QUERY] = $_GET[Constants::SDYM_PARAM_FORCE_QUERY] ? 1 : 0;
        }

        $url = sprintf(
            '%s/%s/%s?%s',
            self::BASE_URL,
            $this->shopUrl,
            static::ENDPOINT,
            http_build_query($this->parameters)
        );

        $payload = null;

        try {
            if ($this->isAlive()) {
                $response = $this->httpClient->get($url);
                if ((string)$response->getStatusCode() === '200') {
                    $payload = $response->getBody();
                }
            }
        } catch (RequestException $exception) {
        }

        return $payload;
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
            '%s/%s/%s?shopkey=%s',
            self::BASE_URL,
            $this->shopUrl,
            self::ALIVE_ENDPOINT,
            $this->shopKey
        );

        $isAlive = false;

        try {
            $response = $this->httpClient->get($url);
            $isAlive = $this->isSuccessful($response) && strpos($response->getBody(), 'alive') !== false;
        } catch (RequestException $exception) {
        }

        return $isAlive;
    }

    /**
     * @return string
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

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param string $query
     */
    public function addQuery($query)
    {
        $this->parameters['query'] = urldecode($query);
    }

    /**
     * @param float $min
     * @param float $max
     */
    public function addPrice($min, $max)
    {
        $this->addParameter('price', ['min' => urldecode($min), 'max' => urldecode($max)]);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function addFilter($key, $value)
    {
        if (empty($this->getParameter($key))) {
            $this->addParameter($key, [urldecode($value)]);
        } else {
            $this->addParameter($key, urldecode($value));
        }
    }

    /**
     * @param array $categories
     */
    public function addCategories(array $categories)
    {
        $this->addParameter('cat', $categories);
    }

    /**
     * @param string $order
     */
    public function addOrder($order)
    {
        $this->parameters['order'] = $order;
    }

    /**
     * @param int $firstResult
     */
    public function setFirstResult($firstResult)
    {
        $this->parameters['first'] = $firstResult;
    }

    /**
     * @param int $maxResults
     */
    public function setMaxResults($maxResults)
    {
        $this->parameters['count'] = $maxResults;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    private function getParameter($key)
    {
        if (!isset($this->parameters[self::PARAMETER_KEY_ATTRIB]) ||
            !isset($this->parameters[self::PARAMETER_KEY_ATTRIB][$key])) {
            return null;
        }

        return $this->parameters[self::PARAMETER_KEY_ATTRIB][$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    private function addParameter($key, $value)
    {
        if (!isset($this->parameters[self::PARAMETER_KEY_ATTRIB])) {
            $this->parameters[self::PARAMETER_KEY_ATTRIB] = [];
        }

        if ($this->getParameter($key) === null) {
            $this->parameters[self::PARAMETER_KEY_ATTRIB][$key] = $value;
        } else {
            $this->parameters[self::PARAMETER_KEY_ATTRIB][$key][] = $value;
        }
    }

    /**
     * @param string $usergroup
     */
    public function addGroup($usergroup)
    {
        $hashedKey = StaticHelper::calculateUsergroupHash($this->shopKey, $usergroup);
        $this->parameters['group'] = [$hashedKey];
    }
}
