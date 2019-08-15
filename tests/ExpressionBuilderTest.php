<?php

declare(strict_types=1);

namespace Somnambulist\CTEBuilder\Tests;

use OutOfBoundsException;
use Somnambulist\CTEBuilder\Expression;
use Somnambulist\CTEBuilder\ExpressionBuilder;
use Somnambulist\CTEBuilder\Exceptions\ExpressionAlreadyExistsException;
use Somnambulist\CTEBuilder\Exceptions\ExpressionNotFoundException;
use Somnambulist\CTEBuilder\Exceptions\UnresolvableDependencyException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Somnambulist\Domain\Entities\Types\DateTime\DateTime;
use Somnambulist\Domain\Utils\EntityAccessor;

/**
 * Class ExpressionBuilderTest
 *
 * @package    Somnambulist\CTEBuilder\Tests
 * @subpackage Somnambulist\CTEBuilder\Tests\ExpressionBuilderTest
 */
class ExpressionBuilderTest extends TestCase
{

    public function testBuildCTEQuery()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb
            ->select('*')
            ->from('relevancy', 'r')
        ;

        $available = $qb->createExpression('available');
        $available
            ->select('p.id', 'p.updated_at', 'p.status')
            ->from('products', 'p')
            ->where('p.updated_at BETWEEN :start_date AND :end_date')
        ;

        $relevancy = $qb->createExpression('relevancy');
        $relevancy
            ->select('p.*')
            ->from('available', 'p')
            ->innerJoin('p', 'prices', 'pr', 'pr.product_id = p.id')
        ;

        $prices    = $qb->createExpression('prices');
        $prices
            ->select('p.id', 'pr.price', 'pr.currency')
            ->from('prices', 'pr')
            ->innerJoin('pr', 'available', 'p', 'pr.product_id = p.id')
        ;

        $relevancy->dependsOn('available', 'prices');
        $prices->dependsOn('available');

        $sql = $qb->getSQL();

        $expected =
            'WITH available AS (SELECT p.id, p.updated_at, p.status FROM products p ' .
            'WHERE p.updated_at BETWEEN :start_date AND :end_date), prices AS (SELECT p.id, ' .
            'pr.price, pr.currency FROM prices pr INNER JOIN available p ON pr.product_id = p.id), ' .
            'relevancy AS (SELECT p.* FROM available p INNER JOIN prices pr ON ' .
            'pr.product_id = p.id) SELECT * FROM relevancy r'
        ;

