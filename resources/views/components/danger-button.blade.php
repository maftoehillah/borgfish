<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[48px] items-center justify-center px-4 py-3.5 bg-rose-600 border border-transparent rounded-xl font-bold text-[15px] text-white hover:bg-rose-500 active:bg-rose-700 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
