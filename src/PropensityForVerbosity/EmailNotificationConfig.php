<?php
namespace PropensityForVerbosity;
use Psr\Log\LogLevel;

class EmailNotificationConfig
{
    public $to;
    public $from;
    public $levelNames=array();
    public $method='instant';


    function setLevelRange($minLevelName=null, $maxLevelName=null)
    {
        if ($minLevelName===null)
        {
            $minLevelNumber = Record::LEVEL_NUMBERS[LogLevel::DEBUG];
        }
        else
        {
            $minLevelNumber = Record::LEVEL_NUMBERS[$minLevelName];
        }
        if ($maxLevelName===null)
        {
            $maxLevelNumber = Record::LEVEL_NUMBERS[LogLevel::EMERGENCY];
        }
        else
        {
            $maxLevelNumber = Record::LEVEL_NUMBERS[$maxLevelName];
        }
        foreach(Record::LEVEL_NUMBERS as $levelName => $levelNumber)
        {
            if ($levelNumber >= $minLevelNumber AND $levelNumber <= $maxLevelNumber)
            {
                $this->levelNames[] = $levelName;
            }
        }
    }







}