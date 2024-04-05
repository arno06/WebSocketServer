<?php

include_once("../src/class.WebSocketMessage.php");
include_once("../src/class.WebSocketClient.php");
include_once("../src/class.WebSocketServer.php");

const PORT = 3001;

$wss = new WebSocketServer(PORT);

$wss->onMessage(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyMessage($pMessage);
});
$wss->onClientConnection(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyMessage($pMessage);
});
$wss->onClientDisconnection(function(WebSocketMessage $pMessage) use ($wss){
    $wss->notifyMessage($pMessage);
});


$wss->start();