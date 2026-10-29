<?php

namespace Common\Pages;

use Common\Files\Actions\SyncFileEntryModels;
use Common\Pages\CustomPage;
use Common\Workspaces\ActiveWorkspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class CrupdatePage
{
    public function execute(CustomPage $page, array $data): CustomPage
    {
        if (!$page->exists) {
            $data['user_id'] = Auth::id();
            $data['slug'] = $data['slug'] ?? slugify(Arr::get($data, 'title'));
            $data['workspace_id'] = app(ActiveWorkspace::class)->id ?? 0;
        }

        $bodyChanged = $page->body !== ($data['body'] ?? null);

        // the page must be saved BEFORE syncing inline images, otherwise
        // a newly created page has no id yet and the file_entry_models
        // insert fails with "model_id cannot be null"
        $page->fill($data)->save();

        // sync inline image entries
        if ($bodyChanged) {
            (new SyncFileEntryModels())->fromHtml(
                $data['body'],
                $page->inlineImages(),
            );
        }

        return $page;
    }
}
