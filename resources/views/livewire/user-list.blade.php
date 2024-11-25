<div class="container">
    <h1>Manage Users</h1>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <button wire:click="edit({{ $user->user_id }})" class="btn btn-warning">Edit</button>
                        <button wire:click="disableUser({{ $user->user_id }})" class="btn btn-danger">Disable</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- User Edit Form -->
    <h3>Edit User</h3>
    <form wire:submit.prevent="updateUser">
        <input type="hidden" wire:model="userId">
        <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" wire:model="name">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" wire:model="email">
        </div>
        <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" wire:model="firstName">
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" wire:model="lastName">
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
    </form>
</div>
