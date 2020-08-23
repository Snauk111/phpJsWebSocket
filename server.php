<?php
define("PORT", "8090");
require_once("classes/PhpJsWebSocket.php");
$phpjswebsocket = new PhpJsWebSocket();
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, 0, PORT);

socket_listen($socket, 0);

$clientArray = [$socket];

while (true) {
    $newSocketArray = $clientArray;
    $null = [];
    socket_select($newSocketArray, $null, $null, 0, 25);
    if (in_array($socket, $newSocketArray)) {
        $newSocket = socket_accept($socket);
        $clientArray[] = $newSocket;
        $header = socket_read($newSocket, 2048);
        $phpjswebsocket->sendHeaders($header, $newSocket, "phpjswebsocket", PORT);

        socket_getpeername($newSocket, $clientIpAddress);

        $connectionPJWS = $phpjswebsocket->newConnectionPJWS($clientIpAddress);

        $phpjswebsocket->send($connectionPJWS, $clientArray);

        $newSocketArrayIndex = array_search($socket, $newSocketArray);
        unset($newSocketArray[$newSocketArrayIndex]);
    }

    foreach ($newSocketArray as $newSocketResource) {
        $bytesocket=@socket_recv($newSocketResource, $socketBuf, 2048, 0);
        while ($bytesocket >= 1) {
            $socketMessage = $phpjswebsocket->unFrame($socketBuf);
            $messageObj = json_decode($socketMessage);
            $serverMessage = $phpjswebsocket->createServerMessage($messageObj->client_user, $messageObj->client_message);

            $phpjswebsocket->send($serverMessage, $clientArray);
            $f = fopen("log/errors.txt", "w+");
            fwrite($f, "Не получилось выполнить socket_recv(); причина: " . socket_strerror(socket_last_error($socket)) . "\n");
            fwrite($f, "Сообщение: $socketMessage");
            fclose($f);
            break 2;
        }
        // Обработка сокетов закрывшие соединение с сервером
        $socketData = @socket_read($newSocketResource, 2048, PHP_NORMAL_READ);
        if ($socketData === false) {
            socket_getpeername($newSocketResource, $clientIpAddress);
            $connectionPJWS = $phpjswebsocket->newDisconnectedPJWS($clientIpAddress);
            $phpjswebsocket->send($connectionPJWS, $clientArray);
            $newSocketArrayIndex = array_search($newSocketResource, $clientArray);
            unset($clientArray[$newSocketArrayIndex]);
        }
    }
}

socket_close($socket);
