<?php
namespace philwc;

class JsonTable
{

    protected $jsonFile;
    protected $fileHandle;
    protected $fileData = array();
    protected $prettyOutput;

    /**
     * @param null $_jsonFile
     */
    public function __construct($_jsonFile = null)
    {
        if ($_jsonFile !== null) {
            $this->init($_jsonFile);
        }
    }

    /**
     * @param $_jsonFile
     *
     * @throws JsonDBException
     */
    public function init($_jsonFile)
    {
        if (file_exists($_jsonFile)) {
            $this->jsonFile = $_jsonFile;
            $this->fileData = json_decode(file_get_contents($this->jsonFile), true);
            $this->checkJson();
            $this->lockFile();
        } else {
            throw new JsonDBException('File not found: ' . $_jsonFile);
        }

        $this->prettyOutput = true;
    }

    /**
     * Destruct
     */
    public function __destruct()
    {
        flock($this->fileHandle, LOCK_UN);
        fclose($this->fileHandle);
    }

    /**
     * Check JSON
     *
     * @throws JsonDBException
     */
    protected function checkJson()
    {
        $error = '';
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                break;
            case JSON_ERROR_DEPTH:
                $error = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $error = 'Unknown error';
                break;
        }

        if ($error !== '') {
            throw new JsonDBException('Invalid JSON: ' . $error);
        }
    }

    /**
     * Set Pretty Output
     *
     * @param bool $val
     *
     * @throws JsonDBException
     */
    public function setPrettyOutput($val)
    {
        if (is_bool($val)) {
            $this->prettyOutput = $val;
        } else {
            throw new JsonDBException('Error. Please supply a bool value');
        }
    }

    /**
     * Lock File
     *
     * @throws JsonDBException
     */
    protected function lockFile()
    {
        $handle = fopen($this->jsonFile, "c+");
        if (flock($handle, LOCK_EX)) {
            $this->fileHandle = $handle;
        } else {
            throw new JsonDBException('Can\'t set file-lock');
        }
    }

    /**
     * Save
     *
     * @return bool
     * @throws JsonDBException
     */
    protected function save()
    {
        if ($this->prettyOutput) {
            $flags = JSON_PRETTY_PRINT;
        } else {
            $flags = 0;
        }

        if ($this->fileData == null || $this->fileData == '' || empty($this->fileData)) {
            throw new JsonDBException('Refusing to write null data to: ' . $this->jsonFile);
        }

        ftruncate($this->fileHandle, 0);
        if (fwrite($this->fileHandle, json_encode($this->fileData, $flags))) {
            fflush($this->fileHandle);

            return true;
        } else {
            throw new JsonDBException('Can\'t write data to: ' . $this->jsonFile);
        }
    }

    /**
     * Select All
     *
     * @return array|mixed
     */
    public function selectAll()
    {
        return $this->fileData;
    }

    /**
     * Get Field Names
     *
     * @param int $recordNumber
     *
     * @return array
     */
    public function getFieldNames($recordNumber = 0)
    {
        if (isset($this->fileData[$recordNumber])) {
            return array_keys($this->fileData[$recordNumber]);
        } else {
            return array();
        }
    }

    /**
     * Select
     *
     * @param mixed $key
     * @param int   $val
     *
     * @return array
     */
    public function select($key, $val = 0)
    {
        $result = array();
        if (is_array($key)) {
            $result = $this->select($key[1], $key[2]);
        } else {
            $data = $this->fileData;

            foreach ($data as $_key => $_val) {
                if (isset($data[$_key][$key])) {
                    if ($data[$_key][$key] == $val) {
                        $result[] = $data[$_key];
                    }
                } elseif ($val == 0) {
                    if ($_key == $key) {
                        $result[] = $data[$_key];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Between
     *
     * @param mixed $key
     * @param int   $min
     * @param int   $max
     *
     * @return array
     */
    public function between($key, $min = 0, $max = 0) {
        if ( is_array( $key ) ) {
            if ( count( $key ) == 4 )
                $result = $this->between( $key[1], $key[2], $key[3] );
        } else {
            $data = $this->fileData;
            $result = [];
            foreach ($data as $_key => $_val) {
                if ( isset( $data[$_key][$key] ) ) {
                    if ( $min <= $data[$_key][$key] and $data[$_key][$key] <= $max ) {
                        array_push( $result, $_val );
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Like
     *
     * @param mixed   $key
     * @param string  $like
     *
     * @return array
     */
    public function like($key, $like = null) {
        if ( is_array( $key ) ) {
            if ( count( $key ) == 3 )
                $result = $this->like( $key[1], $key[2] );
        } else {
            $data = $this->fileData;
            $result = [];
            foreach ($data as $_key => $_val) {
                if ( isset( $data[$_key][$key] ) ) {
                    if ( strrpos( $data[$_key][$key], $like ) )
                        array_push( $result, $_val );
                }
            }
        }
        return $result;
    }

    /**
     * Update All
     *
     * @param array $data
     *
     * @return array
     */
    public function updateAll($data = array())
    {
        if (isset($data[0]) && substr_compare($data[0], $this->jsonFile, 0)) {
            $data = $data[1];
        }

        $this->save();

        return $this->fileData = array($data);
    }

    /**
     * Update
     *
     * @param mixed $key
     * @param int   $val
     * @param array $newData
     *
     * @return bool
     */
    public function update($key, $val = 0, $newData = array())
    {
        if ( is_array( $key ) ) {
            if ( count( $key ) == 4 )
                return $this->update( $key[1], $key[2], $key[3] );
        } else {
            if( count( $this->select( $key, $val ) ) != 0 ) {
                $data = $this->fileData;
                $resultData = [];
                foreach ($data as $_key => $_val) {
                    if( $data[$_key][$key] == $val )
                        array_push( $resultData, $newData );
                    else
                        array_push( $resultData, $_val);
                }
                $this->fileData = $resultData;
                $this->save();
                return true;
            } else
                return false;
        }
    }

    /**
     * Insert
     *
     * @param array $data
     *
     * @return bool
     */
    public function insert($data = array())
    {
        if (isset($data[0]) && substr_compare($data[0], $this->jsonFile, 0)) {
            $data = $data[1];
        }
        $this->fileData[] = $data;
        $this->save();

        return true;
    }

    /**
     * Delete All
     *
     * @return bool
     */
    public function deleteAll()
    {
        $this->fileData = array();
        $this->save();

        return true;
    }

    /**
     * Delete
     *
     * @param mixed $key
     * @param int   $val
     *
     * @return int
     */
    public function delete($key, $val = 0)
    {
        $result = 0;
        if (is_array($key)) {
            $result = $this->delete($key[1], $key[2]);
        } else {
            $data = $this->fileData;
            foreach ($data as $_key => $_val) {

                if ($val === false && $_key == $key) {
                    unset($data[$_key]);
                    $result++;
                    break;
                } elseif (isset($data[$_key][$key])) {
                    if ($data[$_key][$key] == $val) {
                        unset($data[$_key]);
                        $result++;
                    }
                }
            }
            if ($result) {
                sort($data);
                $this->fileData = $data;
            }
        }
        $this->save();

        return $result;
    }
}