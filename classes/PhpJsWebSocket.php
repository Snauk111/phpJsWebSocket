<?php

class PhpJsWebSocket
{
    public function sendHeaders($headersText, $newSocket, $host, $port)
    {
        $headers = [];
        $tmpLine = preg_split("/\r\n/", $headersText);
        foreach ($tmpLine as $line) {
            $line = rtrim($line);
            if (preg_match("/\A(\S+): (.*)\z/", $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $key = $headers['Sec-WebSocket-Key'];
        $sKey = base64_encode(pack("H*", sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));

        $strHeadr = "HTTP/1.1 101 Switching Protocols \r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $host\r\n" .
            "WebSocket-Location: ws://$host:$port/phpjswebsocket/server.php\r\n" .
            "Sec-WebSocket-Accept:$sKey\r\n\r\n";

        socket_write($newSocket, $strHeadr, strlen($strHeadr));
    }

    public function newConnectionPJWS($clientIpAddress)
    {
        $message = "New client $clientIpAddress connected";
        $messageArray = [
            "message" => $message,
            "type" => "newConnectionPJWS"
        ];
        $pjws = $this->frame(json_encode($messageArray));

        return $pjws;
    }

    protected function frame($socketData)
    {
        $fourBits = 0x81; //text frame
        $length = strlen($socketData);
        $header = "";

        if ($length <= 125) {
            $header = pack("CC", $fourBits, $length); //CC 7 bit
        } else if ($length > 125 && $length < 65536) {
            $header = pack("CCn", $fourBits, 126, $length); //CCn 7+16 bits
        } else if ($length > 655356) {
            $header = pack("CCNN", $fourBits, 127, $length); // CCNN 7+64 bits
        }

        return $header . $socketData;
    }

    public function unFrame($socketBuf)
    {
        $length = ord($socketBuf[1]) & 127;
        if ($length == 126) {
            $mask = substr($socketBuf, 4,4);
            $data = substr($socketBuf, 8);
        } else if ($length == 127) {
            $mask = substr($socketBuf, 10,4);
            $data = substr($socketBuf, 14);
        } else {
            $mask = substr($socketBuf, 2,4);
            $data = substr($socketBuf, 6);
        }

        $socketString = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $socketString .= $data[$i] ^ $mask[$i%4];
        }

        return $socketString;
    }

    public function createServerMessage($clientName, $clientMessage)
    {
        $message = "$clientName - $clientMessage";
        $messageArray = [
            "type" => "pjws-box",
            "message" => $message
        ];

        return $this->frame(json_encode($messageArray));
    }

    public function send($message, $clientArray)
    {
        $messageLength = strlen($message);
        foreach ($clientArray as $client) {
            @socket_write($client, $message, $messageLength);
        }

        return true;
    }

    public function newDisconnectedPJWS($clientIpAddress)
    {
        $message = "Client $clientIpAddress disconnected";
        $messageArray = [
            "message" => $message,
            "type" => "newDisconnectPJWS"
        ];
        $pjws = $this->frame(json_encode($messageArray));

        return $pjws;
    }
}