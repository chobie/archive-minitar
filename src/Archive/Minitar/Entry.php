<?php
class Archive_Minitar_Entry
{
    const SIZE = 512;
    const FLAG_REGTYPE = 0; // regular file
    const FLAG_LNKTYPE = 1; // link
    const FLAG_SYMTYPE = 2; // reserved
    const FLAG_CHRTYPE = 3; // character special
    const FLAG_BLKTYPE = 4; // block special
    const FLAG_DIRTYPE = 5; // directory
    const FLAG_FIFOTYPE = 6; // FIFO special
    const FLAG_CONTTYPE = 7; // reserved

    const END_BLOCK     = 999;

    protected $is_gzip = false;

    public $header_block;
    public $name;
    public $mode;
    public $uid;
    public $gid;
    public $size;
    public $mtime;
    public $checksum;
    public $typeflag;
    public $linkname;
    public $magic;
    public $version;
    public $uname;
    public $gname;
    public $devmajor;
    public $devminor;
    public $prefix;

    protected $io;
    protected $offset;

    public function __construct($props, $io)
    {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
        $this->io = $io;
    }

    public function getName()
    {
        return $this->name;
    }

    protected function calc_checksum()
    {
        if (!isset($this->header_block)) {
            throw new Exception("header block does not exist");
        }

        $header = $this->header_block;
        $result = 0;
        for ($i = 0; $i < 148; $i++) {
            $result += ord($header[$i]);
        }
        for ($i = 148; $i < 156; $i++) {
            $result += ord(' ');
        }
        for ($i = 156; $i < 512; $i++) {
            $result += ord($header[$i]);
        }
        return $result;
    }


    public function getContent()
    {
        $sum = $this->calc_checksum();

        if ($this->typeflag == 0 && $sum != $this->checksum) {
            throw new Exception("Invalid Checksum." . $sum . ":" . octdec(trim($this->checksum)));
        }

        $this->seek($this->io, $this->offset + self::SIZE, SEEK_SET);

        $data = $this->read($this->io, $this->size);
        return $data;

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
}