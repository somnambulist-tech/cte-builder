Change Log
==========

2024-03-02
----------

 * Update dependencies
 * Support DBAL 4
 * Drop support for DBAL <3
 * Remove `Expression::getQueryParts()` as it no longer exists in DBAL 4

2023-04-17
----------

 * Add basic UNION/UNION ALL support to `Expression` object
 * Remove unnecessary docblocks
 * Update workflow config

2022-03-30
----------

 * Fix deep cloning should clone the parameters and expressions

2022-03-24
----------

 * BC Break: change return type on `ExpressionBuilder::execute` due to DBAL changes
 * Add Pagerfanta adapter for handling pagination of expression builder
 * Add `__clone` to prevent reference re-use in paginator on `ExpressionBuilder` and `Expression`
 * Fix method calls to DBAL to call deprecated method on 2.X

2021-12-27
----------

 * Add support for `doctrine/dbal` 3.X
 * Update type hints

2021-05-25
----------

 * Add support for recursive expressions
 * Add support for explicit return fields in CTE definition

2021-02-06
----------

 * Minor code tweaks; add missing return types

2021-01-20
----------

 * Require PHP 8

2020-09-29
----------

 * Re-namespace to `Somnambulist\Components`

2020-08-26
----------

 * Require PHP 7.4 as minimum version

2019-08-15
----------

 * Initial commit
