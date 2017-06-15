<?php
namespace PropensityForVerbosity;
use DateTime;

class Viewer
{
    protected $rootStorageFoldersToTry=array();
    protected $rootStorageFolder;

    protected $ViewingDateTime;
    protected $proverbFiles=array();
    protected $Config;

    protected $showFile;
    protected $proverbFilenameRegex='([0-9]+)_([A-Z]+)_([A-Za-z0-9-]+)\.([a-z]+)\.proverb$';

    protected $records=[];

    public function setRootStorageFolder($folder)
    {
        $this->rootStorageFoldersToTry[] = $folder;
    }
    /**
     * @var array Logs and data will be stored here.  Only one folder is actually used for storage...
     * The first array element will be tested for writability, if it isn't writable, the next folder
     * is tried, and so on.
     */

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
        $this->ViewingDateTime = Util::createDateTime();

        if (count($this->rootStorageFoldersToTry)==0)
        {
            $this->rootStorageFoldersToTry = $this->Config->getRootStorageFoldersToTry();
        }

        foreach($this->rootStorageFoldersToTry as $folder)
        {
            $folder = preg_replace('#/+$#', '', trim($folder));
            if (is_dir($folder))
            {
                $this->rootStorageFolder = $folder;
                break;
            }
            else
            {
                #echo "\n\n not dir : $folder \n\n";
            }
        }

        $proverbsFolder = $this->rootStorageFolder . '/proverbs';
        // Find all the "proverbs/yyyy-mm-dd" folders
        $datedFoldersOnly = scandir($proverbsFolder, SCANDIR_SORT_DESCENDING);
        foreach($datedFoldersOnly as $key => $date)
        {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            {
                unset($datedFoldersOnly[$key]);
            }
        }

        if (isset($_GET['showFile']) AND $_GET['showFile']) $this->showFile = $_GET['showFile'];

        $collectedFileCount=0;
        $listLevel = Util::requestGetValueWithFallback('listLevel', 0);
        $listRequests = Util::requestGetValueWithFallback('listRequests', 10);
        foreach($datedFoldersOnly as $date)
        {
            $folderPathWithDate = $proverbsFolder . DIRECTORY_SEPARATOR . $date;
            if ( is_dir($folderPathWithDate))
            {
                $scandir = scandir($folderPathWithDate, SCANDIR_SORT_DESCENDING);
                $scandir = array_diff($scandir, array('..', '.'));

                $folderDate = preg_replace('#^.+/#', '', $folderPathWithDate);

                foreach($scandir as $basename)
                {
                    if (preg_match("/^{$this->proverbFilenameRegex}/", $basename, $matches))
                    {
                        $collectedFileCount++;
                        if ($collectedFileCount > 1)
                        {
                            $requestsAgo = $collectedFileCount;
                        }
                        else
                        {
                            $requestsAgo='Latest';
                        }

                        $filepathFromRootStorageFolder = $folderDate . DIRECTORY_SEPARATOR . $basename;
                        $fullFilepath = $folderPathWithDate . DIRECTORY_SEPARATOR . $basename;
                        if (!isset($this->showFile)) $this->showFile = $filepathFromRootStorageFolder;
                        $highestLevelNameEncountered = $matches[4];
                        $highestLevelNumberEncountered = Record::LEVEL_NUMBERS[$highestLevelNameEncountered];

                        if ($highestLevelNumberEncountered >= $listLevel)
                        {
                            $this->proverbFiles[] = array(
                                'levelClass' => Record::LEVEL_NAMES[$highestLevelNumberEncountered],
                                'highestLevelNameEncountered' => $highestLevelNameEncountered,
                                'filepathFromRootStorageFolder' => $filepathFromRootStorageFolder,
                                'DateTime' => new DateTime('@'.filemtime($fullFilepath)),
                                'requestMethod' => $matches[2],
                                'requestPath' => $matches[3],
                                'url' => Util::urlWithSameParams([ 'showFile' => $filepathFromRootStorageFolder ]),
                                'requestsAgo' => $requestsAgo
                            );
                            if($collectedFileCount >= $listRequests) break 2;
                        }
                    }
                }
            }
        }


        $fileContents = file_get_contents("{$this->rootStorageFolder}/proverbs/{$this->getShowFile()}");
        $arrayOfSerializedRecords = explode("\nPROPENSITYFORVERBOSITYRECORDSEPARATOR\n", $fileContents);
        unset($fileContents);

        foreach($arrayOfSerializedRecords as $serializedRecord)
        {
            if ($serializedRecord)
            {
                $this->records[] = unserialize($serializedRecord);
            }
        }


    }


    protected function getShowFile()
    {
        $regexWithDateFolder = "#^[0-9]{4}-[0-9]{2}-[0-9]{2}/{$this->proverbFilenameRegex}#";
        if (preg_match($regexWithDateFolder, $this->showFile))
        {
            return $this->showFile;
        }
        else
        {
            throw new PropensityForVerbosityException("showFile URL param did not match regex: {$this->showFile}");
        }
    }

    public function display()
    {
        if ($this->Config->viewerUsersCount() > 0)
        {
            if (isset($_SERVER['PHP_AUTH_USER']) AND $this->Config->authenticateViewerUser($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))
            {
                require __DIR__ . '/views/layout.phtml';
            }
            else
            {
                header('WWW-Authenticate: Basic realm="Logging system"');
                header('HTTP/1.0 401 Unauthorized');
                echo 'Authentication required';
                exit;
            }
        }
        else
        {
            echo "No viewer users have been added to the config.";
            die();
        }



    }
    protected function listRequestsLinks()
    {
        $links=array();
        foreach([10, 20, 50, 100, 1000, 10000] as $number)
        {
            $url = Util::urlWithSameParams(['listRequests'=> $number]);
            $links[] = "<a href=\"{$url}\">{$number}</a>";
        }
        return implode(' | ', $links);

    }

    protected function listLevelLinks()
    {
        return Util::linksPiped('listLevel', Record::LEVEL_NAMES);
    }

    protected function viewLatestRequestUrl()
    {
        return Util::urlWithSameParams(['showFile'=>null]);
    }

}

