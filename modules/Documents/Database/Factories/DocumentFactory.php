<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Documents\Models\Document;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'kind' => DocumentKind::Link,
            'visibility' => DocumentVisibility::Organization,
            'url' => fake()->url(),
            'tags' => [],
        ];
    }

    public function file(string $path = 'documents/test.pdf'): static
    {
        return $this->state([
            'kind' => DocumentKind::File,
            'url' => null,
            'file_path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);
    }

    public function visibility(DocumentVisibility $visibility): static
    {
        return $this->state(['visibility' => $visibility]);
    }
}
