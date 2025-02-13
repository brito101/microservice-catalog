<?php

namespace Core\UseCase\DTO\Category\UpdateCategory;

class CategoryUpdateInputDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}
}
