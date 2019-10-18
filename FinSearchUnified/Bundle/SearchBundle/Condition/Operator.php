<?php

namespace FinSearchUnified\Bundle\SearchBundle\Condition;

abstract class Operator
{
    const EQ = '=';
    const NEQ = '!=';
    const LT = '<';
    const LTE = '<=';
    const GT = '>';
    const GTE = '>=';
    const NOT_IN = 'NOT IN';
    const IN = 'IN';
    const BETWEEN = 'BETWEEN';
    const STARTS_WITH = 'STARTS_WITH';
    const ENDS_WITH = 'ENDS_WITH';
    const CONTAINS = 'CONTAINS';
}
