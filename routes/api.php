<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Test API
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'ISP API is working',
    ]);
});


Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {

        Route::post('/login', [
            AuthController::class,
            'login'
        ]);

        Route::middleware('auth:sanctum')
            ->group(function () {

                Route::get('/me', [
                    AuthController::class,
                    'me'
                ]);

                Route::post('/logout', [
                    AuthController::class,
                    'logout'
                ]);
            });
    });


    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:admin'
    ])
        ->prefix('admin')
        ->group(function () {


            Route::post(
                '/send-message',
                function (
                    Request $request,
                    AppNotificationService $notificationService
                ) {
                    $validated = $request->validate([
                        'title' => [
                            'required',
                            'string',
                            'max:255',
                        ],

                        'body' => [
                            'required',
                            'string',
                            'max:1000',
                        ],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Send notification to all customers
                    |--------------------------------------------------------------------------
                    */

                    User::query()
                        ->where('role', 'customer')
                        ->chunkById(
                            200,
                            function ($users) use (
                                $notificationService,
                                $validated,
                                $request
                            ) {
                                foreach ($users as $user) {

                                    $notificationService->send(
                                        user: $user,

                                        title: $validated['title'],

                                        body: $validated['body'],

                                        type: 'admin_notice',

                                        data: [
                                            'sender_id' =>
                                            $request->user()->id,

                                            'source' =>
                                            'admin_broadcast',
                                        ],
                                    );
                                }
                            }
                        );

                    return response()->json([
                        'success' => true,

                        'message' =>
                        'Notification sent to all users successfully.',
                    ]);
                }
            );

            Route::get('/test', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Welcome Admin',
                ]);
            });

            /*
            |--------------------------------------------------------------------------
            | Package Management
            |--------------------------------------------------------------------------
            */

            Route::get('/packages', [
                PackageController::class,
                'index'
            ]);

            Route::post('/packages', [
                PackageController::class,
                'store'
            ]);

            Route::get('/packages/{package}', [
                PackageController::class,
                'show'
            ]);

            Route::put('/packages/{package}', [
                PackageController::class,
                'update'
            ]);

            Route::patch('/packages/{package}/status', [
                PackageController::class,
                'changeStatus'
            ]);
        });


    /*
    |--------------------------------------------------------------------------
    | Admin + Operator
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:admin,operator'
    ])
        ->prefix('staff')
        ->group(function () {

            Route::get('/test', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Welcome Admin / Operator',
                ]);
            });


            /*
            |--------------------------------------------------------------------------
            | Customer Management
            |--------------------------------------------------------------------------
            */
            Route::get('/customers', [
                CustomerController::class,
                'index'
            ]);

            Route::post('/customers', [
                CustomerController::class,
                'store'
            ]);

            Route::get('/customers/{customer}', [
                CustomerController::class,
                'show'
            ]);

            Route::put('/customers/{customer}', [
                CustomerController::class,
                'update'
            ]);

            Route::patch('/customers/{customer}/status', [
                CustomerController::class,
                'changeStatus'
            ]);


            /*
            |--------------------------------------------------------------------------
            | Subscription Management
            |--------------------------------------------------------------------------
            */

            Route::get('/subscriptions', [
                SubscriptionController::class,
                'index'
            ]);

            Route::post('/subscriptions', [
                SubscriptionController::class,
                'store'
            ]);

            Route::get('/subscriptions/{subscription}', [
                SubscriptionController::class,
                'show'
            ]);

            Route::patch(
                '/subscriptions/{subscription}/pending-package',
                [
                    SubscriptionController::class,
                    'updatePendingPackage'
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Payments
            |--------------------------------------------------------------------------
            */

            Route::get('/payments', [
                PaymentController::class,
                'index'
            ]);

            Route::get('/payments/{payment}', [
                PaymentController::class,
                'show'
            ]);

            Route::post(
                '/subscriptions/{subscription}/payments',
                [
                    PaymentController::class,
                    'store'
                ]
            );

            Route::post(
                '/subscriptions/{subscription}/renew',
                [
                    PaymentController::class,
                    'renew'
                ]
            );


            Route::post(
                '/subscriptions/{subscription}/change-package',
                [
                    SubscriptionController::class,
                    'requestPackageChange'
                ]
            );

            Route::delete(
                '/subscriptions/{subscription}/change-package',
                [
                    SubscriptionController::class,
                    'cancelPackageChange'
                ]
            );

            Route::post(
                '/subscriptions/{subscription}/reactivate',
                [
                    PaymentController::class,
                    'reactivate'
                ]
            );


            Route::get('/invoices', [
                InvoiceController::class,
                'index'
            ]);

            Route::get('/invoices/{invoice}', [
                InvoiceController::class,
                'show'
            ]);

            Route::get('/invoices/{invoice}/pdf', [
                InvoiceController::class,
                'download'
            ]);
        });


    /*
    |--------------------------------------------------------------------------
    | Customer Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:customer'
    ])
        ->prefix('customer')
        ->group(function () {

            Route::get('/test', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Welcome Customer',
                ]);
            });

            Route::get('/packages', [
                PackageController::class,
                'activePackages'
            ]);


            Route::get('/subscription', [
                SubscriptionController::class,
                'mySubscription'
            ]);

            Route::post('/subscription/select-package', [
                SubscriptionController::class,
                'selectMyPackage'
            ]);

            Route::get('/payments', [
                PaymentController::class,
                'myPayments'
            ]);


            Route::get('/renewal-info', [
                PaymentController::class,
                'renewalInfo'
            ]);


            Route::post('/subscription/change-package', [
                SubscriptionController::class,
                'requestMyPackageChange'
            ]);

            Route::delete('/subscription/change-package', [
                SubscriptionController::class,
                'cancelMyPackageChange'
            ]);

            Route::get('/invoices', [
                InvoiceController::class,
                'myInvoices'
            ]);

            Route::get('/invoices/{invoice}', [
                InvoiceController::class,
                'myInvoice'
            ]);

            Route::get('/invoices/{invoice}/pdf', [
                InvoiceController::class,
                'myDownload'
            ]);
        });
});



Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Device Tokens
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/v1/device-tokens',
        [
            DeviceTokenController::class,
            'store',
        ]
    );

    Route::delete(
        '/v1/device-tokens',
        [
            DeviceTokenController::class,
            'destroy',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */



    Route::get(
        '/v1/notifications',
        [NotificationController::class, 'index']
    );

    Route::patch(
        '/v1/notifications/read-all',
        [
            NotificationController::class,
            'markAllAsRead'
        ]
    );

    Route::patch(
        '/v1/notifications/{notificationId}/read',
        [
            NotificationController::class,
            'markAsRead'
        ]
    );
});
