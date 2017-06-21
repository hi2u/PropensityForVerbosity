<?php
namespace PropensityForVerbosity;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use DateTime;
use PHPMailer;

class Logger implements LoggerInterface
{
    /**
     * @var Logger Easy global static access to Logger object without using dependency injection etc.
     */
    public static $Logger;

    /**
     * @var array This is the RAM buffer.  It is an array of Record objects.
     */
    protected $recordsBuffer=array();
    protected $highestLevelNumberEncountered;
    protected $highestLevelNameEncountered;

    /**
     * @var string This consists of a current human readable timestamp and 1 (or more) random digit at the end.
     */
    protected $requestNumber;
    /**
     * @var string Type of request/execution, values will be one of: CLI, GET, POST, etc...
     */
    protected $requestMethod;
    /**
     * @var string $_SERVER['REQUEST_URI'] for web, or full command used for CLI.
     */
    protected $requestPath;
    /**
     * @var string The URL to the current request's page, or the CLI command used.
     */
    protected $requestUrl;
    /**
     * @var DateTime Timestamp of when this object was constructed.
     */
    protected $RequestStarted;
    /**
     * @var string The filename (basename) of the output log file for this request.
     */
    protected $proverbFilename;
    /**
     * @var string Full path to $this->proverbFilename
     */
    protected $proverbFilepath;
    /**
     * @var resource File handle for the log to write to.
     */
    protected $proverbFileHandle;
    /**
     * @var string Folder that contains the current log, i.e. equivalent of dirname($this->proverbFilepath)
     */
    protected $proverbsFolder;

    /**
     * @var string The top-level of the data storage folder for everything related to this system.
     * By default it is: /tmp/propensityforverbosity
     */
    protected $rootStorageFolder;

    /**
     * @var bool Once $Config->flushThreshold has been reached, this will be set to true, and all buffer records are
     * written to disk, and then any following records are written to disk immediately.  i.e. $writeToDisk never goes
     * back to false once it has been true.
     */
    protected $writeToDisk=false;

    /**
     * @var Config The \PropensityForVerbosity\Config object containing settings/preferences.
     */
    public $Config;


    #  ██╗███╗   ██╗██╗████████╗
    #  ██║████╗  ██║██║╚══██╔══╝
    #  ██║██╔██╗ ██║██║   ██║
    #  ██║██║╚██╗██║██║   ██║
    #  ██║██║ ╚████║██║   ██║
    #  ╚═╝╚═╝  ╚═══╝╚═╝   ╚═╝

