<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoasRelationManager extends RelationManager
{
    protected static string $relationship = 'coas';

    protected static ?string $title = 'Certificates of Analysis';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('batch_number')
                    ->required()
                    ->maxLength(100)
                    ->hintIcon(Heroicon::InformationCircle, 'Manufacturing batch/lot number this certificate covers.'),
                DatePicker::make('issued_at')
                    ->label('Issued date')
                    ->hintIcon(Heroicon::InformationCircle, 'Date the certificate was issued by the lab.'),
                FileUpload::make('file_path')
                    ->label('Certificate file')
                    ->required()
                    ->disk('public')
                    ->directory('catalog/coas')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->columnSpanFull()
                    ->hintIcon(Heroicon::InformationCircle, 'PDF or image of the certificate of analysis. Max 10 MB.'),
                Toggle::make('is_visible')
                    ->label('Visible on storefront')
                    ->default(true)
                    ->hintIcon(Heroicon::InformationCircle, 'When on, the certificate is listed on the public product detail API.'),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull()
                    ->hintIcon(Heroicon::InformationCircle, 'Internal notes about this batch/certificate. Not shown publicly.'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_number')
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable(),
                TextColumn::make('file_type')
                    ->label('Type')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_visible')->boolean()->label('Visible'),
                TextColumn::make('creator.name')
                    ->label('Uploaded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Uploaded')->since()->sortable(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Upload COA'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
