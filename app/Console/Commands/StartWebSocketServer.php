<?php

namespace App\Console\Commands;

use App\Services\WebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartWebSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:start 
                            {--host=0.0.0.0 : The host to bind the WebSocket server to}
                            {--port=8080 : The port to bind the WebSocket server to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the WebSocket server for real-time task updates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting WebSocket server on {$host}:{$port}");
        
        try {
            $webSocketService = new WebSocketService();
            $webSocketService->start($host, $port);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to start WebSocket server: ' . $e->getMessage());
            
            Log::error('WebSocket server startup failed', [
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
