<?php
    require_once 'config.php';

    date_default_timezone_set('Europe/Warsaw');
    $curtime = date('Y-m-d H:i:s');
    $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    if (!$conn) {
      die("Error: " . mysqli_connect_error());
    }
    $sql = "SELECT * from data order by id desc limit 1;";
    $result = mysqli_query($conn, $sql);
    if(!$result){
        echo "Error: " . mysqli_error($conn);
        exit;
    }
    $row = mysqli_fetch_assoc($result);
    $ULstr = $row['tempUL'].$config['ULcorr'];
    $DOMstr = $row['tempDOM'].$config['DOMcorr'];
    $dev_id = $row['dev_id'];

    $CurTime = new DateTimeImmutable($curtime);
    $DBTime = new DateTimeImmutable($row['inserted']);
    $interval = $CurTime->diff($DBTime);
    $DTinterval = new DateTime($interval->format('%H:%I:%S'));
    $seconds = TimeToSec($interval->format('%H:%I:%S'));

    if($seconds > 600) $dev_id = "--";

    echo $dev_id . ',' . $row['inserted'] . ',' . eval("return $ULstr;") . ',' . eval("return $DOMstr;") . ',' . $config['ULcorr'] . ',' . $config['DOMcorr'];

    mysqli_close($conn);

function TimeToSec($time) {
    $sec = 0;
    foreach (array_reverse(explode(':', $time)) as $k => $v) $sec += pow(60, $k) * $v;
    return $sec;
}
?>
