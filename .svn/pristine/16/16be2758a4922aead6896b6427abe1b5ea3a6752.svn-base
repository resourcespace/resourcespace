<?php

include "../../include/boot.php";
if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
}
include "../../include/header.php";

$functions = array();
$content_code = "";
$content_tests = "";
$test_coverage_count = 0;     // number of unique functions called by our tests

$rdi = new RecursiveDirectoryIterator(__DIR__ . '/../..');
foreach (new RecursiveIteratorIterator($rdi) as $filename => $data) {
    if (!preg_match('/.php$/', $filename)) {
        continue;
    }
    if (!preg_match('/\W(lib|plugins|tests)\W/', $filename)) {
        $content = file_get_contents($filename);
        $content_code .= PHP_EOL . $content;
        preg_match_all('/function\s+?([a-z_\-]+)\s*?\(/i', $content, $matches);
        if (isset($matches[1][0])) {
            foreach ($matches[1] as $match) {
                $functions[$match] = array(
                    'filename' => $filename,
                    'file' => basename($filename, '.php'),
                    'function' => $match,
                    'count_code' => 0,
                    'count_tests' => 0
                );
            }
        }
    } elseif (preg_match('/\Wtests\W/', $filename)) {
        $content_tests .= PHP_EOL . file_get_contents($filename);
    }
}

foreach ($functions as $function => $attributes) {
    preg_match_all('/[\s\W]' .  $function . '\s*?\(/i', $content_code, $matches);
    $match_count = count($matches[0]);
    $functions[$function]['count_code'] = $match_count;

    preg_match_all('/[\s\W]' .  $function . '\s*?\(/i', $content_tests, $matches);
    $match_count = count($matches[0]);
    $functions[$function]['count_tests'] = $match_count;

    if ($match_count > 0) {
        $test_coverage_count++;
    }
}

usort($functions, function ($a, $b) {
    return $b['count_code'] - $a['count_code'];
});       // sort by count of code call descending

?>
<h1><?php echo round(($test_coverage_count / count($functions)) * 100, 3); ?>% test coverage</h1>
<p>(excluding plugins/ and lib/)</p>
<table class="ListviewStyle">
    <tbody>
        <tr class="ListviewTitleStyle">
            <th></th>
            <th>Function</th>
            <th>Code calls<span class="DESC"></span></th>
            <th>Test calls</th>
        </tr>
        <?php for ($i = 0; $i < count($functions); $i++) { ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td>
                    <span style="font-family: monospace;">
                        <span style="color: blue;"><?php echo $functions[$i]['file']; ?></span>.<span style="color: green;"><?php echo $functions[$i]['function']; ?>()</span>
                    </span>
                </td>
                <td><?php echo $functions[$i]['count_code']; ?></td>
                <td><?php echo $functions[$i]['count_tests']; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php
include "../../include/footer.php";
