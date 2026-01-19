<?php
declare(strict_types=1);

const ANNOUNCE_INTERVAL = 1800; // seconds
const PEER_TTL = 5400;          // reap after 90 min
const MAX_PEERS = 50;

function fail(string $msg): never {
    echo bencode(['failure reason' => $msg]);
    exit;
}

function bencode(mixed $v): string {
    if (is_int($v)) return 'i' . $v . 'e';
    if (is_string($v)) return strlen($v) . ':' . $v;
    if (is_array($v)) {
        $isList = array_keys($v) === range(0, count($v) - 1);
        $out = $isList ? 'l' : 'd';
        foreach ($v as $k => $val) {
            if (!$isList) $out .= bencode((string)$k);
            $out .= bencode($val);
        }
        return $out . 'e';
    }
    return '';
}

function getBinary(string $s, int $len): string {
    $raw = rawurldecode($s);
    if (strlen($raw) !== $len) fail('invalid parameter');
    return $raw;
}

$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
if ($path === '') fail('missing passkey');
$passkey = $path;

$infoHash = getBinary($_GET['info_hash'] ?? '', 20);
$peerId   = getBinary($_GET['peer_id']   ?? '', 20);
$port     = (int)($_GET['port'] ?? 0);
$left     = (int)($_GET['left'] ?? -1);

if ($port < 1 || $port > 65535 || $left < 0) {
    fail('bad request');
}

$isSeed = ($left === 0);
$ip = inet_pton($_SERVER['REMOTE_ADDR']);
$now = time();


$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=tracker;charset=utf8mb4',
    'tracker',
    'trackerpass',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);


$u = $pdo->prepare('SELECT id FROM users WHERE passkey=? AND status="active"');
$u->execute([$passkey]);
$user = $u->fetch();
if (!$user) fail('unauthorized');


$t = $pdo->prepare('SELECT id FROM torrents WHERE info_hash=?');
$t->execute([$infoHash]);
$torrent = $t->fetch();
if (!$torrent) fail('unknown torrent');


$pdo->prepare(
    'REPLACE INTO peers
     (torrent_id,user_id,peer_id,ip,port,is_seed,last_seen)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $torrent['id'],
    $user['id'],
    $peerId,
    $ip,
    $port,
    $isSeed ? 1 : 0,
    $now
]);


$pdo->prepare(
    'DELETE FROM peers WHERE last_seen < ?'
)->execute([$now - PEER_TTL]);


$p = $pdo->prepare(
    'SELECT ip, port FROM peers
     WHERE torrent_id = ?
       AND NOT (user_id = ? AND peer_id = ?)
     LIMIT ' . MAX_PEERS
);
$p->execute([$torrent['id'], $user['id'], $peerId]);

$peers = '';
foreach ($p as $row) {
    $peers .= $row['ip'] . pack('n', (int)$row['port']);
}


echo bencode([
    'interval' => ANNOUNCE_INTERVAL,
    'peers'    => $peers
]);