        $this->assertEquals($expected, $sql);
    }

    public function testFetchingUninitialisedCTERaisesException()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);

        $this->expectException(ExpressionNotFoundException::class);

        $qb->get('available');
    }

    public function testCreatingDuplicateAliasRaisesException()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb->createExpression('products');

        $this->expectException(ExpressionAlreadyExistsException::class);

        $qb->createExpression('products');
    }

    public function testCanCastToString()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb
            ->select('*')
            ->from('relevancy', 'r')
        ;

        $available = $qb->createExpression('available');
        $available
            ->select('p.id', 'p.updated_at', 'p.status')
            ->from('products', 'p')
            ->where('p.updated_at BETWEEN :start_date AND :end_date')
        ;

        $relevancy = $qb->createExpression('relevancy');
        $relevancy
            ->select('p.*')
            ->from('available', 'p')
            ->innerJoin('p', 'prices', 'pr', 'pr.product_id = p.id')
        ;

        $prices    = $qb->createExpression('prices');
        $prices
            ->select('p.id', 'pr.price', 'pr.currency')
            ->from('prices', 'pr')
            ->innerJoin('pr', 'available', 'p', 'pr.product_id = p.id')
        ;

        $relevancy->dependsOn('available', 'prices');
        $prices->dependsOn('available');

        $expected =
            'WITH available AS (SELECT p.id, p.updated_at, p.status FROM products p ' .
            'WHERE p.updated_at BETWEEN :start_date AND :end_date), prices AS (SELECT p.id, ' .
            'pr.price, pr.currency FROM prices pr INNER JOIN available p ON pr.product_id = p.id), ' .
            'relevancy AS (SELECT p.* FROM available p INNER JOIN prices pr ON ' .
            'pr.product_id = p.id) SELECT * FROM relevancy r'
        ;

        $this->assertEquals($expected, (string)$qb);
    }

    public function testClear()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb
            ->select('*')
            ->from('relevancy', 'r')
        ;

        $qb->createExpression('available');
        $qb->createExpression('relevancy');
        $qb->createExpression('prices');

        $qb->clear();

        $expected = 'SELECT';

        $this->assertEquals($expected, $qb->getSQL());
    }

    public function testOrderByDependencies()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb->createExpression('available');

        $relevancy = $qb->createExpression('relevancy');
        $prices    = $qb->createExpression('prices');
        $sizes     = $qb->createExpression('sizes');

        $relevancy->dependsOn('available', 'prices', 'sizes');
        $prices->dependsOn('available');
        $sizes->dependsOn('available');

        $ordered = EntityAccessor::call($qb, 'buildDependencyTree', $qb, $qb->expressions());

        $expected = ['available', 'prices', 'sizes', 'relevancy'];

        $this->assertEquals($expected, $ordered->map(function (Expression $cte) { return $cte->getAlias(); })->toArray());
    }

    public function testOrderByDependenciesRaisesExceptionForUnmappedDependency()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $qb->createExpression('available');

        $relevancy = $qb->createExpression('relevancy');
        $prices    = $qb->createExpression('prices');
        $sizes     = $qb->createExpression('sizes');

        $relevancy->dependsOn('available', 'prices', 'sizes');
        $prices->dependsOn('available');
        $sizes->dependsOn('available', 'bob');

        $this->expectException(UnresolvableDependencyException::class);
        $this->expectExceptionMessage('CTE named "sizes" depends on "bob" that could not be resolved');

        EntityAccessor::call($qb, 'buildDependencyTree', $qb, $qb->expressions());
    }

    public function testOrderByDependenciesRaisesExceptionForCyclicalDependencies()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $available = $qb->createExpression('available');
        $available->dependsOn('relevancy');

        $relevancy = $qb->createExpression('relevancy');
        $relevancy->dependsOn('available');

        $this->expectException(UnresolvableDependencyException::class);
        $this->expectExceptionMessage('CTE named "available" has a cyclical dependency with "relevancy"');

        EntityAccessor::call($qb, 'buildDependencyTree', $qb, $qb->expressions());
    }

    public function testCanMergeParametersIntoSingleParametersCollection()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $available = $qb->createExpression('available');
        $available->setParameters([
            ':start_date' => DateTime::now()->toDateTimeString(),
            ':end_date' => DateTime::now()->toDateTimeString(),
        ]);

        $relevancy = $qb->createExpression('relevancy');
        $relevancy
            ->setParameters([
                ':this' => 'that',
            ])
        ;

        $qb->createExpression('foobars');

        $sizes     = $qb->createExpression('bazbars');
        $sizes
            ->setParameters([
                ':foo' => 'bar',
            ])
        ;

        $this->assertEquals([':start_date', ':end_date', ':this', ':foo'], $qb->getParameters()->keys()->toArray());
    }

    public function testCanAccessBoundCTEsByPropertyAccessor()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);
        $cte1 = $qb->createExpression('table');
        $cte2 = $qb->createExpression('table2');
        $cte3 = $qb->createExpression('table3');

        $this->assertSame($cte3, $qb->table3);
        $this->assertSame($cte2, $qb->table2);
        $this->assertSame($cte1, $qb->table);
    }

    public function testUnboundCTEAccessedByPropertyRaisesException()
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects($this->any())
            ->method('createQueryBuilder')
            ->will($this->returnCallback(function () use ($conn) {
                return new QueryBuilder($conn);
            }))
        ;

        $qb = new ExpressionBuilder($conn);

        $this->expectException(OutOfBoundsException::class);

        $qb->table;
    }
}
