<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-primary text-slate-900 border border-transparent rounded-2xl font-semibold text-xs uppercase tracking-widest hover:bg-cyan-300 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 transition ease-in-out duration-150 shadow-lg shadow-primary/30']) }}>
    {{ $slot }}
</button>
