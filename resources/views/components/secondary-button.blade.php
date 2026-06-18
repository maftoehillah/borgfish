<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-[48px] items-center justify-center px-4 py-3.5 bg-white border border-slate-200 rounded-xl font-bold text-[15px] text-slate-700 shadow-sm hover:bg-slate-50 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
