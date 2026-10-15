<?php

namespace App\Services;

use App\Models\Bidder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerAdminService
{
    public function __construct(private AuctionBidService $bids) {}

    /**
     * @param  array{name?: string|null, phone?: string|null, email?: string|null, whatsapp?: string|null}  $input
     */
    public function create(array $input, ?User $admin = null): Bidder
    {
        $data = $this->validated($input);

        $bidder = DB::transaction(function () use ($data): Bidder {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make(Str::random(40)),
            ]);

            return Bidder::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'whatsapp_number' => $data['whatsapp'],
            ]);
        });

        AuditLogger::log('customers', 'customer created by admin', 'customer_created', $bidder, [
            'phone' => $bidder->phone,
            'created_by' => $admin?->id,
        ]);

        return $bidder;
    }

    /**
     * @param  array{name?: string|null, phone?: string|null, email?: string|null, whatsapp?: string|null}  $input
     */
    public function update(Bidder $bidder, array $input, ?User $admin = null): Bidder
    {
        $this->guardStaff($bidder);

        $data = $this->validated($input, $bidder);
        $phoneChanged = $data['phone'] !== $bidder->phone;

        DB::transaction(function () use ($bidder, $data, $phoneChanged): void {
            $bidder->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'whatsapp_number' => $data['whatsapp'],
            ]);

            if ($bidder->user !== null) {
                $bidder->user->forceFill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'phone_verified_at' => $phoneChanged ? null : $bidder->user->phone_verified_at,
                ])->save();
            }
        });

        AuditLogger::log('customers', 'customer updated by admin', 'customer_updated', $bidder, [
            'phone' => $bidder->phone,
            'phone_changed' => $phoneChanged,
            'updated_by' => $admin?->id,
        ]);

        return $bidder->fresh(['user']);
    }

    /**
     * Reasons the customer cannot be deleted. Empty when deletion is allowed.
     *
     * @return list<string>
     */
    public function deletionBlockers(Bidder $bidder): array
    {
        $blockers = [];

        if ($bidder->user?->canAccessAdmin()) {
            $blockers[] = 'This number belongs to a staff account. Manage it from Staff.';
        }

        $paidBids = $bidder->bids()->where('status', 'accepted')->count();

        if ($paidBids > 0) {
            $blockers[] = 'Has '.$paidBids.' paid '.Str::plural('bid', $paidBids).'. Paid participation fees must stay on record.';
        }

        if ($bidder->user_id !== null) {
            $orders = Order::query()->where('user_id', $bidder->user_id)->count();

            if ($orders > 0) {
                $blockers[] = 'Has '.$orders.' shop '.Str::plural('order', $orders).'.';
            }

            $listings = Product::query()->where('seller_user_id', $bidder->user_id)->count();

            if ($listings > 0) {
                $blockers[] = 'Has '.$listings.' guest shop '.Str::plural('listing', $listings).'.';
            }
        }

        return $blockers;
    }

    public function delete(Bidder $bidder, ?User $admin = null): void
    {
        $blockers = $this->deletionBlockers($bidder);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['customer' => $blockers[0]]);
        }

        $snapshot = ['name' => $bidder->name, 'phone' => $bidder->phone, 'deleted_by' => $admin?->id];

        DB::transaction(function () use ($bidder): void {
            $user = $bidder->user;

            $bidder->delete();

            if ($user !== null) {
                $user->verificationDocuments()->get()->each->delete();
                $user->delete();
            }
        });

        AuditLogger::log('customers', 'customer deleted by admin', 'customer_deleted', null, $snapshot);
    }

    /**
     * @param  array{name?: string|null, phone?: string|null, email?: string|null, whatsapp?: string|null}  $input
     * @return array{name: string, phone: string, email: string|null, whatsapp: string|null}
     */
    private function validated(array $input, ?Bidder $bidder = null): array
    {
        $data = Validator::validate($input, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
        ], [], ['whatsapp' => 'WhatsApp number']);

        $phone = $this->bids->normalizeKenyanPhone($data['phone']);
        $whatsapp = $this->normalizeWhatsapp($data['whatsapp'] ?? null);
        $email = filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null;

        Validator::validate(['phone' => $phone, 'email' => $email], [
            'phone' => [
                Rule::unique(Bidder::class, 'phone')->ignore($bidder?->id),
                Rule::unique(User::class, 'phone')->ignore($bidder?->user_id),
            ],
            'email' => ['nullable', Rule::unique(User::class, 'email')->ignore($bidder?->user_id)],
        ], [
            'phone.unique' => 'Another customer already uses this mobile number.',
            'email.unique' => 'Another account already uses this email.',
        ]);

        return [
            'name' => trim($data['name']),
            'phone' => $phone,
            'email' => $email,
            'whatsapp' => $whatsapp,
        ];
    }

    private function normalizeWhatsapp(?string $whatsapp): ?string
    {
        if (blank($whatsapp)) {
            return null;
        }

        try {
            return $this->bids->normalizeKenyanPhone($whatsapp);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['whatsapp' => 'Enter a valid Kenyan WhatsApp number.']);
        }
    }

    private function guardStaff(Bidder $bidder): void
    {
        if ($bidder->user?->canAccessAdmin()) {
            throw ValidationException::withMessages(['customer' => 'This number belongs to a staff account. Manage it from Staff.']);
        }
    }
}
