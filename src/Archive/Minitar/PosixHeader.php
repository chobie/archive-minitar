<?php

class Archive_Minitar_PosixHeader
{
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

    public function __construct($opts)
    {
        foreach ($opts as $key => $value) {
            $this->$key = $value;
        }
    }

    public function oct($num, $len)
    {
        if (is_null($num)) {
            return str_repeat("\0", $len+1);
        } else {
            return sprintf("%0{$len}o", $num);
        }
    }

    public function header($checksum)
    {
        $result  = pack("a100a8a8a8a12A12a7",
            $this->name, $this->oct($this->mode, 7), $this->oct($this->uid, 7), $this->oct($this->gid, 7), $this->oct($this->size,11),
            $this->oct($this->mtime, 11), $checksum);
        $result .= ' ';
        $result .= pack("a1a100a6a2a32a32a8a8a155", $this->typeflag, $this->linkname, $this->magic, $this->version,
            $this->uname, $this->gname, $this->oct($this->devmajor, 7), $this->oct($this->devminor, 7), $this->prefix);
        $result .= str_repeat("\0",((512 - strlen($result)) % 512));


        return $result;
    }


    public function calculate_checksum($hh)
    {
        $sum = 0;
        foreach(unpack("C*", $hh) as $v) {
            $sum += $v;
        }
        return $sum;
    }

    public function updateChecksum()
    {
        $hh = $this->header("        ");
        $checksum = $this->oct($this->calculate_checksum($hh), 6);

        return $checksum;
    }

    public function __toString()
    {
        $chksum = $this->updateChecksum();
        return $this->header($chksum);
    }
}