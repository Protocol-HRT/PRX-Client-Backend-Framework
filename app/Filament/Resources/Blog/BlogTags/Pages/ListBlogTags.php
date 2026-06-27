<?php

namespace App\Filament\Resources\Blog\BlogTags\Pages;

use App\Filament\Resources\Blog\BlogTags\BlogTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogTags extends ListRecords
{
    protected static string $resource = BlogTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
