<x-guest-layout>
    <div class="space-y-5">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600 dark:text-slate-500">Inscription</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                Crée ton profil d’explorateur
            </h1>
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                Un compte CAMINO te permet de garder tes favoris, générer des parcours personnalisés
                et retrouver ton historique.
            </p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <!-- Name -->
            <div>
                <x-input-label for="name" value="Prénom ou pseudo" class="text-[11px] text-slate-700 dark:text-slate-300" />
                <x-text-input
                    id="name"
                    class="block mt-1 w-full rounded-2xl border bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-500"
                    type="text"
                    name="name"
                    :value="old('name')"
                    required
                    autofocus
                    autocomplete="name"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-1 text-[11px]" />
            </div>

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
                    autocomplete="new-password"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-1 text-[11px]" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" value="Confirmer le mot de passe" class="text-[11px] text-slate-700 dark:text-slate-300" />

                <x-text-input
                    id="password_confirmation"
                    class="block mt-1 w-full rounded-2xl border bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder:text-slate-500"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-[11px]" />
            </div>

            <div class="flex items-center justify-between pt-2">
                <a
                    class="text-[11px] text-slate-400 hover:text-primary transition"
                    href="{{ route('login') }}"
                >
                    Déjà un compte ? Se connecter
                </a>

                <x-ui.button variant="accent" size="md" class="rounded-full text-xs">
                    Créer mon compte
                </x-ui.button>
            </div>
        </form>
    </div>
</x-guest-layout>
