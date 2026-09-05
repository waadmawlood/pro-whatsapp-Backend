<?php

namespace App\Services;

use App\Enums\CustomerChatType;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Support\PhoneNumber;
use App\Support\WhatsAppJid;
use Illuminate\Support\Collection;

class CustomerService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function create(User $actor, array $data): Customer
    {
        $phone = PhoneNumber::normalize($data['phone'] ?? $data['whatsapp_number']);
        $whatsappNumber = PhoneNumber::normalize($data['whatsapp_number'] ?? $phone);

        $customer = Customer::create([
            'company_id' => $actor->company_id,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'name' => $data['name'] ?? null,
            'phone' => $phone,
            'whatsapp_number' => $whatsappNumber,
            'whatsapp_jid' => $data['whatsapp_jid'] ?? null,
            'chat_type' => $data['chat_type'] ?? CustomerChatType::Direct,
            'avatar' => $data['avatar'] ?? null,
            'status' => $data['status'] ?? CustomerStatus::New,
        ]);

        if (! empty($data['tag_ids'])) {
            $customer->tags()->sync($this->ownedTagIds($actor->company_id, $data['tag_ids']));
        }

        $this->auditLogger->log('customer.created', $customer, sprintf('%s created customer #%d', $actor->name, $customer->id));

        return $customer->load(['tags', 'assignedUser', 'whatsappAccount']);
    }

    public function update(Customer $customer, User $actor, array $data): Customer
    {
        $old = $customer->only(['name', 'phone', 'whatsapp_number', 'status', 'assigned_user_id']);

        if (isset($data['phone'])) {
            $data['phone'] = PhoneNumber::normalize($data['phone']);
        }

        if (isset($data['whatsapp_number'])) {
            $data['whatsapp_number'] = PhoneNumber::normalize($data['whatsapp_number']);
        }

        $customer->update($data);

        if (array_key_exists('tag_ids', $data)) {
            $customer->tags()->sync($this->ownedTagIds($actor->company_id, $data['tag_ids'] ?? []));
        }

        $this->auditLogger->log(
            'customer.updated',
            $customer,
            sprintf('%s updated customer #%d', $actor->name, $customer->id),
            $old,
            $customer->only(['name', 'phone', 'whatsapp_number', 'status', 'assigned_user_id']),
        );

        return $customer->fresh(['tags', 'assignedUser', 'whatsappAccount']);
    }

    public function findOrCreateFromWhatsApp(
        WhatsAppAccount $account,
        string $whatsappNumber,
        ?string $name = null,
        ?string $avatar = null,
        ?string $whatsappJid = null,
        CustomerChatType $chatType = CustomerChatType::Direct,
    ): Customer {
        $number = PhoneNumber::normalize($whatsappNumber);

        if ($chatType === CustomerChatType::Group) {
            $whatsappJid = WhatsAppJid::isGroupJid($whatsappJid)
                ? $whatsappJid
                : WhatsAppJid::groupJidFromNumber($number);

            $number = WhatsAppJid::digitsFromJid($whatsappJid) ?? $number;
        }

        $customer = null;

        if ($whatsappJid) {
            $customer = Customer::withoutGlobalScopes()
                ->where('company_id', $account->company_id)
                ->where('whatsapp_jid', $whatsappJid)
                ->first();
        }

        $customer ??= Customer::withoutGlobalScopes()
            ->where('company_id', $account->company_id)
            ->where('whatsapp_number', $number)
            ->first();

        if ($customer) {
            $updates = [];

            if ($name && ($chatType->isGroup() || ! $customer->name)) {
                $updates['name'] = $name;
            }

            if ($avatar && ! $customer->avatar) {
                $updates['avatar'] = $avatar;
            }

            if ($whatsappJid && $customer->whatsapp_jid !== $whatsappJid) {
                $updates['whatsapp_jid'] = $whatsappJid;
            }

            if ($customer->chat_type !== $chatType) {
                $updates['chat_type'] = $chatType;
            }

            if ($updates) {
                $customer->update($updates);
            }

            return $customer;
        }

        return Customer::withoutGlobalScopes()->create([
            'company_id' => $account->company_id,
            'whatsapp_account_id' => $account->id,
            'name' => $name,
            'phone' => $number,
            'whatsapp_number' => $number,
            'whatsapp_jid' => $whatsappJid,
            'chat_type' => $chatType,
            'avatar' => $avatar,
            'status' => CustomerStatus::New,
        ]);
    }

    /**
     * @param  list<int>  $tagIds
     * @return Collection<int, int>
     */
    protected function ownedTagIds(int $companyId, array $tagIds): Collection
    {
        return Tag::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $tagIds)
            ->pluck('id');
    }
}
