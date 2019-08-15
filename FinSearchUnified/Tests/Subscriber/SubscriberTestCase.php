<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use FinSearchUnified\Tests\TestCase;
use ReflectionException;
use ReflectionMethod;

class SubscriberTestCase extends TestCase
{
    /**
     * @param string $controller
     * @param Enlight_Controller_Request_RequestHttp $request
     * @param Enlight_Controller_Response_ResponseHttp|null $response
     *
     * @return Enlight_Controller_Action
     * @throws ReflectionException
     */
    protected function getControllerInstance(
        $controller,
        Enlight_Controller_Request_RequestHttp $request,
        Enlight_Controller_Response_ResponseHttp $response = null
    ) {
        if (is_null($response)) {
            $response = new Enlight_Controller_Response_ResponseHttp();
        }

        $reflectionMethod = new ReflectionMethod($controller, 'Instance');

        /** @var Enlight_Controller_Action $subject */
        $subject = $reflectionMethod->invoke(null, $controller, [$request, $response]);

        if (is_callable([$subject, 'initController'])) {
            $subject->initController($request, $response);
        }

        return $subject;
    }
}
