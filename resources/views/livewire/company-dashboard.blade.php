<div class="container mt-5 mb-5">
    <h1 class="text-center mb-4 font-weight-bold text-uppercase">Gestion des Entreprises</h1>

    <div class="row">
        <div class="col-md-8">
            <h2 class="text-center mb-4">Liste des Entreprises</h2>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th scope="col">Nom de l'entreprise</th>
                            <th scope="col">API Key</th>
                            <th scope="col">Accès Autorisé</th>
                            <th scope="col" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($companies as $company)
                        <tr>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->api_key }}</td>
                            <td>
                                <span class="badge {{ $company->access_granted ? 'badge-success' : 'badge-danger' }}">
                                    {{ $company->access_granted ? 'Activé' : 'Désactivé' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-secondary btn-sm" wire:click="edit('{{ $company->company_id }}')">Modifier</button>
                                <button class="btn btn-warning btn-sm" wire:click="toggleAccess('{{ $company->company_id }}')">
                                    {{ $company->access_granted ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-4">
            <form wire:submit.prevent="createOrUpdate" class="mb-4">
                <input type="hidden" wire:model="selectedCompanyId">

                <div class="form-group">
                    <label for="name">Nom de l'entreprise</label>
                    <input type="text" class="form-control" id="name" wire:model="name" placeholder="Entrez le nom de l'entreprise" required>
                </div>

                <div class="form-group">
                    <label for="api_key">API Key</label>
                    <input type="text" class="form-control" id="api_key" wire:model="api_key" placeholder="Entrez la clé API" required>
                </div>

                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" wire:model="access_granted" id="access_granted">
                    <label class="form-check-label" for="access_granted">Accès autorisé</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sauvegarder</button>
            </form>
        </div>
    </div>

    <!-- Users Section -->
    <div class="row">
        <div class="col-md-8" style="height: 400px; overflow-y: auto;">
            <h2 class="text-center mb-4">Liste des Utilisateurs</h2>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Email</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($users)
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user->first_name }} {{ $user->last_name }} </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <button wire:click="editUser({{ $user->id }})" class="btn btn-warning">Modifier</button>
                                <button class="btn btn-danger btn-sm" wire:click="disableUser({{ $user->user_id }})">Désactiver</button>
                            </td>
                        </tr>
                        @endforeach
                        @else
                        <tr>
                            <td colspan="3" class="text-center">Aucun utilisateur trouvé</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    
        <div class="col-md-4" style="position: sticky; top: 20px;">
            @if($userId)
            <h3>Modifier l'utilisateur</h3>
            <form wire:submit.prevent="updateUser">
                <input type="hidden" wire:model="userId">
            
                <div class="form-group">
                    <label for="user_email">Email</label>
                    <input type="email" class="form-control" id="user_email" wire:model="userEmail" required>
                </div>
                <div class="form-group">
                    <label for="user_first_name">Prénom</label>
                    <input type="text" class="form-control" id="user_first_name" wire:model="userFirstName" required>
                </div>
                <div class="form-group">
                    <label for="user_last_name">Nom de famille</label>
                    <input type="text" class="form-control" id="user_last_name" wire:model="userLastName" required>
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour l'utilisateur</button>
            </form>
            @endif
        </div>
    </div>
    
</div>
