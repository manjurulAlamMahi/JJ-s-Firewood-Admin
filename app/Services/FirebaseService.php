<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseService
{
    public static function messaging()
    {
        $credentials = config('services.firebase.credentials');
        
        // If the path is relative, make it absolute using base_path()
        if ($credentials && !str_starts_with($credentials, '/') && !str_contains($credentials, ':')) {
            $credentials = base_path($credentials);
        }

        $factory = (new Factory)->withServiceAccount($credentials);
        return $factory->createMessaging();
    }
}
