<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

class SmartDidYouMean
{
    const DID_YOU_MEAN = 'did-you-mean';
    const IMPROVED = 'improved';

    /** @var null|string */
    private $type;

    /** @var string|null */
    private $link;

    /** @var string */
    private $alternativeQuery;

    /** @var string */
    private $originalQuery;

    public function __construct(
        $originalQuery = null,
        $alternativeQuery = null,
        $didYouMeanQuery = null,
        $type = null,
        $controllerPath = null
    ) {
        $this->type = $didYouMeanQuery !== null ? self::DID_YOU_MEAN : $type;
        $this->alternativeQuery = htmlentities($alternativeQuery ?: '');
        $this->originalQuery = $this->type === self::DID_YOU_MEAN ? '' : htmlentities($originalQuery);

        $this->link = $this->createLink($controllerPath);
    }

    /**
     * @param string|null $controllerPath
     *
     * @return string|null
     */
    private function createLink($controllerPath)
    {
        switch ($this->type) {
            case self::DID_YOU_MEAN:
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->alternativeQuery
                );
            case self::IMPROVED:
                return sprintf(
                    '%s?search=%s&forceOriginalQuery=1',
                    $controllerPath,
                    $this->originalQuery
                );
            default:
                return null;
        }
    }

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return string
     */
    public function getAlternativeQuery()
    {
        return $this->alternativeQuery;
    }

    /**
     * @return string
     */
    public function getOriginalQuery()
    {
        return $this->originalQuery;
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return [
            'type' => $this->type,
            'link' => $this->link,
            'alternativeQuery' => $this->alternativeQuery,
            'originalQuery' => $this->originalQuery,
        ];
    }
}
