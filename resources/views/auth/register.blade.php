<x-guest-layout title="Créer un compte">
    <p class="eyebrow mb-2">Bienvenue</p>
    <h1 class="display text-4xl">Créer un compte</h1>
    <p class="mt-2 text-sm text-ink-muted">Gratuit. Favoris, parcours enregistrés, avis, alertes et recommandations personnalisées.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Prénom ou pseudo</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="field">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="field">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="label" for="password">Mot de passe</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="field">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirmation</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="field">
            </div>
        </div>
        <button type="submit" class="btn btn-lg btn-primary w-full">Créer mon compte</button>
    </form>
    <p class="mt-6 text-sm text-ink-muted text-center">Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-ink underline">Connexion</a></p>
</x-guest-layout>
