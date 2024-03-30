<?php

/**
 * https://datatracker.ietf.org/doc/html/rfc6455
 * https://websockets.spec.whatwg.org//#network-intro
 * https://phppot.com/php/simple-php-chat-using-websocket/
 */

class WebSocketServer
{
    const GUID_PROT  = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private $main_socket;

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
        $this->main_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->main_socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->main_socket, 0, $this->port);
        socket_listen($this->main_socket);

        $this->client_sockets = [new WebSocketClient("main_socket", $this->main_socket)];

        $null = null;
        while(true){
            $newSocketArray = $this->client_sockets;
            $socketsArray = array_map(function($pClient){return $pClient->socket;}, $newSocketArray);
            socket_select($socketsArray, $null, $null, 0, 10);

            foreach($socketsArray as $socket){
                $socketClient = null;
                $index = null;
                foreach($this->client_sockets as $idx=>$s){
                    if($s->socket == $socket){
                        $socketClient = $s;
                        $index = $idx;
                        break;
                    }
                }
                if($socket==$this->main_socket){
                    $this->acceptClient();
                    $this->callHandler($this->clientConnectionHandler, ['new client']);
                }
                else{
                    $bytes = @socket_recv($socket,$buffer,2048,0);
                    if($bytes == 0){
                        $this->debug('disconnecting '.$socketClient->id.' '.count($this->client_sockets));
                        $this->debug(socket_last_error($socket));
                        socket_close($socket);
                        unset($this->client_sockets[$index]);
                        $this->debug('disconnected '.$socketClient->id.' '.count($this->client_sockets));
                        $this->callHandler($this->clientDisconnectionHandler, ['Disconnected client']);
                    }else{
                        $socketMessage = WebSocketMessage::read($buffer);
                        $this->debug('Got a message '.$socketMessage." from ".$socketClient->id.'   '.$buffer);
                        $this->callHandler($this->messageHandler, [$socketMessage]);
                    }
                }
            }
        }
    }

    private function acceptClient(){

        $socket=socket_accept($this->main_socket);
        $header = socket_read($socket, 2048);
        $this->handShake($header, $socket);

        $id = null;
        $in = true;
        while($in){
            $id = WebSocketClient::generateId();
            foreach($this->client_sockets as $client){
                if($id == $client->id){
                    $in = true;
                    continue 2;
                }
            }
            $in = false;
        }

        $client = new WebSocketClient($id, $socket);
        $this->client_sockets[] = $client;
        $this->notifyClients('you are connected', [$client]);
        $this->debug("acceptedClient ".$id." ".count($this->client_sockets));
        return $client;
    }

    private function debug($pData){
        if(is_string($pData)){
            echo $pData;
        }else{
            print_r($pData);
        }
        echo PHP_EOL;
    }

    private function callHandler($pCallable, $pParams){
        if(!is_null($pCallable)){
            call_user_func_array($pCallable, $pParams);
        }
    }

    public function notifyClients($pMessage, $pClients){
        if(empty($pClients)){
            return false;
        }
        $this->debug('notifying "'.$pMessage.'" to '.count($pClients).' clients');
        $message = WebSocketMessage::write($pMessage);
        $messageLength = strlen($message);
        foreach($pClients as $clientSocket)
        {
            @socket_write($clientSocket->socket, $message, $messageLength);
        }
        return true;
    }

    public function notifyAllClients($pMessage) {
        return $this->notifyClients($pMessage, $this->client_sockets);
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

class WebSocketClient
{
    public $id = null;
    public $socket = null;
    public $groups = [];

    public function __construct($pId, $pSocket){
        $this->id = $pId;
        $this->socket = $pSocket;
    }

    static public function generateId(){
        $chars = array();
        $chars = array_merge($chars, range("a", "z"));
        $chars = array_merge($chars, range("A", "Z"));
        $chars = array_merge($chars, range(0, 9));
        $maxChars = count($chars);
        $string = "";
        for($i = 0;$i<16;$i++)
            $string .= $chars[rand(0, $maxChars-1)];
        return $string;
    }
}

class WebSocketMessage
{

    static public function read($pRawMessage){

        $length = ord($pRawMessage[1]) & 127;
        if($length == 126) {
            $masks = substr($pRawMessage, 4, 4);
            $data = substr($pRawMessage, 8);
        }
        elseif($length == 127) {
            $masks = substr($pRawMessage, 10, 4);
            $data = substr($pRawMessage, 14);
        }
        else {
            $masks = substr($pRawMessage, 2, 4);
            $data = substr($pRawMessage, 6);
        }
        $socketData = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $socketData .= $data[$i] ^ $masks[$i%4];
        }
        return mb_convert_encoding($socketData, 'utf-8');
    }

    static public function write($pMessage){
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($pMessage);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$pMessage;
    }
}