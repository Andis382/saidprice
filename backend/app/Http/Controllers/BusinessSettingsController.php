<?php

namespace App\Http\Controllers;

use App\Models\BusinessSettings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Trade, validity, follow-up timing and the standard terms printed on every quote. */
class BusinessSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(BusinessSettings::for($request->user()->organization)->toApi());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::OWNER), 403, __('errors.forbidden'));
        $data = $request->validate([
            'trade' => ['nullable', 'string', 'max:120'],
            'defaultValidityDays' => ['required', 'integer', 'min:1', 'max:90'],
            'firstNudgeHours' => ['required', 'integer', 'min:12', 'max:336'],
            'secondNudgeDays' => ['required', 'integer', 'min:2', 'max:30'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ]);
        $settings = BusinessSettings::for($request->user()->organization);
        $settings->update([
            'trade' => trim((string) ($data['trade'] ?? '')) ?: null,
            'default_validity_days' => $data['defaultValidityDays'],
            'first_nudge_hours' => $data['firstNudgeHours'],
            'second_nudge_days' => $data['secondNudgeDays'],
            'terms' => trim((string) ($data['terms'] ?? '')) ?: null,
        ]);

        return response()->json($settings->toApi());
    }
}
