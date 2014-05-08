<?php
namespace philwc;

class JsonDB
{

    protected $path = "./";
    protected $fileExt = ".json";
    protected $tables = array();

    public function __construct($path)
    {
        $this->validatePath($path);
    }

    private function validatePath($path)
    {
        if (is_dir($path)) {
            if (substr($path, strlen($path) - 1) != '/') {
                $path .= '/';
            }
            $this->path = $path;
        } else {
            throw new \Exception("JsonDB Error: Path not found");
        }
    }

    protected function getTableInstance($table)
    {
        if (isset($tables[$table])) {
            return $tables[$table];
        } else {
            return $tables[$table] = new JsonTable($this->path . $table);
        }
    }

    public function __call($op, $args)
    {
        if ($args && method_exists("philwc\JsonTable", $op)) {
            $table = $args[0] . $this->fileExt;
            return $this->getTableInstance($table)
                        ->$op(
                        $args
              );
        } else {
            throw new \Exception("JsonDB Error: Unknown method or wrong arguments ");
        }
    }

    public function setExtension($_fileExt)
    {
        $this->fileExt = $_fileExt;
        return $this;
    }

}
