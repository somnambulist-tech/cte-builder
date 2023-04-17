<?php declare(strict_types=1);

namespace Somnambulist\Components\CTEBuilder\Tests\Pagination;

use Doctrine\DBAL\DriverManager;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Somnambulist\Components\CTEBuilder\ExpressionBuilder;
use Somnambulist\Components\CTEBuilder\Pagination\PagerfantaAdapter;

class PagerfantaAdapterTest extends TestCase
{
    public function testPaginator()
    {
        $conn = DriverManager::getConnection(['url' => 'sqlite://memory']);

        $conn->executeStatement(<<<SQL
            CREATE TABLE users (
                id, name, type, created_at
            )
        SQL);
        $conn->executeStatement(<<<SQL
            INSERT INTO users 
                (id, name, type, created_at)
            VALUES
                ('522f54cb-c551-4be8-9b3d-bc202251aff0', 'foo bar', 'user', datetime()),
                ('a71f1e13-d19d-4d99-85df-dae410ba7d89', 'baz', 'user', datetime()),
                ('0ff46bd3-ed32-473e-af5f-273a6f6f53e8', 'wah wah', 'user', datetime()),
                ('bc731a21-5651-4c2a-9937-7451c204c0c8', 'bob example', 'admin', datetime()),
                ('96f2f04f-e305-49cb-a79e-4271a4c62900', 'a.n. user', 'user', datetime()),
                ('b8d2ebd4-7cd2-4ecf-823c-6e8548aba615', 'bar hopper', 'user', datetime())
        SQL);

        // really dumb example.... urgh!
        $cte = new ExpressionBuilder($conn);
        $users = $cte->createExpression('only_users');
        $users->select('*')->from('users')->where('type = :type')->setParameter('type', 'user');

        $cte->select('*')->from('only_users');

        $paginator = new PagerfantaAdapter($cte, function (ExpressionBuilder $qb) {
            $qb->select('COUNT(*) AS total_results');
        });

        $pf = new Pagerfanta($paginator);
        $pf->setMaxPerPage(1)->setCurrentPage(3);
        $pf->getCurrentPageResults();

        $this->assertCount(1, $pf->getCurrentPageResults());
        $this->assertEquals(5, $pf->count());
    }
}
