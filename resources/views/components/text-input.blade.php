@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/80 text-slate-900 dark:text-slate-100 placeholder:text-slate-500 focus:border-primary focus:ring-primary shadow-sm']) }}>
