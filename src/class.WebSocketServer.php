<?php

class WebSocketServer
{
    const GUID_PROT  = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    const EVENT_CLIENT_JOIN = 'client_join';

    const EVENT_CLIENT_LEAVE = 'client_leave';

    const EVENT_CLIENT_GROUP_JOIN = 'group_join';

    const EVENT_CLIENT_GROUP_LEAVE = 'group_leave';

    const EVENT_ACCEPTED = 'client_accepted';

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

    /**
     * @var WebSocketClient[]
     */
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

        $this->client_sockets = [new WebSocketClient("WebSocketServer", $this->main_socket)];

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
                    $newClient = $this->acceptClient();
                    $this->callHandler($this->clientConnectionHandler, [new WebSocketMessage(self::EVENT_CLIENT_JOIN, $newClient->id)]);
                }
                else{
                    $bytes = @socket_recv($socket,$buffer,2048,0);
                    if($bytes == 0){
                        socket_close($socket);
                        unset($this->client_sockets[$index]);
                        $this->debug('disconnected '.$socketClient->id.' '.socket_last_error($socket).' '.count($this->client_sockets));
                        $this->callHandler($this->clientDisconnectionHandler, [new WebSocketMessage(self::EVENT_CLIENT_LEAVE)]);
                    }else{
                        $socketMessage = WebSocketMessage::read($buffer);
                        $socketMessage->client = $socketClient;
                        switch($socketMessage->event){
                            case self::EVENT_CLIENT_GROUP_JOIN:
                                $socketClient->join($socketMessage->payload);
                                break;
                            case self::EVENT_CLIENT_GROUP_LEAVE:
                                $socketClient->leave($socketMessage->payload);
                                break;
                        }
                        $this->debug($socketClient->id.' :: '.$socketMessage->event.' '.$socketMessage->payload);
                        $this->callHandler($this->messageHandler, [$socketMessage]);
                    }
                }
            }
        }
    }

    public function notifyClients($pPayload, $pEvent, $pClients){
        if(empty($pClients)){
            return false;
        }
        $this->debug('notifying "'.$pPayload.'" to '.count($pClients).' clients');
        $message = WebSocketMessage::write($pPayload, $pEvent);
        $messageLength = strlen($message);
        foreach($pClients as $clientSocket)
        {
            @socket_write($clientSocket->socket, $message, $messageLength);
        }
        return true;
    }

    public function notifyAllClients($pPayload, $pEvent = null) {
        return $this->notifyClients($pPayload, $pEvent, $this->client_sockets);
    }

    public function notifyGroups($pPayload, $pEvent, $pGroups){
        if(empty($pGroups)){
            return false;
        }
        $clients = array_filter($this->client_sockets, function($pClient) use ($pGroups){
            foreach($pGroups as $group){
                if(in_array($group, $pClient->groups)){
                    return true;
                }
            }
            return false;
        });
        return $this->notifyClients($pPayload, $pEvent, $clients);
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

    private function acceptClient(){

        $socket = socket_accept($this->main_socket);
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
        $this->notifyClients($id, self::EVENT_ACCEPTED, [$client]);
        $this->debug("new Client :: ".$id." ".count($this->client_sockets));
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
}