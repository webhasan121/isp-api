<?php

namespace App\Services;

use App\Jobs\SendFcmNotification;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;

class AppNotificationService
{
    public function send(
        User $user,
        string $title,
        string $body,
        string $type,
        array $data = []
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Save notification history immediately
        |--------------------------------------------------------------------------
        */

        $user->notify(
            new AppDatabaseNotification(
                title: $title,
                body: $body,
                type: $type,
                data: $data,
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Push notification in background
        |--------------------------------------------------------------------------
        */

        SendFcmNotification::dispatch(
            userId: $user->id,
            title: $title,
            body: $body,
            type: $type,
            data: $data,
        );
    }
}
