<?php

namespace PHPPM\React;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\Connection;
use React\Socket\ConnectionException;
use React\Socket\ServerInterface;

/**
 * Socket server.
 *
 * Overwrites React\Socket\Server
 * 
 * @see React\Socket\Server
 * 
 * Version of https://github.com/reactphp/socket/pull/17/
 */
class Server extends EventEmitter implements ServerInterface
{
    public $master;
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function listen($port, $host = '127.0.0.1')
    {
        $localSocket = '';
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $localSocket = 'tcp://' . $host . ':' . $port;
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // enclose IPv6 addresses in square brackets before appending port
            $localSocket = 'tcp://[' . $host . ']:' . $port;
        } elseif (preg_match('#^unix://#', $host)) {
            $localSocket = $host;
        } else {
            throw new \UnexpectedValueException(
                '"' . $host . '" does not match to a set of supported transports. ' .
                'Supported transports are: IPv4, IPv6 and unix:// .'
                , 1433253311);
        }
        $this->master = stream_socket_server($localSocket, $errno, $errstr);
        if (false === $this->master) {
            $message = "Could not bind to $localSocket . Error: [$errno] $errstr";
            throw new ConnectionException($message, $errno);
        }
        stream_set_blocking($this->master, 0);
        $this->loop->addReadStream($this->master, function ($master) {
            $newSocket = stream_socket_accept($master);
            if (false === $newSocket) {
                $this->emit('error', array(new \RuntimeException('Error accepting new connection')));
                return;
            }
            $this->handleConnection($newSocket);
        });
    }

    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $client = $this->createConnection($socket);

        $this->emit('connection', array($client));
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);

        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        $this->loop->removeStream($this->master);
        fclose($this->master);
        $this->removeAllListeners();
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this->loop);
    }
}
