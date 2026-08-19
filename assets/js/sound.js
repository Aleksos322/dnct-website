/**
 * assets/js/sound.js
 *
 * System dźwięków UI: syntezowany Web Audio API, bez plików - krótkie,
 * ciche tony (sinus/trójkąt, szybki atak, wykładniczy zanik), spójne
 * z charakterem DESIGN.md ("techniczny i precyzyjny"), nie gierkowe piknięcia.
 *
 * AudioContext wymaga gestu użytkownika (polityka autoplay przeglądarek) -
 * tworzymy go dopiero przy pierwszym kliknięciu/klawiszu na stronie, nie
 * od razu przy wczytaniu.
 *
 * Włączenie/wyłączenie jest trwałe (localStorage) i domyślnie WŁĄCZONE.
 */
(function () {
    const STORAGE_KEY = 'dnct_sound_enabled';

    function DnctSound() {
        this.ctx = null;
        this.enabled = localStorage.getItem(STORAGE_KEY) !== '0';

        const unlock = () => {
            this._ensureCtx();
            document.removeEventListener('pointerdown', unlock);
            document.removeEventListener('keydown', unlock);
        };
        document.addEventListener('pointerdown', unlock, { once: true });
        document.addEventListener('keydown', unlock, { once: true });
    }

    DnctSound.prototype._ensureCtx = function () {
        if (!this.ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return null;
            this.ctx = new AC();
        }
        if (this.ctx.state === 'suspended') this.ctx.resume();
        return this.ctx;
    };

    DnctSound.prototype.isEnabled = function () {
        return this.enabled;
    };

    DnctSound.prototype.setEnabled = function (value) {
        this.enabled = !!value;
        localStorage.setItem(STORAGE_KEY, this.enabled ? '1' : '0');
        document.dispatchEvent(new CustomEvent('dnct-sound-toggled', { detail: { enabled: this.enabled } }));
    };

    DnctSound.prototype.toggle = function () {
        this.setEnabled(!this.enabled);
        if (this.enabled) this.play('click');
        return this.enabled;
    };

    /** Pojedynczy ton: sinus/trójkąt z krótkim atakiem i wykładniczym zanikiem. */
    DnctSound.prototype._tone = function (ctx, freq, opts) {
        opts = opts || {};
        const start = opts.start || 0;
        const dur = opts.dur || 0.12;
        const type = opts.type || 'sine';
        const gain = opts.gain || 0.16;

        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = type;
        osc.frequency.value = freq;

        const t0 = ctx.currentTime + start;
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.linearRampToValueAtTime(gain, t0 + 0.012);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);

        osc.connect(g).connect(ctx.destination);
        osc.start(t0);
        osc.stop(t0 + dur + 0.03);
    };

    const RECIPES = {
        // Dwa wznoszące się tony - potwierdzenie.
        success: (s, ctx) => {
            s._tone(ctx, 660, { dur: 0.11, type: 'sine', gain: 0.16 });
            s._tone(ctx, 880, { start: 0.09, dur: 0.17, type: 'sine', gain: 0.16 });
        },
        // Dwa krótkie, niższe pulsy - wyraźne, ale nie brzęczące.
        error: (s, ctx) => {
            s._tone(ctx, 220, { dur: 0.09, type: 'triangle', gain: 0.18 });
            s._tone(ctx, 196, { start: 0.11, dur: 0.15, type: 'triangle', gain: 0.18 });
        },
        // Jeden, średni ton o nieco dłuższym zaniku.
        warning: (s, ctx) => {
            s._tone(ctx, 440, { dur: 0.22, type: 'triangle', gain: 0.15 });
        },
        // Pojedynczy, cichy sygnał.
        info: (s, ctx) => {
            s._tone(ctx, 523, { dur: 0.14, type: 'sine', gain: 0.12 });
        },
        // Opadające dwa tony - metafora dzwonka.
        ring: (s, ctx) => {
            s._tone(ctx, 987, { dur: 0.09, type: 'sine', gain: 0.14 });
            s._tone(ctx, 784, { start: 0.06, dur: 0.13, type: 'sine', gain: 0.12 });
        },
        // Bardzo krótkie kliknięcie - potwierdzenie interakcji (np. przełącznik dźwięku).
        click: (s, ctx) => {
            s._tone(ctx, 700, { dur: 0.05, type: 'sine', gain: 0.09 });
        },
    };

    DnctSound.prototype.play = function (name) {
        if (!this.enabled) return;
        const ctx = this._ensureCtx();
        if (!ctx) return;
        const recipe = RECIPES[name] || RECIPES.info;
        recipe(this, ctx);
    };

    window.DnctSound = window.DnctSound || new DnctSound();

    /* Przełącznik w navbarze (#dnct-sound-toggle, patrz navibar.php) - część
       tego samego systemu, nie osobny skrypt, żeby stan i logika żyły w
       jednym miejscu. */
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('dnct-sound-toggle');
        if (!btn) return;
        const sync = () => {
            const on = window.DnctSound.isEnabled();
            btn.classList.toggle('is-muted', !on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        };
        sync();
        btn.addEventListener('click', () => {
            window.DnctSound.toggle();
            sync();
        });
        document.addEventListener('dnct-sound-toggled', sync);
    });
})();
