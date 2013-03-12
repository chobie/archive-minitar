<?php

class Archive_Minitar_Writer
{
    protected $io;
    protected $struct = array();
    protected $is_gzip = false;

    public function __construct($io, $opts = array())
    {
        if (!is_resource($io)) {
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

            $io = $this->open($io, "wb");
        }

        $this->io = $io;
    }

    protected function paddingContent(&$content, $size)
    {
        if ($size % 512 != 0) {
            $content .= str_repeat("\0", 512 - ($size % 512));
        }
    }

    public function addContent($name, $content, $opts = array())
    {
        $info = posix_getpwnam($_SERVER['user']);
        $group = posix_getgrgid($info['gid']);
        $opts = array_merge(array(
            "mode"     => 0644,
            "uid"      => 0,
            "gid"      => 0,
            "mtime"    => time(),
            "typeflag" => 0,
            "link"     => 0,
            "magic"    => "ustar",
            "version"  => 1,
            "uname"    => $_SERVER['USER'],
            "gname"    => $group['name'],
            "devmajor" => 0,
            "devminor" => 0,
            "prefix"   => "",
        ), $opts);

        $opts["name"] = $name;
        $opts["size"] = strlen($content);

        $this->paddingContent($content, strlen($content));
        $header = new Archive_Minitar_PosixHeader($opts);
        $this->struct[] = array(
            $header,
            $content,
        );
    }

    public function addFile($filename)
    {
        $content  = null;
        $typeflag = 0;
        $stat   = lstat($filename);
        $user   = posix_getpwuid($stat["uid"]);
        $group  = posix_getgrgid($stat['gid']);

        $size     = filesize($filename);
        if (is_dir($filename)) {
            $typeflag = 5;
        } else {
            $content  = file_get_contents($filename);
            $this->paddingContent($content, $size);
        }

        $header = new Archive_Minitar_PosixHeader(array(
            "name"     => $filename,
            "mode"     => $stat["mode"],
            "uid"      => $stat["uid"],
            "gid"      => $stat["gid"],
            "size"     => $stat["size"],
            "mtime"    => $stat["mtime"],
            "typeflag" => $typeflag,
            "link"     => 0,
            "magic"    => "ustar",
            "version"  => 1,
            "uname"    => $user['name'],
            "gname"    => $group['name'],
            "devmajor" => 0,
            "devminor" => 0,
            "prefix"   => "",
        ));
        $this->struct[] = array(
            $header,
            $content
        );
    }

    public function write()
    {
        foreach($this->struct as $struct) {
            $this->put($this->io, $struct[0]->__toString());
            $this->put($this->io, $struct[1]);
        }
        $this->put($this->io, pack("a1024", ''));
    }

    public function __toString()
    {
        $buffer = "";
        foreach($this->struct as $struct) {
            $buffer .= (string)$struct[0];
            $buffer .= $struct[1];
        }
        $buffer .= pack("a1024", '');

        if ($this->is_gzip) {
            return gzencode($buffer);
        } else {
            return $buffer;
        }
    }

     ///////////////////////////////////////////////////
    protected function put($io, $data)
    {
        if ($this->is_gzip) {
            return gzwrite($io, $data);
        } else {
            return fwrite($io, $data);
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
}