@extends('layouts.app')
@section('title', 'Pengaturan Profil')

@section('content')
<style>
    .profile-hero {
        background:
            radial-gradient(circle at 12% 15%, rgba(59, 130, 246, 0.14), transparent 34%),
            radial-gradient(circle at 88% 5%, rgba(34, 211, 238, 0.13), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #eef7ff 52%, #f8fcff 100%);
    }

    .profile-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.58);
    }

    .profile-mobile-tabs {
        display: flex;
        gap: 0.625rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }

    .profile-mobile-tabs::-webkit-scrollbar {
        display: none;
    }

    .profile-mobile-tab {
        flex: 0 0 auto;
        min-width: 5.75rem;
        white-space: nowrap;
    }
</style>

<div class="max-w-4xl mx-auto space-y-6">
    <section class="profile-hero rounded-3xl border border-blue-100/70 px-5 py-5 sm:px-8 sm:py-7">
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Pengaturan Profil</h1>
        <p class="text-slate-600 mt-2">Ubah informasi akun dan data toko Anda.</p>

        <div class="profile-mobile-tabs mt-5 sm:flex sm:flex-wrap sm:overflow-visible sm:pb-0">
            <a href="#section-profil" class="profile-mobile-tab inline-flex min-h-[48px] items-center justify-center rounded-xl border border-blue-200 bg-white/90 px-4 py-3 text-sm font-bold text-blue-700">Akun</a>
            @if($user->isPenjual())
                <a href="#section-toko" class="profile-mobile-tab inline-flex min-h-[48px] items-center justify-center rounded-xl border border-cyan-200 bg-white/90 px-4 py-3 text-sm font-bold text-cyan-700">Toko</a>
            @endif
            @if(Route::has('password.update') && $user->auth_provider !== 'google')
                <a href="#section-password" class="profile-mobile-tab inline-flex min-h-[48px] items-center justify-center rounded-xl border border-amber-200 bg-white/90 px-4 py-3 text-sm font-bold text-amber-700">Password</a>
            @endif
            <a href="#section-danger" class="profile-mobile-tab inline-flex min-h-[48px] items-center justify-center rounded-xl border border-rose-200 bg-white/90 px-4 py-3 text-sm font-bold text-rose-700">Nonaktifkan</a>
        </div>
    </section>

    <div id="section-profil" class="profile-surface scroll-mt-28 rounded-3xl bg-white p-5 sm:p-6">
        <div class="mb-4">
            <h2 class="mt-1 text-lg font-black text-slate-900">Informasi Akun</h2>
        </div>
        <div class="max-w-2xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    @if($user->isPenjual())
        <div id="section-toko" class="profile-surface scroll-mt-28 rounded-3xl bg-white p-5 sm:p-6">
            <div class="mb-4">
                <h2 class="mt-1 text-lg font-black text-slate-900">Profil Toko</h2>
            </div>
            <div class="max-w-2xl">
                @include('profile.partials.update-seller-profile-form')
            </div>
        </div>
    @endif

    @if(Route::has('password.update') && $user->auth_provider !== 'google')
        <div id="section-password" class="profile-surface scroll-mt-28 rounded-3xl bg-white p-5 sm:p-6">
            <div class="mb-4">
                <h2 class="mt-1 text-lg font-black text-slate-900">Keamanan Akun</h2>
            </div>
            <div class="max-w-2xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    @endif

    <div id="section-danger" class="profile-surface scroll-mt-28 rounded-3xl border border-rose-100 bg-white p-5 sm:p-6">
        <div class="mb-4">
            <h2 class="mt-1 text-lg font-black text-slate-900">Nonaktifkan Akun</h2>
        </div>
        <div class="max-w-2xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
