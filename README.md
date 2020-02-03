# Common Table Expression Builder

[![GitHub Actions Build Status](https://github.com/somnambulist-tech/cte-builder/workflows/tests/badge.svg)](https://github.com/somnambulist-tech/cte-builder/actions?query=workflow%3Atests)

Provides a programmatic interface via Doctrine DBAL Query Builder for creating Common
Table Expressions (WITH clauses). Includes support for CTE dependencies and debugging.
A CTE allows extracting a sub-select or derived query that can be executed separately
to the main query, but then referenced again. Depending on the database server being
used, there may be significant performance advantages to this approach, for example:
in Postgres a CTE is evaluated once and the same result used no matter how many times
it is referenced.

CTEs can also be used to pre-generate content that is needed multiple times, ensuring
that any significant computational cost is only incurred once.

Be sure to read up on CTEs and WITH clauses for your chosen database server.

## Requirements

 * PHP 7.2+
 * doctrine/dbal
 * somnambulist/collection

## Installation

Install using composer, or checkout / pull the files from github.com.

 * composer require somnambulist/cte-builder

## Usage

CTE Builder consists of the ExpressionBuilder and the Expression. Expressions are created
either directly and bound to the builder, or via the builder. The builder requires:

 * DBAL Connection

If using Symfony the default configured connection can be used.

```php
$eb = new ExpressionBuilder($connection);
$expr = $eb->createExpression('first_clause');

$stmt = $eb->select('field', 'another field')->from('table_or_cte')->execute();
```

Each expression is its own independent query builder instance using the same connection.
Each CTE can be as complex as required.

CTEs can reference other CTEs. When creating the query, use the CTE alias. It helps to use
constants for the aliases to make it easier to keep them in-sync. When referencing other
CTEs it is important to then set that as an explicit dependency of the CTE:

```php
$eb = new ExpressionBuilder($connection);
$expr1 = $eb->createExpression('first_clause');
$expr1->from('second_clause');
$expr2 = $eb->createExpression('second_clause');

$expr1->dependsOn('second_clause');
```

It is very important to keep track of your dependencies as CTEs must be defined before
they are referenced - they cannot be back-referenced; hence the need to set the dependency.

Alternatively, specify the dependencies when creating the expression:

```php
$eb = new ExpressionBuilder($connection);
$expr1 = $eb->createExpression('first_clause', 'second_clause');
$expr1->from('second_clause');
```

__Note:__ if dependencies are specified at creation they cannot be undone i.e. they are
permanently bound to the expression.

Both the ExpressionBuilder and Expression expose `query()` for accessing the QueryBuilder
directly. Parameters can be bound to both and will be automatically merged together in the
ExpressionBuilder as needed.

__Note:__ because the CTEs can be re-ordered and all parameters must be collected together
and passed to the compiled query named-placeholders __MUST__ be used. If positional
placeholders are used, the query will almost certainly fail.

Once defined a CTE can be accessed from the ExpressionBuilder either via the `get()` method
or via a dynamic property accessor:

```php
$eb = new ExpressionBuilder($connection);
$eb->createExpression('first_clause');
$eb->createExpression('second_clause');
$eb->createExpression('third_clause');

$eb->third_clause->select();
```

## Profiling

If you use Symfony; using the standard Doctrine DBAL connection from your entity manager will
automatically ensure that the main SQL query is automatically profiled. However: the fully
compiled query with substituted parameters can be dumped by passing in a logger instance. The
query will be logged using debug. This should be done when testing / debugging building a
complex query.

For further insights consider using an application profiler such as:

 * [Tideways](https://tideways.io)
 * [BlackFire](https://blackfire.io)

For other frameworks; as DBAL is used, hook into the Configuration object and add an SQL
logger instance that can report to your frameworks profiler.

## Test Suite

Run the test suite via: vendor/bin/phpunit.
