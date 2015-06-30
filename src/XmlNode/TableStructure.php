<?php
namespace Initvector\Xml2Mysql\XmlNode;

use Initvector\Xml2Mysql\MysqlDumpException;

class TableStructure extends XmlElement {
    public function getCreateTable() {
        // Grab the current node's attributes.
        $table = $this->getAttribute('name');

        // We need a valid table name to proceed.
        if (!$table) {
            throw new MysqlDumpException('No table name');
        }

        // Begin building our SQL statement.
        $createStatement = "create table if not exists `$table` (\n";

        // Iterate through all child field nodes for column details.
        $fields = array();
        foreach ($this->field as $currentField) {
            $fieldAttributes = $currentField->attributes();

            // We need a column name.
            if (!isset($fieldAttributes['Field'])) {
                throw new MysqlDumpException('No column name');
            }
            $field = (string)$currentField->attributes()->Field;

            // We also need a column type.
            if (!isset($fieldAttributes['Type'])) {
                throw new MysqlDumpException('No column type');
            }
            $type = (string)$currentField->attributes()->Type;

            /*
             * The basic column details are field and name, so that's how we
             * start the column details for our create statement.
             */
            $fieldDetails = "\t`$field` $type";

            /*
             * We don't *need* to know the specifics of whether or not it's
             * null-able.  We grab them, if they're available.
             */
            $null = isset($fieldAttributes['Null']) ? (string)$currentField->attributes()->Null : false;

            // If the column is explicitly not null-able, set it.
            if ($null == 'NO') {
                $fieldDetails .= " not null";
            }

            /*
             * Add the column details to our running list of all columns for the
             * current table.
             */
            $tableDetails[] = $fieldDetails;
            unset($currentField, $fieldAttributes, $field, $type, $fieldDetails, $null, $key);
        }

        // Iterate through our key nodes, gathering and assembling key details.
        $keyDetails = array();
        foreach ($this->key as $currentKey) {
            $keyAttributes = $currentKey->attributes();


            // Grab the name of the key.  Require it.
            $keyName = isset($keyAttributes['Key_name']) ? (string)$keyAttributes['Key_name'] : false;
            if (!$keyName) {
                throw new MysqlDumpException('No key name');
            }

            // Grab the name of the column.  Require it.
            $columnName = isset($keyAttributes['Column_name']) ? (string)$keyAttributes['Column_name'] : false;
            if (!$columnName) {
                throw new MysqlDumpException('No key column');
            }

            // Uniqueness isn't required, but we grab it, all the same.
            $nonUnique = isset($keyAttributes['Non_unique']) ? (string)$keyAttributes['Non_unique'] : false;

            /*
             * Prepare a destination for the current key node's details, if one
             * doesn't already exist.
             */
            if (!array_key_exists($keyName, $keyDetails)) {
                $keyDetails[$keyName] = array(
                    'columns' => array(),
                    'nonUnique' => $nonUnique
                );
            }

            $keyDetails[$keyName]['columns'][] = $columnName;

            unset($currentKey, $keyAttributes);
        }

        // Generate the key rows.
        $keyRows = array();
        foreach ($keyDetails as $keyName => $keySpecifics) {
            $keyColumns = array_map(
                function($columnName) {
                    return "`$columnName`";
                },
                $keySpecifics['columns']
            );

            $columnList = implode(',', $keyColumns);
            if ($keyName == 'PRIMARY') {
                $tableDetails[] = "\tprimary key ($columnList)";
            } elseif ($keySpecifics['nonUnique'] == '0') {
                $tableDetails[] = "\tunique index $keyName ($columnList)";
            } else {
                $tableDetails[] = "\tindex $keyName ($columnList)";
            }
        }

        // Smoosh all column detail and key rows into one list for our statement.
        $createStatement .= implode(",\n", $tableDetails)."\n)";

        // Is there a options node for this table?
        if (isset($this->options)) {
            $optionsAttributes = $this->options->attributes();

            // Set the table engine, if specified.
            if (isset($optionsAttributes['Engine'])) {
                $engine = $optionsAttributes['Engine'];
                $createStatement .= " engine=$engine";
            }
            // Set the collation, if specified.
            if (isset($optionsAttributes['Collation'])) {
                $collation = $optionsAttributes['Collation'];
                $createStatement .= " collate=$collation";
            }
        }

        // If we've made it this far, return it.
        return "$createStatement;";
    }
}
