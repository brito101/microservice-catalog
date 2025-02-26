<?php

namespace Tests\Unit\App\Models;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

abstract class ModelTestCase extends TestCase
{
    abstract protected function model(): Model;

    abstract protected function traits(): array;

    abstract protected function fillables(): array;

    abstract protected function casts(): array;

    public function test_if_use_traits()
    {
        $traitsNeed = $this->traits();

        $traitsUsed = array_keys(class_uses($this->model()));

        $this->assertEquals($traitsNeed, $traitsUsed);
    }

    public function test_fillables()
    {
        $expected = $this->fillables();

        $fillable = $this->model()->getFillable();

        $this->assertEquals($expected, $fillable);
    }

    public function test_incrementing_is_false()
    {
        $model = $this->model();

        $this->assertFalse($model->incrementing);
    }

    public function test_has_casts()
    {
        $excepectedCasts = $this->casts();

        $casts = $this->model()->getCasts();

        $this->assertEquals($excepectedCasts, $casts);
    }
}
