<?php
require(__DIR__ . '/functions.php');
requireDevUser();
$tableColumns=4;
ini_set('display_errors', 1);

if (isset($_GET['folder']))
{
    if (preg_match('#[^A-Za-z0-9/]#', $_GET['folder']))
    {
        die('Invalid characters in folder:' . $_GET['folder']);
    }
    else
    {
        $folder = $_GET['folder'];

    }
}
else
{
    $tryFolder = '/nobackup/sinlog';
    if (is_dir($tryFolder))
    {
        $folder=$tryFolder;
    }
    else
    {
        $folder='/tmp';
    }
}

if (!is_dir($folder)) die('Folder not found: ' . $folder);


if (isset($_GET['file']))
{
    $filepath=$folder . '/' . $_GET['file'];
    if (is_file($filepath))
    {
        $filesArray=[];
        $filesArray[] = $filepath;
    }
    else
    {
        die('File not found: ' . $filepath);
    }
}
elseif (isset($_GET['glob']))
{
    $filesArray = glob("{$folder}/*{$_GET['glob']}*");
}
else
{
    if (isset($_GET['num']))
    {
        $num = (int)$_GET['num'];
    }
    else
    {
        $num=5;
    }


    $filesString = trim(`find $folder -maxdepth 1 -type f -name "*.sinlog" -o -name "*.jsonl" | sort | tail -n{$num}`);
    $filesArray = explode("\n", $filesString);
    $filesCount = count($filesArray);
}




#print_r($files);


?>


<html>
<head>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <link rel="icon" type="image/ico" href="/favicon.ico">
    <title>sinlog:<?=gethostname();?></title>
</head>
<style type="text/css">
    body, p, td {
        font-family: Consolas, Monospace;
        color:#000000;
        font-size:12px;
    }
    table {
        border: none;
        border-collapse: collapse;
    }
    table th {
        padding: 4px;
        font-size: 12px;
        border: 2px solid black;
        text-align: left;
        font-weight: normal;
        white-space: nowrap;
    }
    table td {
        padding: 4px;
        border: 2px solid black;
    }
    table.backtrace {
    }
    table.backtrace th {
        font-weight: bold;
    }
    td.backtrace {
        background-color: #c3c3c3;
    }
    td.context {
    }
    tr.spacer {
        background-color: #353030;
    }
    tr.spacer td {
        color:white;
        text-align: center;
    }

    tr.DEBUG {
        background-color: #FFF6B9;
    }
    tr.INFO {
        background-color: #CEFFCB;
    }
    tr.NOTICE {
        background-color: #93FFEB;
    }
    tr.WARNING {
        background-color: #93C8FF;
    }
    tr.ERROR,
    tr.CRITICAL,
    tr.ALERT,
    tr.EMERGENCY
    {
        background-color: #FF8383;
    }
    .clickable {
        cursor: pointer;
    }

</style>

<body onload="window.scrollTo(0,document.body.scrollHeight);">



