<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Somnambulist\Components\Collection\MutableCollection as Collection;
use Somnambulist\Components\CTEBuilder\Exceptions\CannotCreateUnionWithOrderByException;
use Somnambulist\Components\CTEBuilder\Expression;

class ExpressionTest extends TestCase
{

    public function testCreate()
    {
        $cte = new Expression('alias', $q = new QueryBuilder($this->createMock(Connection::class)), ['this', 'that']);

        $this->assertEquals('alias', $cte->getAlias());
        $this->assertEquals(['this', 'that'], $cte->getDependencies());
        $this->assertSame($q, $cte->query());
        $this->assertInstanceOf(Collection::class, $cte->getParameters());
    }

    public function testAddDependencies()
    {
        $cte = new Expression('alias', $q = new QueryBuilder($this->createMock(Connection::class)), ['this', 'that']);

        $this->assertEquals(['this', 'that'], $cte->getDependencies());

        $cte->dependsOn('bob');

        $this->assertEquals(['this', 'that', 'bob'], $cte->getDependencies());
    }

    public function testMethodPassThrough()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', $q = new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
            ->orderBy('field2', 'ASC')
        ;

        $this->assertInstanceOf(ExpressionBuilder::class, $cte->expr());
        $this->assertEquals('SELECT field, field2 FROM table ORDER BY field2 ASC', $cte->getSQL());
    }

    public function testUnion()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $cte->union($expr);

        $this->assertEquals('SELECT field, field2 FROM table UNION SELECT a, b, c FROM table2', $cte->getSQL());
    }

    public function testUnionAll()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $cte->unionAll($expr);

        $this->assertEquals('SELECT field, field2 FROM table UNION ALL SELECT a, b, c FROM table2', $cte->getSQL());
    }

    public function testUnionWithOrderByFails()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
            ->orderBy('field2')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $this->expectException(CannotCreateUnionWithOrderByException::class);

        $cte->union($expr);
        $cte->getSQL();
    }

    public function testMixedUnions()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');
        $expr2 = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $cte->addUnion($expr, all: true)->addUnion($expr2);

        $this->assertEquals('SELECT field, field2 FROM table UNION ALL SELECT a, b, c FROM table2 UNION SELECT a, b, c FROM table2', $cte->getSQL());
    }

    public function testCallsToUnionResetsSet()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');
        $expr2 = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $cte->unionAll($expr)->union($expr2);

        $this->assertEquals('SELECT field, field2 FROM table UNION SELECT a, b, c FROM table2', $cte->getSQL());
    }

    public function testCallsToUnionAllResetsSet()
    {
        $conn = $this->getConnection();

        $cte = new Expression('alias', new QueryBuilder($conn), ['this', 'that']);
        $cte
            ->select('field', 'field2')
            ->from('table')
        ;

        $expr = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');
        $expr2 = (new Expression('', new QueryBuilder($conn)))->select('a', 'b', 'c')->from('table2');

        $cte->union($expr2)->unionAll($expr);

        $this->assertEquals('SELECT field, field2 FROM table UNION ALL SELECT a, b, c FROM table2', $cte->getSQL());
    }

    private function getConnection(): Connection
    {
        return DriverManager::getConnection((new DsnParser)->parse('sqlite3:///:memory:'));
    }
}
