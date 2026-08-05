<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Livewire\Component;

class CategoryCreate extends Component
{
    public ?Category $category = null;
    public string $name = '';
    public ?string $description = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Category::create($this->only(['name', 'description']));

        $this->redirect(route('master.kategori'));
    }

    public function render()
    {
        return view('pages.master.category-form');
    }
}