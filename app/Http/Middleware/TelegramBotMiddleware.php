<?php

namespace App\Http\Middleware;

use App\Models\TelegramUser;
use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramBotMiddleware
{
    protected AccessControlService $accessControlService;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verify webhook secret if configured
        $webhookSecret = config('telegram.webhook_secret');
        
        if (!empty($webhookSecret)) {
            $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            
            if ($providedSecret !== $webhookSecret) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // For API endpoints, you might want to check user authentication
        // This is a simplified version - in a real implementation, you'd have more robust checks
        
        return $next($request);
    }
}