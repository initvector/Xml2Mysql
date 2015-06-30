<?php
namespace Initvector\Xml2Mysql\XmlNode;

abstract class XmlElement extends \SimpleXMLElement {

    public function getAttribute($name, $default = false) {
        $attributes = $this->attributes();

        return isset($attributes[$name]) ? (string)$attributes[$name] : $default;
    }
}
