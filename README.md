# WebSocketServer

WIP

## Running sample

Starting standalone server :

```cli
php -q samples/server-chat.php
```

`samples/index.html` has to be server by a webserver (IE `Apache` or `Nginx`)

## Todo

 * Groups (events for joining and leaving)

## Code

### class.WebSocketServer.php
 * Main loop is ran within the `start` method. 
 * Responsible for client handling : joining, leaving & receiving, sending messages 

### class.WebSocketMessage.php
 * Simple message implementation
 * Exchanges between Server and client are made through JSON containing 2 props : `event` and `payload`
   * `event` must be a string (some are reserved, see @WebSocketServer constants)
   * `payload` could be a string, an array or an object
 * Class is responsible for packing (`write`), unpacking (`read`) messages

### class.WebSocketClient.php
 * A Client constists of
   * an ID (16 char string generated when the client is accepted)
   * a socket
   * groups