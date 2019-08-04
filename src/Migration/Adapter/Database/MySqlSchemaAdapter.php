<?php

namespace Pruanik\Migration\Adapter\Database;

use Pruanik\Migration\Utility\ArrayUtil;
use PDO;
use PDOStatement;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MySqlAdapter.
 */
class MySqlSchemaAdapter implements SchemaAdapterInterface
{
    /**
     * PDO.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Console Output Interface.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Current database name.
     *
     * @var string
     */
    protected $dbName;

    /**
     * Tables list database.
     *
     * @var array
     */
    protected $tables;

    /**
     * Constructor.
     *
     * @param PDO $pdo
     * @param OutputInterface $output
     */
    public function __construct(PDO $pdo, OutputInterface $output)
    {
        $this->pdo = $pdo;
        $this->dbName = $this->getDbName();
        $this->tables = $this->getTables();
        $this->output = $output;
        $this->output->writeln(sprintf('Database: <info>%s</info>', $this->dbName));
    }

    /**
     * Get current database name.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return (string)$this->createQueryStatement('select database()')->fetchColumn();
    }

    /**
     * Create a new PDO statement.
     *
     * @param string $sql The sql
     *
     * @return PDOStatement The statement
     */
    protected function createQueryStatement(string $sql): PDOStatement
    {
        $statement = $this->pdo->query($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Invalid statement');
        }

        return $statement;
    }

    /**
     * Fetch all rows as array.
     *
     * @param string $sql The sql
     * @param string $sql The sql
     *
     * @return array The rows
     */
    protected function queryFetchAll(string $sql, int $fetchParam = PDO::FETCH_ASSOC): array
    {
        $statement = $this->createQueryStatement($sql);
        $rows = $statement->fetchAll($fetchParam);

        if (!$rows) {
            return [];
        }

        return $rows;
    }

    /**
     * Load current database schema.
     *
     * @param array $tables The tables list
     *
     * @return array
     */
    public function getRows(array $tables): array
    {
        if(!empty($tables)){
            $this->output->writeln('Load current database data.');
        } else {
            $this->output->writeln('Tables not in config file.');
        }

        $result = [];

        $result['database'] = $this->dbName;

        foreach ($tables as $tableName) {
            if(in_array($tableName, $this->tables)){
                $this->output->writeln(sprintf('Table: <info>%s</info>', $tableName));

                $result['id'][$tableName] = $this->getPrimaryKey($tableName);
                $result['columns'][$tableName] = $this->getColumns($tableName);

                $sql = 'SELECT * FROM %s;';
                $sql = sprintf($sql, $tableName);
                $rows = $this->queryFetchAll($sql, [PDO::FETCH_UNIQUE, PDO::FETCH_ASSOC]);
                $result['tables'][$tableName] = $rows;
            } else {
                $this->output->writeln(sprintf('Table not exist: <error>%s</error>', $tableName));
            }
        }
        return $result;
    }

    /**
     * Quote value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function quote(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $this->pdo->quote($value);
    }

    /**
     * Get all tables.
     *
     * @return array
     */
    protected function getTables(): array
    {
        $result = [];
        $sql = "SELECT table_name
            FROM
                information_schema.tables AS t,
                information_schema.collation_character_set_applicability AS ccsa
            WHERE
                ccsa.collation_name = t.table_collation
                AND t.table_schema=database()
                AND t.table_type = 'BASE TABLE'";

        return $this->queryFetchAll($sql, PDO::FETCH_COLUMN);
    }

    /**
     * Get table columns.
     *
     * @param string $tableName
     *
     * @return array
     */
    protected function getColumns($tableName): array
    {
        $sql = sprintf('SELECT * FROM information_schema.columns
                    WHERE table_schema=database()
                    AND table_name = %s', $this->quote($tableName));

        $rows = $this->queryFetchAll($sql);

        $result = [];
        foreach ($rows as $row) {
            $name = $row['COLUMN_NAME'];
            $result[$name] = $row;
        }

        return $result;
    }

    /**
     * Get name primary column.
     *
     * @param string $tableName
     *
     * @return array
     */
    protected function getPrimaryColumn($tableName): array
    {
        $sql = sprintf('SELECT column_name FROM information_schema.columns
                    WHERE table_schema=database() AND column_key = "PRI"
                    AND table_name = %s', $this->quote($tableName));

        $rows = $this->queryFetchAll($sql);

        $result = [];
        foreach ($rows as $row) {
            $name = $row['COLUMN_NAME'];
            $result[$name] = $row;
        }

        return $result;
    }

    /**
     * Escape identifier (column, table) with backtick.
     *
     * @see: http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     *
     * @param string $value
     *
     * @return string identifier escaped string
     */
    public function ident(string $value): string
    {
        $quote = '`';
        $value = preg_replace('/[^A-Za-z0-9_\.]+/', '', $value);
        $value = is_scalar($value) ? (string)$value : '';

        if (strpos($value, '.') !== false) {
            $values = explode('.', $value);
            $value = $quote . implode($quote . '.' . $quote, $values) . $quote;
        } else {
            $value = $quote . $value . $quote;
        }

        return $value;
    }

    /**
     * Escape value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function esc(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = substr($this->pdo->quote($value), 1, -1);

        return $value;
    }
}
