<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context;

use SymfonyBehatContext\Helper\DatabaseHelper;
use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Loader\PersisterLoader;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class DatabaseContext implements Context
{
    private EntityManagerInterface $entityManager;
    private PersisterLoader $persisterLoader;
    private LoggerInterface $logger;
    private ?DatabaseHelper $databaseHelper = null;

    public function __construct(
        EntityManagerInterface $entityManager,
        PersisterLoader $persisterLoader,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->persisterLoader = $persisterLoader;
        $this->logger = $logger;
    }

    /**
     * Before Scenario.
     *
     * @BeforeScenario
     */
    public function beforeScenario(): void
    {
        $this->getDatabaseHelper()->loadFixtures();
    }

    /**
     * I load fixtures.
     *
     * @param string $aliases
     *
     * @throws InvalidArgumentException
     *
     * @Given /^I load fixtures "([^\"]*)"$/
     */
    public function loadFixtures(string $aliases): void
    {
        $aliases = array_map('trim', explode(',', $aliases));
        $fixtures = [];

        foreach ($aliases as $alias) {
            $fixture = sprintf('tests/DataFixtures/ORM/%s.yml', $alias);

            if (!is_file($fixture)) {
                throw new InvalidArgumentException(sprintf('The "%s" fixture not found.', $alias));
            }

            $fixtures[] = $fixture;
        }

        $this->getDatabaseHelper()->loadFixtures($fixtures);
    }

    private function getDatabaseHelper(): DatabaseHelper
    {
        if ($this->databaseHelper === null) {
            $this->databaseHelper = new DatabaseHelper(
                $this->entityManager,
                $this->persisterLoader,
                $this->logger,
                true
            );
        }

        return $this->databaseHelper;
    }
}
