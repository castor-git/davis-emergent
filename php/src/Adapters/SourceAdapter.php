<?php
namespace App\Adapters;

interface SourceAdapter {
    public function slug(): string;
    public function label(): string;
    /** @return iterable<array> yields normalized video arrays */
    public function fetch(int $limit = 100): iterable;
}
