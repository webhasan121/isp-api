<?php

namespace App\Http\Controllers\Api;

use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly AppNotificationService $notificationService,
    ) {}
    /*
    |--------------------------------------------------------------------------
    | Staff: Payment History
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $payments = Payment::query()
            ->with([
                'customer.user',
                'package',
                'receivedBy',
            ])
            ->when(
                $request->customer_id,
                fn($query, $customerId) =>
                $query->where('customer_id', $customerId)
            )
            ->latest('paid_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff: First Payment + Connection Activation
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Subscription $subscription
    ) {
        $validated = $request->validate([
            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bkash',
                    'nagad',
                    'bank',
                ]),
            ],

            'transaction_id' => [
                'nullable',
                'required_unless:payment_method,cash',
                'string',
                'max:100',
                'unique:payments,transaction_id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
         * lockForUpdate ব্যবহার করছি যাতে একই payment
         * double click / concurrent request-এ দুইবার
         * activate না হয়ে যায়।
         */
        $result = DB::transaction(function () use (
            $request,
            $validated,
            $subscription
        ) {

            $lockedSubscription = Subscription::query()
                ->with([
                    'customer.user',
                    'package',
                ])
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Step 7-এ শুধু প্রথম payment handle করছি।
             * Renewal Step 8-এ add করব।
             */
            if ($lockedSubscription->status !== 'pending') {
                return [
                    'error' => 'This subscription has already been activated.',
                ];
            }

            /*
             * Customer account disabled হলে activation নয়।
             */
            if (! $lockedSubscription->customer->user->status) {
                return [
                    'error' => 'Customer account is inactive.',
                ];
            }

            /*
             * Pending অবস্থায় package disable হয়ে গেলে
             * first activation করা যাবে না।
             */
            if (! $lockedSubscription->package->status) {
                return [
                    'error' => 'Selected package is currently unavailable.',
                ];
            }

            $startAt = now();

            /*
             * addMonthNoOverflow:
             * 31 January + 1 month যেন March-এ jump না করে।
             */
            $endAt = $startAt
                ->copy()
                ->addMonthNoOverflow();

            $package = $lockedSubscription->package;

            /*
             * Amount request থেকে নিচ্ছি না।
             * Database package price-ই authoritative।
             */
            $payment = Payment::create([
                'customer_id' =>
                $lockedSubscription->customer_id,

                'subscription_id' =>
                $lockedSubscription->id,

                'package_id' =>
                $package->id,

                'package_name_snapshot' =>
                $package->name,

                'speed_mbps_snapshot' =>
                $package->speed_mbps,

                'amount' =>
                $package->price,

                'payment_type' =>
                'activation',

                'payment_method' =>
                $validated['payment_method'],

                'transaction_id' =>
                $validated['transaction_id'] ?? null,

                'coverage_start_at' =>
                $startAt,

                'coverage_end_at' =>
                $endAt,

                'paid_at' =>
                $startAt,

                'received_by' =>
                $request->user()->id,

                'status' =>
                'paid',

                'notes' =>
                $validated['notes'] ?? null,
            ]);

            /*
             * Human readable payment reference.
             *
             * Example:
             * PAY-000001
             */
            $payment->update([
                'payment_reference' =>
                'PAY-' .
                    str_pad(
                        $payment->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
            ]);

            $invoice = $this
                ->invoiceService
                ->createForPayment($payment);

            /*
             * Payment সফল হওয়ার পরেই subscription active।
             */
            $lockedSubscription->update([
                'period_start_at' => $startAt,
                'period_end_at' => $endAt,
                'paid_until' => $endAt,
                'status' => 'active',
            ]);

            return [
                'payment' => $payment
                    ->fresh()
                    ->load([
                        'customer.user',
                        'package',
                        'receivedBy',
                    ]),

                'invoice' => $invoice,

                'subscription' =>
                $lockedSubscription
                    ->fresh()
                    ->load([
                        'package',
                        'nextPackage',
                    ]),
            ];
        });


        /*
         * Business validation error
         */
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }
        $result['email_queued'] =
            $this->queueInvoiceEmail(
                $result['invoice']
            );

        $this->sendPaymentNotification(
            result: $result,
            title: 'Connection Activated',
            type: 'subscription_activated',
            action: 'activated',
        );
        return response()->json([
            'success' => true,
            'message' => 'Payment received and connection activated successfully.',
            'data' => $result,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff: Single Payment
    |--------------------------------------------------------------------------
    */

    public function show(Payment $payment)
    {
        $payment->load([
            'customer.user',
            'package',
            'receivedBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer: My Payment History
    |--------------------------------------------------------------------------
    */

    public function myPayments(Request $request)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found.',
            ], 404);
        }

        $payments = $customer
            ->payments()
            ->with('package')
            ->latest('paid_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }



    public function renew(
        Request $request,
        Subscription $subscription
    ) {
        $validated = $request->validate([
            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bkash',
                    'nagad',
                    'bank',
                ]),
            ],

            'transaction_id' => [
                'nullable',
                'required_unless:payment_method,cash',
                'string',
                'max:100',
                'unique:payments,transaction_id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $result = DB::transaction(function () use (
            $request,
            $validated,
            $subscription
        ) {

            /*
        |--------------------------------------------------------------------------
        | Lock Subscription
        |--------------------------------------------------------------------------
        |
        | একই সময় double click / দুইটা request এলে
        | duplicate renewal আটকাবে।
        |
        */

            $lockedSubscription = Subscription::query()
                ->with([
                    'customer.user',
                    'package',
                ])
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();


            /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

            if (! $lockedSubscription->customer->user->status) {
                return [
                    'error' => 'Customer account is inactive.',
                ];
            }

            if ($lockedSubscription->status === 'pending') {
                return [
                    'error' => 'First payment is required before renewal.',
                ];
            }

            if ($lockedSubscription->status === 'suspended') {
                return [
                    'error' => 'Suspended subscription cannot be renewed.',
                ];
            }

            if ($lockedSubscription->status === 'terminated') {
                return [
                    'error' => 'Terminated subscription cannot be renewed.',
                ];
            }

            $now = now();


            /*
        |--------------------------------------------------------------------------
        | Normalize Current Cycle
        |--------------------------------------------------------------------------
        |
        | Scheduler এখনও run না করলেও payment API যেন
        | correct business state বুঝতে পারে।
        |
        */

            if (
                $lockedSubscription->period_end_at &&
                $now->gte($lockedSubscription->period_end_at)
            ) {

                /*
             * Example:
             *
             * Current:
             * 25 Aug → 25 Sep
             *
             * Advance already paid:
             * paid_until = 25 Oct
             *
             * এখন date 26 Sep হলে,
             * 25 Sep → 25 Oct current cycle হবে।
             */

                if (
                    $lockedSubscription->paid_until &&
                    $lockedSubscription->paid_until
                    ->gt($lockedSubscription->period_end_at)
                ) {

                    $updateData = [
                        'period_start_at' =>
                        $lockedSubscription->period_end_at,

                        'period_end_at' =>
                        $lockedSubscription->paid_until,

                        'status' => 'active',
                    ];

                    /*
                    * Next package already paid হলে
                    * cycle boundary-তে package switch হবে।
                    */
                    if ($lockedSubscription->next_package_id) {
                        $updateData['package_id'] =
                            $lockedSubscription->next_package_id;

                        $updateData['next_package_id'] = null;
                    }

                    $lockedSubscription->update($updateData);

                    $lockedSubscription->refresh();
                }

                /*
             * Advance payment নেই।
             * Connection expired.
             */ else {

                    $lockedSubscription->update([
                        'status' => 'expired',
                    ]);

                    $lockedSubscription->refresh();
                }
            }


            /*
        |--------------------------------------------------------------------------
        | Package
        |--------------------------------------------------------------------------
        */

            /*
            |--------------------------------------------------------------------------
            | Determine Renewal Package
            |--------------------------------------------------------------------------
            |
            | Package change scheduled থাকলে next cycle-এর payment
            | next package-এর price অনুযায়ী হবে।
            |
            */

            if (
                $lockedSubscription->status === 'active' &&
                $lockedSubscription->next_package_id
            ) {
                $package = Package::query()
                    ->whereKey($lockedSubscription->next_package_id)
                    ->where('status', true)
                    ->first();

                if (! $package) {
                    return [
                        'error' => 'The selected next package is no longer available.',
                    ];
                }
            } else {
                $package = $lockedSubscription->package;

                if (! $package->status) {
                    return [
                        'error' => 'Current package is no longer available for renewal.',
                    ];
                }
            }


            /*
        |--------------------------------------------------------------------------
        | ACTIVE CUSTOMER
        |--------------------------------------------------------------------------
        |
        | Current cycle শেষ হওয়ার আগেই next month pay করছে।
        |
        */

            if ($lockedSubscription->status === 'active') {

                /*
             * paid_until > period_end_at
             *
             * মানে next month already paid.
             */

                if (
                    $lockedSubscription->paid_until &&
                    $lockedSubscription->period_end_at &&
                    $lockedSubscription->paid_until
                    ->gt($lockedSubscription->period_end_at)
                ) {
                    return [
                        'error' =>
                        'Next month has already been paid. Advance payment is limited to one month.',
                    ];
                }

                $coverageStart =
                    $lockedSubscription->period_end_at->copy();

                $coverageEnd =
                    $coverageStart
                    ->copy()
                    ->addMonthNoOverflow();


                /*
             * Current cycle change হবে না।
             *
             * শুধু paid_until next cycle পর্যন্ত যাবে।
             */

                $lockedSubscription->update([
                    'paid_until' => $coverageEnd,
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | EXPIRED CUSTOMER
        |--------------------------------------------------------------------------
        |
        | Expiry-এর পরে payment করলে payment date থেকেই
        | নতুন এক মাস শুরু হবে।
        |
        */ elseif ($lockedSubscription->status === 'expired') {

                $coverageStart = $now->copy();

                $coverageEnd = $coverageStart
                    ->copy()
                    ->addMonthNoOverflow();

                $lockedSubscription->update([
                    'period_start_at' => $coverageStart,
                    'period_end_at' => $coverageEnd,
                    'paid_until' => $coverageEnd,
                    'status' => 'active',
                ]);
            } else {
                return [
                    'error' => 'Subscription cannot be renewed.',
                ];
            }


            /*
        |--------------------------------------------------------------------------
        | Payment Create
        |--------------------------------------------------------------------------
        */

            $payment = Payment::create([
                'customer_id' =>
                $lockedSubscription->customer_id,

                'subscription_id' =>
                $lockedSubscription->id,

                'package_id' =>
                $package->id,

                'package_name_snapshot' =>
                $package->name,

                'speed_mbps_snapshot' =>
                $package->speed_mbps,

                'amount' =>
                $package->price,

                'payment_type' =>
                'renewal',

                'payment_method' =>
                $validated['payment_method'],

                'transaction_id' =>
                $validated['transaction_id'] ?? null,

                'coverage_start_at' =>
                $coverageStart,

                'coverage_end_at' =>
                $coverageEnd,

                'paid_at' =>
                $now,

                'received_by' =>
                $request->user()->id,

                'status' =>
                'paid',

                'notes' =>
                $validated['notes'] ?? null,
            ]);


            /*
        |--------------------------------------------------------------------------
        | Payment Reference
        |--------------------------------------------------------------------------
        */

            $payment->update([
                'payment_reference' =>
                'PAY-' .
                    str_pad(
                        $payment->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
            ]);

            $invoice = $this
                ->invoiceService
                ->createForPayment($payment);



            return [
                'payment' => $payment
                    ->fresh()
                    ->load([
                        'customer.user',
                        'package',
                        'receivedBy',
                    ]),

                'invoice' => $invoice,
                'subscription' =>
                $lockedSubscription
                    ->fresh()
                    ->load([
                        'package',
                        'nextPackage',
                    ]),
            ];
        });


        /*
    |--------------------------------------------------------------------------
    | Business Error
    |--------------------------------------------------------------------------
    */


        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        $result['email_queued'] =
            $this->queueInvoiceEmail(
                $result['invoice']
            );

        $this->sendPaymentNotification(
            result: $result,
            title: 'Subscription Renewed',
            type: 'subscription_renewed',
            action: 'renewed',
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription renewed successfully.',
            'data' => $result,
        ], 201);
    }


    public function renewalInfo(Request $request)
    {
        $customer = $request->user()
            ->customer()
            ->with([
                'subscription.package',
                'subscription.nextPackage',
            ])
            ->first();

        if (! $customer || ! $customer->subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found.',
            ], 404);
        }

        $subscription = $customer->subscription;


        /*
    |--------------------------------------------------------------------------
    | Pending Subscription
    |--------------------------------------------------------------------------
    */

        if ($subscription->status === 'pending') {
            return response()->json([
                'success' => true,
                'data' => [
                    'can_renew' => false,
                    'reason' => 'First payment is required.',
                ],
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Suspended / Terminated
    |--------------------------------------------------------------------------
    */

        if (in_array(
            $subscription->status,
            ['suspended', 'terminated']
        )) {
            return response()->json([
                'success' => true,
                'data' => [
                    'can_renew' => false,
                    'reason' => 'Subscription is not eligible for renewal.',
                ],
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Expired Subscription
    |--------------------------------------------------------------------------
    |
    | Expired হলে normal advance renewal না।
    | Reactivation flow ব্যবহার হবে।
    |
    */

        if (
            $subscription->period_end_at &&
            now()->gte($subscription->period_end_at) &&
            (
                ! $subscription->paid_until ||
                $subscription->paid_until
                ->lte($subscription->period_end_at)
            )
        ) {
            return response()->json([
                'success' => true,
                'data' => [
                    'can_renew' => true,
                    'renewal_type' => 'reactivation',

                    'current_package' => [
                        'id' => $subscription->package->id,
                        'name' => $subscription->package->name,
                        'speed_mbps' => $subscription->package->speed_mbps,
                        'price' => $subscription->package->price,
                    ],

                    'coverage_start_at' => now(),

                    'coverage_end_at' =>
                    now()
                        ->copy()
                        ->addMonthNoOverflow(),
                ],
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Next Cycle Already Paid
    |--------------------------------------------------------------------------
    */

        if (
            $subscription->paid_until &&
            $subscription->period_end_at &&
            $subscription->paid_until
            ->gt($subscription->period_end_at)
        ) {
            return response()->json([
                'success' => true,
                'data' => [
                    'can_renew' => false,
                    'reason' => 'Next month is already paid.',
                    'paid_until' => $subscription->paid_until,
                ],
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Renewal Package
    |--------------------------------------------------------------------------
    |
    | Package change scheduled থাকলে nextPackage ব্যবহার হবে।
    |
    | না থাকলে current package ব্যবহার হবে।
    |
    */

        $renewalPackage = $subscription->nextPackage
            ?? $subscription->package;


        /*
    |--------------------------------------------------------------------------
    | Advance Renewal Available
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,

            'data' => [
                'can_renew' => true,

                'renewal_type' => 'advance',

                'package' => [
                    'id' => $renewalPackage->id,
                    'name' => $renewalPackage->name,
                    'speed_mbps' => $renewalPackage->speed_mbps,
                ],

                'amount' => $renewalPackage->price,

                'coverage_start_at' =>
                $subscription->period_end_at,

                'coverage_end_at' =>
                $subscription
                    ->period_end_at
                    ->copy()
                    ->addMonthNoOverflow(),
            ],
        ]);
    }

    public function reactivate(
        Request $request,
        Subscription $subscription
    ) {
        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],

            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bkash',
                    'nagad',
                    'bank',
                ]),
            ],

            'transaction_id' => [
                'nullable',
                'required_unless:payment_method,cash',
                'string',
                'max:100',
                'unique:payments,transaction_id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $result = DB::transaction(function () use (
            $request,
            $validated,
            $subscription
        ) {

            $lockedSubscription = Subscription::query()
                ->with([
                    'customer.user',
                    'package',
                ])
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSubscription->customer->user->status) {
                return [
                    'error' => 'Customer account is inactive.',
                ];
            }

            if (in_array(
                $lockedSubscription->status,
                ['pending', 'suspended', 'terminated']
            )) {
                return [
                    'error' => 'This subscription cannot be reactivated.',
                ];
            }

            $now = now();

            /*
         * এখনও active থাকলে direct reactivation allowed নয়।
         */
            if (
                $lockedSubscription->period_end_at &&
                $now->lt($lockedSubscription->period_end_at)
            ) {
                return [
                    'error' => 'Subscription is still active. Use package change instead.',
                ];
            }

            /*
         * Advance paid থাকলেও expired বলা যাবে না।
         */
            if (
                $lockedSubscription->paid_until &&
                $lockedSubscription->period_end_at &&
                $lockedSubscription->paid_until
                ->gt($lockedSubscription->period_end_at)
            ) {
                return [
                    'error' => 'A paid next cycle already exists.',
                ];
            }

            $package = Package::query()
                ->whereKey($validated['package_id'])
                ->where('status', true)
                ->first();

            if (! $package) {
                return [
                    'error' => 'Selected package is not available.',
                ];
            }

            $startAt = $now->copy();

            $endAt = $startAt
                ->copy()
                ->addMonthNoOverflow();

            /*
         * Payment
         */
            $payment = Payment::create([
                'customer_id' =>
                $lockedSubscription->customer_id,

                'subscription_id' =>
                $lockedSubscription->id,

                'package_id' =>
                $package->id,

                'package_name_snapshot' =>
                $package->name,

                'speed_mbps_snapshot' =>
                $package->speed_mbps,

                'amount' =>
                $package->price,

                'payment_type' =>
                'renewal',

                'payment_method' =>
                $validated['payment_method'],

                'transaction_id' =>
                $validated['transaction_id'] ?? null,

                'coverage_start_at' =>
                $startAt,

                'coverage_end_at' =>
                $endAt,

                'paid_at' =>
                $startAt,

                'received_by' =>
                $request->user()->id,

                'status' =>
                'paid',

                'notes' =>
                $validated['notes'] ?? null,
            ]);

            $payment->update([
                'payment_reference' =>
                'PAY-' .
                    str_pad(
                        $payment->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
            ]);

            $invoice = $this
                ->invoiceService
                ->createForPayment($payment);

            /*
         * সরাসরি নতুন package active
         */
            $lockedSubscription->update([
                'package_id' => $package->id,
                'next_package_id' => null,

                'period_start_at' => $startAt,
                'period_end_at' => $endAt,
                'paid_until' => $endAt,

                'status' => 'active',
            ]);

            return [
                'payment' => $payment
                    ->fresh()
                    ->load([
                        'package',
                        'receivedBy',
                    ]),
                'invoice' => $invoice,
                'subscription' =>
                $lockedSubscription
                    ->fresh()
                    ->load([
                        'package',
                        'nextPackage',
                    ]),
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        $result['email_queued'] =
            $this->queueInvoiceEmail(
                $result['invoice']
            );

        $this->sendPaymentNotification(
            result: $result,
            title: 'Connection Reactivated',
            type: 'subscription_reactivated',
            action: 'reactivated',
        );
        return response()->json([
            'success' => true,
            'message' => 'Connection reactivated successfully.',
            'data' => $result,
        ], 201);
    }


    private function queueInvoiceEmail(
        $invoice
    ): bool {
        if (! $invoice->customer_email_snapshot) {
            return false;
        }

        try {

            Mail::to(
                $invoice->customer_email_snapshot
            )->queue(
                new InvoiceMail($invoice)
            );

            return true;
        } catch (\Throwable $e) {

            Log::error(
                'Invoice email queue failed',
                [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }




    private function sendPaymentNotification(
        array $result,
        string $title,
        string $type,
        string $action
    ): void {

        $payment = $result['payment'];

        // customer.user relation না থাকলে load করবে
        $payment->loadMissing('customer.user');

        $customerUser = $payment->customer->user;

        $this->notificationService->send(
            user: $customerUser,

            title: $title,

            body: 'Your ' .
                $payment->package_name_snapshot .
                ' internet package has been ' .
                $action .
                ' successfully.',

            type: $type,

            data: [
                'payment_id' =>
                (string) $payment->id,

                'invoice_id' =>
                (string) $result['invoice']->id,

                'subscription_id' =>
                (string) $result['subscription']->id,
            ],
        );
    }
}
