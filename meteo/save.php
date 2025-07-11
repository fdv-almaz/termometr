<?php
    require_once 'db.php';

    $dev_id = htmlspecialchars($_GET["dev_id"]);
    $dev_time = htmlspecialchars($_GET["time"]);
    $tempUL = htmlspecialchars($_GET["temperatureUL"]);
    $tempDOM = htmlspecialchars($_GET["temperatureDOM"]);
    $press = htmlspecialchars($_GET["press"]);
    echo "$dev_id, $dev_time, $tempUL, $tempDOM, $press <br>";
    $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    if (!$conn) {
      die("Ошибка: " . mysqli_connect_error());
    }
    $sql = "INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure) VALUES ('$dev_id', '$dev_time', '$tempUL', '$tempDOM', '$press')";
    if(mysqli_query($conn, $sql)){
        echo "Данные успешно добавлены";
        } else{
        echo "Ошибка: " . mysqli_error($conn);
    }
    mysqli_close($conn);
?>