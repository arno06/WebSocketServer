<?php

/**
 * https://datatracker.ietf.org/doc/html/rfc6455
 * https://websockets.spec.whatwg.org//#network-intro
 * https://phppot.com/php/simple-php-chat-using-websocket/
 */

class WebSocketServer
{
    const GUID_PROT  = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private $socket;

    private $host;

    private $port;

    /**
     * @var callable
     */
    private $messageHandler = null;

    /**
     * @var callable
     */
    private $clientConnectionHandler = null;

    /**
     * @var callable
     */
    private $clientDisconnectionHandler = null;

    private $client_sockets = [];

    public function __construct($pHost = 'localhost', $pPort = 3001)
    {
        $this->port = $pPort;
        $this->host = $pHost;
    }

    public function start(){
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, 0, $this->port);
        socket_listen($this->socket);

        $this->client_sockets = [$this->socket];

        $null = null;
        while(true){

            $newSocketArray = $this->client_sockets;
            socket_select($newSocketArray, $null, $null, 0, 10);

            if (in_array($this->socket, $newSocketArray)) {
                $newSocket = socket_accept($this->socket);
                $this->client_sockets[] = $newSocket;

                $header = socket_read($newSocket, 2048);
                $this->handShake($header, $newSocket);

                socket_getpeername($newSocket, $client_ip_address);
                $this->callHandler($this->clientConnectionHandler, ['new client']);

                $newSocketIndex = array_search($this->socket, $newSocketArray);
                unset($newSocketArray[$newSocketIndex]);
            }

            foreach ($newSocketArray as $newSocketArrayResource) {
                while(socket_recv($newSocketArrayResource, $socketData, 2048, 0) >= 1){
                    $socketMessage = $this->unseal($socketData);
                    $this->callHandler($this->messageHandler, [$socketMessage]);
                    break 2;
                }

                /**
                 * Déconnexion ?
                 */
                $socketData = @socket_read($newSocketArrayResource, 2048, PHP_NORMAL_READ);
                if ($socketData === false) {
                    socket_getpeername($newSocketArrayResource, $client_ip_address);
                    $this->callHandler($this->clientDisconnectionHandler, ['client leaved']);
                    $newSocketIndex = array_search($newSocketArrayResource, $this->client_sockets);
                    unset($this->client_sockets[$newSocketIndex]);
                }
            }
        }

        socket_close($this->socket);
    }

    private function callHandler($pCallable, $pParams){
        if(!is_null($pCallable)){
            call_user_func_array($pCallable, $pParams);
        }
    }

    public function notifyClients($message) {
        $message = $this->seal($message);
        $messageLength = strlen($message);
        foreach($this->client_sockets as $clientSocket)
        {
            @socket_write($clientSocket,$message,$messageLength);
        }
        return true;
    }

    private function handShake($received_header,$client_socket_resource){

        $headers = array();
        $lines = preg_split("/\r\n/", $received_header);
        foreach($lines as $line)
        {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
            {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . self::GUID_PROT)));
        $buffer  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->host\r\n" .
            "WebSocket-Location: ws://$this->host:$this->port/\r\n".
            "Sec-WebSocket-Accept: $secAccept\r\n\r\n";
        socket_write($client_socket_resource,$buffer,strlen($buffer));
    }

    private function unseal($socketData) {
        $length = ord($socketData[1]) & 127;
        if($length == 126) {
            $masks = substr($socketData, 4, 4);
            $data = substr($socketData, 8);
        }
        elseif($length == 127) {
            $masks = substr($socketData, 10, 4);
            $data = substr($socketData, 14);
        }
        else {
            $masks = substr($socketData, 2, 4);
            $data = substr($socketData, 6);
        }
        $socketData = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $socketData .= $data[$i] ^ $masks[$i%4];
        }
        return $socketData;
    }

    private function seal($socketData) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($socketData);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$socketData;
    }

    public function onMessage($pCallBack){
        if(is_callable($pCallBack)){
            $this->messageHandler = $pCallBack;
        }
    }

    public function onClientConnection($pCallBack){
        if(is_callable($pCallBack)){
            $this->clientConnectionHandler = $pCallBack;
        }
    }

    public function onClientDisconnection($pCallBack){
        if(is_callable($pCallBack)){
            $this->clientDisconnectionHandler = $pCallBack;
        }
    }
}