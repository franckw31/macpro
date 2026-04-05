<?php
/**
 * Simple SMTP Mock Server (Sink)
 * This script listens on port 1025 and logs all incoming SMTP traffic.
 * Run this in a terminal: php smtp_mock.php
 */

set_time_limit(0);
$host = '127.0.0.1';
$port = 1025;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket) {
    die("Could not create socket\n");
}

if (!socket_bind($socket, $host, $port)) {
    die("Could not bind to socket on $host:$port\n");
}

socket_listen($socket);

echo "SMTP Mock Server started on $host:$port\n";
echo "Press Ctrl+C to stop.\n\n";

while (true) {
    $client = socket_accept($socket);
    socket_write($client, "220 localhost SMTP Mock Server Ready\r\n");
    
    echo "--- New Connection ---\n";
    
    while ($data = socket_read($client, 1024)) {
        $line = trim($data);
        echo "> $line\n";
        
        if (preg_match('/^QUIT/i', $line)) {
            socket_write($client, "221 Bye\r\n");
            break;
        } elseif (preg_match('/^HELO|^EHLO/i', $line)) {
            socket_write($client, "250-localhost\r\n250-SIZE 10485760\r\n250-AUTH LOGIN PLAIN\r\n250 OK\r\n");
        } elseif (preg_match('/^MAIL FROM/i', $line)) {
            socket_write($client, "250 OK\r\n");
        } elseif (preg_match('/^RCPT TO/i', $line)) {
            socket_write($client, "250 OK\r\n");
        } elseif (preg_match('/^DATA/i', $line)) {
            socket_write($client, "354 End data with <CR><LF>.<CR><LF>\r\n");
            // Read until "."
            $emailContent = "";
            while ($content = socket_read($client, 1024)) {
                $emailContent .= $content;
                if (strpos($emailContent, "\r\n.\r\n") !== false) break;
            }
            echo "Email Content Received.\n";
            socket_write($client, "250 OK: queued as 12345\r\n");
        } elseif (preg_match('/^AUTH/i', $line)) {
            socket_write($client, "235 Authentication successful\r\n");
        } else {
            socket_write($client, "250 OK\r\n");
        }
    }
    socket_close($client);
    echo "--- Connection Closed ---\n\n";
}
