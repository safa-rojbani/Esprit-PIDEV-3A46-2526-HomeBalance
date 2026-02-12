<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    private bool $primedRequestStack = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->primeRequestStack();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $this->resetDatabase($entityManager);
        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }
    }

    protected function tearDown(): void
    {
        if ($this->primedRequestStack) {
            static::getContainer()->get(RequestStack::class)->pop();
            $this->primedRequestStack = false;
        }

        parent::tearDown();
    }

    private function primeRequestStack(): void
    {
        $requestStack = static::getContainer()->get(RequestStack::class);
        if ($requestStack->getCurrentRequest() !== null) {
            return;
        }

        if (!static::getContainer()->has('session.factory')) {
            return;
        }

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = static::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $request = Request::create('/');
        $request->setSession($session);
        $requestStack->push($request);
        $this->primedRequestStack = true;
    }

    private function resetDatabase(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $isMySql = $platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform;
        $isSqlite = $platform instanceof SQLitePlatform;
        $isPostgres = $platform instanceof PostgreSQLPlatform;

        if ($isMySql) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($isSqlite) {
            $connection->executeStatement('PRAGMA foreign_keys=OFF');
        }

        $schemaManager = $connection->createSchemaManager();
        foreach ($schemaManager->listTableNames() as $tableName) {
            $quotedTable = $platform->quoteIdentifier($tableName);
            $dropSql = 'DROP TABLE IF EXISTS '.$quotedTable;
            if ($isPostgres) {
                $dropSql .= ' CASCADE';
            }
            $connection->executeStatement($dropSql);
        }

        if ($isMySql) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($isSqlite) {
            $connection->executeStatement('PRAGMA foreign_keys=ON');
        }
    }
}
