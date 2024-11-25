<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Company;
use App\Models\User;

class CompanyDashboard extends Component
{
    public $companies, $name, $api_key, $access_granted, $selectedCompanyId;
    public $users; // Add a variable to hold the users of the selected company
    public $userId, $userName, $userEmail, $userFirstName, $userLastName;

    public function mount()
    {
        $this->companies = Company::all();
        $this->users = []; // Initialize users
    }

    public function createOrUpdate()
    {
        $this->validate([
            'name' => 'required',
            'api_key' => 'required',
        ]);

        Company::updateOrCreate(
            ['company_id' => $this->selectedCompanyId],
            [
                'name' => $this->name,
                'api_key' => $this->api_key,
                'access_granted' => $this->access_granted
            ]
        );

        $this->resetForm();
        $this->companies = Company::all();
    }

    public function edit($id)
    {
        $company = Company::where('company_id', $id)->first();
        if ($company) {
            $this->name = $company->name;
            $this->api_key = $company->api_key;
            $this->access_granted = $company->access_granted;
            $this->selectedCompanyId = $company->company_id;
            
            // Load users for the selected company
            $this->loadUsers($company->company_id);
        }
    }

    public function loadUsers($companyId)
    {
        $this->users = User::where('portal_id', $companyId)->get();
    }

    public function editUser($id)
    {
        $user = User::find($id);
        if ($user) {
            $this->userId = $user->user_id;
            $this->userEmail = $user->email;
            $this->userFirstName = $user->first_name;
            $this->userLastName = $user->last_name;
        }
    }

    public function updateUser()
    {
        $this->validate([
            'userEmail' => 'required|email',
            'userFirstName' => 'required',
            'userLastName' => 'required',
        ]);

        User::where('user_id', $this->userId)->update([
            'email' => $this->userEmail,
            'first_name' => $this->userFirstName,
            'last_name' => $this->userLastName,
        ]);

        $this->resetUserForm();
        $this->loadUsers($this->selectedCompanyId); // Refresh users
    }

    public function resetForm()
    {
        $this->name = '';
        $this->api_key = '';
        $this->access_granted = true;
        $this->selectedCompanyId = null;
        $this->resetUserForm();
    }

    public function resetUserForm()
    {
        $this->userId = null;
        $this->userName = '';
        $this->userEmail = '';
        $this->userFirstName = '';
        $this->userLastName = '';
    }

    public function toggleAccess($id)
    {
        $company = Company::where('company_id', $id)->first();
        if ($company) {
            $company->access_granted = !$company->access_granted;
            $company->save();
            $this->companies = Company::all();
        }
    }

    public function render()
    {
        return view('livewire.company-dashboard')->layout('layouts.app');
    }
}