<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'parent_id',
        'name',
        'slug',
        'description',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'folder_page');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Get the full path for this folder including parent paths
     * Example: /vietnam/halong or /vietnam
     */
    public function getPath(): string
    {
        $parts = [$this->slug];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($parts, $parent->slug);
            $parent = $parent->parent;
        }

        return '/' . implode('/', $parts);
    }
}