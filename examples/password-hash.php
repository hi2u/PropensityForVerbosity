<?php
if ('cli'!==php_sapi_name())
{
    echo "Use this script on the command line.";
    die();
}

echo "Enter password to be hashed: ";
$plain = trim(readline());
$hash = password_hash($plain, PASSWORD_DEFAULT);

echo "\n\nHash:\n{$hash}\n\n";
