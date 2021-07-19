<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use Exception;
use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Config;
use FINDOLOGIC\Api\Definitions\OutputAdapter;
use FINDOLOGIC\Api\Definitions\QueryParameter;
use FINDOLOGIC\Api\Exceptions\InvalidParamException;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Responses\Response;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware_Components_Config;

abstract class QueryBuilder
{
    /**
     * @var SearchNavigationRequest
     */
    protected $searchNavigationRequest;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var Client
     */
    private $apiClient;

    /**
     * @var string
     */
    private $shopKey;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var InstallerService
     */
    private $installerService;

    public function __construct(
        InstallerService $installerService,
        Shopware_Components_Config $config,
        Client $apiClient = null
    ) {
        $this->installerService = $installerService;
        $this->config = $config;
        $this->shopKey = $config->offsetGet('ShopKey');
        $this->shopUrl = rtrim(Shopware()->Shop()->getHost(), '/');

        if ($apiClient === null) {
            $apiConfig = new Config();
            $apiConfig->setServiceId($this->shopKey);
            $apiClient = new Client($apiConfig);
        }

        $this->apiClient = $apiClient;
        $this->setDefaultParameters();
    }

    /**
     * @return Response
     * @throws ServiceNotAliveException
     */
    public function execute()
    {
        return $this->apiClient->send($this->searchNavigationRequest);
    }

    /**
     * @param array $categories
     */
    abstract public function addCategories(array $categories);

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->searchNavigationRequest->getParams();
    }

    /**
     * @return string
     */
    public function getRequestUrl(Config $config)
    {
        return $this->searchNavigationRequest->buildRequestUrl($config);
    }

    /**
     * @param string $query
     */
    public function addQuery($query)
    {
        $this->searchNavigationRequest->setQuery($query);
    }

    /**
     * @param string $key
     * @param float $min
     * @param float $max
     */
    public function addRangeFilter($key, $min, $max)
    {
        $this->searchNavigationRequest->addAttribute($key, $min, 'min');
        $this->searchNavigationRequest->addAttribute($key, $max, 'max');
    }

    /**
     * @param string $key
     * @param mixed $values
     */
    public function addFilter($key, $values)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        foreach ($values as $value) {
            $this->addParameter($key, $value);
        }
    }

    /**
     * @param string $filterName
     * @param string $filterValue
     * @param float $weight
     */
    public function addPushAttrib($filterName, $filterValue, $weight)
    {
        $this->searchNavigationRequest->addPushAttrib($filterName, $filterValue, $weight);
    }

    /**
     * @param string $order
     */
    public function addOrder($order)
    {
        $this->searchNavigationRequest->setOrder($order);
    }

    /**
     * @param int $firstResult
     */
    public function setFirstResult($firstResult)
    {
        $this->searchNavigationRequest->setFirst($firstResult);
    }

    /**
     * @param int $maxResults
     */
    public function setMaxResults($maxResults)
    {
        $this->searchNavigationRequest->setCount($maxResults);
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getParameter($key)
    {
        $params = $this->searchNavigationRequest->getParams();

        return $params[QueryParameter::ATTRIB][$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function addParameter($key, $value)
    {
        $this->searchNavigationRequest->addAttribute($key, $value);
    }

    /**
     * @param string $usergroup
     */
    public function addUserGroup($usergroup)
    {
        $usergrouphash = StaticHelper::calculateUsergroupHash($this->shopKey, $usergroup);
        $this->searchNavigationRequest->addUserGroup($usergrouphash);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function addFlag($key, $value = true)
    {
        if ($key === Constants::SDYM_PARAM_FORCE_QUERY) {
            $this->searchNavigationRequest->setForceOriginalQuery();

            return;
        }

        $this->searchNavigationRequest->addAttribute($key, (bool)$value ? '1' : '0');
    }

    /**
     * @throws Exception
     */
    protected function setDefaultParameters()
    {
        $plugin = $this->installerService->getPluginByName('FinSearchUnified');

        try {
            // setShopUrl() requires a valid host. If we do not have a valid host (e.g. local development)
            // this would cause an exception.
            $this->searchNavigationRequest->setShopUrl($this->shopUrl);
        } catch (InvalidParamException $e) {
            $this->searchNavigationRequest->setShopUrl('example.org');
        }

        $this->searchNavigationRequest->setShopkey($this->shopKey);
        $this->searchNavigationRequest->setUserIp($this->getClientIp());
        $this->searchNavigationRequest->setRevision(rtrim($plugin->getVersion()));
        $this->searchNavigationRequest->setOutputAdapter(OutputAdapter::XML_21);
    }

    /**
     * @return string
     */
    protected function getClientIp()
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
            $ipAddress = '192.168.0.1';
        }

        $ipAddress = implode(',', array_unique(array_map('trim', explode(',', $ipAddress))));

        return $ipAddress;
    }
}
