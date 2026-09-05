<x-app-layout title="Mon profil">
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12 space-y-6">
        <div>
            <p class="eyebrow mb-1.5">Compte</p>
            <h1 class="display text-4xl">Mon profil</h1>
        </div>
        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-password-form')
        </div>
        <div class="card p-6 sm:p-8 border-coral/20">
            @include('profile.partials.delete-user-form')
        </div>
    </section>
</x-app-layout>
