<script setup>
import { appUrl } from '../../appUrl';
import Logo from '../../Components/Logo.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <main class="admin-login-shell">
        <div class="admin-login-glow glow-blue" aria-hidden="true"></div>
        <div class="admin-login-glow glow-pink" aria-hidden="true"></div>
        <div class="admin-login-glow glow-gold" aria-hidden="true"></div>

        <div class="temple-silhouette" aria-hidden="true">
            <span class="temple-dome dome-left"></span>
            <span class="temple-dome dome-center"></span>
            <span class="temple-dome dome-right"></span>
        </div>

        <div class="petals" aria-hidden="true">
            <span v-for="petal in 9" :key="petal" :class="`petal petal-${petal}`"></span>
        </div>

        <section class="admin-login-panel" aria-labelledby="admin-login-title">
            <div class="login-blessing">॥ राधे राधे ॥</div>
            <Logo />
            <div class="login-divider" aria-hidden="true"><span>❋</span></div>

            <div class="text-center mb-4">
                <p class="login-eyebrow">Marketplace administration</p>
                <h1 id="admin-login-title" class="login-title">Welcome Back</h1>
                <p class="login-subtitle">Sign in to manage the travel marketplace.</p>
            </div>

            <form @submit.prevent="form.post(appUrl('/admin/login'))">
                <div class="mb-3">
                    <label for="admin-email" class="form-label login-label">Email address</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope-heart" aria-hidden="true"></i>
                        <input id="admin-email" v-model="form.email" type="email" class="form-control login-input" autocomplete="username" placeholder="admin@example.com" required>
                    </div>
                    <div v-if="form.errors.email" class="text-danger small mt-1">{{ form.errors.email }}</div>
                </div>

                <div class="mb-4">
                    <label for="admin-password" class="form-label login-label">Password</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <input id="admin-password" v-model="form.password" type="password" class="form-control login-input" autocomplete="current-password" placeholder="Enter your password" required>
                    </div>
                    <div v-if="form.errors.password" class="text-danger small mt-1">{{ form.errors.password }}</div>
                </div>

                <button class="btn login-button w-100" type="submit" :disabled="form.processing">
                    <span>{{ form.processing ? 'Signing in...' : 'Enter Admin Portal' }}</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="login-footnote"><i class="bi bi-lock-fill" aria-hidden="true"></i> Secure access for authorised team members</p>
            <p class="text-center small mt-3 mb-0">New traveller? <a :href="appUrl('/register')">Create an account</a></p>
        </section>
    </main>
</template>

