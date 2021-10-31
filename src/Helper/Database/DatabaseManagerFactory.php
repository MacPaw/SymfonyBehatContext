<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Helper\Database;

use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SymfonyBehatContext\Helper\Database\Exception\DatabaseManagerNotFoundForCurrentPlatform;
use SymfonyBehatContext\Helper\Database\Manager\DatabaseManager;
use SymfonyBehatContext\Helper\Database\Manager\PgSqlDatabaseManager;
use SymfonyBehatContext\Helper\Database\Manager\SQLiteDatabaseManager;

class DatabaseManagerFactory
{
    static function createDatabaseManager(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $cacheDir
    ): DatabaseManager {
        $databasePlatform = $entityManager->getConnection()->getDatabasePlatform();

        if($databasePlatform instanceof SqlitePlatform){
            return new SQLiteDatabaseManager($entityManager, $logger, $cacheDir);
        }

        if($databasePlatform instanceof PostgreSQL100Platform){
            return new PgSqlDatabaseManager($entityManager, $logger, $cacheDir);
        }

        throw new DatabaseManagerNotFoundForCurrentPlatform($databasePlatform);
    }
}
