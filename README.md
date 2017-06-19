# teeworlds\_server\_info.php
Acquire server info from teeworlds servers

## Overview
Retrieves the complete server info, converts it to PHP types and stores it
into an array. The script supports teeworlds version 0.6 servers including
the legacy and extended server info protocol for up to 64 players.

The relevant data retrieved might look like the following:

| key        | value        |
|------------|--------------|
| response   | 1            |
| version    | 0.6.4        |
| name       | My DM Server |
| map        | dm1          |
| gametype   | DM           |
| flags      | 0            |
| numplayers | 2            |
| maxplayers | 16           |
| numclients | 3            |
| maxclients | 16           |

Each player gets his own array:

| key     | value   |
|---------|---------|
| name    | timakro |
| clan    | υηιqυє  |
| country | 276     |
| score   | 19      |
| player  | 1       |

## Usage
Download teeworlds\_server\_info.php and include it. Create an array with the
ips and ports of the servers to request, note that domain names don't work.
The array is passed by reference, the function will set the acquired
keys on the server arrays. You may also define more keys you want to use
later, only specific ones are overwritten by the function.

    $servers = [
        ['ip' => '95.172.92.151', 'port' => 8303],
        ['ip' => '212.224.75.126', 'port' => 9911],
    ];
    \TwServerInfo\fill_server_info($servers);

After the function returns each server array contains the `response` key. It
is true if we got a response from the server. In that case this server array
is also guaranteed to contain all of the keys mentioned in the table above,
as well as a `clients` key which stores an indexed array of client arrays with
the keys shown above.

A full working example can be found in the demo.php file.

## License
[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html)
