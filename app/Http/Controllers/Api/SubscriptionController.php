<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Staff: All Subscriptions
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
        ]);

        $subscriptions = Subscription::query()
            ->with([
                'customer.user',
                'package',
                'nextPackage',
            ])

            /*
        |--------------------------------------------------------------------------
        | Optional Customer Filter
        |--------------------------------------------------------------------------
        |
        | customer_id দিলে শুধু ওই customer-এর subscription।
        | customer_id না দিলে admin-এর জন্য সব subscription।
        |
        */

            ->when(
                $validated['customer_id'] ?? null,

                function ($query, $customerId) {
                    $query->where(
                        'customer_id',
                        $customerId
                    );
                }
            )

            ->latest()
            ->paginate(10);


        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff: Assign Initial Package
    |--------------------------------------------------------------------------
    |
    | Package assign করলেই connection active হবে না।
    | First payment-এর আগে subscription = pending.
    |
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],
        ]);

        $customer = Customer::with([
            'user',
            'subscription',
        ])->findOrFail($validated['customer_id']);

        /*
         * Disabled customer account হলে package assign নয়।
         */
        if (! $customer->user->status) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account is inactive.',
            ], 422);
        }

        /*
         * একজন customer-এর একটাই subscription থাকবে।
         */
        if ($customer->subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Customer already has a subscription.',
            ], 422);
        }

        /*
         * Inactive package assign করা যাবে না।
         */
        $package = Package::where('id', $validated['package_id'])
            ->where('status', true)
            ->first();

        if (! $package) {
            return response()->json([
                'success' => false,
                'message' => 'Selected package is not available.',
            ], 422);
        }

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $subscription->load([
            'customer.user',
            'package',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package selected successfully. Payment is required to activate the connection.',
            'data' => $subscription,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff: Single Subscription
    |--------------------------------------------------------------------------
    */

    public function show(Subscription $subscription)
    {
        $subscription->load([
            'customer.user',
            'package',
            'nextPackage',
        ]);

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer: My Subscription
    |--------------------------------------------------------------------------
    */

    public function mySubscription(Request $request)
    {
        $customer = $request->user()
            ->customer()
            ->with([
                'subscription.package',
                'subscription.nextPackage',
            ])
            ->first();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer->subscription,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer: Select Initial Package
    |--------------------------------------------------------------------------
    |
    | Customer নিজেও app থেকে প্রথম package select করতে পারবে।
    |
    */

    public function selectMyPackage(Request $request)
    {
        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],
        ]);

        $customer = $request->user()
            ->customer()
            ->with('subscription')
            ->first();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found.',
            ], 404);
        }

        if ($customer->subscription) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a subscription.',
            ], 422);
        }

        $package = Package::where('id', $validated['package_id'])
            ->where('status', true)
            ->first();

        if (! $package) {
            return response()->json([
                'success' => false,
                'message' => 'Selected package is not available.',
            ], 422);
        }

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $subscription->load('package');

        return response()->json([
            'success' => true,
            'message' => 'Package selected successfully. Complete payment to activate your connection.',
            'data' => $subscription,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Pending Subscription Package Change
    |--------------------------------------------------------------------------
    |
    | First payment-এর আগে package ভুল select করলে change করা যাবে।
    | Active হওয়ার পরে এই endpoint কাজ করবে না।
    |
    */

    public function updatePendingPackage(
        Request $request,
        Subscription $subscription
    ) {
        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],
        ]);

        if ($subscription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending subscriptions can change the initial package.',
            ], 422);
        }

        $package = Package::where('id', $validated['package_id'])
            ->where('status', true)
            ->first();

        if (! $package) {
            return response()->json([
                'success' => false,
                'message' => 'Selected package is not available.',
            ], 422);
        }

        $subscription->update([
            'package_id' => $package->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully.',
            'data' => $subscription->fresh()->load('package'),
        ]);
    }


    /*
|--------------------------------------------------------------------------
| Customer: Request Package Change
|--------------------------------------------------------------------------
*/

    public function requestMyPackageChange(Request $request)
    {
        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],
        ]);

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

        return $this->schedulePackageChange(
            $customer->subscription,
            $validated['package_id']
        );
    }


    /*
|--------------------------------------------------------------------------
| Staff: Request Package Change
|--------------------------------------------------------------------------
*/

    public function requestPackageChange(
        Request $request,
        Subscription $subscription
    ) {
        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],
        ]);

        return $this->schedulePackageChange(
            $subscription,
            $validated['package_id']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Package Change Business Logic
    |--------------------------------------------------------------------------
    */

    private function schedulePackageChange(
        Subscription $subscription,
        int $packageId
    ) {
        $subscription->load([
            'package',
            'nextPackage',
            'customer.user',
        ]);

        /*
     * Pending subscription হলে initial package update endpoint
     * ব্যবহার করবে।
     */
        if ($subscription->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Complete the first payment before requesting a package change.',
            ], 422);
        }

        if (in_array(
            $subscription->status,
            ['suspended', 'terminated']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription cannot change package.',
            ], 422);
        }

        /*
     * Actual expiry date পার হয়ে গেলে এটা আর scheduled
     * package change নয়।
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
                'success' => false,
                'message' => 'Subscription has expired. Select a package during reactivation.',
            ], 422);
        }

        /*
     * Next cycle already paid হলে package change lock।
     */
        if (
            $subscription->paid_until &&
            $subscription->period_end_at &&
            $subscription->paid_until
            ->gt($subscription->period_end_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Package cannot be changed because the next cycle is already paid.',
            ], 422);
        }

        /*
     * Same package select করা যাবে না।
     */
        if ($subscription->package_id === $packageId) {
            return response()->json([
                'success' => false,
                'message' => 'This is already your current package.',
            ], 422);
        }

        $package = Package::query()
            ->whereKey($packageId)
            ->where('status', true)
            ->first();

        if (! $package) {
            return response()->json([
                'success' => false,
                'message' => 'Selected package is not available.',
            ], 422);
        }

        $subscription->update([
            'next_package_id' => $package->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package change scheduled successfully.',
            'data' => $subscription
                ->fresh()
                ->load([
                    'package',
                    'nextPackage',
                ]),
        ]);
    }

    public function cancelMyPackageChange(Request $request)
    {
        $customer = $request->user()
            ->customer()
            ->with('subscription')
            ->first();

        if (! $customer || ! $customer->subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found.',
            ], 404);
        }

        return $this->cancelScheduledPackageChange(
            $customer->subscription
        );
    }


    public function cancelPackageChange(
        Subscription $subscription
    ) {
        return $this->cancelScheduledPackageChange(
            $subscription
        );
    }


    private function cancelScheduledPackageChange(
        Subscription $subscription
    ) {
        if (! $subscription->next_package_id) {
            return response()->json([
                'success' => false,
                'message' => 'No package change is scheduled.',
            ], 422);
        }

        /*
     * Next cycle payment হয়ে গেলে আর cancel নয়।
     */
        if (
            $subscription->paid_until &&
            $subscription->period_end_at &&
            $subscription->paid_until
            ->gt($subscription->period_end_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Package change cannot be cancelled because the next cycle is already paid.',
            ], 422);
        }

        $subscription->update([
            'next_package_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package change cancelled successfully.',
            'data' => $subscription
                ->fresh()
                ->load([
                    'package',
                    'nextPackage',
                ]),
        ]);
    }
}
