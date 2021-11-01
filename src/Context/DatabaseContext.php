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
    protected EntityManagerInterface $entityManager;
    protected PersisterLoader $persisterLoader;
    protected LoggerInterface $logger;
    protected ?DatabaseHelper $databaseHelper = null;
    protected string $dataFixturesPath;
    protected string $cacheDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        PersisterLoader $persisterLoader,
        LoggerInterface $logger,
        string $dataFixturesPath,
        string $cacheDir
    ) {
        $this->entityManager = $entityManager;
        $this->persisterLoader = $persisterLoader;
        $this->logger = $logger;
        $this->dataFixturesPath = $dataFixturesPath;
        $this->cacheDir = $cacheDir;
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
            $fixture = sprintf('%s/%s.yml', $this->dataFixturesPath, $alias);

            if (!is_file($fixture)) {
                throw new InvalidArgumentException(sprintf('The "%s" fixture not found.', $alias));
            }

            $fixtures[] = $fixture;
        }

        $this->getDatabaseHelper()->loadFixtures($fixtures);
    }

    protected function getDatabaseHelper(): DatabaseHelper
    {
        if ($this->databaseHelper === null) {
            $this->databaseHelper = new DatabaseHelper(
                $this->entityManager,
                $this->persisterLoader,
                $this->logger,
                $this->cacheDir
            );
        }

        return $this->databaseHelper;
    }
}
