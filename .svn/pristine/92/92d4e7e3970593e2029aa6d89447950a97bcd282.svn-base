<?php
include '../../include/boot.php';
include '../../include/authenticate.php';

if (!checkperm('a')) {
    exit('Permission denied.');
}

include "../../include/header.php";

# Baseline metrics (reported metrics are a percentage of these)
# Based on an Intel NUC with a quad core Intel Core i5-4250U CPU @ 1.30GHz, 8GB RAM and a Samsung SSD hard disk (Dan's desktop PC)
$mysql_baseline = 85;
$cpu_baseline = 970410;
$disk_baseline = 20558;
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["system_performance"]); ?></h1>
    <?php
    renderBreadcrumbs([
        ['title' => $lang["systemsetup"], 'href'  => $baseurl_short . "pages/admin/admin_home.php", 'menu' => true],
        ['title' => $lang["system_performance"]]
    ]);
    ?>

    <p>
        <a href="admin_system_performance.php?reload=<?php echo time() ?>" onClick="return CentralSpaceLoad(this,false);">
            <?php echo '<i aria-hidden="true" class="icon-refresh-cw"></i>&nbsp;' . escape($lang["reload"]); ?>
        </a>
    </p>

    <?php
    # Database read/write speed
    ps_query("drop table if exists performance_test");
    ps_query("create table performance_test (c int(11),d char(64))");
    $timer = microtime(true);
    $counter = 0;

    while (microtime(true) < ($timer + 1)) { // Run for one second
        $d = md5(microtime());
        ps_query("insert into performance_test(c,d) values (?,?)", array("i",$counter,"s",$d));
        ps_query("select c,d from performance_test where c=?", array("i",$counter));
        $counter++;
    }

    ps_query("drop table if exists performance_test");
    ?>

    <div class="Question">
        <label><?php echo escape($lang["mysql_throughput"]); ?></label>
        <div class="Fixed"><?php echo round(($counter / $mysql_baseline) * 100, 1) ?></div>
        <div class="clearerleft"></div>
    </div>

    <?php
    # CPU speed
    $timer = microtime(true);
    $counter = 0;

    while (microtime(true) < ($timer + 1)) { // Run for one second
        $x = md5(microtime());
        $counter++;
    }
    ?>

    <div class="Question">
        <label><?php echo escape($lang["cpu_benchmark"]); ?></label>
        <div class="Fixed"><?php echo round(($counter / $cpu_baseline) * 100, 1) ?></div>
        <div class="clearerleft"></div>
    </div>

    <?php
    # Disk write test
    $tmp = get_temp_dir();
    $timer = microtime(true);
    $counter = 0;
    $f = fopen($tmp . "/performance_test.txt", "w");

    while (microtime(true) < ($timer + 1)) { // Run for one second
        fwrite($f, str_pad("", 10000, "X"));
        $counter++;
    }

    fclose($f);
    unlink($tmp . "/performance_test.txt");
    ?>

    <div class="Question">
        <label><?php echo escape($lang["disk_write_speed"]); ?></label>
        <div class="Fixed"><?php echo round(($counter / $disk_baseline) * 100, 1) ?></div>
        <div class="clearerleft"></div>
    </div>
</div>

<?php
include "../../include/footer.php";
?>