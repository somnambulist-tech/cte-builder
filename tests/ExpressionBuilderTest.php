<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use Somnambulist\Components\CTEBuilder\Exceptions\ExpressionAlreadyExistsException;
use Somnambulist\Components\CTEBuilder\Exceptions\ExpressionNotFoundException;
use Somnambulist\Components\CTEBuilder\Exceptions\UnresolvableDependencyException;
use Somnambulist\Components\CTEBuilder\Expression;
use Somnambulist\Components\CTEBuilder\ExpressionBuilder;
use Somnambulist\Components\Domain\Entities\Types\DateTime\DateTime;
use Somnambulist\Components\Domain\Utils\EntityAccessor;

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

        $prices = $qb->createExpression('prices');
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
            'pr.product_id = p.id) SELECT * FROM relevancy r';

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

        $prices = $qb->createExpression('prices');
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
            'pr.product_id = p.id) SELECT * FROM relevancy r';

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
        $prices = $qb->createExpression('prices');
        $sizes = $qb->createExpression('sizes');

        $relevancy->dependsOn('available', 'prices', 'sizes');
        $prices->dependsOn('available');
        $sizes->dependsOn('available');

        $ordered = EntityAccessor::call($qb, 'buildDependencyTree', $qb, $qb->expressions());

        $expected = ['available', 'prices', 'sizes', 'relevancy'];

        $this->assertEquals($expected, $ordered->map(function (Expression $cte) {
            return $cte->getAlias();
        })->toArray());
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
        $prices = $qb->createExpression('prices');
        $sizes = $qb->createExpression('sizes');

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
            ':end_date'   => DateTime::now()->toDateTimeString(),
        ]);

        $relevancy = $qb->createExpression('relevancy');
        $relevancy
            ->setParameters([
                ':this' => 'that',
            ])
        ;

        $qb->createExpression('foobars');

        $sizes = $qb->createExpression('bazbars');
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

    public function testCanBuildRecursiveCTEs()
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
        $cte = $qb->createRecursiveExpression('category_tree');
        $cte
            ->withFields('id', 'name', 'parent_id')
            ->withInitialSelect('SELECT id, name, parent_id FROM category WHERE id = 3')
            ->query()
            ->select('c.id', 'c.name', 'c.parent_id')
            ->from('category', 'c')
            ->innerJoin('c', 'category_tree', 'ct', 'ct.parent_id = c.id')
        ;

        $qb->select('*')->from('category_tree');

        /* @link https://github.com/somnambulist-tech/cte-builder/issues/1 */
        $expected =
            'WITH RECURSIVE category_tree (id, name, parent_id) AS ' .
            '(' .
            'SELECT id, name, parent_id FROM category WHERE id = 3 ' .
            'UNION ALL' .
            ' SELECT c.id, c.name, c.parent_id FROM category c INNER JOIN category_tree ct ON ct.parent_id = c.id' .
            ')' .
            ' SELECT * FROM category_tree';

        $this->assertEquals($expected, $qb->getSQL());
    }

    public function testCanBuildRecursiveCTEMoreComplexExample()
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
        $qb->createRecursiveExpression('xaxis')->withFields('x')->withInitialSelect('VALUES(-2.0)')->select('x+0.05')->from('xaxis')->where('x<1.2');
        $qb->createRecursiveExpression('yaxis')->withFields('y')->withInitialSelect('VALUES(-1.0)')->select('y+0.1')->from('yaxis')->where('y<1.0');
        $qb
            ->createRecursiveExpression('m')
            ->withFields('iter', 'cx', 'cy', 'x', 'y')
            ->withInitialSelect('SELECT 0, x, y, 0.0, 0.0 FROM xaxis, yaxis')
            ->select('iter+1', 'cx', 'cy', 'x*x-y*y + cx', '2.0*x*y + cy')
            ->from('m')
            ->where('(x*x + y*y) < 4.0 AND iter<28')
            ->dependsOn('xaxis', 'yaxis')
        ;
        $qb->createExpression('m2')->select('max(iter) AS iter', 'cx', 'cy')->from('m')->groupBy('cx')->addGroupBy('cy')->dependsOn('m');
        $qb->createExpression('a')->select('group_concat( substr(\' .+*#\', 1+min(iter/7,4), 1), \'\') as t')->from('m2')->groupBy('cy')->dependsOn('m2');

        $qb->select('group_concat(rtrim(t),x\'0a\')')->from('a');

        /**
         * The (mangled) query below is the example Mandelbrot recursive query from the SQlite docs,
         * slightly modified to use aliases on some of the later CTEs.
         *
         * @link https://sqlite.org/lang_with.html
         */
        $expected =
            'WITH RECURSIVE ' .
                'xaxis (x) AS (VALUES(-2.0) UNION ALL SELECT x+0.05 FROM xaxis WHERE x<1.2), ' .
                'yaxis (y) AS (VALUES(-1.0) UNION ALL SELECT y+0.1 FROM yaxis WHERE y<1.0), ' .
                'm (iter, cx, cy, x, y) AS (' .
                    'SELECT 0, x, y, 0.0, 0.0 FROM xaxis, yaxis ' .
                    'UNION ALL ' .
                    'SELECT iter+1, cx, cy, x*x-y*y + cx, 2.0*x*y + cy FROM m ' .
                    'WHERE (x*x + y*y) < 4.0 AND iter<28' .
                '), ' .
                'm2 AS (' .
                    'SELECT max(iter) AS iter, cx, cy FROM m GROUP BY cx, cy' .
                '), ' .
                'a AS (' .
                    'SELECT group_concat( substr(\' .+*#\', 1+min(iter/7,4), 1), \'\') as t ' .
                    'FROM m2 GROUP BY cy' .
                ') ' .
            'SELECT group_concat(rtrim(t),x\'0a\') FROM a'
        ;

        $this->assertEquals($expected, $qb->getSQL());
    }

    public function testRecursiveExpressionHonourDependencies()
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
            ->createRecursiveExpression('m')
            ->withFields('iter', 'cx', 'cy', 'x', 'y')
            ->withInitialSelect('SELECT 0, x, y, 0.0, 0.0 FROM xaxis, yaxis')
            ->select('iter+1', 'cx', 'cy', 'x*x-y*y + cx', '2.0*x*y + cy')
            ->from('m')
            ->where('(x*x + y*y) < 4.0 AND iter<28')
            ->dependsOn('xaxis', 'yaxis')
        ;
        $qb->createExpression('m2')->select('max(iter) AS iter', 'cx', 'cy')->from('m')->groupBy('cx')->addGroupBy('cy')->dependsOn('m');
        $qb->createRecursiveExpression('xaxis')->withFields('x')->withInitialSelect('VALUES(-2.0)')->select('x+0.05')->from('xaxis')->where('x<1.2');
        $qb->createRecursiveExpression('yaxis')->withFields('y')->withInitialSelect('VALUES(-1.0)')->select('y+0.1')->from('yaxis')->where('y<1.0');
        $qb->createExpression('a')->select('group_concat( substr(\' .+*#\', 1+min(iter/7,4), 1), \'\') as t')->from('m2')->groupBy('cy')->dependsOn('m2');

        $qb->select('group_concat(rtrim(t),x\'0a\')')->from('a');

        $expected =
            'WITH RECURSIVE ' .
                'xaxis (x) AS (VALUES(-2.0) UNION ALL SELECT x+0.05 FROM xaxis WHERE x<1.2), ' .
                'yaxis (y) AS (VALUES(-1.0) UNION ALL SELECT y+0.1 FROM yaxis WHERE y<1.0), ' .
                'm (iter, cx, cy, x, y) AS (' .
                    'SELECT 0, x, y, 0.0, 0.0 FROM xaxis, yaxis ' .
                    'UNION ALL ' .
                    'SELECT iter+1, cx, cy, x*x-y*y + cx, 2.0*x*y + cy FROM m ' .
                    'WHERE (x*x + y*y) < 4.0 AND iter<28' .
                '), ' .
                'm2 AS (' .
                    'SELECT max(iter) AS iter, cx, cy FROM m GROUP BY cx, cy' .
                '), ' .
                'a AS (' .
                    'SELECT group_concat( substr(\' .+*#\', 1+min(iter/7,4), 1), \'\') as t ' .
                    'FROM m2 GROUP BY cy' .
                ') ' .
            'SELECT group_concat(rtrim(t),x\'0a\') FROM a'
        ;

        $this->assertEquals($expected, $qb->getSQL());
    }

    public function testExecuteQuery()
    {
        $conn = DriverManager::getConnection(['url' => 'sqlite://memory']);

        $qb = new ExpressionBuilder($conn);
        $qb
            ->createRecursiveExpression('m')
            ->withFields('iter', 'cx', 'cy', 'x', 'y')
            ->withInitialSelect('SELECT 0, x, y, 0.0, 0.0 FROM xaxis, yaxis')
            ->select('iter+1', 'cx', 'cy', 'x*x-y*y + cx', '2.0*x*y + cy')
            ->from('m')
            ->where('(x*x + y*y) < 4.0 AND iter<28')
            ->dependsOn('xaxis', 'yaxis')
        ;
        $qb->createExpression('m2')->select('max(iter) AS iter', 'cx', 'cy')->from('m')->groupBy('cx')->addGroupBy('cy')->dependsOn('m');
        $qb->createRecursiveExpression('xaxis')->withFields('x')->withInitialSelect('VALUES(-2.0)')->select('x+0.05')->from('xaxis')->where('x<1.2');
        $qb->createRecursiveExpression('yaxis')->withFields('y')->withInitialSelect('VALUES(-1.0)')->select('y+0.1')->from('yaxis')->where('y<1.0');
        $qb->createExpression('a')->select('group_concat( substr(\' .+*#\', 1+min(iter/7,4), 1), \'\') as t')->from('m2')->groupBy('cy')->dependsOn('m2');

        $qb->select('group_concat(rtrim(t),x\'0a\')')->from('a');

        $ret = $qb->execute()->fetchAllAssociative();

        $this->assertIsArray($ret);
    }

    public function testBuildCteWithUnionQuery()
    {
        $conn = DriverManager::getConnection(['url' => 'sqlite://memory']);

        $qb = new ExpressionBuilder($conn);

        $id = '50991b98-1769-4332-9a7f-e2150d6eea58';

        // Created aside from the main ExpressionBuilder and not even registered to it.
        $unionSubQb1 = $qb->createExpression('reported_objects');
        $unionSubQb1
            ->addSelect('p.id')
            ->addSelect('p.full_name as object_name')
            ->addSelect('\'person\' as type')
            ->from('people', 'p')
            ->andWhere('p.id = :id1')
            ->setParameter('id1', $id)
        ;

        // Created aside from the main ExpressionBuilder and not even registered to it.
        $unionSubQb2 = $qb->createDetachedExpression();
        $unionSubQb2
            ->addSelect('c.id')
            ->addSelect('c.company_name as object_name')
            ->addSelect('\'company\' as type')
            ->from('companies', 'c')
            ->andWhere('c.id = :id2')
            ->setParameter('id2', $id)
        ;

        // Created aside from the main ExpressionBuilder and not even registered to it.
        $unionSubQb3 = $qb->createDetachedExpression();
        $unionSubQb3
            ->addSelect('p.id')
            ->addSelect('p.planet_name as object_name')
            ->addSelect('\'planet\' as type')
            ->from('planets', 'p')
            ->andWhere('p.id = :id3')
            ->setParameter('id3', $id)
        ;

        $unionSubQb1->union($unionSubQb2, $unionSubQb3);

        $qb
            ->addSelect('ro.id')
            ->addSelect('ro.object_name')
            ->addSelect('ro.type')
            ->from('reported_objects', 'ro')
            ->addOrderBy('ro.object_name', 'ASC')
        ;

        $expected =
            'WITH reported_objects AS (' .
                'SELECT p.id, p.full_name as object_name, \'person\' as type FROM people p WHERE p.id = :id1 ' .
                'UNION ' .
                'SELECT c.id, c.company_name as object_name, \'company\' as type FROM companies c WHERE c.id = :id2 ' .
                'UNION ' .
                'SELECT p.id, p.planet_name as object_name, \'planet\' as type FROM planets p WHERE p.id = :id3' .
            ') ' .
            'SELECT ro.id, ro.object_name, ro.type FROM reported_objects ro ORDER BY ro.object_name ASC'
        ;

        $this->assertEquals($expected, $qb->getSQL());
        $this->assertEquals(
            [
                'id1' => $id,
                'id2' => $id,
                'id3' => $id,
            ],
            $qb->getParameters()->toArray(),
        );
    }
}
