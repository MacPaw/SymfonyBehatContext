<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context;

use Behat\Behat\Context\Context;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use SymfonyBundles\RedisBundle\Redis\ClientInterface;

class RedisFixturesContext implements Context
{
    private ClientInterface $redis;

    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
    }

    /**
     * I load fixtures.
     *
     * @param string $aliases
     *
     * @throws InvalidArgumentException
     *
     * @Given /^I load redis fixtures "([^\"]*)"$/
     */
    public function loadRedisFixtures(string $aliases): void
    {
        $aliases = array_map('trim', explode(',', $aliases));
        $fixtures = [];

        foreach ($aliases as $alias) {
            $fixture = sprintf('tests/DataFixtures/Redis/%s.yml', $alias);

            if (!is_file($fixture)) {
                throw new InvalidArgumentException(sprintf('The "%s" redis fixture not found.', $alias));
            }

            $fixtures[] = $fixture;
        }

        $this->loadFixtures($fixtures);
    }

    private function loadFixtures(array $fixtures): void
    {
        foreach ($fixtures as $fixture) {
            $this->loadFile(Yaml::parseFile($fixture));
        }
    }

    private function loadFile(array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $this->redis->hmset($key, $value);
            } else {
                $this->redis->set($key, $value);
            }
        }
    }
}
