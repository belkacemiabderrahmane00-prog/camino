<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-slate-900/80 border border-slate-300 dark:border-slate-700 rounded-2xl font-semibold text-xs text-slate-700 dark:text-slate-200 uppercase tracking-widest shadow-sm hover:bg-slate-50 dark:hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
