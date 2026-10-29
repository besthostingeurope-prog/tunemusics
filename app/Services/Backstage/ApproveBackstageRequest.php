<?php

namespace App\Services\Backstage;

use App\Models\BackstageRequest;
use App\Notifications\BackstageRequestWasHandled;
use Common\Auth\Roles\Role;
use Common\Billing\Models\Product;
use Common\Billing\Subscription;
use Exception;
use Illuminate\Support\Facades\Log;

class ApproveBackstageRequest
{
    public function execute(
        BackstageRequest $backstageRequest,
        array $actionParams,
    ): BackstageRequest {
        if ($backstageRequest->type === 'become-artist') {
            $this->approveBecomeArtistRequest($backstageRequest, $actionParams);
        } elseif ($backstageRequest->type === 'verify-artist') {
            $backstageRequest->artist()->update(['verified' => true]);
        } elseif ($backstageRequest->type === 'claim-artist') {
            $this->approveClaimArtistRequest($backstageRequest, $actionParams);
        }

        $backstageRequest->fill(['status' => 'approved'])->save();
        $backstageRequest->user->notify(
            new BackstageRequestWasHandled(
                $backstageRequest,
                $actionParams['notes'] ?? null,
            ),
        );
        return $backstageRequest;
    }

    private function approveBecomeArtistRequest(
        BackstageRequest $backstageRequest,
        array $actionParams,
    ) {
        if ($backstageRequest->user->primaryArtist()) {
            throw new BackstageApprovalException(
                'This user already has an artist profile.',
            );
        }
        $params = [
            'artist_name' => $backstageRequest->artist_name,
            'image' => $backstageRequest->data['image'] ?? null,
            'verified' => $actionParams['markArtistAsVerified'] ?? false,
        ];
        $artist = $backstageRequest->user->getOrCreateArtist($params);
        $this->attachArtistRoleToUser($backstageRequest);
        $backstageRequest->artist_id = $artist->id;
    }

    private function approveClaimArtistRequest(
        BackstageRequest $backstageRequest,
        array $actionParams,
    ) {
        if (
            $backstageRequest->user
                ->artists()
                ->where('artist_id', $backstageRequest->artist_id)
                ->exists()
        ) {
            throw new BackstageApprovalException(
                'This user is already attached to this artist.',
            );
        }
        $backstageRequest->user
            ->artists()
            ->attach($backstageRequest->artist_id, [
                'role' => $backstageRequest->data['role'] ?? 'artist',
            ]);

        // claimed profiles are always marked as verified
        $backstageRequest->artist()->update(['verified' => true]);

        $this->attachArtistRoleToUser($backstageRequest);
        $this->grantFreeTrial($backstageRequest);
    }

    /**
     * Give the approved claimant 3 months of "Artist Pro" for free
     * (local subscription, no payment gateway involved). It expires on
     * its own after 3 months, locking artist features again until the
     * user subscribes normally.
     */
    private function grantFreeTrial(BackstageRequest $backstageRequest)
    {
        try {
            $user = $backstageRequest->user;

            // don't touch anything if the user already has an active plan
            $existing = $user->subscriptions()->first();
            if ($existing && $existing->valid()) {
                return;
            }

            $product = Product::where('name', 'Artist Pro')->first();
            if (!$product) {
                return;
            }
            $price = $product->prices()->orderBy('amount')->first();

            $attributes = [
                'product_id' => $product->id,
                'price_id' => $price?->id,
                'gateway_name' => 'none',
                'renews_at' => null,
                'ends_at' => now()->addMonths(3),
                'description' => '3 month free Artist Pro trial (claimed profile)',
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                Subscription::create(
                    array_merge($attributes, ['user_id' => $user->id]),
                );
            }
        } catch (\Throwable $e) {
            // a trial failure should never block the claim approval itself
            Log::error(
                'Failed to grant free artist trial: ' . $e->getMessage(),
            );
        }
    }

    private function attachArtistRoleToUser(BackstageRequest $backstageRequest)
    {
        $artistRole = Role::where('artists', true)->first();
        if (
            !$backstageRequest->user
                ->roles()
                ->where('artists', true)
                ->exists()
        ) {
            $backstageRequest->user->roles()->attach($artistRole);
        }
    }
}

class BackstageApprovalException extends Exception
{
}
