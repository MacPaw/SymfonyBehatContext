<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Helper;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\ProxyReferenceRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SqliteDriver;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Fidry\AliceDataFixtures\Loader\PersisterLoader;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class DatabaseHelper
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private bool $cacheSqliteDb;
    private ?string $databaseName;
    private $referenceRepository;
    private PersisterLoader $fixturesLoader;
    private static $cachedMetadata;

    public function __construct(
        EntityManagerInterface $em,
        PersisterLoader $fixturesLoader,
        LoggerInterface $logger,
        bool $cacheSqliteDb
    ) {
        $this->em = $em;
        $this->fixturesLoader = $fixturesLoader;
        $this->logger = $logger;
        $this->cacheSqliteDb = $cacheSqliteDb;
    }

    /**
     * Set the database to the provided fixtures.
     *
     * Drops the current database and then loads the specified fixtures.
     *
     * When using SQLite database this method will automatically make a copy of the loaded schema and fixtures
     * which will be restored automatically in case the same fixture classes are to be loaded again.
     *
     * @param array $fixtures
     *
     * @throws InvalidArgumentException
     */
    public function loadFixtures(array $fixtures = []): void
    {
        if ($this->referenceRepository === null) {
            $this->referenceRepository = new ProxyReferenceRepository($this->em);
        }

        if ($cacheDriver = $this->em->getMetadataFactory()->getCacheDriver()) {
            $cacheDriver->deleteAll();
        }

        $connection = $this->em->getConnection();
        $executor = null;
        $loadedFixtures = [];

        if ($connection->getDriver() instanceof SqliteDriver) {
            $params = $params['master'] ?? $connection->getParams();

            $this->databaseName = $params['path'] ?? ($params['dbname'] ?? false);
            if (!$this->databaseName) {
                throw new InvalidArgumentException(
                    "Connection does not contain a 'path' or 'dbname' parameter and cannot be dropped."
                );
            }

            $schemaCreated = false;

            if ($this->cacheSqliteDb) {
                $this->log('Searching for database backup', ['fixtures' => $fixtures]);
                $loadedFixtures = $fixtures;

                while (true) {
                    $backup = $this->getBackupFilename($loadedFixtures);

                    if (file_exists($backup)) {
                        $this->em->clear();
                        $connection->close();

                        $this->loadDataBackup($backup);

                        if ($loadedFixtures === $fixtures) {
                            $this->log('Found whole database backup');

                            return;
                        }

                        if (0 > count($loadedFixtures)) {
                            $this->log('Found incremental database backup', ['fixtures' => $loadedFixtures]);
                        } else {
                            $this->log('Found database schema backup');
                        }

                        $executor = new ORMExecutor($this->em);
                        $executor->setReferenceRepository($this->referenceRepository);

                        $schemaCreated = true;
                        array_splice($fixtures, 0, count($loadedFixtures));

                        break;
                    }

                    if (count($loadedFixtures) > 0) {
                        $loadedFixtures = [];
                    } else {
                        break;
                    }
                }
            }

            if (!$schemaCreated) {
                $this->log('Creating database schema');

                $this->createDatabaseSchema();

                $executor = new ORMExecutor($this->em);
                $executor->setReferenceRepository($this->referenceRepository);
            }

            if ($this->cacheSqliteDb) {
                $this->saveBackup($loadedFixtures);
            }
        }

        if ($executor === null) {
            $purger = new ORMPurger();
            $executor = new ORMExecutor($this->em, $purger);

            $executor->setReferenceRepository($this->referenceRepository);
            $executor->purge();
        }

        if ($fixtures !== []) {
            $this->log('Loading database fixtures', ['fixtures' => $fixtures]);

            $fixturesFiles = [];
            foreach ($fixtures as $fixtureName) {
                $fixturesFiles[] = __DIR__ . '/../../' . $fixtureName;
            }

            $fixturesObjects = $this->fixturesLoader->load($fixturesFiles);

            if (count($fixturesObjects) === 0) {
                throw new InvalidArgumentException(sprintf('Fixtures were not loaded: %s', implode(', ', $fixtures)));
            }

            if ($this->cacheSqliteDb) {
                $this->saveBackup($fixtures);
            }
        }

        if ($this->databaseName) {
            chmod($this->databaseName, 0666);
        }

        $this->em->clear();
    }

    private function log(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    private function saveBackup(array $fixtures): void
    {
        $backupName = $this->getBackupFilename($fixtures);

        if (file_exists($backupName)) {
            return;
        }

        $this->log('Saving database backup', ['fixtures' => $fixtures]);

        $this->saveDataBackup($backupName);
    }

    private function getBackupFilename(array $fixtures): string
    {
        return preg_replace('|[^\\\\/]+$|', 'backup_' . md5(serialize($fixtures)) . '.db', $this->databaseName);
    }

    private function saveDataBackup(string $backupName): void
    {
        copy($this->databaseName, $backupName);
    }

    private function loadDataBackup(string $backupName): void
    {
        copy($backupName, $this->databaseName);

        chmod($this->databaseName, 0666);
    }

    private function createDatabaseSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropDatabase();

        if (self::$cachedMetadata === null) {
            self::$cachedMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        }

        if (self::$cachedMetadata !== []) {
            $conn = $this->em->getConnection();

            $schema = $schemaTool->getSchemaFromMetadata(self::$cachedMetadata);

            if ($conn->getDriver() instanceof SqliteDriver) {
                $this->adaptDatabaseSchemaToSqlite($schema);
            }

            $createSchemaSql = $schema->toSql($conn->getDatabasePlatform());

            foreach ($createSchemaSql as $sql) {
                $conn->executeQuery($sql);
            }
        }
    }

    private function adaptDatabaseSchemaToSqlite(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if ($column->hasCustomSchemaOption('collation')) {
                    $column->setCustomSchemaOptions(array_diff_key(
                        $column->getCustomSchemaOptions(),
                        ['collation' => true]
                    ));
                }
            }
        }
    }
}
