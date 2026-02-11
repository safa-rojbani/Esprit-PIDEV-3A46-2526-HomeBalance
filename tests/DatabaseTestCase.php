<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
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

        $schemaTool->dropDatabase();
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
}
