<?php

include_once("../src/class.WebSocketServer.php");

const PORT = 3001;

$wss = new WebSocketServer(PORT);

$wss->onMessage(function($pMessage) use ($wss){
    $wss->notifyAllClients('Got a message '.$pMessage);
});
$wss->onClientConnection(function($pMessage) use ($wss){
    $wss->notifyAllClients('new Client '.$pMessage);
});
$wss->onClientDisconnection(function($pMessage) use ($wss){
    $wss->notifyAllClients('Client left');
});


$wss->start();