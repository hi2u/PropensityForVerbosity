<?php
namespace PropensityForVerbosity;
use DateTime;
use DateTimeZone;

class Util
{
    /**
     * @return mixed Returns a new current DateTime object with microseconds in UTC
     */
    public static function createDateTime($timestamp=null)
    {
        if ($timestamp===null) $timestamp = microtime(true);
        if (!($timestamp > 0)) throw new PropensityForVerbosityException("Given timestamp not above zero: " . $timestamp);
        return DateTime::createFromFormat('U.u', sprintf('%.6F', $timestamp), new DateTimeZone('UTC'));
    }





    /**
     * Make sure that the folder exists, if not try to create it.
     */
    public static function ensureFolderExists($folder, $mkdirMode=0777)
    {
        if (file_exists($folder))
        {
            if (!is_dir($folder))
            { // It exists but is a file
                return false;
            }
        }
        else
        {
            if (mkdir($folder, $mkdirMode, true))
            { // It didn't exist, but we created it
                chmod($folder, $mkdirMode);
            }
            else
            { // It didn't exist, and unable to create
                return false;
            }
        }
        return is_writable($folder);
    }



    public static function timeElement($DateTime, $class='')
    {
        if (!is_object($DateTime))
        {
            if ($DateTime=='') return '';
            $DateTime = new DateTime($DateTime);
        }
        $rfc3339 = $DateTime->format('Y-m-d\TH:i:s\Z');
        $display = $DateTime->format('Y-m-d H:i:sP');
        $html = "<time datetime=\"{$rfc3339}\" class=\"{$class}\">$display</time>";
        #logDebug('html: ' . $html);
        return $html;
    }

    
    public static function urlWithSameParams(array $newValues, $url=null)
    {
        if ($url===null) $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace('/\?.*/', '', $url);
        $url .= '?';
        $params = array_merge($_GET, $newValues);
        $url .= http_build_query($params);
        return $url;
    }


    public static function requestGetValueWithFallback($key, $fallbackValue)
    {
        if (isset($_GET[$key]))
        {
            return $_GET[$key];
        }
        else
        {
            return $fallbackValue;
        }
    }


    public static function detectWebsiteHostname()
    {
        if (isset($_SERVER['HTTP_HOST']) AND $_SERVER['HTTP_HOST']) return $_SERVER['HTTP_HOST'];
        if (isset($_SERVER['SERVER_NAME']) AND $_SERVER['SERVER_NAME']) return $_SERVER['SERVER_NAME'];
        return null;
    }




    public static function linksPiped($getKey, $array, $separator=' | ')
    {
        $links=array();
        foreach($array as $getValue => $display)
        {
            $url = Util::urlWithSameParams([$getKey => $getValue]);
            $links[] = "<a href=\"{$url}\">{$display}</a>";
        }
        return implode($separator, $links);
    }

    public static function cleanup($string)
    {
        return htmlentities($string);
    }

    public static function backtraceTable($backtrace_array)
    {
        if ( !is_array($backtrace_array) ) return '';
        $trimpath = str_replace('code/classes', '', __DIR__);
        $html='<table border="1" width="100%" class="backtrace">';
        $html.='<tr><th>#</th><th>File</th><th>Function</th></tr>';

        foreach( $backtrace_array as $i => $assoc )
        {
            $fileAndLine='';
            if (isset($assoc['file'])) $fileAndLine .= $assoc['file'];
            if (isset($assoc['line']) AND $assoc['line'] > 0) $fileAndLine .= ':' . $assoc['line'];
            if (!isset($assoc['type'])) $assoc['type']='';
            if (!isset($assoc['class'])) $assoc['class']='';
            if (!isset($assoc['function'])) $assoc['function']='';

            $html .= "<tr><td style=\"text-align:right;\">$i</td>";

            $html .= "<td>{$fileAndLine}</td>";
            $html .= "<td>{$assoc['class']}{$assoc['type']}{$assoc['function']}()</td>";
            #$html .= "<td><pre>";
            #$html .= implode('<br>', describe_array($assoc['args']) );
            #if (isset($assoc['args'])) $html .= static::cleanup(var_dump_return($assoc['args']));
            #$html .= "</pre></td>";
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }


    public static function getUserIpAddress()
    {
        if ('cli' == php_sapi_name()) return null;
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        return $_SERVER['REMOTE_ADDR'];
    }

    public static function twoColumnTable($rows)
    {
        $html  = '<table border="1" cellpadding="4" cellspacing="0">';
        foreach($rows as $heading => $value)
        {
            $heading = htmlentities($heading);
            $value = htmlentities($value);
            $html .= "<tr><td style=\"background-color: #90cbff;\">{$heading}</td><td style=\"background-color: #d4ebff;\">{$value}</td></tr>";
        }
        $html .= "</table>";
        return $html;
    }


    public static function arrayRedact($array, $redactionRegex)
    {

        foreach($array as $key => $value)
        {
            if (preg_match($redactionRegex, $key))
            {
                $array[$key] = '(redacted from logging)';
            }
        }
        return $array;
    }



}

























