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

            $iterator = 1;
    
            if ($tableDiffs) {
                $action = 'insert';
                foreach($tableDiffs as $rowID => $columns){
                    $name = $this->makeClassName($className, $action, $tableName, $rowID);
                    $fileName = $this->makeFileName($className, $action, $tableName, $rowID, $iterator);
                    $path = $this->makePath($filePath, $fileName);
                    var_dump($name);
                    var_dump($fileName);
                    var_dump($path);
                    
                    $output = $this->makeClass($action, $name, $tableName, $rowID, $columns);
                    $this->saveMigrationFile($path, $output);
                    // Mark migration as as completed
                    if (!empty($this->options['mark_migration'])) {
                        $this->markMigration($className, $fileName);
                    }
                    $iterator++;
                }
            }

            // if ($tableDiffsRemove) {
            //     foreach($tableDiffs as $rowID => $columns){
            //         var_dump($columns);
            //         if(count($columns)==count($this->columnsList[$tableName])){//check is it update or delete
            //             $action = 'delete';
            //         } else {
            //             $action = 'update';
            //         }
            //         $name = $this->makeClassName($className, $action, $tableName, $rowID);
            //         $path = $this->makePath($filePath, $action, $tableName, $rowID, $iterator);
            //         //$output = $this->makeClass($action, $name, $tableName, $rowID, $columns);
            //         //$this->saveMigrationFile($path, $output);
            //         // Mark migration as as completed
            //         if (!empty($this->options['mark_migration'])) {
            //             $this->markMigration($className, $fileName);
            //         }
            //         $iterator++;
            //     }
            //     exit;          
            // }
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

            case 'update':
                //$output = $this->getUpdateInstruction($output, $new, $old);
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
     * Get table migration (update data).
     *
     * @param array $output
     * @param string $tableName
     * @param array $id_new
     * @param array $diff
     *
     * @return array
     */
    protected function getUpdateInstructions(array $output, string $tableName, array $id_new, array $diff): array
    {
        if (empty($diff)||empty($tableName)) {
            return $output;
        }

        $field_id = $this->getFieldId($id_new, $tableName);

        foreach($diff as $id => $rows){
            $output[] = sprintf('%s$builder', $this->ind2);
            $output[] = sprintf('%s->update("'.$tableName.'")', $this->ind3);
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

    protected function makeClassName($className, $action, $tableName, $rowID){
        $name = '';
        $name .= $className;
        $name .= ucfirst($action);
        $name .= ucfirst($tableName);
        $name .= $rowID;
        return $name;
    }

    protected function makeFileName($className, $action, $tableName, $rowID, $iterator){
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = date('YmdHis') . $iterator .  '_' . 
                    strtolower(implode($arr, '_')) . '_' . 
                    lcfirst($action) . '_' . 
                    lcfirst($tableName). '_' . 
                    lcfirst($rowID). '.php';
        return $fileName;
    }

    protected function makePath($filePath, $fileName){
        $filePath = $filePath . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        return $filePath;
    }

    /**
     * Mark migration as completed.
     *
     * @param string $migrationName migrationName
     * @param string $fileName fileName
     */
    protected function markMigration(string $migrationName, string $fileName): void
    {
        $this->output->writeln('Mark migration');

        /* @var $adapter AdapterInterface */
        $adapter = $this->options['adapter'];

        $schemaTableName = $this->options['default_migration_table'];

        /* @var $pdo \PDO */
        $pdo = $this->options['adapter'];

        // Get version from filename prefix
        $version = explode('_', $fileName)[0];

        // Record it in the database
        $time = time();
        $startTime = date('Y-m-d H:i:s', $time);
        $endTime = date('Y-m-d H:i:s', $time);
        $breakpoint = 0;

        $sql = sprintf(
            "INSERT INTO %s (%s, %s, %s, %s, %s) VALUES ('%s', '%s', '%s', '%s', %s);",
            $schemaTableName,
            $adapter->quoteColumnName('version'),
            $adapter->quoteColumnName('migration_name'),
            $adapter->quoteColumnName('start_time'),
            $adapter->quoteColumnName('end_time'),
            $adapter->quoteColumnName('breakpoint'),
            $version,
            substr($migrationName, 0, 100),
            $startTime,
            $endTime,
            $breakpoint
        );

        $pdo->query($sql);
    }
}