<style scoped>
.admin-login-shell {
    position: relative;
    display: grid;
    min-height: 100vh;
    min-height: 100svh;
    place-items: center;
    overflow: hidden;
    padding: 2rem 1rem;
    isolation: isolate;
    background:
        radial-gradient(circle at 50% 18%, rgba(255, 244, 205, 0.9), transparent 34%),
        linear-gradient(145deg, #fff9ed 0%, #fce9df 48%, #f5e8f1 100%);
}

.admin-login-shell::before {
    position: absolute;
    inset: 0;
    z-index: -4;
    background-image:
        linear-gradient(rgba(122, 36, 93, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(122, 36, 93, 0.035) 1px, transparent 1px);
    background-size: 36px 36px;
    content: '';
    mask-image: linear-gradient(to bottom, transparent, #000 30%, #000);
}

.admin-login-glow {
    position: absolute;
    z-index: -3;
    width: clamp(18rem, 36vw, 34rem);
    aspect-ratio: 1;
    border-radius: 50%;
    opacity: 0.34;
    filter: blur(16px);
    animation: devotional-drift 12s ease-in-out infinite alternate;
}

.glow-blue { top: -13rem; left: -9rem; background: #3778b8; }
.glow-pink { right: -10rem; bottom: -14rem; background: #e26b9b; animation-delay: -5s; }
.glow-gold { top: 36%; right: 9%; width: 15rem; background: #f3b93f; animation-delay: -8s; }

.admin-login-panel {
    width: min(100%, 460px);
    padding: clamp(1.6rem, 4vw, 2.7rem);
    border: 1px solid rgba(255, 255, 255, 0.82);
    border-radius: 28px;
    background: rgba(255, 252, 247, 0.86);
    box-shadow: 0 30px 80px rgba(77, 25, 58, 0.2), 0 8px 24px rgba(23, 62, 114, 0.1), inset 0 1px 0 #fff;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.admin-login-panel :deep(.svtp-logo) { display: flex; justify-content: center; }
.admin-login-panel :deep(.svtp-logo-image) { width: min(100%, 310px); }

.login-blessing {
    margin-bottom: 0.65rem;
    color: #a13a6f;
    font-family: var(--font-devanagari);
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-align: center;
}

.login-divider { display: flex; align-items: center; gap: 0.7rem; margin: 1rem 0 1.25rem; color: #d89a2a; }
.login-divider::before, .login-divider::after { height: 1px; flex: 1; background: linear-gradient(90deg, transparent, rgba(216, 154, 42, 0.7)); content: ''; }
.login-divider::after { transform: scaleX(-1); }

.login-eyebrow { margin-bottom: 0.3rem; color: #a13a6f; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; }
.login-title { margin-bottom: 0.35rem; color: #52203d; font-family: var(--font-display); font-size: clamp(2rem, 7vw, 2.55rem); font-weight: 700; }
.login-subtitle { margin: 0; color: #74636c; font-size: 0.92rem; }
.login-label { color: #563346; font-size: 0.82rem; font-weight: 600; }

.login-input-wrap { position: relative; }
.login-input-wrap > i { position: absolute; top: 50%; left: 1rem; z-index: 2; color: #a13a6f; transform: translateY(-50%); }
.login-input {
    min-height: 50px;
    padding-left: 2.75rem;
    border: 1px solid rgba(122, 36, 93, 0.16);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.76);
    box-shadow: inset 0 2px 5px rgba(77, 25, 58, 0.04);
}
.login-input:focus { border-color: rgba(161, 58, 111, 0.55); box-shadow: 0 0 0 0.25rem rgba(225, 77, 135, 0.12), 0 8px 22px rgba(77, 25, 58, 0.08); }

.login-button {
    display: flex;
    min-height: 52px;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    border: 0;
    border-radius: 14px;
    color: #fff;
    font-weight: 700;
    background: linear-gradient(105deg, #244f8f, #7a245d 58%, #b33b70);
    box-shadow: 0 14px 28px rgba(86, 32, 67, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.login-button:hover:not(:disabled) { color: #fff; transform: translateY(-2px); box-shadow: 0 18px 34px rgba(86, 32, 67, 0.34); }
.login-button:disabled { opacity: 0.68; }

.login-footnote { margin: 1.2rem 0 0; color: #88717d; font-size: 0.72rem; text-align: center; }
.login-footnote i { margin-right: 0.35rem; color: #d89a2a; }

.temple-silhouette { position: absolute; right: 0; bottom: -2px; left: 0; z-index: -2; height: 24vh; min-height: 150px; opacity: 0.12; }
.temple-silhouette::before { position: absolute; right: 0; bottom: 0; left: 0; height: 42%; background: #6f2755; content: ''; }
.temple-dome { position: absolute; bottom: 36%; left: 50%; width: 19vw; min-width: 130px; max-width: 250px; aspect-ratio: 1.5; border-radius: 55% 55% 9% 9%; background: #6f2755; transform: translateX(-50%); }
.temple-dome::before { position: absolute; bottom: 100%; left: 50%; width: 4px; height: 30px; background: #6f2755; content: ''; transform: translateX(-50%); }
.temple-dome::after { position: absolute; bottom: calc(100% + 25px); left: 50%; border-right: 9px solid transparent; border-bottom: 16px solid #6f2755; border-left: 9px solid transparent; content: ''; transform: translateX(-50%); }
.dome-left { left: 20%; scale: 0.72; }
.dome-right { left: 80%; scale: 0.72; }

.petal { position: absolute; top: -8%; z-index: -1; width: 12px; height: 18px; border-radius: 80% 15% 70% 25%; background: rgba(225, 77, 135, 0.42); animation: petal-fall 13s linear infinite; }
.petal-1 { left: 8%; animation-delay: -2s; }
.petal-2 { left: 19%; width: 9px; height: 14px; animation-delay: -9s; animation-duration: 16s; }
.petal-3 { left: 31%; animation-delay: -5s; }
.petal-4 { left: 43%; width: 8px; height: 13px; animation-delay: -11s; animation-duration: 15s; }
.petal-5 { left: 57%; animation-delay: -7s; }
.petal-6 { left: 68%; width: 10px; height: 15px; animation-delay: -1s; animation-duration: 17s; }
.petal-7 { left: 78%; animation-delay: -10s; }
.petal-8 { left: 88%; width: 8px; height: 12px; animation-delay: -4s; animation-duration: 14s; }
.petal-9 { left: 95%; animation-delay: -13s; animation-duration: 18s; }

@keyframes devotional-drift {
    to { transform: translate3d(4rem, 2.5rem, 0) scale(1.08); }
}

@keyframes petal-fall {
    0% { opacity: 0; transform: translate3d(0, -5vh, 0) rotate(0deg); }
    12% { opacity: 0.8; }
    100% { opacity: 0; transform: translate3d(70px, 115vh, 0) rotate(520deg); }
}

@media (max-width: 575px) {
    .admin-login-shell { align-items: start; padding-top: 1.25rem; }
    .admin-login-panel { border-radius: 22px; }
    .glow-gold { display: none; }
}

@media (prefers-reduced-motion: reduce) {
    .admin-login-glow, .petal { animation: none; }
    .petal { display: none; }
    .login-button { transition: none; }
}
</style>
