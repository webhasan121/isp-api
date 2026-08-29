<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $type,
        public array $data = [],
    ) {
    }

    public function handle(
        Messaging $messaging
    ): void {

        $user = User::query()
            ->with('deviceTokens')
            ->find($this->userId);

        if (! $user) {
            return;
        }

        if ($user->deviceTokens->isEmpty()) {
            return;
        }

        $messageData = [
            'type' => $this->type,
        ];

        foreach ($this->data as $key => $value) {
            $messageData[(string) $key] =
                is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value);
        }


        foreach ($user->deviceTokens as $deviceToken) {

            try {

                $message = CloudMessage::withTarget(
                    'token',
                    $deviceToken->token
                )
                    ->withNotification(
                        Notification::create(
                            $this->title,
                            $this->body
                        )
                    )
                    ->withData(
                        $messageData
                    );

                $messaging->send($message);

            } catch (\Throwable $e) {

                Log::error(
                    'FCM notification failed',
                    [
                        'user_id' => $user->id,

                        'device_token_id' =>
                            $deviceToken->id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }
    }
}
