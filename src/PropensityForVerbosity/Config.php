<?php
namespace PropensityForVerbosity;
use Psr\Log\LogLevel;

class Config
{
    /**
     * @var int Once this level or higher is encountered, all records in the RAM buffer will be written to disk.
     * Once this happens, all following records write to disk immediately upon creation, i.e. no buffering is done.
     * So this means that the default of "DEBUG" there would never be any buffering at all.
     * i.e.
     */
    protected $flushThreshold = LogLevel::DEBUG;

    /**
     * @var int Minimum level to store in the RAM buffer.  If the buffer is never flushed, these records are lost.
     */
    protected $bufferThreshold = LogLevel::DEBUG;



    /**
     * @var int At the very start of the request, a 1st Record will be added automatically with some info about
     * the request, including $_GET, $_SERVER, $_COOKIE, $_SESSION data.  Set the level you'd like this Record
     * to use.  Set to NULL to disable entirely.
     */
    public $requestInitRecordLevel=LogLevel::INFO;


    /**
     * @var bool This will automatically set any newly created Logger object into
     * the static Logger::$Logger property for easy access without without the need
     * of a dependency injector.
     */
    public $setLoggerStaticProperty=true;

    public $memoryGetUsageReal=false;

    /**
     * @var bool If this is true, global shortcut functions will be registered, logDebug(), logError() etc.
     * See RegisterGlobalFunctions.php for the functions that are created.
     */
    public $registerGlobalFunctions=true;

    public $filenameRequestPathLength=50;
    public $mkdirMode=0777;



    protected $rootStorageFoldersToTry=array();

    public $tailErrorLogLines=60;


    public $logGetArray=true;
    public $logPostArray=false;
    public $logSessionArray=true;
    public $logFilesArray=true;
    public $logServerArray=true;
    public $logCookieArray=true;


    /**
     * @var string Context items that are arrays (e.g. $_POST) will have senstitive stuff like passwords removed before
     * being logged.  This regex is used to match array keys that may contain secrets...
     */
    public $arrayRedactionRegex = '/password|passwd|secret|key|AUTH_PW|PHPSESSID/i';



    /**
     * @var string Set this to the URL to your viewer page.  This will allow email notifications to contain a link
     * directly to the relevant log page for the current request.
     */
    public $viewerUrl;


    /**
     * @var string The <head><title> to show on the viewer
     */
    public $viewerTitle='Log viewer';

    public $emailNotificationConfigs=array();

    protected $viewerUsers=array();


    #   ██████╗ ██████╗ ███╗   ██╗███████╗██╗ ██████╗
    #  ██╔════╝██╔═══██╗████╗  ██║██╔════╝██║██╔════╝
    #  ██║     ██║   ██║██╔██╗ ██║█████╗  ██║██║  ███╗
    #  ██║     ██║   ██║██║╚██╗██║██╔══╝  ██║██║   ██║
    #  ╚██████╗╚██████╔╝██║ ╚████║██║     ██║╚██████╔╝
    #   ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝     ╚═╝ ╚═════╝

    /**
     * @var int This number is added to the end of the "request number" in the filename to avoid conflicts
     * within the same microsecond.
     * It's only set to 1 by default so that the whole number fits within signed BIGINT range.  This logging system
     * just uses the number as a string, so you can bump this up if you want to go "web scale".
     */

    public $filenameRandomDigitsLength=1;

    public function getFilenameRandomNumber()
    {
        if ($this->filenameRandomDigitsLength > 0)
        {
            $digitsLengthPadded = sprintf('%02d', $this->filenameRandomDigitsLength);
            $max = pow(10, $this->filenameRandomDigitsLength) - 1;
            $random = mt_rand(0, $max);
            return sprintf('%'.$digitsLengthPadded.'d', $random);
        }
        else
        {
            return '';
        }


    }

    public function addRootStorageFolder($folder)
    {
        $this->rootStorageFoldersToTry[] = $folder;
    }
    /**
     * @var array Logs and data will be stored here.  Only one folder is actually used for storage...
     * The first array element will be tested for writability, if it isn't writable, the next folder
     * is tried, and so on.
     * @return array Array of folders to try and write data to
     */
    public function getRootStorageFoldersToTry()
    {
        if (count($this->rootStorageFoldersToTry)==0)
        {
            $this->rootStorageFoldersToTry = array('/tmp/propensityforverbosity', '/tmp');
        }
        return $this->rootStorageFoldersToTry;
    }

    public function addEmailNotificationConfig(EmailNotificationConfig $EmailNotificationConfig)
    {
        $this->emailNotificationConfigs[] = $EmailNotificationConfig;
    }


    public function addViewerUser($username, $passwordHash)
    {
        $this->viewerUsers[$username] = $passwordHash;
    }
    public function authenticateViewerUser($username, $passwordPlain)
    {
        return (
            $username AND
            $passwordPlain AND
            isset($this->viewerUsers[$username]) AND
            password_verify($passwordPlain, $this->viewerUsers[$username])
        );
    }
    public function viewerUsersCount()
    {
        return count($this->viewerUsers);
    }














}