@props([
    'livewire' => null,
])

@push('styles')
    <style>
        :root {
            --ha-primary: #2563eb;
            --ha-primary-dark: #1d4ed8;
            --ha-slate-900: #0f172a;
            --ha-slate-700: #334155;
            --ha-slate-500: #64748b;
            --ha-slate-200: #e2e8f0;
            --ha-slate-100: #f1f5f9;
        }

        .ha-login {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #f8fafc 0%, #eef2f7 100%);
            padding: 1.5rem;
            overflow: hidden;
        }

        .ha-login__glow {
            position: absolute;
            border-radius: 9999px;
            filter: blur(80px);
            opacity: 0.35;
            pointer-events: none;
        }

        .ha-login__glow--one {
            width: 22rem;
            height: 22rem;
            background: #bfdbfe;
            top: -6rem;
            right: -4rem;
        }

        .ha-login__glow--two {
            width: 18rem;
            height: 18rem;
            background: #c7d2fe;
            bottom: -5rem;
            left: -4rem;
        }

        .ha-login__shell {
            position: relative;
            width: 100%;
            max-width: 26rem;
            z-index: 1;
        }

        .ha-login__card {
            background: #ffffff;
            border: 1px solid var(--ha-slate-200);
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: 0 20px 45px -20px rgba(15, 23, 42, 0.25);
        }

        .ha-login__header {
            margin-bottom: 1.5rem;
        }

        .ha-login__brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--ha-primary);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .ha-login__brand svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .ha-login__header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ha-slate-900);
            margin: 0 0 0.35rem;
        }

        .ha-login__header p {
            color: var(--ha-slate-500);
            font-size: 0.9rem;
            margin: 0;
        }

        .ha-login__form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .ha-login__alert {
            display: none;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 0.85rem;
            border-radius: 0.6rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.82rem;
        }

        .ha-login__alert--visible {
            display: flex;
        }

        .ha-login__alert svg {
            width: 1.1rem;
            height: 1.1rem;
            flex-shrink: 0;
        }

        .ha-login__field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .ha-login__label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--ha-slate-700);
        }

        .ha-login__input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .ha-login__input-icon {
            position: absolute;
            left: 0.75rem;
            width: 1.05rem;
            height: 1.05rem;
            color: var(--ha-slate-500);
            pointer-events: none;
        }

        .ha-login__input {
            width: 100%;
            padding: 0.65rem 0.8rem 0.65rem 2.35rem;
            border: 1px solid var(--ha-slate-200);
            border-radius: 0.6rem;
            font-size: 0.9rem;
            color: var(--ha-slate-900);
            background: #fff;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .ha-login__input:focus {
            border-color: var(--ha-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .ha-login__reveal {
            position: absolute;
            right: 0.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ha-slate-500);
            padding: 0.25rem;
        }

        .ha-login__reveal svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .ha-login__remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.82rem;
            color: var(--ha-slate-700);
        }

        .ha-login__checkbox {
            width: 1rem;
            height: 1rem;
            accent-color: var(--ha-primary);
        }

        .ha-login__submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1rem;
            border: none;
            border-radius: 0.6rem;
            background: var(--ha-primary);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }

        .ha-login__submit:hover {
            background: var(--ha-primary-dark);
        }

        .ha-login__submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .ha-login__submit-icon {
            width: 1.15rem;
            height: 1.15rem;
        }

        .ha-login__security {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.25rem;
            font-size: 0.75rem;
            color: var(--ha-slate-500);
        }

        .ha-login__security svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        /* ── Overlay/pop-up SSO (dikunci, tidak bisa ditutup) ── */
        .ha-sso-gate {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(6px);
        }

        .ha-sso-gate__card {
            width: 100%;
            max-width: 26rem;
            background: #ffffff;
            border-radius: 1.25rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 25px 60px -20px rgba(15, 23, 42, 0.5);
        }

        .ha-sso-gate__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--ha-primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.75rem;
        }

        .ha-sso-gate__eyebrow svg {
            width: 1rem;
            height: 1rem;
        }

        .ha-sso-gate__title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--ha-slate-900);
            margin: 0 0 0.6rem;
        }

        .ha-sso-gate__desc {
            font-size: 0.9rem;
            color: var(--ha-slate-500);
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }

        .ha-sso-gate__error {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            text-align: left;
            padding: 0.7rem 0.85rem;
            border-radius: 0.6rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.82rem;
            line-height: 1.45;
            margin: 0 0 1.25rem;
        }

        .ha-sso-gate__error svg {
            width: 1.1rem;
            height: 1.1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .ha-sso-gate__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.6rem;
            background: var(--ha-primary);
            color: #fff;
            font-size: 0.92rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
        }

        .ha-sso-gate__button:hover {
            background: var(--ha-primary-dark);
        }

        .ha-sso-gate__button-icon {
            width: 1.15rem;
            height: 1.15rem;
        }

        .ha-sso-gate__note {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: var(--ha-slate-500);
        }

        .ha-sso-gate__note svg {
            width: 0.95rem;
            height: 0.95rem;
        }
    </style>
@endpush

<x-filament-panels::layout.base :livewire="$livewire">
    {{ $slot }}
</x-filament-panels::layout.base>
