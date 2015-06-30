<?php
namespace Initvector\Xml2Mysql\XmlNode;

use Initvector\Xml2Mysql\MysqlDumpException;

class Row extends XmlElement {

    public function getData() {
        $data = array();

        foreach ($this->field as $currentField) {
            $fieldAttributes = $currentField->attributes();

            $column = isset($fieldAttributes['name']) ? (string)$fieldAttributes['name'] : false;

            if (!$column) {
                throw new MysqlDumpException('No column name');
            }

            $data[$column] = (string)$currentField;
        }

        return $data;
    }
}
