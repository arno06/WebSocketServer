<?php

class WebSocketClient
{
    public $id = null;
    public $socket = null;
    public $groups = [];

    public function __construct($pId, $pSocket){
        $this->id = $pId;
        $this->socket = $pSocket;
    }

    public function join($pGroup){
        if(in_array($pGroup, $this->groups)){
            return false;
        }
        $this->groups[] = $pGroup;
        return true;
    }

    public function leave($pGroup){
        if(!in_array($pGroup, $this->groups)){
            return false;
        }
        $index = array_search($pGroup, $this->groups);
        array_splice($this->groups, $index, 1);
        return true;
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