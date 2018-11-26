<?php

use Shopware\Components\Test\Plugin\TestCase;

class FrontendTest extends TestCase
{
    public function frontendDispatchProvider()
    {
        return [
            'Search Page' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'Controller' => null,
                'Action' => null,
                'module' => null
            ],
            'Category Page' => [
                'sSearch' => null,
                'sCategory' => 1,
                'Controller' => 'listing',
                'Action' => null,
                'module' => null
            ],
            'Manufacturer Page' => [
                'sSearch' => null,
                'sCategory' => null,
                'Controller' => 'listing',
                'Action' => 'manufacturer',
                'module' => null
            ],
            'Backend Module' => [
                'sSearch' => null,
                'sCategory' => null,
                'Controller' => null,
                'Action' => null,
                'module' => 'backend'
            ],
            'Backend Module' => [
                'sSearch' => null,
                'sCategory' => null,
                'Controller' => 'testing',
                'Action' => null,
                'module' => null
            ],
        ];
    }

    /**
     * @dataProvider frontendDispatchProvider
     *
     * @param string $sSearch
     * @param int|null $sCategory
     * @param string $controller
     * @param string $action
     * @param string $module
     */
    public function testFrontendPreDispatchConditions($sSearch, $sCategory, $controller, $action, $module)
    {
        // TODO implement the test here
    }
}