    public function __construct(Config $Config=null)
    {


        if ($Config===null)
        {
            $this->Config = new Config;
        }
        else
        {
            $this->Config = $Config;
        }

        if ($this->Config->setLoggerStaticProperty) Logger::$Logger = $this;
        if ($this->Config->registerGlobalFunctions) require_once __DIR__ . '/RegisterGlobalFunctions.php';
        $this->RequestStarted = Util::createDateTime($_SERVER['REQUEST_TIME_FLOAT']);
        $this->requestNumber  = $this->RequestStarted->format('ymdHisu');
        $this->requestNumber .= $this->Config->getFilenameRandomNumber();

        // Command line script, or web request?
        if ('cli'==php_sapi_name())
        {
            $this->requestMethod = 'CLI';
            $this->requestPath = implode(' ', $_SERVER['argv']);
            $this->requestUrl = 'CLI: ' . implode(' ', $_SERVER['argv']);
        }
        else
        {
            $this->requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
            $this->requestPath = $_SERVER['REQUEST_URI'];
            $this->requestUrl = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        // Storage folder
        foreach($this->Config->getRootStorageFoldersToTry() as $folder)
        {
            $folder = preg_replace('#/+$#', '', trim($folder));
            if (Util::ensureFolderExists($folder, $this->Config->mkdirMode))
            {
                $this->rootStorageFolder = $folder;
                break;
            }
        }
        if (!isset($this->rootStorageFolder)) throw new PropensityForVerbosityException("Could not find a writable storage folder");

        $this->proverbsFolder = $this->rootStorageFolder . DIRECTORY_SEPARATOR . 'proverbs';
        if (!Util::ensureFolderExists($this->proverbsFolder, $this->Config->mkdirMode))
        {
            throw new PropensityForVerbosityException("Could not create: {$this->proverbsFolder}");
        }

        $this->proverbsFolder .= '/' . $this->RequestStarted->format('Y-m-d');
        if (!Util::ensureFolderExists($this->proverbsFolder, $this->Config->mkdirMode))
        {
            throw new PropensityForVerbosityException("Could not create: {$this->proverbsFolder}");
        }

        if ($this->Config->requestInitRecordLevel !== null)
        {
            $initMessage = "{$this->requestMethod} {$this->requestPath}";
            $context=[];
            if ($this->Config->logGetArray) $context['$_GET'] = $_GET;
            if ($this->Config->logPostArray AND isset($_POST))
            {
                $postValuesWithoutPasswords=$_POST;
                foreach($postValuesWithoutPasswords as $key => $value)
                {
                    if (stristr($key, 'password')) $postValuesWithoutPasswords[$key] = '(possible password redacted)';
                }
                $context['$_POST'] = $postValuesWithoutPasswords;
            }
            if ($this->Config->logFilesArray AND isset($_FILES)) $context['$_FILES'] = $_FILES;
            if ($this->Config->logSessionArray AND isset($_SESSION)) $context['$_SESSION'] = $_SESSION;
            if ($this->Config->logCookieArray AND isset($_COOKIE)) $context['$_COOKIE'] = $_COOKIE;
            if ($this->Config->logServerArray) $context['$_SERVER'] = $_SERVER;

            $this->log($this->Config->requestInitRecordLevel, $initMessage, $context);
        }

    }

    public function getFilenameWithDateFolder()
    {
        return $this->RequestStarted->format('Y-m-d') . DIRECTORY_SEPARATOR . $this->proverbFilename;
    }


    public function renderProverbFilename()
    {
        // Replace non-alphanumeric with hyphens
        if ($this->requestPath === '/')
        {
            $filenameRequestPath = 'homepage';
        }
        else
        {
            $filenameRequestPath = preg_replace("/[^A-Za-z0-9-]+/" , '-' , $this->requestPath);
        }

        // Get rid of prefix+suffix hyphens
        $filenameRequestPath = preg_replace('/^-*/', '', $filenameRequestPath);
        $filenameRequestPath = preg_replace('/-*$/', '', $filenameRequestPath);
        if ($filenameRequestPath==='') $filenameRequestPath = 'unknown';
        // Limit the path length, 50 characters by default
        $filenameRequestPath = substr($filenameRequestPath, 0, $this->Config->filenameRequestPathLength);
        // Set the object properties
        $this->proverbFilename = "{$this->requestNumber}_{$this->requestMethod}_{$filenameRequestPath}.{$this->highestLevelNameEncountered}.proverb";
        $this->proverbFilepath = $this->proverbsFolder . DIRECTORY_SEPARATOR . $this->proverbFilename;
    }

    #  ██╗      ██████╗  ██████╗  ██████╗ ██╗███╗   ██╗ ██████╗
    #  ██║     ██╔═══██╗██╔════╝ ██╔════╝ ██║████╗  ██║██╔════╝
    #  ██║     ██║   ██║██║  ███╗██║  ███╗██║██╔██╗ ██║██║  ███╗
    #  ██║     ██║   ██║██║   ██║██║   ██║██║██║╚██╗██║██║   ██║
    #  ███████╗╚██████╔╝╚██████╔╝╚██████╔╝██║██║ ╚████║╚██████╔╝
    #  ╚══════╝ ╚═════╝  ╚═════╝  ╚═════╝ ╚═╝╚═╝  ╚═══╝ ╚═════╝


    public function log($levelName, $message, array $context = array())
    {
        if (!is_string($levelName)) throw new InvalidArgumentException("Level name should be a string, argument given was: " . $levelName);
        if (!is_string($message) AND !is_array($message) AND !is_object($message)) throw new InvalidArgumentException("Logger::log() message argument should be a string, array or object, type given: " . gettype($message));
        if (!is_array($context)) throw new InvalidArgumentException('$context should be an array, given argument was a ' . gettype($context));

        $levelNumber = Record::LEVEL_NUMBERS[$levelName];
        if (!isset($this->highestLevelNumberEncountered) OR $levelNumber > $this->highestLevelNumberEncountered)
        {
            if (!$this->writeToDisk AND $levelNumber >= Record::LEVEL_NUMBERS[$this->Config->flushThreshold]) $this->writeToDisk=true;
            $oldProverbFilepath = $this->proverbFilepath;
            $this->highestLevelNumberEncountered = $levelNumber;
            $this->highestLevelNameEncountered = $levelName;
            $this->renderProverbFilename();
            // Rename the existing file
            if ($oldProverbFilepath AND file_exists($oldProverbFilepath))
            {
                fclose($this->proverbFileHandle);
                unset($this->proverbFileHandle);
                rename($oldProverbFilepath, $this->proverbFilepath);
            }
        }
        if ($this->writeToDisk AND !isset($this->proverbFileHandle)) $this->proverbFileHandle = fopen( $this->proverbFilepath , 'a' );
        $Record = Record::create($levelName, $message, $context, $this);

        // Only buffer/write records that are $this->Config->bufferThreshold and above.
        if ($levelNumber >= Record::LEVEL_NUMBERS[$this->Config->bufferThreshold]) $this->recordsBuffer[] = $Record;
        if ($this->writeToDisk)
        {
            foreach($this->recordsBuffer as $writeRecordKey => $WriteRecord)
            {
                fwrite($this->proverbFileHandle, $WriteRecord->getSerialized() . "\nPROPENSITYFORVERBOSITYRECORDSEPARATOR\n");
                unset($this->recordsBuffer[$writeRecordKey]);
            }
        }

        // SEND EMAILS
        $instantEmails=[];
        foreach($this->Config->emailNotificationConfigs as $EmailNotificationConfig)
        {
            #print_r($this->Config->emailJobs);
            #die();
            if (in_array($levelName, $EmailNotificationConfig->levelNames))
            {
                $mailKey = $EmailNotificationConfig->from;
                if (!isset($instantEmails[$mailKey]))
                {
                    $instantEmails[$mailKey] = new PHPMailer();
                    $instantEmails[$mailKey]->setFrom($EmailNotificationConfig->from);
                    $instantEmails[$mailKey]->Subject = strtoupper($levelName) . ": {$this->requestUrl}: {$Record->getMessage()}";
                    $instantEmails[$mailKey]->isHTML(true);

                    if ($this->Config->viewerUrl)
                    {
                        $rows['Viewer URL'] = Util::urlWithSameParams(['showFile'=>$this->getFilenameWithDateFolder()], $this->Config->viewerUrl);
                    }

                    $rows['Request URL'] = $this->requestUrl;
                    $rows['Level'] = $levelName;
                    $rows['Message'] = $Record->getMessage();
                    $rows['File'] = $Record->getFileBasename() . ':' . $Record->getLine();
                    $rows['User IP address'] = Util::getUserIpAddress();

                    $instantEmails[$mailKey]->Body = Util::twoColumnTable($rows);


                }
                $instantEmails[$mailKey]->addAddress($EmailNotificationConfig->to);
            }
        }
        #print_r($instantEmails);
        foreach($instantEmails as $instantEmail)
        {
            $instantEmail->send();
        }

    }

    public function debug($message, array $context = array()) { $this->log(LogLevel::DEBUG, $message, $context); }
    public function info($message, array $context = array()) { $this->log(LogLevel::INFO, $message, $context); }
    public function notice($message, array $context = array()) { $this->log(LogLevel::NOTICE, $message, $context); }
    public function warning($message, array $context = array()) { $this->log(LogLevel::WARNING, $message, $context); }
    public function error($message, array $context = array()) { $this->log(LogLevel::ERROR, $message, $context); }
    public function critical($message, array $context = array()) { $this->log(LogLevel::CRITICAL, $message, $context); }
    public function alert($message, array $context = array()) { $this->log(LogLevel::ALERT, $message, $context); }
    public function emergency($message, array $context = array()) { $this->log(LogLevel::EMERGENCY, $message, $context); }


    /**
     * @return string This number can be useful to display to users on error/exception screens, so that they can report
     * An "incident number" for you to look into.
     */

    public function getRequestNumber()
    {
        return $this->requestNumber;
    }
}


