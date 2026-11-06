<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class OrderTag extends Model
{
    protected $table = 'order_tags';

    protected $fillable = [
        'name',
        'slug',
        'colour',
    ];

    /**
     * Auto-generate slug from name if not provided.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug) && !empty($tag->name)) {
                $tag->slug = Str::slug($tag->name);
            }
            if (empty($tag->colour)) {
                $tag->colour = '#6B7280';
            }
        });
    }

    /**
     * Many-to-many to Order via the order_tag pivot.
     * We define the relationship here (not on Order.php) to avoid touching
     * the existing Order model.
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_tag', 'tag_id', 'order_id')
            ->withTimestamps();
    }
}
