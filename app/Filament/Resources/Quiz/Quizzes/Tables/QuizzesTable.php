<?php

namespace App\Filament\Resources\Quiz\Quizzes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuizzesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->copyable()->toggleable(),
                TextColumn::make('steps_count')->label('Steps')->counts('steps'),
                TextColumn::make('questions_count')->label('Questions')->counts('questions'),
                IconColumn::make('is_default')->boolean()->label('Default'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->defaultSort('is_default', 'desc')
            ->filters([TernaryFilter::make('is_active')])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
