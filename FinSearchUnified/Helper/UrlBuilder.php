<?php

namespace FinSearchUnified\Helper;

use Exception;
use FinSearchUnified\Constants;
use Zend_Http_Client;
use Zend_Http_Client_Exception;

class UrlBuilder
{
    const CDN_URL = 'https://cdn.findologic.com/static/';
    const JSON_CONFIG = '/config.json';
    const JSON_PATH = 'directIntegration';

    /**
     * @var Zend_Http_Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $shopkey;

    /**
     * @var string
     */
    private $configUrl;

    /**
     * UrlBuilder constructor.
     *
     * @param null|Zend_Http_Client $httpClient The Zend HTTP client to use.
     *
     * @throws Exception
     */
    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient instanceof Zend_Http_Client ? $httpClient : new Zend_Http_Client();
    }

    /**
     * Never call this method in any constructor since Shopware can't guarantee that the relevant shop is already
     * loaded at that point. Therefore the master shops shopkey would be returned.
     * Caches and returns the current shop's shopkey.
     *
     * @return string
     */
    private function getShopkey()
    {
        if ($this->shopkey === null) {
            $this->shopkey = strtoupper(Shopware()->Config()->get('ShopKey'));
        }

        return $this->shopkey;
    }

    /**
     * Caches and returns the URL for the current shop's config JSON.
     *
     * @return string
     */
    private function getConfigUrl()
    {
        if ($this->configUrl === null) {
            $this->configUrl = self::CDN_URL . strtoupper(md5($this->getShopkey())) . self::JSON_CONFIG;
        }

        return $this->configUrl;
    }

    /**
     * @return bool
     */
    public function getConfigStatus()
    {
        try {
            $request = $this->httpClient->setUri($this->getConfigUrl());
            $requestHandler = $request->request();

            if ($requestHandler->getStatus() === 200) {
                $response = $requestHandler->getBody();
                $jsonResponse = json_decode($response, true);
                $isDirectIntegration = (bool)$jsonResponse[self::JSON_PATH]['enabled'];
            } else {
                $isDirectIntegration = Shopware()->Config()->offsetGet('IntegrationType') === Constants::INTEGRATION_TYPE_DI;
            }
        } catch (Zend_Http_Client_Exception $e) {
            $isDirectIntegration = Shopware()->Config()->offsetGet('IntegrationType') === Constants::INTEGRATION_TYPE_DI;
        }

        return $isDirectIntegration;
    }
}
