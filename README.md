# gw2server
A websocket server using Ratchet in PHP. It takes in connections from the gw2client application and passes on the avatar information to the gw2site application.

The server accepts messages in JSON format, with a request parameter. It registers connections with the correct request messages, and then takes any update messages from avatar sources (from gw2client applications on various players' computers) and sends it out to any avatar consumers (currently only one, the gw2site).
