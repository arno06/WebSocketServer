<?php

include_once("../src/class.WebSocketMessage.php");
include_once("../src/class.WebSocketClient.php");
include_once("../src/class.WebSocketServer.php");

const PORT = 3001;

$wss = new WebSocketServer(PORT);

$wss->onMessage(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyAllClients($pMessage->payload, $pMessage->event);
});
$wss->onClientConnection(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyAllClients($pMessage->payload, $pMessage->event);
});
$wss->onClientDisconnection(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyAllClients($pMessage->payload, $pMessage->event);
});


$wss->start();