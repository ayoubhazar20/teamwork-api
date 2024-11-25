<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;

class UserList extends Component
{
    public $users = [];
    public $name, $email, $userId, $firstName, $lastName;

    public function mount($companyId)
    {
        // Fetch users associated with the company ID
        $this->users = User::where('portal_id', $companyId)->get();
    }

    public function editUser($id)
    {
        $user = User::find($id);
        if ($user) {
            $this->userId = $user->user_id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->firstName = $user->first_name;
            $this->lastName = $user->last_name;
        }
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email',
            'firstName' => 'required',
            'lastName' => 'required',
        ]);

        User::where('user_id', $this->userId)->update([
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
        ]);

        $this->resetFields();
        $this->users = User::where('portal_id', $this->companyId)->get(); // Refresh the user list
    }

    public function disableUser($id)
    {
        User::where('user_id', $id)->update(['active' => false]);
        $this->users = User::where('portal_id', $this->companyId)->get(); // Refresh the user list
    }

    public function resetFields()
    {
        $this->name = '';
        $this->email = '';
        $this->firstName = '';
        $this->lastName = '';
        $this->userId = null;
    }

    public function render()
    {
        return view('livewire.user-list', [
            'users' => $this->users
        ]);
    }
}