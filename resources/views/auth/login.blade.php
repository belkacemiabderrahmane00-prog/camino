<x-guest-layout>
    <div class="space-y-5">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600 dark:text-slate-500">Connexion</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                Retrouve ton espace CAMINO
            </h1>
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                Connecte-toi pour générer des parcours, enregistrer des lieux et suivre ton activité.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-2 text-xs" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" value="Adresse e-mail" class="text-[11px] text-slate-700 dark:text-slate-300" />
                <x-text-input
                    id="email"
                    class="block mt-1 w-full rounded-2xl border bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-500"
                    type="email"
                    name="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-1 text-[11px]" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" value="Mot de passe" class="text-[11px] text-slate-700 dark:text-slate-300" />

                <x-text-input
                    id="password"
                    class="block mt-1 w-full rounded-2xl border bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-500"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-1 text-[11px]" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between mt-1">
                <label for="remember_me" class="inline-flex items-center gap-2 text-[11px] text-slate-300">
                    <input
                        id="remember_me"
                        type="checkbox"
                        class="rounded border-slate-600 bg-slate-900 text-primary focus:ring-primary"
                        name="remember"
                    >
                    <span>Se souvenir de moi</span>
                </label>

                @if (Route::has('password.request'))
                    <a
                        class="text-[11px] text-slate-400 hover:text-primary transition"
                        href="{{ route('password.request') }}"
                    >
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>

            <div class="flex items-center justify-between pt-2">
                <a
                    href="{{ route('register') }}"
                    class="text-[11px] text-slate-400 hover:text-primary transition"
                >
                    Pas encore de compte ? Créer un profil
                </a>

                <x-ui.button variant="primary" size="md" class="rounded-full text-xs">
                    Se connecter
                </x-ui.button>
            </div>
        </form>
    </div>
</x-guest-layout>