<?php
$row=0;
$filei=0;
foreach($filesArray as $filepath)
{
    $filei++;

    $filename = basename($filepath);

    echo "<div style=\"padding: 20px; font-size: 20px;\">";
    if (isset($filesCount))
    {
        $ago=$filesCount-$filei+1;
        if ($ago == 1) $ago='LATEST';
        echo "{$ago} | ";
    }
    echo $filename;

    if (preg_match('/^([0-9]{10,})([0-9]{6})_/', $filename, $matches))
    {
        #print_r($matches);
        $FilenameDateTime = DateTime::createFromFormat('U.u', $matches[1] . '.' . $matches[2]);
        $NowDateTime = new DateTime();
        $fileDiffSeconds = $NowDateTime->getTimestamp() - $FilenameDateTime->getTimestamp();
        if ($fileDiffSeconds > 120)
        {
            $fileDiffMinutes = round($fileDiffSeconds/60);
            $intervalDisplay =  "{$fileDiffMinutes} minutes ago";
        }
        else
        {
            $intervalDisplay =  "{$fileDiffSeconds} seconds ago";
        }
        $FilenameDateTime->setTimezone(new DateTimeZone('Australia/Brisbane'));
        echo " || {$intervalDisplay} || " . $FilenameDateTime->format('c');
    }

    ?>
    <a href="view.php?microtime=<?=microtime(true);?>#bottom">REFRESH</a>
    <?php

    echo "</div>";
    $fileLines = file($filepath);
    $previousMicrotime=null;
    $diffSinceStart=null;
    $diffSincePrevious=null;



?>
<table width="100%">

    <?php foreach($fileLines as $lineKey => $line) {
        $row++;
        $record = json_decode($line, true);
        #print_r($record);

        if (isset($record['context']['fileAndLine']))
        {
            $fileAndLine = $record['context']['fileAndLine'];
        }
        elseif(isset($record['context']['Exception']['file']))
        {
            $fileAndLine = 'A: ' . basename($record['context']['Exception']['file']);
        }
        elseif (isset($record['context']['backtrace'][0]['file']))
        {
            $fileAndLine = 'A: ' . basename($record['context']['backtrace'][0]['file']);
            $fileAndLine .= ':' . $record['context']['backtrace'][0]['line'];
        }
        else
        {
            $fileAndLine = 'no backtrace array';
        }

        if (isset($record['context']['microtime']))
        {
            if ($previousMicrotime)
            {
                $diffSinceStart = round(($record['context']['microtime'] - $firstMicrotime)*1000);
                $diffSincePrevious = round(($record['context']['microtime'] - $previousMicrotime)*1000);
            } else
            {
                $diffSincePrevious = '';
                $firstMicrotime = $record['context']['microtime'];
            }
        }


        if ($diffSincePrevious > 20)
        {
            $slowDisplay = "{$diffSincePrevious} milliseconds";
        }
        else
        {
            $slowDisplay = '';
        }

        $fontsize = $diffSincePrevious/4;
        if ($fontsize < 10) $fontsize=10;
        if ($fontsize > 120) $fontsize=120;
        $fontsize = round($fontsize);


        ?>


        <?php if ($lineKey > 0) { ?>
        <tr class="spacer">
            <td colspan="<?=$tableColumns;?>" style="height: <?=min(100, ($diffSincePrevious/4));?>px; font-size: <?=$fontsize;?>px; overflow: hidden; "><?=$slowDisplay;?></td>
        </tr>
        <?php } ?>

        <tr class="<?=$record['level_name'];?>">
            <td>+<?=$diffSincePrevious;?>=<?=$diffSinceStart;?></td>
            <td width="150" onclick="$('#context<?=$row;?>').hide(); $('#backtrace<?=$row;?>').toggle()" class="clickable"><?=$fileAndLine;?></td>
            <td onclick="$('#backtrace<?=$row;?>').hide() ; $('#context<?=$row;?>').toggle(); " class="clickable" width="50"><?=$record['level_name'];?> <?php if (isset($record['context']['Exception'])) echo 'EXCEPTION'; ?></td>
            <td><?=$record['message'];?></td>
        </tr>

        <tr id="backtrace<?= $row; ?>" style="display:none;">
            <td class="backtrace" colspan="<?=$tableColumns;?>">
                <h1 onclick="$('#backtrace<?=$row;?>').hide();" class="clickable"> Backtrace</h1>
                <div class="attachment">
                    <?php if (isset($record['context']['backtrace'])) echo backtraceTable($record['context']['backtrace']);?>
                </div>
            </td>
        </tr>


        <tr id="context<?= $row; ?>" style="display:none;">
            <td class="context" colspan="<?=$tableColumns;?>">

                <?php foreach ($record['context'] as $contextKey => $contextData) {
                    if ($contextKey==='backtrace') continue;
                    ?>
                    <h1 onclick="$('#context<?= $row; ?>').hide();" class="clickable"><?=$contextKey;?></h1>
<pre class="attachment">
<?php echo(cleanup(print_r($contextData, true))); ?>
</pre>
                <?php } ?>


                <hr><hr><hr>
                Whole JSON record:
<pre class="attachment">
<?php echo(cleanup(print_r($record, true))); ?>
</pre>



            </td>
        </tr>


    <?php
        if (isset($record['context']['microtime'])) $previousMicrotime = $record['context']['microtime'];

    } ?>
</table>

<?php } ?>


<a name="bottom" href="view.php?microtime=<?=microtime(true);?>#bottom">
    <div style="text-align:center; padding: 1em; background-color:#400000; color:white; font-size: 40px; margin-top: 1em;">END - REFRESH</div>
</a>



</body>
</html>