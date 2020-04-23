<?php
declare(strict_types=1);

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

class Promotion
{
    /** @var string */
    private $image;

    /** @var string */
    private $link;

    public function __construct(string $image, string $link)
    {
        $this->image = $image;
        $this->link = $link;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getLink(): string
    {
        return $this->link;
    }
}
