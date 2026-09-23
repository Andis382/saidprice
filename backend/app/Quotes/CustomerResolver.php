<?php

namespace App\Quotes;

use App\Models\Customer;
use App\Support\Phones;

/**
 * Finds the customer a quote is for, or adds them. Only a phone number identifies someone:
 * two "Znj. Hoxha" are two people, and a quote must never reach the wrong one.
 */
class CustomerResolver
{
    /**
     * @param  array{id?: ?int, name?: ?string, phone?: ?string, address?: ?string, locale?: ?string}  $data
     */
    public function resolve(array $data, string $defaultLocale): ?Customer
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = Phones::normalize($data['phone'] ?? null);
        $address = trim((string) ($data['address'] ?? '')) ?: null;
        $locale = in_array($data['locale'] ?? null, ['en', 'sq'], true) ? $data['locale'] : null;

        if (! empty($data['id'])) {
            // Editing the customer on the quote edits the customer.
            $customer = Customer::findOrFail($data['id']);
            $customer->fill(['phone' => $phone, 'address' => $address]);
            if ($name !== '') {
                $customer->name = $name;
            }
            if ($locale) {
                $customer->locale = $locale;
            }
            $customer->save();

            return $customer;
        }

        if ($name === '' && $phone === null) {
            return null;
        }
        $existing = $phone ? Customer::where('phone', $phone)->first() : null;
        if ($existing) {
            $existing->address ??= $address;
            $existing->save();

            return $existing;
        }

        return Customer::create([
            'name' => $name !== '' ? $name : '+'.$phone,
            'phone' => $phone,
            'address' => $address,
            'locale' => $locale ?? $defaultLocale,
        ]);
    }
}
