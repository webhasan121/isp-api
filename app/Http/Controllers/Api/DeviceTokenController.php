<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
                'max:4096',
            ],

            'platform' => [
                'required',
                Rule::in([
                    'android',
                    'ios',
                ]),
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $tokenHash = hash(
            'sha256',
            $validated['token']
        );

        /*
        |--------------------------------------------------------------------------
        | One FCM token = one current user
        |--------------------------------------------------------------------------
        */

        $deviceToken = DeviceToken::updateOrCreate(
            [
                'token_hash' => $tokenHash,
            ],
            [
                'user_id' => $request->user()->id,

                'token' => $validated['token'],

                'platform' => $validated['platform'],

                'device_name' =>
                    $validated['device_name'] ?? null,

                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Device token registered successfully.',

            'data' => $deviceToken,
        ]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
            ],
        ]);

        $tokenHash = hash(
            'sha256',
            $validated['token']
        );

        DeviceToken::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'token_hash',
                $tokenHash
            )
            ->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Device token removed successfully.',
        ]);
    }
}
