@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-[48px] border-slate-200 bg-white px-4 py-3.5 text-base text-slate-800 placeholder:text-slate-400 focus:border-cyan-400 focus:ring-cyan-400 rounded-xl shadow-sm']) }}>
