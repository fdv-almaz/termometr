<?php
    require_once 'db.php';

    date_default_timezone_set('Europe/Warsaw');
    $curtime = date('Y-m-d H:i:s');
    $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    if (!$conn) {
      die("Error: " . mysqli_connect_error());
    }
    $sql_config = "SELECT * from config";
    $sql = "SELECT * from data order by id desc limit 1;";
    $result_config = mysqli_query($conn, $sql_config);
    if(!$result_config){
        echo "Error: " . mysqli_error($conn);
        exit;
    }

    $result = mysqli_query($conn, $sql);
    if(!$result){
        echo "Error: " . mysqli_error($conn);
        exit;
    }

    while ($row_config = mysqli_fetch_assoc($result_config))
    {
       switch($row_config['param_name'])
       {
	    case "ULcorr":
		$ULcorr = $row_config['param_data'];
		break;
	    case "DOMcorr":
		$DOMcorr = $row_config['param_data'];
		break;
	    case "PRESScorr":
		$PRESScorr = $row_config['param_data'];
		break;
       }
    }

    $row = mysqli_fetch_assoc($result);

    $ULstr = $row['tempUL'].$ULcorr;
    $DOMstr = $row['tempDOM'].$DOMcorr;
    $dev_id = $row['dev_id'];
    $press = $row['pressure'].$PRESScorr;

    $CurTime = new DateTimeImmutable($curtime);
    $DBTime = new DateTimeImmutable($row['inserted']);
    $interval = $CurTime->diff($DBTime);
    $DTinterval = new DateTime($interval->format('%H:%I:%S'));
    $seconds = TimeToSec($interval->format('%H:%I:%S'));

    if($seconds > 600) $dev_id = "--";

    echo $dev_id . ',' . $row['inserted'] . ',' . eval("return $ULstr;") . ',' . eval("return $DOMstr;") . ',' . $ULcorr . ',' . $DOMcorr . ',' . eval("return $press;");

    mysqli_close($conn);

function TimeToSec($time) {
    $sec = 0;
    foreach (array_reverse(explode(':', $time)) as $k => $v) $sec += pow(60, $k) * $v;
    return $sec;
}
?>
