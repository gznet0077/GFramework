<?php


namespace G\Util;


use MongoDB\BSON\ObjectID;

class Misc
{
    public static function parseBody($body)
    {
        try {
            $body = json_decode($body, true);
        } catch (\Exception $e) {
            $body = [];
        }

        return $body;
    }

    public static function randomStr($str_len = 32)
    {
        $str = '0123456789abcdefABCDEF';
        $random_str = '';
        for ($i = 0; $i < $str_len; $i++) {
            $random_str .= $str[rand(0, 21)];
        }
        return $random_str;
    }

    public static function uuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    static public function encodeHtml($html)
    {
        return htmlentities($html);
    }

    static public function decodeHtml($html)
    {
        return html_entity_decode($html);
    }

    static public function xss($str)
    {
        return \filter_var(strval($str), FILTER_SANITIZE_STRING);
    }

    static public function realID($id)
    {
        return ($id instanceof ObjectID) ? $id : new ObjectID($id);
    }

    static public function ObjectID()
    {
        return (new ObjectID())->__toString();
    }

    static public function filterCursor($cursor, $filter = null)
    {
        $results = [];
        foreach ($cursor as $doc) {
            if (is_callable($filter)) {
                $results[] = call_user_func($filter, $doc);
            } else {
                $doc['_id'] = (string)$doc['_id'];
                $results[] = $doc;
            }
        }
        return $results;
    }

    static public function flatArray($a, $prefix = '')
    {
        $b = [];
        if (!is_array($prefix)) {
            $keys = explode('.', $prefix);
        } else {
            $keys = (array)$prefix;
        }
        foreach ($a as $k => $v) {
            $tmpKeys = $keys;
            array_push($tmpKeys, $k);
            if (is_array($v)) {
                $b1 = self::flatArray($v, $tmpKeys);
                $b = array_merge($b, $b1);
            } else {
                $key = implode('.', $tmpKeys);
                $b[$key] = $v;
            }
        }
        return $b;
    }

    static public function isMobile($useragent)
    {
        return (
            stripos($useragent, 'UCBrowser') && (stripos($useragent, 'mobile') || stripos($useragent, 'android')))
            || preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent)
            || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i'
                , substr($useragent, 0, 4));
    }

    static public function mergeObject($desc, $src)
    {
        foreach ($src as $k => $item) {
            if (is_array($item) || $item instanceof \ArrayObject) {
                $desc[$k] = Misc::mergeObject($desc[$k], $item);
            } else {
                $desc[$k] = $item;
            }
        }

        return $desc;
    }

    static private function _int2letter2($i)
    {
        $letters = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];

        $p = intval($i / 26);
        $m = $i % 26;
        $l = $letters[$m];

        if ($p > 0) {
            $l = self::_int2letter2($p) . $l;
        }

        return $l;
    }

    static private function _int2letter($i)
    {
        $str = self::_int2letter2($i);

        if (strlen($str) == 1) {
            $str = 'A' . $str;
        }

        return $str;
    }

    static public function gen()
    {
        $seq = 0;

        return function () use (&$seq) {
            return Misc::_int2letter($seq++);
        };
    }

    static public function cpu_number()
    {
        $numCpus = 1;
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $numCpus = count($matches[0]);
        } else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process) {
                fgets($process);
                $numCpus = intval(fgets($process));
                pclose($process);
            }
        } else {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process) {
                $output = stream_get_contents($process);
                preg_match('/hw.ncpu: (\d+)/', $output, $matches);
                if ($matches) {
                    $numCpus = intval($matches[1][0]);
                }
                pclose($process);
            }
        }

        return $numCpus;
    }

    static public function password($pwd)
    {
        // 没有大写字母
        if (!preg_match('/[A-Z]/', $pwd)) {
            return 1;
        }

        // 没有小写字母
        if (!preg_match('/[a-z]/', $pwd)) {
            return 2;
        }

        //没有数字
        if (!preg_match('/\d/', $pwd)) {
            return 3;
        }

        // 小于6位
        if (strlen($pwd) < 6) {
            return 4;
        }
    }

    static function array_filter_key(array $array, array $filter)
    {
        $new = [];
        foreach ($filter as $item) {
            if (isset($array[$item])) {
                $new[$item] = $array[$item];
            }
        }
        return $new;
    }


    static public function parseIni($path)
    {
        $config = [];
        if (is_dir($path)) {
            $files = glob($path . '/*.ini');
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $config[$name] = self::parseInit($file);
            }
        } else if (is_file($path)) {
            $ini = parse_ini_file($path, true);
            foreach ($ini as $key => $c) {
                $ps = array_map('trim', explode(':', $key));
                if (count($ps) == 2) {
                    $config[$ps[0]] = array_merge($config[$ps[1]], $c);
                } else {
                    $config[$key] = $c;
                }
            }
        }
        return $config;
    }

    static public function pack($data)
    {
        return \Swoole\Serialize\pack($data);
    }

    static public function unpack($data)
    {
        return \Swoole\Serialize\unpack($data);
    }
}