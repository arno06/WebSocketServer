<?php

include_once("../src/class.WebSocketServer.php");

const PORT = 3001;

$wss = new WebSocketServer(PORT);

$wss->onMessage(function($pMessage) use ($wss){
    $wss->notifyClients('Got a message '.$pMessage);
});

$wss->start();