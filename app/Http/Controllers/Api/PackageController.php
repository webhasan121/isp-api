<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin: Get All Packages
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $packages = Package::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer: Get Active Packages
    |--------------------------------------------------------------------------
    */

    public function activePackages()
    {
        $packages = Package::where('status', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin: Create Package
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:packages,name',
            ],

            'speed_mbps' => [
                'required',
                'integer',
                'min:1',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated['status'] = $validated['status'] ?? true;

        $package = Package::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully.',
            'data' => $package,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin: Show Single Package
    |--------------------------------------------------------------------------
    */

    public function show(Package $package)
    {
        return response()->json([
            'success' => true,
            'data' => $package,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin: Update Package
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('packages', 'name')
                    ->ignore($package->id),
            ],

            'speed_mbps' => [
                'required',
                'integer',
                'min:1',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ]);

        $package->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully.',
            'data' => $package->fresh(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin: Enable / Disable Package
    |--------------------------------------------------------------------------
    */

    public function changeStatus(Request $request, Package $package)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'boolean',
            ],
        ]);

        $package->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $package->status
                ? 'Package activated successfully.'
                : 'Package deactivated successfully.',

            'data' => $package,
        ]);
    }
}
