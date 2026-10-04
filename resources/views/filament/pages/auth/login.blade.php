<div class="ha-login">
    <div class="ha-login__glow ha-login__glow--one" aria-hidden="true"></div>
    <div class="ha-login__glow ha-login__glow--two" aria-hidden="true"></div>

    <div class="ha-login__shell">
        <main class="ha-login__main">
            <section class="ha-login__card">
                <header class="ha-login__header">
                    <span class="ha-login__brand">
                        <x-filament::icon icon="heroicon-m-chart-bar-square" />
                        <strong>Handayani Analytics</strong>
                    </span>
                    <h1>{{ $this->getHeading() }}</h1>
                    <p>{{ $this->getSubheading() }}</p>
                </header>

                {{ \Filament\Support\Facades\FilamentView::renderHook(
                    \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                    scopes: $this->getRenderHookScopes(),
                ) }}

                <form class="ha-login__form" wire:submit.prevent="authenticate" novalidate>
                    <div
                        @class([
                            'ha-login__alert',
                            'ha-login__alert--visible' => filled($this->loginErrorMessage),
                        ])
                        aria-live="polite"
                    >
                        @if ($this->loginErrorMessage)
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                            <span>{{ $this->loginErrorMessage }}</span>
                        @endif
                    </div>

                    <label class="ha-login__field" for="email">
                        <span class="ha-login__label">Email</span>
                        <span class="ha-login__input-wrap">
                            <x-filament::icon class="ha-login__input-icon" icon="heroicon-m-envelope" />
                            <input
                                id="email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                autofocus
                                tabindex="1"
                                wire:model="data.email"
                                placeholder="nama@email.com"
                                class="ha-login__input"
                            >
                        </span>
                    </label>

                    <label class="ha-login__field" for="password" x-data="{ showPassword: false }">
                        <span class="ha-login__label">Kata sandi</span>
                        <span class="ha-login__input-wrap">
                            <x-filament::icon class="ha-login__input-icon" icon="heroicon-m-lock-closed" />
                            <input
                                id="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                tabindex="2"
                                wire:model="data.password"
                                placeholder="Masukkan kata sandi"
                                class="ha-login__input"
                            >
                            <button
                                type="button"
                                class="ha-login__reveal"
                                x-on:click="showPassword = ! showPassword"
                                x-bind:aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                tabindex="4"
                            >
                                <x-filament::icon x-show="! showPassword" icon="heroicon-m-eye" />
                                <x-filament::icon x-cloak x-show="showPassword" icon="heroicon-m-eye-slash" />
                            </button>
                        </span>
                    </label>

                    <label class="ha-login__remember">
                        <input
                            type="checkbox"
                            wire:model="data.remember"
                            tabindex="3"
                            class="ha-login__checkbox"
                        >
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <button
                        type="submit"
                        class="ha-login__submit"
                        wire:loading.attr="disabled"
                        wire:target="authenticate"
                    >
                        <x-filament::icon class="ha-login__submit-icon" icon="heroicon-m-arrow-right-end-on-rectangle" />
                        <span wire:loading.remove wire:target="authenticate">Masuk</span>
                        <span wire:loading wire:target="authenticate">Memproses...</span>
                    </button>
                </form>

                {{ \Filament\Support\Facades\FilamentView::renderHook(
                    \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                    scopes: $this->getRenderHookScopes(),
                ) }}

                <div class="ha-login__security">
                    <x-filament::icon icon="heroicon-m-lock-closed" />
                    <span>Koneksi aman &middot; Akses hanya untuk pengguna terdaftar</span>
                </div>
            </section>
        </main>
    </div>

    @if (config('sso.enabled'))
        {{-- SSO aktif: login langsung DITUTUP. Arahkan user ke Portal. --}}
        {{-- Overlay dikunci (tidak bisa ditutup) & memblok semua interaksi  --}}
        {{-- dengan form di belakangnya. Bila SSO_ENABLED=false, blok ini    --}}
        {{-- tidak dirender → login langsung kembali normal.                 --}}
        <div
            class="ha-sso-gate"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ha-sso-gate-title"
            aria-describedby="ha-sso-gate-desc"
        >
            <div class="ha-sso-gate__card">
                <span class="ha-sso-gate__eyebrow">
                    <x-filament::icon icon="heroicon-m-shield-check" />
                    Login terpusat
                </span>

                <h2 id="ha-sso-gate-title" class="ha-sso-gate__title">
                    Masuk melalui Portal Handayani
                </h2>

                <p id="ha-sso-gate-desc" class="ha-sso-gate__desc">
                    Demi keamanan, login langsung di halaman ini dinonaktifkan.
                    Silakan masuk menggunakan akun Portal Handayani Anda.
                </p>

                <a
                    class="ha-sso-gate__button"
                    href="{{ route('sso.login') }}"
                    rel="noopener"
                >
                    <x-filament::icon class="ha-sso-gate__button-icon" icon="heroicon-m-arrow-right-end-on-rectangle" />
                    <span>Login dengan Portal Handayani</span>
                </a>

                <span class="ha-sso-gate__note">
                    <x-filament::icon icon="heroicon-m-lock-closed" />
                    Koneksi aman &middot; Anda akan diarahkan ke Portal Handayani
                </span>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
