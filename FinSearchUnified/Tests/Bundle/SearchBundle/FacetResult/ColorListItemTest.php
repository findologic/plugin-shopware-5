<?php

use FinSearchUnified\Bundle\SearchBundle\FacetResult\ColorListItem;
use FinSearchUnified\Tests\TestCase;

class ColorListItemTest extends TestCase
{
    public function testMemberVariablesAreFilled()
    {
        $colorListItem = new ColorListItem(
            1,
            'Color',
            true,
            '#696969',
            'https://via.placeholder.com/50x50'
        );

        $this->assertSame('#696969', $colorListItem->getColorcode());
        $this->assertSame('https://via.placeholder.com/50x50', $colorListItem->getImageUrl());
    }
}
