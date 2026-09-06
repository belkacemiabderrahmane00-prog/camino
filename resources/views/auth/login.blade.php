<x-guest-layout title="{{ __('Connexion') }}">
    <p class="eyebrow mb-2">{{ __('Bon retour') }}</p>
    <h1 class="display text-4xl">{{ __('Connexion') }}</h1>
    <p class="mt-2 text-sm text-ink-muted">{{ __('Retrouve tes favoris, tes parcours et ton profil culturel.') }}</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">{{ __('E-mail') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <div class="flex items-center justify-between"><label class="label" for="password">{{ __('Mot de passe') }}</label>
                @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="text-xs text-ink-muted hover:text-ink mb-1.5">{{ __('Oublié ?') }}</a>@endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="field">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="remember" class="rounded border-ink/20 text-coral focus:ring-coral">{{ __('Se souvenir de moi') }}</label>
        <button type="submit" class="btn btn-lg btn-primary w-full">{{ __('Se connecter') }}</button>
    </form>
    <p class="mt-6 text-sm text-ink-muted text-center">{{ __('Pas encore de compte ?') }} <a href="{{ route('register') }}" class="font-semibold text-ink underline">{{ __('Créer un compte') }}</a></p>
    <p class="mt-2 text-xs text-ink-muted text-center"><a href="{{ route('home') }}" class="hover:text-ink">{{ __('← Retour à l\'accueil') }}</a></p>
</x-guest-layout>
