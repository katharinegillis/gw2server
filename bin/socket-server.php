<?php
/**
 * @file   socket-server.php
 * @author kgillis@levementum.com
 * @date   2/15/14
 * @brief  Starts the socket server.
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';

$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			new Chat()
		)
	),
	44791
);

$server->run();
