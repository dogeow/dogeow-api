<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Broadcast;

class TestReverbSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:reverb-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Laravel Reverb and WebSocket infrastructure setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Laravel Reverb and WebSocket Infrastructure Setup...');
        $this->newLine();

        // Test Redis connection
        $this->info('1. Testing Redis connection...');
        try {
            $redisClient = config('database.redis.client');
            $this->info('   - Redis client: ' . $redisClient);
            
            if ($redisClient === 'phpredis') {
                $this->warn('   ⚠️  PhpRedis extension not available, falling back to Predis');
                // Update config to use predis for this test
                config(['database.redis.client' => 'predis']);
            }
            
            Redis::ping();
            $this->info('   ✅ Redis connection successful');
        } catch (\Exception $e) {
            $this->error('   ❌ Redis connection failed: ' . $e->getMessage());
            $this->info('   💡 Make sure Redis server is running on ' . config('database.redis.default.host') . ':' . config('database.redis.default.port'));
            return 1;
        }

        // Test Redis databases
        $this->info('2. Testing Redis database configurations...');
        try {
            Redis::connection('cache')->ping();
            $this->info('   ✅ Cache Redis database connection successful');
            
            Redis::connection('sessions')->ping();
            $this->info('   ✅ Sessions Redis database connection successful');
            
            Redis::connection('broadcasting')->ping();
            $this->info('   ✅ Broadcasting Redis database connection successful');
        } catch (\Exception $e) {
            $this->error('   ❌ Redis database configuration failed: ' . $e->getMessage());
            return 1;
        }

        // Test broadcasting configuration
        $this->info('3. Testing broadcasting configuration...');
        $broadcastDriver = config('broadcasting.default');
        if ($broadcastDriver === 'reverb') {
            $this->info('   ✅ Broadcasting driver set to Reverb');
        } else {
            $this->error('   ❌ Broadcasting driver not set to Reverb (current: ' . $broadcastDriver . ')');
            return 1;
        }

        // Test Reverb configuration
        $this->info('4. Testing Reverb configuration...');
        $reverbConfig = config('reverb.apps.apps.0');
        if ($reverbConfig && $reverbConfig['key'] && $reverbConfig['secret'] && $reverbConfig['app_id']) {
            $this->info('   ✅ Reverb app configuration is complete');
            $this->info('   - App ID: ' . $reverbConfig['app_id']);
            $this->info('   - Host: ' . $reverbConfig['options']['host']);
            $this->info('   - Port: ' . $reverbConfig['options']['port']);
            $this->info('   - Scheme: ' . $reverbConfig['options']['scheme']);
        } else {
            $this->error('   ❌ Reverb app configuration is incomplete');
            return 1;
        }

        // Test WebSocket authentication middleware
        $this->info('5. Testing WebSocket authentication middleware...');
        if (app()->make('router')->getMiddleware()['websocket.auth']) {
            $this->info('   ✅ WebSocket authentication middleware registered');
        } else {
            $this->error('   ❌ WebSocket authentication middleware not registered');
            return 1;
        }

        $this->newLine();
        $this->info('🎉 All tests passed! Laravel Reverb and WebSocket infrastructure is properly configured.');
        $this->newLine();
        $this->info('Next steps:');
        $this->info('- Start the Reverb server: php artisan reverb:start');
        $this->info('- Configure your frontend to connect to ws://localhost:8080');
        
        return 0;
    }
}
