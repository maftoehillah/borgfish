@extends('layouts.app')

@section('title','UI Demo — Borgfish')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold">Design System Demo</h1>

        <section class="panel-surface p-4 rounded-xl">
            <h2 class="font-semibold mb-3">Buttons</h2>
            <div class="flex items-center gap-3">
                <x-ui.button variant="soft">Dana Penjual</x-ui.button>
                <x-ui.button variant="default">Batal</x-ui.button>
                <button class="btn" disabled>Disabled</button>
            </div>
        </section>

        <section class="panel-surface p-4 rounded-xl">
            <h2 class="font-semibold mb-3">Tabs</h2>
            <div class="flex items-center gap-2">
                <x-ui.tab :active="true">Semua</x-ui.tab>
                <x-ui.tab>Terjual</x-ui.tab>
                <x-ui.tab>Aktif</x-ui.tab>
            </div>
        </section>

        <section class="panel-surface p-4 rounded-xl">
            <h2 class="font-semibold mb-3">Navigation</h2>
            <div class="flex flex-col gap-2">
                <x-ui.nav-item href="#" :active="true">Dana Penjual</x-ui.nav-item>
                <x-ui.nav-item href="#">Pesanan</x-ui.nav-item>
                <x-ui.nav-item href="#">Pengaturan</x-ui.nav-item>
            </div>
        </section>
    </div>
@endsection
