<?php
namespace PropensityForVerbosity;
use Psr\Log\LogLevel;

class Record
{
    protected $timestamp;
    protected $requestStartedTimestamp;
    protected $levelName;
    protected $message;
    protected $context=array();
    protected $backtrace;
    protected $file;
    protected $line;
    protected $memoryUsageUsed;
    protected $memoryUsageReal;

    const DEBUG = 100;
    const INFO = 200;
    const NOTICE = 250;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;
    const ALERT = 550;
    const EMERGENCY = 600;

    const LEVEL_NUMBERS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600
    ];
    const LEVEL_NAMES = [
        100 => LogLevel::DEBUG,
        200 => LogLevel::INFO,
        250 => LogLevel::NOTICE,
        300 => LogLevel::WARNING,
        400 => LogLevel::ERROR,
        500 => LogLevel::CRITICAL,
        550 => LogLevel::ALERT,
        600 => LogLevel::EMERGENCY
    ];

    public static function create($levelName, $message, $context, Logger $Logger)
    {
        $Record = new Record;
        $Record->memoryUsageUsed = memory_get_usage(false);
        $Record->memoryUsageReal = memory_get_usage(true);
        $Record->requestStartedTimestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        $Record->timestamp = microtime(true);
        $Record->levelName = $levelName;


        // Normal string $message or Exception object?
        if (is_string($message))
        {
            $Record->message = $message;
            $Record->backtrace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach($Record->backtrace as $key => $frame)
            {
                if (isset($frame['file']) AND preg_match('#/src/PropensityForVerbosity/[A-Za-z]+\.php$#', $frame['file']))
                {
                    unset($Record->backtrace[$key]);
                }
            }
            $Record->backtrace = array_values($Record->backtrace); // Reset keys
            if (isset($Record->backtrace[0]['file'])) $Record->file = $Record->backtrace[0]['file'];
            if (isset($Record->backtrace[0]['line'])) $Record->line = $Record->backtrace[0]['line'];
        }
        else
        {
            if ($message instanceof \Exception)
            {
                $Record->message = (string)$message;
                $Record->file = $message->getFile();
                $Record->line = $message->getLine();
                $Record->backtrace = $message->getTrace();

                $context['Exception'] = [
                    'exceptionClass' => get_class($message),
                    'file' => $Record->file . ':' . $Record->line,
                    'message' => $Record->message,
                    'code' => $message->getCode(),
                ];

            }
            else
            {
                throw new PropensityForVerbosityException("$message argument should be a string or Exception object, type was: " . gettype($message));
            }

        }

        // Remove 'args' field from backtraces as they case cause serialize issues when containing closures etc.
        foreach($Record->backtrace as $frameKey => $frameFields)
        {
            if (isset($frameFields['args'])) unset($Record->backtrace[$frameKey]['args']);
        }

        // Context array.  Do redactions and store each context item as print_r output.
        foreach($context as $key => $value)
        {
            if (is_array($value)) $value = Util::arrayRedact($value, $Logger->Config->arrayRedactionRegex);
            $Record->context[$key] = print_r($value, true);
        }


        return $Record;
    }

    public function timeSinceRequestStarted()
    {
        return $this->timestamp - $this->requestStartedTimestamp;
    }
    public function millisecondsSinceRequestStarted()
    {
        return round($this->timeSinceRequestStarted()*1000);
    }

    public function getSerialized() {
        return serialize($this);
    }

    public function getFileBasename()
    {
        return basename($this->file);
    }
    public function getLine()
    {
        return $this->line;
    }

    public function getBacktraceTable()
    {
        return Util::backtraceTable($this->backtrace);
    }
    public function getContext()
    {
        return $this->context;
    }
    public function getContextCount()
    {
        return count($this->context);
    }
    public function getContextCountDisplay()
    {
        $count = count($this->context);
        if ($count > 0)
        {
            return $count;
        }
        else
        {
            return '';
        }
    }

    public function getContextClickableClass()
    {
        if ($this->getContextCount() > 0)
        {
            return 'contextClickableAllowed';
        }
        else
        {
            return 'contextClickableNotAllowed';
        }
    }
    public function getContextHeading()
    {
        $count = count($this->getContext());
        if ($count > 1)
        {
            return "<h1>{$count} context items:</h1>";
        }
        elseif($count==1)
        {
            return "<h1>1 context item:</h1>";
        }
        else
        {
            return "<h1 class=\"dimHeading\">Record has no context items</h1>";
        }
    }

    public function getMemoryUsageUsed()
    {
        return $this->memoryUsageUsed;
    }
    public function getMemoryUsageReal()
    {
        return $this->memoryUsageReal;
    }

    public function getUniqueIdentifier()
    {
        $micro = str_replace('.', '', $this->timestamp);
        return 'Record' . $micro;
    }

    public function getDateTime() {
        return Util::createDateTime($this->timestamp);
    }

    public function getMessage() { return $this->message; }

    public function levelNameUpper()
    {
        return strtoupper($this->levelName);
    }

}
