Change Log
==========

2022-03-24
----------

 * BC Break: change return type on `ExpressionBuilder::execute` due to DBAL changes
 * Add Pagerfanta adapter for handling pagination of expression builder
 * Add `__clone` to prevent reference re-use in paginator on `ExpressionBuilder` and `Expression`

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
