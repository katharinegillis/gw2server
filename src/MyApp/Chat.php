<?php
/**
 * @file   Chat.php
 * @author kgillis@levementum.com
 * @date   2/15/14
 * @brief  Tutorial chat class.
 */

namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
	protected $unknownConnections;
	protected $avatarSources;
	protected $avatarConsumers;
	protected $avatars;
	protected $guids;

	public function __construct() {
        // Set up the various storage variables.
		$this->unknownConnections = new \SplObjectStorage;
		$this->avatarSources = new \SplObjectStorage;
		$this->avatarConsumers = new \SplObjectStorage;

		$this->avatars = array();
		$this->guids = array();
	}

	public function onOpen(ConnectionInterface $conn) {
		// Store the new connection to send messages to later
		$this->unknownConnections->attach($conn);

		echo "New connection! ({$conn->resourceId})\n";
	}

	public function onMessage(ConnectionInterface $from, $msg) {
        // Parse the message sent on the socket.
		$request = json_decode($msg, true);

        // If there is no method, or the message can't be parsed, exit.
		if (!is_array($request) || !isset($request['method'])) {
			echo 'Unparsable message: ' . $msg;
			return;
		}

        // Handle the message based on the method.
		switch ($request['method']) {
			case 'register':
				if ($request['type'] == 'AvatarSource') {
                    // Registers the requesting connection as an avatar source. Any update messages from here will be sent to all avatar consumers.
					$this->avatarSources->attach($from);
					$this->unknownConnections->detach($from);

					$this->guids[$from->resourceId] = $request['guid'];

                    // Send back the register successful message.
					$msg = array(
						'requestedMethod' => 'register',
					    'result' => true
					);

					$from->send(json_encode($msg));
				} elseif ($request['type'] == 'AvatarConsumer') {
                    // Registers the requesting connection as an avatar consumer. It will be sent any update messages from the avatar sources.
					$this->avatarConsumers->attach($from);
					$this->unknownConnections->detach($from);

                    // Send back the request success message.
					$msg = array(
						'requestedMethod' => 'register',
						'result' => true
					);

					$from->send(json_encode($msg));

                    // Send all known avatar information to all consumers.
					$msg = array(
						'method' => 'sendAvatars',
					    'avatars' => $this->avatars
					);

					foreach ($this->avatarConsumers as $client) {
						$client->send(json_encode($msg));
					}
				} else {
                    // Leave any other types of register message in the unknown storage.
					echo 'Unknown register type ' . $request['type'] ? $request['type'] : '';

					$this->guids[$from->resourceId] = $request['guid'];

                    // Send back an unsuccessful register message.
					$msg = array(
						'requestedMethod' => 'register',
						'result' => false
					);

					$from->send(json_encode($msg));
				};

				break;
			case 'updateAvatar':
                // An update message was sent.
				if (!$this->avatarSources->contains($from)) {
                    // The connection was not registered as an avatar source, so ignore the update.
					echo 'UpdateAvatar request from invalid source.';
				} elseif (empty($request['name'])) {
                    // The avatar has no name (happens when the user is in Gw2 but is in the character select screen), so ignore the update.
					echo 'UpdateAvatar request with empty avatar. Ignoring.';
				} else {
                    // A valid update message was received. Store the information.
					$this->avatars[$request['guid']] = array(
						'name' => $request['name'],
					    'x' => $request['x'],
					    'y' => $request['y'],
					    'z' => $request['z'],
					    'mapId' => $request['mapId'],
					    'worldId' => $request['worldId']
					);

                    // Send out the update information to each avatar consumer.
					foreach ($this->avatarConsumers as $client) {
						$msg = array(
							'method' => 'sendAvatars',
						    'avatars' => array(
							    $request['guid'] => $this->avatars[$request['guid']]
						    )
						);

						$client->send(json_encode($msg));
					}
				}

				break;
			default:
				break;
		}
	}

	public function onClose(ConnectionInterface $conn) {
		// The connection is closed, remove it, as we can no longer send it messages
		if ($this->unknownConnections->contains($conn)) {
            // For unknown connections, just remove the connection.
			$this->unknownConnections->detach($conn);
		} elseif ($this->avatarSources->contains($conn)) {
            // For avatar source connections, remove the connection and send to all consumers that the particular avatar from that source is to be removed from display.
			$this->avatarSources->detach($conn);

			foreach ($this->avatarConsumers as $client) {
				$msg = array(
					'method' => 'removeAvatars',
					'avatars' => array(
						$this->guids[$conn->resourceId] => $this->avatars[$this->guids[$conn->resourceId]]
					)
				);

				$client->send(json_encode($msg));
			}

			unset($this->avatars[$this->guids[$conn->resourceId]]);
			unset($this->guids[$conn->resourceId]);
		} else {
            // For avatar consumers, just remove the connection.
			$this->avatarConsumers->detach($conn);
		}

		echo "Connection {$conn->resourceId} has disconnected\n";
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
        // Notify that there was an error, and shut down the connection.
		echo "An error has occurred: {$e->getMessage()}\n";

		unset($this->avatars[$this->guids[$conn->resourceId]]);
		unset($this->guids[$conn->resourceId]);

		$conn->close();
	}
}