<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>GPU Status</title>

    <meta http-equiv="refresh" content="30">

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style type="text/css">
    body {padding-bottom: 200px; }
    .process.label { font-size: 80%; font-weight: normal; color: #e3e9ee; }

    .th-id { width: 18px; }
    .th-name { width: 180px; }
    .th-mem { width: 130px; }
    .th-usage { width: 130px; }

    td .progress { margin: 0; }
    td .progress.progress-success { background-color: #deedde; }
    td .progress.progress-warning { background-color: #f8eed3; }
    td .progress.progress-danger { background-color: #f3dedd; }
    .process .user { font-weight: bold; color: #fff; }

    h1 small, h2 small { font-size: 50%; }
    h2 small .label-danger { font-size: 80%; }
    </style>
  </head>
  <body>
    <div class="container">
        <div class="page-header">
            <h1>GPU Status <small>(Refreshed every 30 seconds)</small><a href="https://github.com/ThomasRobertFr/gpu-monitor" style="float:right"><img src="css/gh.png" height="20px"></a></h1>
        </div>

<?php

$HOSTS = array("pas" => "Pas", "cal" => "Cal", "titan" => "Titan", "bigcountry" => "Big Country", "kepler" => "Kepler", "tesla" => "Tesla", "drunk" => "Drunk");
$SHORT_GPU_NAMES = array("GeForce GTX TITAN X" => "Titan X Maxwell", "TITAN X (Pascal)" => "Titan X Pascal", "GeForce GTX 980" => "GTX 980");
$GPU_COLS_LIST = array("index", "uuid",   "name", "memory.used", "memory.total", "utilization.gpu", "utilization.memory", "temperature.gpu", "timestamp");
$GPU_PROC_LIST = array("timestamp", "gpu_uuid", "used_gpu_memory", "process_name", "pid");

foreach ($HOSTS as $hostname => $hosttitle) {

    $gpus = array();
    $time = false;

    foreach(file('data/'.$hostname.'_gpus.csv') as $gpu) {
        $gpu = str_getcsv($gpu);
        if (count($gpu) != count($GPU_COLS_LIST))
            continue;
        $gpu = array_combine($GPU_COLS_LIST, array_map('trim', $gpu));
        $gpu["index"] = (int) $gpu["index"];
        $gpu["memory.used"] = (int) $gpu["memory.used"];
        $gpu["memory.total"] = (int) $gpu["memory.total"];
        $gpu["memory"] = round($gpu["memory.used"] * 100.0 / $gpu["memory.total"]);
        $gpu["utilization.gpu"] = (int) $gpu["utilization.gpu"];
        $gpu["utilization.memory"] = (int) $gpu["utilization.memory"];
        $gpu["temperature.gpu"] = (int) $gpu["temperature.gpu"];
        $gpu["processes"] = array();

        $gpus[$gpu["uuid"]] = $gpu;

        $time = $gpu["timestamp"]; // save time, keeps the latest
    }
    uasort($gpus, function($a, $b) { return $a["index"] - $b["index"]; });

    $users = array();

    foreach(file('data/'.$hostname.'_users.csv') as $user) {
        $user = array_map('trim', str_getcsv(trim($user), " "));
        $users[$user[0]] = array("user" => $user[1], "time" => join(array_slice($user, 2), " "));
    }

    $last_process_time = 0;
    foreach(file('data/'.$hostname.'_processes.csv') as $process) {
        $process = str_getcsv($process);
        if (count($process) != count($GPU_PROC_LIST))
            continue;
        $process_time = strtotime($process[0]);
        if ($last_process_time < $process_time)
            $last_process_time = $process_time;
    }

    foreach(file('data/'.$hostname.'_processes.csv') as $process) {
        $process = str_getcsv($process);
        if (count($process) != count($GPU_PROC_LIST))
            continue;
        $process = array_combine($GPU_PROC_LIST, array_map('trim', $process));

        // 5sec before last info (probably previous loop) or 1min old => exclude
        $process_time = strtotime($process["timestamp"]);
        if ($last_process_time - $process_time > 3 || time() - $process_time > 60)
            continue;

        // get more process info from `ps` data
        $process["user"] = $users[$process["pid"]] ? $users[$process["pid"]]["user"] : "???";
        $process["time"] = $users[$process["pid"]] ? $users[$process["pid"]]["time"] : "???";
        $process["usage"] = round($process['used_gpu_memory'] / $gpus[$process["gpu_uuid"]]['memory.total'] * 100);
        // if the process does not appear in ps and the gpu is not used, the process is probably dead but still appearing here because no running process was added by nvidia-smi
        if (!$users[$process["pid"]] && $gpus[$process["gpu_uuid"]]['memory.used'] < 10)
            continue;
        $gpus[$process["gpu_uuid"]]["processes"][$process["pid"]] = $process;
    }

    $deltaTSec = (strtotime($time) - time());
    $deltaT = abs($deltaTSec);
    $deltaTUnit = 's';
    $deltaTDirection = ($deltaTSec <= 0) ? ' ago' : 'in the future';
    if ($deltaT >= 60) {
        $deltaT = $deltaT / 60;
        $deltaTUnit = ' min';
        if ($deltaT >= 60) {
            $deltaT = $deltaT / 60;
            $deltaTUnit = ' hours';
            if ($deltaT >= 24) {
                $deltaT = $deltaT / 24;
                $deltaTUnit = ' days';
            }
        }
    }

    ?>
    <h2><?php echo $hosttitle; ?> <small>@ <?php echo round($deltaT).$deltaTUnit.$deltaTDirection ?>
    <?php
    if ($deltaTSec < -500) { ?>
        <span class="label label-danger"><i class="glyphicon glyphicon-warning-sign"></i> Data is not up to date!</span>
    <?php } ?>
    </small></h2>

    <table class="table table-striped table-condensed">
        <tr>
            <th class="th-id">#</th>
            <th class="th-name">Name</th>
            <th class="th-mem">Memory</th>
            <th class="th-usage">GPU</th>
            <th class="th-processes">Processes <span class="label label-default">pid@user (RAM)</span></th>
        </tr>
    <?php foreach ($gpus as $gpu) { ?>
        <tr>
            <td><?php echo $gpu['index']; ?></td>
            <td>
                <?php echo $SHORT_GPU_NAMES[$gpu['name']] ? '<span data-toggle="tooltip" title="'.$gpu['name'].'">'.$SHORT_GPU_NAMES[$gpu['name']].'</span>' : $gpu['name']; ?>
                (<?php echo round($gpu['memory.total'] / 1000) ?> Go)
            </td>
            <td>
                <?php
                $bar_status = "success";
                if ($gpu['memory'] > 20) $bar_status = "warning";
                if ($gpu['memory'] > 60) $bar_status = "danger";
                ?>
                <div class="progress progress-<?php echo $bar_status ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $gpu['memory.used'].'/'.$gpu['memory.total']; ?> Mo / Access rate: <?php echo $gpu["utilization.memory"] ?>%">
                    <div class="progress-bar progress-bar-<?php echo $bar_status ?>" role="progressbar" aria-valuenow="<?php echo $gpu['memory'] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $gpu['memory'] ?>%">
                        <?php echo $gpu['memory'] ?>%
                    </div>
                </div>
            </td>
            <td>
                <?php
                $bar_status = "success";
                if ($gpu['utilization.gpu'] > 20) $bar_status = "warning";
                if ($gpu['utilization.gpu'] > 60) $bar_status = "danger";
                ?>
                <div class="progress progress-<?php echo $bar_status ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $gpu['temperature.gpu']; ?> °C">
                    <div class="progress-bar progress-bar-<?php echo $bar_status ?>" role="progressbar" aria-valuenow="<?php echo $gpu['utilization.gpu'] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $gpu['utilization.gpu'] ?>%">
                        <?php echo $gpu['utilization.gpu'] ?>%
                    </div>
                </div>
            </td>
            <td>
                <?php foreach ($gpu["processes"] as $process) { ?>
                    <?php
                    $process_status = "default";
                    if ($process["usage"] > 15) $process_status = "info";
                    if ($process["usage"] > 40) $process_status = "primary";
                    ?>
                    <span class="process label label-<?php echo $process_status ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $process['process_name'] ?> (Mem: <?php echo $process['used_gpu_memory'] ?> Mo) / Started: <?php echo $process['time'] ?>"><?php echo $process["pid"].'@<span class="user">'.$process["user"] ?></span> (<?php echo $process["usage"] ?>%)</span>
                <?php } ?>
            </td>
        </tr>
    <?php } ?>
    </table>
    <?php
}

?>

    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script type="text/javascript">
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    })
    </script>
  </body>
</html>
