<?php
namespace Initvector\Xml2Mysql;

class DumpParser {

    protected $maxInsertRows = 50;

    protected function prepareValue($value) {
        $escapedValue = str_replace(
            array('\\', "\0", "'", '"', "\n", "\r", "\t", '%'),
            array('\\\\', "\\0", "''", '\\"', "\\n", "\\r", "\\t", "\\%"),
            $value
        );

        return "'$escapedValue'";
    }

    public function generateSql($xmlDump, $sqlOutput = false) {
        if ($sqlOutput) {
            if (file_exists($sqlOutput)) {
                throw new \Exception('SQL output file exists');
            }

            $output = fopen($sqlOutput, 'w');

            if (!$output) {
                throw new \Exception('Unable to create SQL output file');
            }
        } else {
            $output = false;
        }

        // Load the XML file for reading and advance to the first element.
        $xmlReader = new \XmlReader();
        $xmlReader->open($xmlDump);
        $xmlReader->read();

        /**
         * If the dump is properly formed, the first element should be a
         * mysqldump node.
         */
        if ($xmlReader->name != 'mysqldump') {
            throw new MysqlDumpException('mysqldump node not present');
        }

        /**
         * Iterate through the available XML nodes, while there's still
         * something to read.
         */
        while ($xmlReader->read()) {
            // If this isn't the start of a node, we don't care.  Next.
            if ($xmlReader->nodeType != \XmlReader::ELEMENT) {
                continue;
            }

            switch ($xmlReader->name) {
                case 'database':
                    // Attempt to pull the database name we should be using.
                    $database = $xmlReader->getAttribute('name');
                    if (empty($database)) {
                        throw new MysqlDumpException('No database name');
                    }

                    // Generate that SQL.
                    $createDatabase = sprintf(
                        'create database if not exists `%s`;',
                        $database
                    );
                    $this->doOutput("$createDatabase\n", $output);
                    $this->doOutput("use `$database`;\n", $output);
                    unset($database,$createDatabase);
                    break;

                case 'table_structure':
                    $tableStructure = simplexml_load_string(
                        $xmlReader->readOuterXML(),
                        'Initvector\\Xml2Mysql\\XmlNode\\TableStructure'
                    );
                    $this->doOutput($tableStructure->getCreateTable()."\n", $output);

                    /**
                     * All children for this node have been processed, so we
                     * can safely skip to the next sibling.
                     */
                    $xmlReader->next();
                    unset($tableStructure);
                    break;

                case 'table_data':
                    $table = $xmlReader->getAttribute('name');
                    if (empty($table)) {
                        throw new MysqlDumpException('No table name');
                    }

                    $xmlReader->read();
                    $columns = array();
                    $rowBuffer = array();
                    while ($xmlReader->nodeType != \XMLReader::END_ELEMENT) {
                        if ($xmlReader->name == 'row') {
                            $tableRow = $tableStructure = simplexml_load_string(
                                $xmlReader->readOuterXML(),
                                'Initvector\\Xml2Mysql\\XmlNode\\Row'
                            );

                            $rowData = $tableRow->getData();

                            if (empty($columns)) {
                                $columns = array_keys($rowData);
                            }

                            $rowBuffer[] = $rowData;

                            if ($this->outputInsert($table, $columns, $rowBuffer, $output)) {
                                $rowBuffer = array();
                            }
                        }

                        $xmlReader->next();
                    }
                    $this->outputInsert($table, $columns, $rowBuffer, $output, true);
                    break;
            }
        }

        // Close the MySQL XML dump file
        $xmlReader->close();
    }

    protected function getInsert($table, $fields, $values) {

    }

    protected function doOutput($value, $output = false) {
        if (is_resource($output) && get_resource_type($output) == 'stream') {
            fwrite($output, $value);
        } else {
            echo $value;
        }
    }

    protected function outputInsert($tableName, $columns, $rows, $output = false, $force = false) {
        if (count($rows) < $this->maxInsertRows || !$force) {
            return false;
        }

        if (count($rows) == 0) {
            return true;
        }

        $columnList = implode(
            ',',
            array_map(
                function($columnName) {
                    return "`$columnName`";
                },
                $columns
            )
        );

        $rowValues = array();
        foreach ($rows as $currentRow) {
            $valueList = implode(
                ',',
                array_map(
                    array($this, 'prepareValue'),
                    $currentRow
                )
            );
            $rowValues[] = "($valueList)";
        }

        $rowList = implode(', ', $rowValues);

        $insertStatement = "insert into `$tableName` ($columnList) values $rowList;\n";

        $this->doOutput($insertStatement, $output);

        return true;
    }
}
