<?php

namespace App\Filament\Resources\ListingComments\Pages;

use App\Filament\Resources\ListingComments\ListingCommentResource;
use Filament\Resources\Pages\ListRecords;

class ListListingComments extends ListRecords
{
    protected static string $resource = ListingCommentResource::class;

    public function getSubheading(): ?string
    {
        return 'Yorumlar onay beklemeden yayınlanır; uygunsuz olanları buradan silebilirsiniz.';
    }
}
