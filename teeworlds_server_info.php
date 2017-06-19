<?php
/*
Acquire server info from teeworlds servers

Copyright (C) 2017 Tim Schumacher <tim@timakro.de>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace TwServerInfo;

function get_random($length)
{
    $urandom = fopen("/dev/urandom", 'r');
    return fread($urandom, $length);
}


function send_packet($sock, $data, &$server)
{
    socket_sendto($sock, $data, strlen($data), 0, $server['ip'], $server['port']);
}


function send_getinfo($sock, &$server)
{
    $server['_token'] = get_random(1);
    $server['_extratoken'] = get_random(2);
    $server['response'] = false;

    // vanilla, ext
    $data = "xe" . $server['_extratoken'] . "xD" . "\xff\xff\xff\xffgie3" . $server['_token'];
    send_packet($sock, $data, $server);

    // legacy64
    $data = "tezcan" . "\xff\xff\xff\xfffstd" . $server['_token'];
    send_packet($sock, $data, $server);
}


function unpack_int(&$slots)
{
    $src = $slots[0];

    if ($src === '') {
        array_shift($slots);
        return 0;
    }

    $offset = 0;

    $byte = ord($src[$offset]);
    $sign = ($byte >> 6) & 0x01;
    $value = $byte & 0x3f;
    while (true) {
        if (!($byte & 0x80)) {
            break;
        }
        $offset += 1;
        $byte = ord($src[$offset]);
        $value |= ($byte & 0x7f) << ($offset*7 - 1);
        if ($offset === 4) {
            break;
        }
    }

    $slots[0] = substr($src, $offset + 1);
    if ($sign) {
        $value = -$value;
    }
    return $value;
}


function parse_info($type, $slots, $initclients, &$server)
{
    if ($type !== 'extmore') {
        $server['version'] = strval(array_shift($slots));
        $server['name'] = strval(array_shift($slots));
        $server['map'] = strval(array_shift($slots));

        if ($type === 'ext') {
            $server['mapcrc'] = intval(array_shift($slots));
            $server['mapsize'] = intval(array_shift($slots));
        }

        $server['gametype'] = strval(array_shift($slots));
        $server['flags'] = intval(array_shift($slots));
        $server['numplayers'] = intval(array_shift($slots));
        $server['maxplayers'] = intval(array_shift($slots));
        $server['numclients'] = intval(array_shift($slots));
        $server['maxclients'] = intval(array_shift($slots));
    }

    if ($initclients) {
        $server['clients'] = [];
        $server['_clientcount'] = 0;
    }

    $clientnum = 0;
    if ($type === '64legacy') {
        $clientnum = unpack_int($slots);
    }

    $packetnum = 0;
    if ($type === 'extmore') {
        $packetnum = intval(array_shift($slots));
    }

    if ($server['type'] === 'ext') {
        array_shift($slots);

        if (!array_key_exists('_clientpackets', $server)) {
            $server['_clientpackets'] = [];
        }
        if (!in_array($packetnum, $server['_clientpackets'])) {
            array_push($server['_clientpackets'], $packetnum);
        }
        else {
            return;
        }
    }

    while (true) {
        if (count($slots) === 0) {
            return;
        }

        if ($type === 'vanilla' && $server['_clientcount'] === 16) {
            return;
        }
        if ($server['_clientcount'] === 64) {
            return;
        }

        $addclient = true;
        if ($type === '64legacy') {
            if (!array_key_exists('_clientnumbers', $server)) {
                $server['_clientnumbers'] = [];
            }
            if (!in_array($clientnum, $server['_clientnumbers'])) {
                array_push($server['_clientnumbers'], $clientnum);
            }
            else {
                $addclient = false;
            }
        }

        $client = [];
        $client['name'] = strval(array_shift($slots));
        $client['clan'] = strval(array_shift($slots));
        $client['country'] = intval(array_shift($slots));
        $client['score'] = intval(array_shift($slots));
        $client['player'] = intval(array_shift($slots));

        if ($server['type'] === 'ext') {
            array_shift($slots);
        }

        if ($addclient) {
            array_push($server['clients'], $client);
            $server['_clientcount'] += 1;
        }

        $clientnum += 1;
    }
}


function process_packet($data, &$server)
{
    if (substr($data, 6, 8) === "\xff\xff\xff\xffinf3") {
        $type = 'vanilla';
    }
    elseif (substr($data, 6, 8) === "\xff\xff\xff\xffdtsf") {
        $type = '64legacy';
    }
    elseif (substr($data, 6, 8) === "\xff\xff\xff\xffiext") {
        $type = 'ext';
    }
    elseif (substr($data, 6, 8) === "\xff\xff\xff\xffiex+") {
        $type = 'extmore';
    }
    else {
        return;
    }
    if ($type === 'extmore') {
        $stype = 'ext';
    }
    else {
        $stype = $type;
    }

    $slots = explode("\x00", substr($data, 14, strlen($data)-15));

    $token = intval(array_shift($slots));
    if (($token & 0xff) !== ord($server['_token'])) {
        return;
    }
    if ($stype === 'ext') {
        $extratoken = (ord($server['_extratoken'][0]) << 8) + ord($server['_extratoken'][1]);
        if (($token & 0xffff00) >> 8 !== $extratoken) {
            return;
        }
    }

    $server['response'] = true;

    $initclients = false;
    if (!array_key_exists('type', $server)) {
        $server['type'] = $stype;
        $initclients = true;
    }
    elseif ($server['type'] === 'vanilla') {
        if ($stype === '64legacy' || $stype === 'ext') {
            $server['type'] = $stype;
            $initclients = true;
        }
    }
    elseif ($server['type'] === '64legacy') {
        if ($stype === 'vanilla') {
            return;
        }
        elseif ($stype === 'ext') {
            $server['type'] = $stype;
            $initclients = true;
        }
    }
    elseif ($server['type'] === 'ext') {
        if ($stype === 'vanilla' || $stype === '64legacy') {
            return;
        }
    }

    parse_info($type, $slots, $initclients, $server);
}


function recieve_packet($sock, &$servers)
{
    $data = '';
    $ip = '';
    $port = 0;
    if (!socket_recvfrom($sock, $data, 1400, 0, $ip, $port)) {
        return false;
    }

    foreach ($servers as &$server) {
        if ($server['ip'] === $ip && $server['port'] === $port) {
            process_packet($data, $server);
        }
    }

    return true;
}


function fill_server_info(&$servers)
{
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_set_nonblock($sock);

    foreach ($servers as &$server) {
        send_getinfo($sock, $server);
    }

    // expect answer after 50ms:
    // - ping between timakro.de and unique-clan.tk takes 10ms
    // - a teeworlds server tick takes 20ms
    usleep(50 * 1000);

    while (true) {
        if (!recieve_packet($sock, $servers)) {
            break;
        }
    }
}

?>
