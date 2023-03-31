<?php

namespace Cangokdayi\WPFacades\Database;

/**
 * MySQL Query Operators
 */
final class Operator
{
    const EQUAL            = '=';
    const NOT_EQUAL        = '<>';
    const GREATER          = '>';
    const LESS             = '<';
    const GREATER_OR_EQUAL = '>=';
    const LESS_OR_EQUAL    = '<=';
    const BETWEEN          = 'BETWEEN';
    const LIKE             = 'LIKE';
    const IN               = 'IN';
    const NULL             = 'IS NULL';
    const NOT_NULL         = 'IS NOT NULL';
}