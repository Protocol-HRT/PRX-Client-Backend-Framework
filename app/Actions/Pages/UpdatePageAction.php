<?php

namespace App\Actions\Pages;

use App\Actions\Concerns\Transacts;
use App\Data\Pages\PageData;
use App\Models\Page;
use Illuminate\Support\Facades\Auth;

class UpdatePageAction
{
    use Transacts;

    public function execute(Page $page, PageData $data): Page
    {
        return $this->tx(function () use ($page, $data) {
            $page->update([
                'title' => $data->title,
                'slug' => $data->slug ?: Page::generateUniqueSlug($data->title, $page->id),
                'status' => $data->status,
                'template' => $data->template,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'noindex' => $data->noindex,
                'publish_at' => $data->publish_at,
                'updated_by' => Auth::id(),
            ]);

            return $page->fresh();
        });
    }
}
