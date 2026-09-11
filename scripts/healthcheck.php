<?php
/**
 * Container HEALTHCHECK probe.
 *
 * Uses PHP's own socket support rather than curl, so the image does not need an apt
 * layer just to install a probe tool. fsockopen is unaffected by allow_url_fopen=Off
 * (see docker/php.ini), which would otherwise rule out file_get_contents here.
 *
 * Exit 0 when the app answers with a non-error status, 1 otherwise.
 *
 * Usage: php scripts/healthcheck.php [path]   (default /health)
 */
$path = $argv[1] ?? '/health';
$port = (int)(getenv('PORT') ?: 80);
$host = '127.0.0.1';

$socket = @fsockopen($host, $port, $errno, $errstr, 5);
if (!$socket) {
    fwrite(STDERR, "connect to $host:$port failed: $errstr ($errno)\n");
    exit(1);
}

stream_set_timeout($socket, 5);
fwrite($socket, "GET $path HTTP/1.1\r\n"
    . "Host: $host\r\n"
    . "User-Agent: docker-healthcheck\r\n"
    . "Connection: close\r\n\r\n");

$statusLine = fgets($socket, 256);
fclose($socket);

if ($statusLine === false || !preg_match('#^HTTP/[\d.]+ (\d{3})#', $statusLine, $m)) {
    fwrite(STDERR, "no HTTP status line from $host:$port$path\n");
    exit(1);
}

$code = (int)$m[1];
// 2xx and 3xx both mean "Apache and PHP are alive and routing", which is all a
// liveness probe should assert. health.php only returns 5xx for genuinely fatal state.
if ($code >= 200 && $code < 400) {
    exit(0);
}
fwrite(STDERR, "unhealthy: HTTP $code from $path\n");
exit(1);
