<?php

namespace Pruanik\Migration\Adapter\Generator;

use Pruanik\Migration\Adapter\Database\SchemaAdapterInterface;
use Pruanik\Migration\Utility\ArrayUtil;
use Phinx\Db\Adapter\AdapterInterface;
use Riimu\Kit\PHPEncoder\PHPEncoder;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PhinxMySqlGenerator.
 */
class PhinxMySqlGenerator
{
    /**
     * Database adapter.
     *
     * @var SchemaAdapterInterface
     */
    protected $dba;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
     *
     * @var string
     */
    protected $nl = "\n";

    /**
     * @var string
     */
    protected $ind = '    ';

    /**
     * @var string
     */
    protected $ind2 = '        ';

    /**
     * @var string
     */
    protected $ind3 = '            ';

    /**
     * @var string
     */
    protected $ind4 = '                ';

    /**
     * @var string
     */
    protected $ind5 = '                    ';

    /**
     * List field name id.
     *
     * @var array
     */
    protected $idList;

    /**
     * List columns of table.
     *
     * @var string
     */
    protected $columnsList;

    /**
     * Constructor.
     *
     * @param SchemaAdapterInterface $dba
     * @param OutputInterface $output
     * @param mixed $options Options
     */
    public function __construct(SchemaAdapterInterface $dba, OutputInterface $output, $options = [])
    {
        $this->dba = $dba;
        $this->output = $output;

        $default = [
            // Experimental foreign key support.
            'foreign_keys' => false,
            // Default migration table name
            'default_migration_table' => 'phinxlog',
        ];

        $this->options = array_replace_recursive($default, $options) ?: [];
    }

    /**
     * Create migration.
     *
     * @param string $filePath Path of the migration
     * @param string $className Name of the migration
     * @param array $newSchema
     * @param array $oldSchema
     *
     * @return string PHP code
     */
    public function createMigration($filePath, $className, $newSchema, $oldSchema): string
    {
        $arrayUtil = new ArrayUtil();

        $this->idList = $newSchema['id'];
        $this->columnsList = $newSchema['columns'];

        foreach ($newSchema['tables'] ?? [] as $tableName => $table) {

            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            $tableDiffs = $arrayUtil->diff($newSchema['tables'][$tableName] ?? [], $oldSchema['tables'][$tableName] ?? []);
            $tableDiffsRemove = $arrayUtil->diff($oldSchema['tables'][$tableName] ?? [], $newSchema['tables'][$tableName] ?? []);
    
            if ($tableDiffs) {
                $action = 'insert';
                foreach($tableDiffs as $rowID => $columns){
                    $name = $this->makeName($className, $action, $tableName, $rowID);
                    $path = $this->makePath($filePath, $action, $tableName, $rowID);
                    $output = $this->makeClass($action, $name, $tableName, $rowID, $columns);
                    $this->saveMigrationFile($path, $output);
                }
            }
    
            if ($tableDiffsRemove) {
                //$output = $this->getDeleteInstructions($output, $tableName, $new['id'], $tableDiffsRemove);
                var_dump($tableDiffsRemove);
            }
        }

        return 1;
    }

    /**
     * Generate code for change function.
     *
     * @param string[] $output Output
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return string[] Output
     */
    protected function makeClass($action, $className, $tableName, $rowID, $columns): string
    {
        $output = [];
        $output[] = '<?php';
        $output[] = '';
        $output[] = 'use Phinx\Migration\AbstractMigration;';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends AbstractMigration', $className);
        $output[] = '{';
        $output = $this->makeMethod($output, $action, $tableName, $rowID, $columns);
        $output[] = '}';
        $output[] = '';

        $result = implode($this->nl, $output);

        return $result;
    }

    /**
     * Generate code for change function.
     *
     * @param string[] $output Output
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return string[] Output
     */
    protected function makeMethod($output, $action, $tableName, $rowID, $columns): array
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';
    
        $output[] = $this->getQueryBuilder();
        switch ($action) {
            case 'insert':
                $output = $this->getInsertInstruction($output, $tableName, $rowID, $columns);
                break;

            case 'delete':
                //$output = $this->getDeleteInstruction($output, $new, $old);
                break;
        }
        
        $output[] = $this->ind . '}';

        return $output;
    }

    /**
     * Generate Object Creation.
     *
     * @return string
     */
    protected function getQueryBuilder(): string
    {
        return sprintf('%s$builder = $this->getQueryBuilder();', $this->ind2);
    }

    /**
     * Get table migration (insert data).
     *
     * @param array $output
     * @param string $tableName
     * @param array $id_new
     * @param array $columns_new
     * @param array $diff
     *
     * @return array
     */
    protected function getInsertInstruction(array $output, string $tableName, int $rowID, array $columns): array
    {
        if (empty($columns)||empty($tableName)) {
            return $output;
        }

        $fields = implode(', ', array_map(function($v){ return '"'.$v.'"'; }, $this->columnsList[$tableName]));
        $field_id = $this->idList[$tableName];

        $columns = array_merge([$field_id => $rowID], $columns);
        $values = var_export($columns, true);
        $values = str_replace("  ", $this->ind5, var_export($columns, true));
        $values = $this->ind4.$values;
        $values = str_replace(")", $this->ind4.')', $values);

        $output[] = sprintf('%s$builder', $this->ind2);
        $output[] = sprintf('%s->insert([%s])', $this->ind3, $fields);
        $output[] = sprintf('%s->into("%s")', $this->ind3, $tableName);
        $output[] = sprintf('%s->values(', $this->ind3);
        $output[] = $values;
        $output[] = sprintf('%s)', $this->ind3);
        $output[] = sprintf('%s->execute();', $this->ind3);

        return $output;
    }

    /**
     * Get table migration (delete data).
     *
     * @param array $output
     * @param string $tableName
     * @param array $id_new
     * @param array $diff
     *
     * @return array
     */
    protected function getDeleteInstructions(array $output, string $tableName, array $id_new, array $diff): array
    {
        if (empty($diff)||empty($tableName)) {
            return $output;
        }

        $field_id = $this->getFieldId($id_new, $tableName);

        foreach($diff as $id => $rows){
            $output[] = sprintf('%s$builder', $this->ind2);
            $output[] = sprintf('%s->delete("'.$tableName.'")', $this->ind3);
            $output[] = sprintf('%s->where(["'.$field_id.'" => "'.$id.'"])', $this->ind3);
            $output[] = sprintf('%s->execute();', $this->ind3);
        }
        return $output;
    }

    /**
     * Save migration file.
     *
     * @param string $filePath Name of migration file
     * @param string $migration Migration code
     */
    protected function saveMigrationFile(string $filePath, string $migration): void
    {
        $this->output->writeln(sprintf('Generate migration file: %s', $filePath));
        file_put_contents($filePath, $migration);
    }

    protected function makeName($className, $action, $tableName, $rowID){
        $name = '';
        $name .= $className;
        $name .= ucfirst($action);
        $name .= ucfirst($tableName);
        $name .= $rowID;
        return $name;
    }

    protected function makePath($filePath, $action, $tableName, $rowID){
        $path = '';
        $path .= mb_substr($filePath , 0, -4);
        $path .= '_'.lcfirst($action);
        $path .= '_'.lcfirst($tableName);
        $path .= '_'.$rowID;
        $path .= '.php';
        return $path;
    }
}
