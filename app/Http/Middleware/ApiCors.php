<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiCors
{

    
    public function handle(Request $request, Closure $next)
    {
        if ($request->method() === 'OPTIONS') {
            return response()->json([], 204, $this->corsHeaders());
        }

        $response = $next($request);

        foreach ($this->corsHeaders() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    protected function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,Accept',
            'Access-Control-Allow-Credentials' => 'true',
        ];
    }
}
