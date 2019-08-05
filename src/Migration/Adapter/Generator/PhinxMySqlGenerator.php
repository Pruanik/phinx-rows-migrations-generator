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
     * @param string $name Name of the migration
     * @param array $newSchema
     * @param array $oldSchema
     *
     * @return string PHP code
     */
    public function createMigration($name, $newSchema, $oldSchema): string
    {
        $output = [];
        $output[] = '<?php';
        $output[] = '';
        $output[] = 'use Phinx\Migration\AbstractMigration;';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends AbstractMigration', $name);
        $output[] = '{';
        $output = $this->addChangeMethod($output, $newSchema, $oldSchema);
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
    protected function addChangeMethod($output, $new, $old): array
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';

        $output[] = $this->getQueryBuilder();
        $output = $this->getInstructions($output, $new, $old);

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
     * Get table migration (new tables).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getInstructions(array $output, array $new, array $old): array
    {
        $arrayUtil = new ArrayUtil();

        foreach ($new['tables'] ?? [] as $tableName => $table) {

            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            $tableDiffs = $arrayUtil->diff($new['tables'][$tableName] ?? [], $old['tables'][$tableName] ?? []);
            $tableDiffsRemove = $arrayUtil->diff($old['tables'][$tableName] ?? [], $new['tables'][$tableName] ?? []);

            if ($tableDiffs) {
                $output = $this->getInsertInstructions($output, $tableName, $new['id'], $new['columns'], $tableDiffs);
            }

            if ($tableDiffsRemove) {
                $output = $this->getDeleteInstructions($output, $tableName, $new['id'], $tableDiffsRemove);
            }
        }

        return $output;
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
    protected function getInsertInstructions(array $output, string $tableName, array $id_new, array $columns_new, array $diff): array
    {
        if (empty($diff)||empty($tableName)) {
            return $output;
        }

        $fields = $this->getTableFields($columns_new, $tableName);
        $field_id = $this->getFieldId($id_new, $tableName);

        $output[] = sprintf('%s$builder', $this->ind2);
        $output[] = sprintf('%s->insert(['.$fields.'])', $this->ind3);
        $output[] = sprintf('%s->into("'.$tableName.'")', $this->ind3);

        foreach($diff as $id => $rows){
            $rows = array_merge([$field_id => $id], $rows);
            $output[] = sprintf('%s->values(%s)', $this->ind3, str_replace("\n", "", var_export($rows, true)));
        }
        
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
     * Get columns of table.
     *
     * @param array $columns
     * @param string $tableName
     *
     * @return string
     */
    protected function getTableFields($columns, $tableName){
        $columns = $columns[$tableName];
        $columns = array_map(function($v){ return '"'.$v.'"'; }, $columns);
        $columns = implode(', ', $columns);

        return $columns;
    }

    /**
     * Get PRIMARY KEY of table.
     *
     * @param array $ids
     * @param string $tableName
     *
     * @return string
     */
    protected function getFieldId($ids, $tableName){
        return $ids[$tableName];
    }
}
