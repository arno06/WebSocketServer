<?php

class WebSocketMessage
{
    public $event;

    public $payload;

    public $client;

    public $groups;

    public function __construct($pEvent = "", $pPayload = "", $pGroups = []){
        $this->event = $pEvent;
        $this->payload = $pPayload;
        $this->groups = $pGroups;
    }

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
        $stringJson = json_decode(mb_convert_encoding($socketData, 'utf-8'), true);
        if(!$stringJson){
            return null;
        }
        return new WebSocketMessage($stringJson['event'], $stringJson['payload'], $stringJson['groups']);
    }

    static public function write($pPayload, $pEvent = ""){
        $data = json_encode(array('payload'=>$pPayload, 'event'=>$pEvent));
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($data);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$data;
    }
}