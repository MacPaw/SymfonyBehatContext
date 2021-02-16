<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context;

use Behat\Behat\Context\Context;
use InvalidArgumentException;
use Liip\TestFixturesBundle\Test\FixturesTrait;

class DatabaseFixtureContext implements Context
{
    use FixturesTrait;
    protected string $dataFixturesPath;

    public function __construct(string $dataFixturesPath)
    {
        $this->dataFixturesPath = $dataFixturesPath;
    }
    
    /**
     * Before Scenario.
     *
     * @BeforeScenario
     */
    public function beforeScenario(): void
    {
        $this->loadFixtureFiles([], false);
    }

    /**
     * I load fixtures.
     *
     * @param string $classNames
     *
     * @throws InvalidArgumentException
     *
     * @Given /^I load fixtures "([^\"]*)"$/
     */
    public function loadFixtures(string $classNames): void
    {
        $classNames = array_map('trim', explode(',', $classNames));
        $fixtures = [];

        foreach ($classNames as $alias) {
            $fixture = sprintf('%s/%s.yml', $this->dataFixturesPath, $alias);

            if (!is_file($fixture)) {
                throw new InvalidArgumentException(sprintf('The "%s" fixture not found.', $alias));
            }

            $fixtures[] = $fixture;
        }

        $this->loadFixtureFiles($fixtures);
    }
}
