<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Helper\Database\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class DatabaseManager
{
    protected Connection $connection;

    protected EntityManagerInterface $entityManager;

    protected $schemaCreated = false;

    protected string $cacheDir;

    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, string $cacheDir){
        $this->connection = $entityManager->getConnection();
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cacheDir = $cacheDir;
    }

    abstract public function prepareSchema(): void;

    abstract public function saveBackup(array $fixtures): void;

    abstract public function loadBackup(array $fixtures): void;

    abstract protected function getBackupFilename(array $fixtures): string;
    
    public function backupExists(array $fixtures): bool
    {
        $backupFilename = $this->getBackupFilename($fixtures);
        if(file_exists($backupFilename)){
            return true;
        }

        return false;
    }

    protected function getDatabaseName(): string
    {
        return $this->connection->getDatabase();
    }

    protected function log(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}
