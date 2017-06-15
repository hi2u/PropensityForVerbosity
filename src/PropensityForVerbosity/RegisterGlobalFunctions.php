<?php
/**
 * This file is optionally included to register the following global functions as shortcuts to the static
 * \PropensityForVerbosity\Logger::$Logger object.
 */

function logDebug($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->debug($message, $context);
}

function logInfo($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->info($message, $context);
}

function logNotice($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->notice($message, $context);
}

function logWarning($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->warning($message, $context);
}

function logError($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->error($message, $context);
}

function logCritical($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->critical($message, $context);
}

function logAlert($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->alert($message, $context);
}

function logEmergency($message, array $context = array())
{
    \PropensityForVerbosity\Logger::$Logger->emergency($message, $context);
}
