<button style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;" {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[48px] items-center justify-center px-5 py-3.5 bg-slate-900 border border-slate-900 rounded-xl font-bold text-[15px] text-white hover:bg-slate-800 active:bg-slate-950 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
