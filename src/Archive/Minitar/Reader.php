<?php

class Archive_Minitar_Reader implements Iterator
{
    const SIZE = 512;

    protected $is_gzip = false;

    protected $parsed = false;
    protected $pos = 0;
    protected $collection = array();

    public function getEntry($name)
    {
        foreach($this as $entry) {
            if ($entry->getName() == $name) {
                return $entry;
            }
        }
    }


    protected function read($io, $length)
    {
        if ($this->is_gzip) {
            return gzread($io, $length);
        } else {
            return fread($io, $length);
        }
    }

    protected function tell($io)
    {
        if ($this->is_gzip) {
            return gztell($io);
        } else {
            return ftell($io);
        }
    }

    protected function seek($io, $pos, $opt)
    {
        if ($this->is_gzip) {
            return gzseek($io, $pos, $opt);
        } else {
            return fseek($io, $pos, $opt);
        }
    }

    protected function open($file, $mode)
    {
        if ($this->is_gzip) {
            return gzopen($file, $mode);
        } else {
            return fopen($file, $mode);
        }
    }

    protected function fclose($file)
    {
        if ($this->is_gzip) {
            return gzclose($file);
        } else {
            return fclose($file);
        }
    }

    public function __construct($io, $opts = array())
    {
        if (!is_resource($io) && file_exists($io)) {

            $ext = pathinfo($io, PATHINFO_EXTENSION);
            switch ($ext) {
                case (strpos($ext, "gz") !== false):
                    $this->is_gzip = true;
                    if (!extension_loaded("zlib")) {
                        throw new Exception("zlib extension does not exist.");
                    }
                    break;
                default:
            }
            if (!empty($opts['gzip']) && $opts['gzip']) {
                $this->is_gzip = true;
            }

            $io = $this->open($io, "rb");
        }

        $offset = 0;
        try {
            while ($entry = $this->parse($io, $offset)) {
                $this->collection[] = $entry;
            }
        } catch (Exception $e) {
        }
    }


    public function parse($io)
    {
        $offset = $this->tell($io);
        $header = $this->read($io, self::SIZE);

        if (strlen($header) != self::SIZE) {
            throw new Exception("Invalid block size.");
        }

        if ($header == 999) {
            if ($this->read($io, self::SIZE) == 999) {
                throw new Exception("End Block");
            } else {
                throw new Exception();
            }
        }

        $chunk = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/"
                . "a8checksum/a1typeflag/a100link/a6magic/a2version/"
                . "a32uname/a32gname/a8devmajor/a8devminor/a155prefix/",
            $header);

        $name = $chunk['filename'];
        $mode = octdec(trim($chunk['mode']));
        $uid = octdec(trim($chunk['uid']));
        $gid = octdec(trim($chunk['gid']));
        $size = octdec(trim($chunk['size']));
        $mtime = octdec(trim($chunk['mtime']));
        $checksum = octdec(trim($chunk['checksum']));
        $typeflag = octdec(trim($chunk['typeflag']));
        $link = trim($chunk['link']);

        $magic = trim($chunk['magic']);
        $version = trim($chunk['version']);
        $uname = trim($chunk['uname']);
        $gname = trim($chunk['gname']);
        $devmajor = trim($chunk['devmajor']);
        $devminor = trim($chunk['devminor']);
        $prefix   = trim($chunk['prefix']);

        $padding = 0;
        if ($size % 512 != 0) {
            $padding = 512 - ($size % 512);
        }
        fseek($io, $size + $padding, SEEK_CUR);

        return new Archive_Minitar_Entry(array(
            "header_block" => $header,
            "name" => $name,
            "mode" => $mode,
            "uid"  => $uid,
            "gid"  => $gid,
            "size" => $size,
            "mtime" => $mtime,
            "checksum" => $checksum,
            "typeflag" => $typeflag,
            "link" => $link,
            "magic" => $magic,
            "version" => $version,
            "uname" => $uname,
            "gname" => $gname,
            "devmajor" => $devmajor,
            "devminor" => $devminor,
            "prefix" => $prefix,
            "offset" => $offset,
            "is_gzip" => $this->is_gzip,
        ), $io);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->collection[$this->pos];
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        ++$this->pos;
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->pos;
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return isset($this->collection[$this->pos]);
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->pos = 0;
    }
}