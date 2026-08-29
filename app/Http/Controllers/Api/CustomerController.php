<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Customer List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $search = $request->query('search');

        $customers = Customer::query()
            ->with('user')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {

                    $query->where(
                        'customer_code',
                        'like',
                        "%{$search}%"
                    );

                    $query->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Create Customer
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $customer = DB::transaction(function () use ($validated) {

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'customer',
                'status' => true,
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $customer->update([
                'customer_code' => 'CUS-' . str_pad(
                    $customer->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            return $customer->load('user');
        });

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Show Customer
    |--------------------------------------------------------------------------
    */

    public function show(Customer $customer)
    {
        $customer->load('user');

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Customer
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->ignore($customer->user_id),
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')
                    ->ignore($customer->user_id),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $customer
        ) {

            $customer->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);

            $customer->update([
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => $customer->fresh()->load('user'),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Customer Account
    |--------------------------------------------------------------------------
    */

    public function changeStatus(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'boolean',
            ],
        ]);

        $customer->user->update([
            'status' => $validated['status'],
        ]);

        /*
         * Customer account disable করলে
         * তার existing login token remove করে দিচ্ছি।
         */
        if (! $validated['status']) {
            $customer->user->tokens()->delete();
        }

        return response()->json([
            'success' => true,

            'message' => $validated['status']
                ? 'Customer account activated successfully.'
                : 'Customer account deactivated successfully.',

            'data' => $customer->fresh()->load('user'),
        ]);
    }
}
