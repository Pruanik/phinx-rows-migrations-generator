<?php

namespace Pruanik\Migration\Command;

use Exception;
use Pruanik\Migration\Adapter\Database\MySqlSchemaAdapter;
use Pruanik\Migration\Generator\MigrationGenerator;
use PDO;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Db\Adapter\AdapterWrapper;
use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends AbstractCommand
{
    /**
     * Configure.
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setName('generate');
        $this->setDescription('Generate a new migration of rows data');

        // Allow the migration path to be chosen non-interactively.
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the path in which to generate this migration'
        );

        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the name of the migration for this migration'
        );

        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite schema_rows.php file');
    }

    /**
     * Generate migration.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws Exception On Error
     *
     * @return int integer 0 on success, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');
        $environment = is_scalar($environment) ? (string)$environment : null;

        if ($environment === null) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        if (!is_string($environment)) {
            throw new RuntimeException('Invalid or missing environment');
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter']) && $envOptions['adapter'] != 'mysql') {
            $output->writeln('<error>adapter not supported</error> ' . $envOptions['adapter']);

            return 1;
        }
        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');

            return 1;
        }

        // Load config and database adapter
        $manager = $this->getManager();

        if (!$manager) {
            throw new RuntimeException('Manager not found');
        }

        $config = $manager->getConfig();

        $configFilePath = $config->getConfigFilePath();
        $output->writeln('<info>using config file</info> ' . $configFilePath);

        // First, try the non-interactive option:
        $migrationsPaths = $input->getOption('path');
        if (empty($migrationsPaths)) {
            $migrationsPaths = $config->getMigrationPaths();
        }

        $migrationsPaths = is_array($migrationsPaths) ? $migrationsPaths : (array)$migrationsPaths;

        // No paths? That's a problem.
        if (empty($migrationsPaths[0])) {
            throw new RuntimeException('No migration paths set in your Phinx configuration file.');
        }

        $migrationsPath = (string)$migrationsPaths[0];
        $this->verifyMigrationDirectory($migrationsPath);

        $output->writeln('<info>using migration path</info> ' . $migrationsPath);

        $schemaFile = $migrationsPath . DIRECTORY_SEPARATOR . 'schema_rows.php';
        $output->writeln('<info>using schema file of data</info> ' . $schemaFile);

        // Gets the database adapter.
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        $pdo = $this->getPdo($manager, $environment);

        $foreignKeys = $config->offsetExists('foreign_keys') ? $config->offsetGet('foreign_keys') : false;

        $watchingTables = $config->offsetExists('watching_tables') ? $config->offsetGet('watching_tables') : [];

        $defaultMigrationTable = $envOptions['default_migration_table'] ?? 'phinxlog';

        $name = $input->getOption('name');
        $overwrite = $input->getOption('overwrite');

        $settings = [
            'pdo' => $pdo,
            'manager' => $manager,
            'environment' => $environment,
            'adapter' => $dbAdapter,
            'schema_file' => $schemaFile,
            'migration_path' => $migrationsPaths[0],
            'watching_tables' => $watchingTables,
            'foreign_keys' => $foreignKeys,
            'config_file' => $configFilePath,
            'name' => $name,
            'overwrite' => $overwrite,
            'mark_migration' => true,
            'default_migration_table' => $defaultMigrationTable,
        ];

        $dba = new MySqlSchemaAdapter($pdo, $output);
        $generator = new MigrationGenerator($settings, $input, $output, $dba);

        return $generator->generate();
    }

    /**
     * Get PDO instance.
     *
     * @param Manager $manager Manager
     * @param string $environment Environment name
     *
     * @throws Exception On error
     *
     * @return PDO PDO object
     */
    protected function getPdo(Manager $manager, $environment): PDO
    {
        /* @var AdapterWrapper $dbAdapter */
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        if ($dbAdapter instanceof PdoAdapter) {
            $pdo = $dbAdapter->getConnection();
        } elseif ($dbAdapter instanceof AdapterWrapper) {
            $dbAdapter->connect();
            $pdo = $dbAdapter->getAdapter()->getConnection();
        } else {
            throw new RuntimeException('Adapter not found');
        }
        if (empty($pdo)) {
            $pdo = $dbAdapter->getOption('connection');
        }
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO database connection not found.');
        }

        return $pdo;
    }
}
