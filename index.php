<?php

// CACHE
$CACHE_DURATION = 7; // seconds
$CACHE_DISABLED = false;

$TIME_CACHE = filemtime("content.html");
$TIME_COMMENT = filemtime("data/comments.json");

if (file_exists("content.html") && time() - $CACHE_DURATION < $TIME_CACHE && $TIME_CACHE > $TIME_COMMENT && !isset($_POST["host"]) && !$CACHE_DISABLED) {
    if (!isset($_GET["content"]))
        include("header.html");
    include("content.html");
}
else {
    ob_start();

$HOSTS = array("fb" => "Facebook",  "pascal" => "Pascal", "chic" => "Chic", "nile" => "Nile", "rodgers" => "Rodgers", "bernard" => "Bernard", "edwards" => "Edwards", "pas" => "Pas", "cal" => "Cal", "titan" => "Titan", "bigcountry" => "Big Country", "sledge" => "Sledge", "sister" => "Sister");
$SHORT_GPU_NAMES = array("GeForce GTX TITAN X" => "Titan X Maxwell", "TITAN X (Pascal)" => "Titan X Pascal", "TITAN Xp" => "Titan Xp", "GeForce GTX 980" => "GTX 980", "Tesla P100-PCIE-16GB" => "Tesla P100", "GeForce RTX 2080 Ti" => "2080 Ti");
$SHORTER_GPU_NAMES = array("GeForce GTX TITAN X" => "X Max", "TITAN X (Pascal)" => "X Pas", "TITAN Xp" => "Xp", "GeForce GTX 980" => "GTX 980", "Tesla K20m" => "K20m", "Tesla M2090" => "M2090", "Tesla P100-PCIE-16GB" => "P100");
$GPU_COLS_LIST = array("index", "uuid",   "name", "memory.used", "memory.total", "utilization.gpu", "utilization.memory", "temperature.gpu", "timestamp");
$GPU_PROC_LIST = array("timestamp", "gpu_uuid", "used_gpu_memory", "process_name", "pid");
$CPU_COLS_LIST = array("average_use","total_nb_proc");


class Stats {
    public $data = array();
    public $ema_time;
    public $ema_Ts;

    function __construct() {
        $this->ema_time = time();
        $this->ema_Ts = array(2*60*60, 24*60*60, 7*24*60*60);
    }

    function rewrite_user($user) {
        $user = strtolower(substr(trim($user), 0, 7));
        if (preg_match("/pe?r+i+n+e?/", $user) || $user == "relou") $user = "cribier";
        if ($user == "antoine" || $user == "taylor" || $user == "saporta" || $user == "mordan") $user = "saporta-mordan";
        if ($user == "yifu" || $user == "yif") $user = "chenyi";
        if ($user == "clara") $user = "gainond";
        if ($user == "tom") $user = "veniat";
        if ($user == "yin") $user = "yiny";
        if ($user == "valenti") $user = "guiguet";
        if ($user == "remi" || $user == "caddene") $user = "cadene";
        if ($user == "taylor") $user = "mordan";
        if ($user == "antoine") $user = "saporta";
        if ($user == "etienne") $user = "esimon";
        if ($user == "agnes") $user = "mustar";
        if ($user == "daniel") $user = "brooks";
        if ($user == "eloi") $user = "zablock";
        if ($user == "???" || $user == "en pann" || $user == "broken") $user = "";
        return $user;
    }

    function init_user($user) {
        if (empty($user)) return;

        if (!isset($this->data[$user]))
            $this->data[$user] = array("resa" => 0, "used" => 0, "emas" => array(0, 0, 0));
    }

    function add($user, $type) {
        $user = $this->rewrite_user($user);
        if (empty($user)) return;
        if ($type != "used" && $type != "resa") return;

        $this->init_user($user);
        $this->data[$user][$type] += 1;
    }

    function set_ema($user, $index, $val) {
        $user = $this->rewrite_user($user);
        if (empty($user)) return;

        $this->init_user($user);
        $this->data[$user]["emas"][$index] = max($val, $this->data[$user]["emas"][$index]); // useful when merging
    }

    function load_ema_data() {
        $json_data = json_decode(file_get_contents("data/statsV2.json"), true);
        $this->ema_time = (isset($json_data["time"])) ? $json_data["time"] : time() - 60;

        foreach ($json_data["data"] as $user => $datum) {
            foreach ($datum["emas"] as $index => $value) {
                $this->set_ema($user, $index, $value);
            }
        }
    }

    function ema_step() {
        $deltaT = time() - $this->ema_time;
        if ($deltaT > 30) {
            foreach($this->ema_Ts as $index => $T) {
                $alpha = 1 - exp(-$deltaT/$T);
                foreach ($this->data as $user => $datum) {
                    $this->data[$user]["emas"][$index] += $alpha * ($datum["used"] - $datum["emas"][$index]);
                }
            }

            $this->ema_time = time();
            $this->save_data();
        }
    }

    function save_data() {
        $json_data = array("time"=> $this->ema_time, "data" => $this->data);
        file_put_contents("data/statsV2.json", json_encode($json_data));
    }

    function sort() {
        uasort($this->data, function ($b, $a) {
            return $a["resa"] + $a["used"]*1.5 + $a["emas"][0]*0.15 > $b["resa"] + $b["used"]*1.5 + $b["emas"][0]*0.15;
        });

    }
}

$STATS = new Stats();
$STATS->load_ema_data();

if (is_file("data/comments.json"))
    $COMMENTS = json_decode(file_get_contents("data/comments.json"), true);
else
    $COMMENTS = array();

if (isset($_POST["host"]) && isset($HOSTS[$_POST["host"]])) {
    try {
        if (isset($_POST["reset"])) throw new Exception("reset");
        $name = preg_replace('/[^\p{L}\p{N}-_.,+\/#&]/u', " ", $_POST["name"]);
        $comment = preg_replace('/[^\p{L}\p{N}-_.,+\/#&]/u', " ", $_POST["comment"]);
        if (preg_match('/^ *(([0-9]+)d)? ?(([0-9]+)h)? *$/i', $_POST["date"], $matches)) {
            $interv = new DateInterval("P".($matches[2] ? $matches[2] : 0)."DT".(isset($matches[4]) ? $matches[4] : 0)."H");
            $date = new DateTime();
            $date->add($interv);
        }
        else {
            $date = new DateTime($_POST["date"]);
        }
        $date = $date->format("Y-m-d H:i");
    }
    catch (Exception $e) {
        $name = "";
        $date = "";
        $comment = "";
    }
    $index = (((int) $_POST["id"]) + 20) % 20; // limit btwn 0 and 19

    $COMMENTS[$_POST["host"]][$index] = array("name" => $name, "date" => $date, "comment" => $comment);

    file_put_contents("data/comments.json", json_encode($COMMENTS));
    touch("content.html", time() - 100, time() - 100);
    header("Location: http://".$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']."#".$_POST["host"]);
    exit();
}



/////// PAGE START


if (!isset($_GET["content"]))
    include("header.html");
?>
<div class="page-header">
    <h1>GPU Status <small class="hidden-xs">(Refreshed every 30 seconds)</small><a href="https://github.com/ThomasRobertFr/gpu-monitor" style="float:right"><img src="css/gh.png" height="20px"></a></h1>
</div>
<?php


foreach ($HOSTS as $hostname => $hosttitle) {

    $gpus = array();
    $time = false;

    // USAGE OF LOCAL DISK
    $disk_usage = array();
    foreach(file('data/'.$hostname.'_local.txt') as $local_usage) {
        if (preg_match("#^([0-9,]+)([KMGT])\s*/local/(\w+)$#", $local_usage, $matches)) {
            $multiplier = 1;
            if ($matches[2] == "K") $multiplier = 0.001 * 0.001;
            if ($matches[2] == "M") $multiplier = 0.001;
            if ($matches[2] == "G") $multiplier = 1;
            if ($matches[2] == "T") $multiplier = 1000;
            $val = floatval(str_replace(",", ".", $matches[1])) * $multiplier;
            if ($val > 10)
                $disk_usage[$matches[3]] = $val;
        }
    }
    arsort($disk_usage);

    // LIST OF GPUS
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
    $users_childs = array();

    $first = true;
    foreach(file('data/'.$hostname.'_users.csv') as $user) {
        if ($first) {
            $users_childs = json_decode($user, true);
            $first = false;
            continue;
        }
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
        if ($last_process_time - $process_time > 5 || time() - $process_time > 60)
            continue;

        // get more process info from `ps` data
        $process["user"] = "???"; $process["time"] = "???"; $process["alert"] = "This process is probably dead";
        if (isset($users[$process["pid"]])) {
            $process["user"] = $users[$process["pid"]]["user"];
            $process["time"] = $users[$process["pid"]]["time"];
            $process["alert"] = false;
        }
        elseif (isset($users_childs[$process["pid"]][0]) && isset($users[$users_childs[$process["pid"]][0]])) {
            $process["user"] = $users[$users_childs[$process["pid"]][0]]["user"];
            $process["time"] = $users[$users_childs[$process["pid"]][0]]["time"];
            $process["alert"] .= ". Kill childs PIDs: ".implode($users_childs[$process["pid"]], ", ");
        }
        $process["usage"] = round($process['used_gpu_memory'] / $gpus[$process["gpu_uuid"]]['memory.total'] * 100);
        // if the process does not appear in ps and the gpu is not used, the process is probably dead but still appearing here because no running process was added by nvidia-smi
        if (!$users[$process["pid"]] && $gpus[$process["gpu_uuid"]]['memory.used'] < 10)
            continue;
        $gpus[$process["gpu_uuid"]]["processes"][$process["pid"]] = $process;
    }

    $f = fopen('data/'.$hostname.'_status.csv', "r");
    $diskRaw = fgets($f);
    if (substr($diskRaw, 0, 3) != "Mem") {
        preg_match("#^[^ ]+ +([^ ]+) +([^ ]+)#", $diskRaw, $diskRaw);
        $disk = array("total" => round($diskRaw[1]/1024/1024, -1), "used" => round($diskRaw[2]/1024/1024, -1), "usage" => round($diskRaw[2] / $diskRaw[1] * 100));

        $ramRaw = fgets($f);
    }
    else {
        $disk = array("total" => 0, "used" => 0, "usage" => 0);
    }
    preg_match("#^[^ ]+ +([^ ]+) +([^ ]+)#", $ramRaw, $ramRaw);
    $ram = array("total" => round($ramRaw[1] / 1024), "used" => round($ramRaw[2] / 1024), "usage" => round($ramRaw[2] / $ramRaw[1] * 100));

    //// based on top
    //$cpuRaw = fgets($f);
    //preg_match("#[ ,]([0-9,.]+) id#", $cpuRaw, $cpuRaw);
    //$cpu = 100 - round((float)str_replace(",",".", $cpuRaw[1]));

    $nbCpu = (int) fgets($f);
    $uptime = fgets($f);
    preg_match("#load average: ([0-9\,]+), #", $uptime, $uptime);
    $cpu = round((float)str_replace(",",".", $uptime[1]) / $nbCpu * 100);

    fclose($f);

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

    <table id="<?php echo $hostname ?>" class="table table-condensed title-table">
        <?php
        if ($deltaTSec < -500) { ?>
            <tr><td colspan="5" style="border-top: 0"><span class="label label-danger"><i class="glyphicon glyphicon-warning-sign"></i> Data is not up to date for the server below</span></td></tr>
        <?php } ?>
        <tr>
            <th class="th-machine" rowspan="2"><h2><a href="#<?php echo $hostname ?>"><?php echo $hosttitle; ?></a></h2><br>
            <small>@ <?php echo round($deltaT).$deltaTUnit.$deltaTDirection ?></small></th>
            <th class="th-mem">RAM</th>
            <th class="th-mem">CPU</th>
            <th class="th-mem">SSD</th>
            <th></th>
        </tr>
        <tr>
            <td>
                <?php
                $bar_status = "success";
                if ($ram["usage"] > 35) $bar_status = "warning";
                if ($ram["usage"] > 70) $bar_status = "danger";
                ?>
                <div class="progress progress-<?php echo $bar_status ?>" data-toggle="tooltip" data-placement="top" title="<?php printf("%d/%d Go", $ram['used'], $ram['total']); ?>">
                    <div class="progress-bar progress-bar-<?php echo $bar_status ?>" role="progressbar" aria-valuenow="<?php echo $ram["usage"] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $ram["usage"] ?>%">
                        <?php echo $ram["usage"] ?>%
                    </div>
                </div>
            </td>
                <td>
                    <?php
                    $bar_status = "success";
                    if ($cpu > 35) $bar_status = "warning";
                    if ($cpu > 70) $bar_status = "danger";
                    ?>
                    <div class="progress progress-<?php echo $bar_status ?>" data-toggle="tooltip" data-placement="top" title="A score > 100% means processes are waiting">
                        <div class="progress-bar progress-bar-<?php echo $bar_status ?>" role="progressbar" aria-valuenow="<?php echo $cpu ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo min(100,$cpu) ?>%">
                            <?php echo $cpu ?>%
                        </div>
                    </div>
                </td>
            <td>
                <?php
                $bar_status = "success";
                if ($disk["usage"] > 35) $bar_status = "warning";
                if ($disk["usage"] > 70) $bar_status = "danger";
                ?>
                <div class="progress progress-<?php echo $bar_status ?>" data-toggle="tooltip" data-placement="top" title="<?php printf("%d/%d Go", $disk['used'], $disk['total']); ?>">
                    <div class="progress-bar progress-bar-<?php echo $bar_status ?>" role="progressbar" aria-valuenow="<?php echo $disk["usage"] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $disk["usage"] ?>%">
                        <?php echo $disk["usage"] ?>%
                    </div>
                </div>

                <?php if (!empty($disk_usage)) { ?>
                <span class="disk-content hidden">
                <?php
                $volume_max = 0;
                foreach ($disk_usage as $user => $volume) {
                    if ($volume > $volume_max)
                        $volume_max = $volume;
                    $volume_percent = round($volume / $disk['total'] * 100);
                    $volume_percent_disp = round($volume / ($volume_max * 5/4) * 100);
                    ?>
                    <div class="progress progress-disk">
                        <div class="progress-bar progress-bar-info" role="progressbar"
                        aria-valuenow="<?php echo $volume_percent_disp ?>"
                        aria-valuemin="0" aria-valuemax="100"
                        style="width: <?php echo $volume_percent_disp ?>%">
                            <?php echo $volume_percent ?>% <?php echo round($volume) ?>Go - <?php echo $user ?>
                        </div>
                    </div>
                <?php } ?>
                </span>
                <?php } ?>
            </td>
            <td>
                <?php if (!empty($disk_usage)) { ?>
                <a type="button" role="button" class="btn btn-default btn-xs btn-disk"><i class="glyphicon glyphicon-triangle-bottom"></i></a>
                <?php } ?>
            </td>
        </tr>
    </table>

    <table class="table table-striped table-condensed">
        <tr>
            <th class="th-id">#</th>
            <th class="th-name">Name</th>
            <th class="th-mem">Memory</th>
            <th class="th-usage">GPU</th>
            <th class="th-comment">Reservation</th>
            <th class="th-processes"><span class="hidden-xs">Processes <span class="label label-default">pid@user (RAM)</span></span></th>
        </tr>
    <?php foreach ($gpus as $gpu) { ?>
        <tr>
            <td><?php echo $gpu['index']; ?></td>
            <td>
                <span class="hidden-xs"><?php echo $SHORT_GPU_NAMES[$gpu['name']] ? '<span data-toggle="tooltip" title="'.$gpu['name'].'">'.$SHORT_GPU_NAMES[$gpu['name']].'</span>' : $gpu['name']; ?></span>
                <span class="visible-xs-inline"><?php echo $SHORTER_GPU_NAMES[$gpu['name']] ? '<span data-toggle="tooltip" title="'.$gpu['name'].'">'.$SHORTER_GPU_NAMES[$gpu['name']].'</span>' : $gpu['name']; ?></span>
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

            <?php
            try {
                $comment = $COMMENTS[$hostname][$gpu['index']];

                $date = date_create($comment["date"]);
                $now = date_create();
                if ($date > $now)
                    $now->sub(new DateInterval("PT1H")); // remove 1h from now to round up diff to ceil instead of floor
                $diff = date_diff($now, $date);
                if ($diff->days >= 1)
                    $diff_disp = $diff->format("%ad");
                else
                    $diff_disp = $diff->format("%hh");

                if ($date < $now && $diff->days > 2)
                    throw new Exception("remove, too old");
            }
            catch (Exception $e) { $comment = array("date" => "", "name" => ""); }

            if (!empty($comment["name"])) {
                $STATS->add($comment["name"], "resa");
            }

            // color
            echo "<!-- ".$diff->days.'-->';
            if ($diff->days > 2)
                $color = "danger";
            elseif ($diff->days > 1)
                $color = "primary";
            elseif ($date > $now)
                $color = "info";
            else
                $color = "default";

            ?>

            <td class="td-comment" data-name="<?php echo $comment["name"] ?>" data-comment="<?php echo $comment["comment"] ?>" data-date="<?php echo $comment["date"] ?>" data-host="<?php echo $hostname ?>" data-id="<?php echo $gpu['index'] ?>">
                <button class="btn btn-default btn-xs comment-btn"><i class="fas fa-pencil"></i></button>
                <?php if ($comment["date"] && $comment["name"]) { ?>
                    <span class="label label-comment label-<?php echo $color; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $comment["comment"]; ?>"><?php echo $comment["name"].' ('.$diff_disp.($date > $now ? "" : " ago").')'; if ($comment["comment"]) echo '&nbsp;&nbsp;<i class="fas fa-comment"></i>'; ?></span>
                <?php } ?>
            </td>
            <td>
                <span class="hidden-xs process-content">
                <?php foreach ($gpu["processes"] as $process) { ?>
                    <?php
                    $process_status = "default";
                    if ($process["usage"] > 15) $process_status = "info";
                    if ($process["usage"] > 40) $process_status = "primary";
                    if ($process["alert"] !== false) $process_status = "danger";
                    ?>
                    <span class="process label label-<?php echo $process_status ?>" data-toggle="tooltip" data-placement="top" title="<?php if ($process["alert"]) echo $process["alert"]; ?> <?php echo $process['process_name'] ?> (Mem: <?php echo $process['used_gpu_memory'] ?> Mo) / Started: <?php echo $process['time'] ?>"><?php echo $process["pid"].'@<span class="user">'.$process["user"] ?></span> (<?php echo $process["usage"] ?>%)</span>
                <?php } ?>
                </span>
                <span class="visible-xs-inline">
                    <a type="button" tabindex="0" role="button" class="btn btn-default btn-xs btn-process"><i class="glyphicon glyphicon-triangle-bottom"></i></a>
                </div>
            </td>
        </tr>
    <?php } ?>
    </table>
    <?php

    foreach ($gpus as $gpu) {
        // list users on the GPU, count them once, add them to the stats counter
        $users = array();
        foreach ($gpu["processes"] as $process)
            $users[] = $process["user"];
        $users = array_unique($users);
        foreach($users as $user)
            $STATS->add($user, "used");
    }
}

$STATS->ema_step();
$STATS->sort();

?>
<h2>Current usage statistics <small>a.k.a. who to ask for GPUs</small></h2>
<table class="table table-striped table-condensed" style="width: auto; margin: 0; text-align: center">
    <tr><th>#</th><th>User</th><th>Reserved</th><th>Used</th>
    <th><abbr title="Exponential Moving Average, period 2h">EMA 2h</abbr></th>
    <th><abbr title="Exponential Moving Average, period 24h">EMA 24h</abbr></th>
    <th><abbr title="Exponential Moving Average, period 1 week">EMA 1w</abbr></th>
    </tr>
<?php

function get_color($value) {
    if ($value <= 0.95) return "active";
    if ($value <= 2.01) return "success";
    if ($value <= 5.01) return "warning";
    return "danger";
}
$i = 1;
foreach ($STATS->data as $user => $usage) { ?>
    <tr>
        <td><?php echo $i++; ?></td>
        <td><?php echo $user; ?></td>
        <td class="<?php echo get_color($usage["resa"]); ?>"><?php echo $usage["resa"]; ?></td>
        <td class="<?php echo get_color($usage["used"]); ?>"><?php echo $usage["used"]; ?></td>
        <?php foreach ($usage["emas"] as $val) { ?>
        <td class="text-right <?php echo get_color($val); ?>"><?php echo sprintf("%.1f", $val); ?>
            <?php if ($val > $usage["used"] + 0.1) { ?>&nbsp;<i class="far fa-angle-down text-success"></i><?php }
              elseif ($val < $usage["used"] - 0.1) { ?>&nbsp;<i class="far fa-angle-up text-danger"></i><?php }
              else { ?>&nbsp;<i class="fal fa-equals" style="opacity: 0.2"></i><?php } ?>
        </td>
        <?php } ?>
    </tr>
<?php } ?>
</table>

    <div id="popover-content" style="display:none">
        <form class="comment-form" method="POST" url="?">
            <input type="hidden" name="id" class="form-id">
            <input type="hidden" name="host" class="form-host">
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fas fa-user"></i></div>
                    <input type="text" name="name" class="form-name form-control" placeholder="Name">
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fas fa-calendar-alt"></i></div>
                    <input type="text" name="date" class="form-date form-control" placeholder="Date">
                </div>
                <small>Format: date (YYYY-MM-DD HH:MM) or duration (<i>x</i>d <i>y</i>h)</small>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fas fa-comment"></i></div>
                    <input type="text" name="comment" class="form-comment form-control" placeholder="Comment (optional)">
                </div>
            </div>
            <button type="submit" name="save" class="btn btn-success btn-sm">Save</button>
            <button type="submit" name="reset" class="btn btn-danger btn-sm">Remove</button>
        </form>
    </div>

<?php 
$cached = fopen("content.html", 'w');
fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush();
}
if (!isset($_GET["content"])) { ?>

    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script type="text/javascript">
    function preparePage () {
        $('[data-toggle="tooltip"]').tooltip();

        $('.btn-process').popover({
            placement: 'top',
            container: 'body',
            html: true,
            //selector: '[rel="popover"]', //Sepcify the selector here
            content: function () {
                var data = $(this).parent().parent().find(".process-content").html();
                if (data.trim() == "") data = "<small>No&nbsp;process</small>"
                return data;
            },
            trigger: "focus"
        });

        $('.comment-btn').popover({
            html : true,
            container: 'body',
            placement: 'top',
            title: "",
            //trigger: "focus",
            content: function() {
                var td = $(this).parent();
                $(".form-name").attr("value", td.data("name"));
                $(".form-date").attr("value", td.data("date"));
                $(".form-comment").attr("value", td.data("comment"));
                $(".form-id").attr("value", td.data("id"));
                $(".form-host").attr("value", td.data("host"));
                return $("#popover-content").html();
            }
        });

        $('.btn-disk').click(function () {
            $(this).parent().parent().find(".disk-content").toggleClass("hidden");
        });
    }

    $(preparePage);

    window.setInterval(function() {
        if ($('div.popover').length == 0) {
            $.get("?content", {}, function (data) {
                $('.container').empty().html(data);
                preparePage();
            })
        }
    }, 30000);
    </script>
  </body>
</html>

<?php } ?>
