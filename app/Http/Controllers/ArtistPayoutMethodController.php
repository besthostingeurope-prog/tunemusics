<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\ArtistPayoutMethod;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ArtistPayoutMethodController extends BaseController
{
    public function index()
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('show', $artist);

        $methods = ArtistPayoutMethod::where('artist_id', $artist->id)->get();

        return $this->success(['methods' => $methods]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('update', $artist);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['bank_transfer', 'paypal', 'stripe'])],
            'account_holder_name' => 'required_if:type,bank_transfer|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'required_if:type,bank_transfer|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'paypal_email' => 'required_if:type,paypal|email|max:255',
            'country' => 'nullable|string|max:2',
            'is_default' => 'boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            ArtistPayoutMethod::where('artist_id', $artist->id)
                ->update(['is_default' => false]);
        }

        $method = ArtistPayoutMethod::create([
            ...$validated,
            'artist_id' => $artist->id,
            'is_verified' => false, // Admin must verify
        ]);

        return $this->success(['method' => $method]);
    }

    public function update(Request $request, ArtistPayoutMethod $payoutMethod)
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('update', $artist);

        if ($payoutMethod->artist_id !== $artist->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'type' => [Rule::in(['bank_transfer', 'paypal', 'stripe'])],
            'account_holder_name' => 'string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'paypal_email' => 'email|max:255',
            'country' => 'nullable|string|max:2',
            'is_default' => 'boolean',
        ]);

        // Remove empty IBAN and account_number to keep existing values
        if (empty($validated['iban'])) {
            unset($validated['iban']);
        }
        if (empty($validated['account_number'])) {
            unset($validated['account_number']);
        }

        // If this is set as default, unset other defaults
        if (($validated['is_default'] ?? false) && !$payoutMethod->is_default) {
            ArtistPayoutMethod::where('artist_id', $artist->id)
                ->where('id', '!=', $payoutMethod->id)
                ->update(['is_default' => false]);
        }

        // Reset verification if sensitive fields change
        if (isset($validated['account_number']) || isset($validated['iban']) || isset($validated['paypal_email'])) {
            $validated['is_verified'] = false;
        }

        $payoutMethod->update($validated);

        return $this->success(['method' => $payoutMethod]);
    }

    public function destroy(ArtistPayoutMethod $payoutMethod)
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('update', $artist);

        if ($payoutMethod->artist_id !== $artist->id) {
            abort(403, 'Unauthorized');
        }

        $payoutMethod->delete();

        return $this->success();
    }
}
