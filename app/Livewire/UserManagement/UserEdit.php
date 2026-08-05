<?php

namespace App\Livewire\UserManagement;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class UserEdit extends Component
{
    public User $user;
    public string $name = '';
    public string $email = '';
    public ?string $password = null;
    public string $role = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
        ];
    }

    public function update(): void
    {
        $this->validate();

        $data = ['name' => $this->name, 'email' => $this->email, 'role' => $this->role];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $this->user->update($data);

        $this->redirect(route('users.index'));
    }

    public function render(): View
    {
        return view('pages.user-management.form');
    }
}