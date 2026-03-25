<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class Product extends Model
{
    public int $id;

    public string $name;

    public string $content;

    public string $price;

    public int $category_id;

    public int $in_stock;

    public string $created_at;

    public string $updated_at;

    public function initialize(): void
    {
        $this->setSource('products');

        $this->belongsTo('category_id', Category::class, 'id', [
            'alias' => 'category',
            'reusable' => true,
        ]);
    }
}
