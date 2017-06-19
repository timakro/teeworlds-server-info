<?php
require_once('teeworlds_server_info.php');

$servers = [
    ['ip' => '95.172.92.151', 'port' => 8303], // DDraceNetwork
    ['ip' => '212.224.75.126', 'port' => 9911], // Utd.Legends
];
?>
<!doctype html>
<html><head><style>
table {
    margin: 2px;
    float: left;
    border-collapse: collapse;
}
table, th, td {
    border: 1px solid black;
}
</style></head><body>
<?php
\TwServerInfo\fill_server_info($servers);
foreach ($servers as $server) {
?>
<table style="float: left;">
<tr><th>key</th><th>value</th></tr>
<?php
    foreach ($server as $key => $value) {
        if ($key[0] === '_') {
            continue;
        }
?>
<tr><td><?php echo(htmlspecialchars($key)); ?></td><td>
<?php
        if ($key !== 'clients') {
            echo(htmlspecialchars(strval($value)));
        }
        else {
            foreach ($value as $client) {
?>
<table>
<tr><th>key</th><th>value</th></tr>
<?php
                foreach ($client as $clkey => $clvalue) {
?>
<tr><td><?php echo(htmlspecialchars($clkey)); ?></td>
<td><?php echo(htmlspecialchars($clvalue)); ?></td></tr>
<?php
                }
?>
</table>
<?php
            }
        }
?>
</td></tr>
<?php
    }
?>
</table>
<?php
}
?>
</body>
