<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketService implements MessageComponentInterface
{
    /**
     * Connected clients
     *
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * User connections mapping
     *
     * @var array
     */
    protected $userConnections = [];

    /**
     * Task event service
     *
     * @var TaskEventService
     */
    protected $taskEventService;

    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->taskEventService = new TaskEventService();
        $this->loop = Loop::get();
    }

    /**
     * Start the WebSocket server
     *
     * @param string $host
     * @param int $port
     * @return void
     */
    public function start(string $host = '0.0.0.0', int $port = 8080): void
    {
        try {
            $server = IoServer::factory(
                new HttpServer(
                    new WsServer($this)
                ),
                $port,
                $host
            );

            // Subscribe to Redis channels for real-time events
            $this->subscribeToRedisEvents();

            Log::info('WebSocket server started', [
                'host' => $host,
                'port' => $port
            ]);

            echo "WebSocket server started on {$host}:{$port}\n";
            
            $server->run();
        } catch (\Exception $e) {
            Log::error('Failed to start WebSocket server', [
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Called when a new connection is opened
     *
     * @param ConnectionInterface $conn
     * @return void
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        
        Log::info('New WebSocket connection', [
            'connection_id' => $conn->resourceId,
            'total_connections' => count($this->clients)
        ]);
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connection',
            'message' => 'Connected to Task Management WebSocket server',
            'connection_id' => $conn->resourceId,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Called when a message is received
     *
     * @param ConnectionInterface $from
     * @param string $msg
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }

            Log::info('WebSocket message received', [
                'connection_id' => $from->resourceId,
                'type' => $data['type'],
                'data' => $data
            ]);

            switch ($data['type']) {
                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;
                
                case 'subscribe':
                    $this->handleSubscription($from, $data);
                    break;
                
                case 'unsubscribe':
                    $this->handleUnsubscription($from, $data);
                    break;
                
                case 'ping':
                    $this->handlePing($from);
                    break;
                
                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
            }
        } catch (\Exception $e) {
            Log::error('Error processing WebSocket message', [
                'connection_id' => $from->resourceId,
                'message' => $msg,
                'error' => $e->getMessage()
            ]);
            
            $this->sendError($from, 'Error processing message');
        }
    }

    /**
     * Called when a connection is closed
     *
     * @param ConnectionInterface $conn
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        
        // Remove from user connections
        foreach ($this->userConnections as $userId => $connections) {
            if (($key = array_search($conn, $connections, true)) !== false) {
                unset($this->userConnections[$userId][$key]);
                
                if (empty($this->userConnections[$userId])) {
                    unset($this->userConnections[$userId]);
                }
                
                Log::info('User disconnected from WebSocket', [
                    'user_id' => $userId,
                    'connection_id' => $conn->resourceId
                ]);
                break;
            }
        }
        
        Log::info('WebSocket connection closed', [
            'connection_id' => $conn->resourceId,
            'total_connections' => count($this->clients)
        ]);
    }

    /**
     * Called when an error occurs
     *
     * @param ConnectionInterface $conn
     * @param \Exception $e
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        Log::error('WebSocket connection error', [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $conn->close();
    }

    /**
     * Handle user authentication
     *
     * @param ConnectionInterface $conn
     * @param array $data
     * @return void
     */
    private function handleAuthentication(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['user_id']) || !isset($data['token'])) {
            $this->sendError($conn, 'Missing user_id or token');
            return;
        }

        $userId = (int) $data['user_id'];
        $token = $data['token'];

        // Validate token (simplified - in production, validate against Sanctum tokens)
        if (!$this->validateToken($userId, $token)) {
            $this->sendError($conn, 'Invalid authentication token');
            return;
        }

        // Add connection to user connections
        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }
        
        $this->userConnections[$userId][] = $conn;
        
        // Store user ID in connection
        $conn->userId = $userId;

        $conn->send(json_encode([
            'type' => 'authenticated',
            'user_id' => $userId,
            'message' => 'Successfully authenticated',
            'timestamp' => now()->toISOString()
        ]));

        Log::info('WebSocket user authenticated', [
            'user_id' => $userId,
            'connection_id' => $conn->resourceId
        ]);
    }

    /**
     * Handle subscription to channels
     *
     * @param ConnectionInterface $conn
     * @param array $data
     * @return void
     */
    private function handleSubscription(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->userId)) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $channels = $data['channels'] ?? [];
        
        if (!is_array($channels)) {
            $this->sendError($conn, 'Channels must be an array');
            return;
        }

        // Store subscribed channels in connection
        if (!isset($conn->subscribedChannels)) {
            $conn->subscribedChannels = [];
        }
        
        foreach ($channels as $channel) {
            if (!in_array($channel, $conn->subscribedChannels)) {
                $conn->subscribedChannels[] = $channel;
            }
        }

        $conn->send(json_encode([
            'type' => 'subscribed',
            'channels' => $conn->subscribedChannels,
            'timestamp' => now()->toISOString()
        ]));

        Log::info('WebSocket channel subscription', [
            'user_id' => $conn->userId,
            'connection_id' => $conn->resourceId,
            'channels' => $channels
        ]);
    }

    /**
     * Handle unsubscription from channels
     *
     * @param ConnectionInterface $conn
     * @param array $data
     * @return void
     */
    private function handleUnsubscription(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->subscribedChannels)) {
            return;
        }

        $channels = $data['channels'] ?? [];
        
        if (!is_array($channels)) {
            $this->sendError($conn, 'Channels must be an array');
            return;
        }

        foreach ($channels as $channel) {
            if (($key = array_search($channel, $conn->subscribedChannels, true)) !== false) {
                unset($conn->subscribedChannels[$key]);
            }
        }

        $conn->subscribedChannels = array_values($conn->subscribedChannels);

        $conn->send(json_encode([
            'type' => 'unsubscribed',
            'channels' => $conn->subscribedChannels,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Handle ping message
     *
     * @param ConnectionInterface $conn
     * @return void
     */
    private function handlePing(ConnectionInterface $conn): void
    {
        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Send error message to connection
     *
     * @param ConnectionInterface $conn
     * @param string $message
     * @return void
     */
    private function sendError(ConnectionInterface $conn, string $message): void
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Validate authentication token
     *
     * @param int $userId
     * @param string $token
     * @return bool
     */
    private function validateToken(int $userId, string $token): bool
    {
        // Simplified validation - in production, validate against Sanctum tokens
        // For now, just check if token is not empty
        return !empty($token) && $userId > 0;
    }

    /**
     * Subscribe to Redis events
     *
     * @return void
     */
    private function subscribeToRedisEvents(): void
    {
        $this->loop->addPeriodicTimer(0.1, function () {
            try {
                // Check for Redis messages (simplified approach)
                // In production, use proper Redis pub/sub with ReactPHP
                $this->processRedisMessages();
            } catch (\Exception $e) {
                Log::error('Error processing Redis messages', [
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /**
     * Process Redis messages and broadcast to WebSocket clients
     *
     * @return void
     */
    private function processRedisMessages(): void
    {
        // This is a simplified implementation
        // In production, use proper Redis pub/sub integration with ReactPHP
        
        foreach ($this->userConnections as $userId => $connections) {
            // Check for user-specific events
            $channel = "user_task_events:{$userId}";
            
            // Simulate checking for messages (in production, use proper pub/sub)
            // For now, we'll integrate this with the TaskEventService directly
        }
    }

    /**
     * Broadcast message to user connections
     *
     * @param int $userId
     * @param array $message
     * @return void
     */
    public function broadcastToUser(int $userId, array $message): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        $jsonMessage = json_encode($message);
        
        foreach ($this->userConnections[$userId] as $conn) {
            try {
                $conn->send($jsonMessage);
            } catch (\Exception $e) {
                Log::error('Failed to send message to WebSocket connection', [
                    'user_id' => $userId,
                    'connection_id' => $conn->resourceId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Message broadcasted to user WebSocket connections', [
            'user_id' => $userId,
            'connection_count' => count($this->userConnections[$userId])
        ]);
    }

    /**
     * Broadcast message to all connections
     *
     * @param array $message
     * @return void
     */
    public function broadcastToAll(array $message): void
    {
        $jsonMessage = json_encode($message);
        
        foreach ($this->clients as $conn) {
            try {
                $conn->send($jsonMessage);
            } catch (\Exception $e) {
                Log::error('Failed to send message to WebSocket connection', [
                    'connection_id' => $conn->resourceId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Message broadcasted to all WebSocket connections', [
            'connection_count' => count($this->clients)
        ]);
    }

    /**
     * Get connection statistics
     *
     * @return array
     */
    public function getConnectionStats(): array
    {
        return [
            'total_connections' => count($this->clients),
            'authenticated_users' => count($this->userConnections),
            'user_connections' => array_map('count', $this->userConnections),
            'status' => 'healthy'
        ];
    }
}