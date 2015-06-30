xml2mysql *
==========
A very rough, untested library for converting mysqldump XML exports to SQL using PHP.

Example Usage
----------
Generate SQL from mysqldump.xml and echo result
```PHP
$dumpParser = new Initvector\Xml2Mysql\DumpParser();

$dumpParser->generateSql(
    'mysqldump.xml'
);
```
Generate SQL from mysqldump.xml and write to mysqldump.sql
```PHP
$dumpParser = new Initvector\Xml2Mysql\DumpParser();

$dumpParser->generateSql(
    'mysqldump.xml',
    'mysqldump.sql'
);
```

\* Use at your own peril.
