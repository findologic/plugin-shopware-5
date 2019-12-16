<?php

namespace FinSearchUnified\Tests\BusinessLogic;

use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\Tests\TestCase;
use Shopware\Components\Logger;

class FindologicArticleFactoryTest extends TestCase
{
    public function testFindologicArticleFactory(){

        $mockedCreate = $this->createMock(FindologicArticleFactory::class);
        $mockedCreate->expects($this->once())
            ->method('create')
            ->willThrowException(new EmptyValueNotAllowedException());

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())->method('info')
            ->with("Product with id  could not be exported. It appears to has empty values assigned to it. 
                If you see this message in your logs, please report this as a bug");

        Shopware()->Container()->set('pluginlogger', $mockLogger);
    }
}