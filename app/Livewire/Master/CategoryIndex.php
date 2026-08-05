<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        if ($category->products()->exists()) {
            $this->dispatch('category-delete-error');
            return;
        }

        $category->delete();
        $this->dispatch('refresh-categories');
    }

    public function render()
    {
        $categories = Category::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.categories', ['categories' => $categories]);
    }
}