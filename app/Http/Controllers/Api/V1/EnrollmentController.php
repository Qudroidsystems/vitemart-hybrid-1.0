<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function store(Request $request, EnrollmentService $service)
    {
        if (!Install::isCentral()) {
            return response()->json(['error' => ['code' => 'not_central', 'message' => 'Not a headquarters installation.']], 404);
        }
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'branch_code' => 'required|string|max:10',
            'fingerprint' => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:20',
        ]);

        try {
            $r = $service->enroll($data['code'], $data['branch_code'], $data['fingerprint'] ?? null, $data['app_version'] ?? null, $request->ip());
        } catch (ValidationException $e) {
            return response()->json(['error' => ['code' => 'enrollment_refused', 'message' => collect($e->errors())->flatten()->first(), 'details' => $e->errors()]], 422);
        }

        $org = Install::organization();
        return response()->json([
            'installation_uid' => $r['installation']->uid,
            'token' => $r['token'],
            'hmac_secret' => $r['secret'],
            'organization' => $org?->only(['uid', 'name', 'currency_code', 'timezone', 'prices_include_tax', 'vat_rate']),
            'branch' => $r['installation']->branch->toSync(),
            'hq_branch' => Install::branch()?->toSync(),
            'server_time' => now()->toIso8601String(),
            'app_version' => config('vitemart.version'),
        ], 201);
    }
}
