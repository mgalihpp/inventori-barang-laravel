<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Livewire\Component;

class CategoryEdit extends Component
{
    public Category $category;
    public string $name = '';
    public ?string $description = null;

    public function mount(): void
    {
        $this->name = $this->category->name;
        $this->description = $this->category->description;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $this->category->id],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->category->update($this->only(['name', 'description']));

        $this->redirect(route('master.kategori'));
    }

    public function render()
    {
        return view('pages.master.category-form');
    }
}