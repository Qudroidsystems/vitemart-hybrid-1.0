<?php

namespace App\Services\MultiBranch;

use App\Models\Branch;
use App\Models\BranchEnrollmentCode;
use App\Models\BranchInstallation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * HQ side of branch enrollment:
 *   admin issues a one-time code → branch server presents it once → gets token + secret.
 */
class EnrollmentService
{
    /** Returns the plain code (shown once). */
    public function issueCode(Branch $branch): string
    {
        $code = strtoupper(implode('-', str_split(Str::random(12), 4))); // e.g. 7KQ2-M9XA-PL3D
        BranchEnrollmentCode::create([
            'branch_id' => $branch->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addHours((int) config('vitemart.enrollment_code_ttl_hours', 24)),
            'created_by' => auth()->id(),
        ]);
        AuditLogger::log('enrollment_code', "Enrollment code issued for branch {$branch->code}", 'branches', $branch);
        return $code;
    }

    /**
     * @return array{installation: BranchInstallation, token: string, secret: string}
     */
    public function enroll(string $code, string $branchCode, ?string $fingerprint, ?string $appVersion, ?string $ip = null): array
    {
        return DB::transaction(function () use ($code, $branchCode, $fingerprint, $appVersion, $ip) {
            $row = BranchEnrollmentCode::where('code_hash', hash('sha256', strtoupper(trim($code))))->lockForUpdate()->first();
            if (!$row || $row->used_at || $row->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'This enrollment code is invalid, already used or expired.']);
            }
            $branch = Branch::find($row->branch_id);
            if (!$branch || strcasecmp($branch->code, $branchCode) !== 0) {
                throw ValidationException::withMessages(['branch_code' => 'The code was issued for a different branch.']);
            }
            if ($branch->status !== 'active') {
                throw ValidationException::withMessages(['branch_code' => 'This branch is not active.']);
            }

            // A new server for this branch replaces the old one.
            BranchInstallation::where('branch_id', $branch->id)->where('status', 'active')
                ->update(['status' => 'revoked', 'revoked_at' => now()]);

            $token = Str::random(48);
            $secret = Str::random(64);
            $inst = BranchInstallation::create([
                'branch_id' => $branch->id,
                'token_hash' => hash('sha256', $token),
                'hmac_secret' => $secret,
                'fingerprint' => $fingerprint,
                'status' => 'active',
                'enrolled_at' => now(),
                'app_version' => $appVersion,
                'last_ip' => $ip,
            ]);
            $row->update(['used_at' => now(), 'used_by_installation_id' => $inst->id]);

            AuditLogger::log('enrolled', "Branch server enrolled for {$branch->code}", 'branches', $branch, null,
                ['installation' => $inst->uid, 'ip' => $ip]);

            return ['installation' => $inst, 'token' => $token, 'secret' => $secret];
        });
    }

    public function revoke(BranchInstallation $inst): void
    {
        $inst->update(['status' => 'revoked', 'revoked_at' => now()]);
        AuditLogger::log('revoked', 'Branch server credentials revoked', 'branches', $inst->branch);
    }
}
