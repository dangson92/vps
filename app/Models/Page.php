<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'path',
        'filename',
        'content',
        'title',
        'meta_description',
        'template_type',
        'template_data',
        'primary_folder_id'
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function folders()
    {
        return $this->belongsToMany(Folder::class, 'folder_page');
    }

    public function primaryFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'primary_folder_id');
    }

    public function getFullPath(): string
    {
        return $this->website->getDocumentRoot() . '/' . $this->filename;
    }

    protected $casts = [
        'template_data' => 'array',
    ];
}