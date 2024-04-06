# WebSocketServer

PHP implementation of a web socket server. No dependency required. 

## Running sample

Starting standalone server :

```cli
php -q samples/server-chat.php
```

`samples/simple_chat.html` has to be served by a webserver (IE `Apache` or `Nginx`)

## Code

### class.WebSocketServer.php
 * Main loop is ran within the `start` method. 
 * Responsible for client handling : joining, leaving & receiving, sending messages 

### class.WebSocketMessage.php
 * Simple message implementation
 * Exchanges between Server and client are made through JSON containing 3 props : `event`, `payload` and `groups`
   * `event` must be a string (some are reserved, see @WebSocketServer constants)
   * `payload` could be a string, an array or an object
   * `groups` array of strings, represents targeted groups. If empty, the message should be sent to every client.
 * Class is responsible for packing (`write`), unpacking (`read`) messages

### class.WebSocketClient.php
 * A Client constists of
   * an ID (16 char string generated when the client is accepted)
   * a socket
   * groups