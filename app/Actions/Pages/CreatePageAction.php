<?php

namespace App\Actions\Pages;

use App\Actions\Concerns\Transacts;
use App\Data\Pages\PageData;
use App\Models\Page;
use Illuminate\Support\Facades\Auth;

class CreatePageAction
{
    use Transacts;

    public function execute(PageData $data): Page
    {
        return $this->tx(function () use ($data) {
            $userId = Auth::id();

            return Page::create([
                'title' => $data->title,
                'slug' => $data->slug ?: Page::generateUniqueSlug($data->title),
                'status' => $data->status,
                'template' => $data->template,
                'title_banner' => $data->title_banner,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
                'og_image_path' => $data->og_image_path,
                'noindex' => $data->noindex,
                'publish_at' => $data->publish_at,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }
}
