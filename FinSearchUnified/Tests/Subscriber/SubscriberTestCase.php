<?php

namespace FinSearchUnified\Tests\Subscriber;

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
     * @return mixed
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
        $subject = $reflectionMethod->invoke(null, $controller, [$request, $response]);

        if (method_exists($subject, 'initController')) {
            $subject->initController($request, $response);
        }

        return $subject;
    }
}
