/**
 * Page Flip Sound Generator using Web Audio API
 * Creates a realistic paper flip sound without needing an external audio file.
 */

class FlipSoundGenerator {
    constructor() {
        this.audioCtx = null;
        this.enabled = true;
        this.volume = 0.3;
        this._initOnInteraction();
    }

    _initOnInteraction() {
        // Firefox does not count arrow keys as user gestures for audio.
        // Only create + resume the AudioContext on click/touch (which Firefox
        // does trust). Once the context is running, keyboard events can freely
        // create audio nodes on it — no play()/resume() call needed.
        const init = () => {
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            document.removeEventListener('click', init);
            document.removeEventListener('touchstart', init);
        };
        document.addEventListener('click', init);
        document.addEventListener('touchstart', init);
    }

    play() {
        if (!this.enabled || !this.audioCtx) return;
        // Don't call resume() here — the context should already be running
        // from the click/touch that created it. Calling resume() from a
        // keyboard event in Firefox can re-suspend the context.

        const ctx = this.audioCtx;
        const now = ctx.currentTime;
        const duration = 0.25;

        // Create noise buffer for paper rustling
        const bufferSize = ctx.sampleRate * duration;
        const noiseBuffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const data = noiseBuffer.getChannelData(0);

        // Generate filtered noise that sounds like paper
        let lastOut = 0;
        for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            // Brown noise filter for paper-like quality
            data[i] = (lastOut + (0.02 * white)) / 1.02;
            lastOut = data[i];
            data[i] *= 3.5; // Boost
        }

        // Apply envelope for snap/swoosh feel
        for (let i = 0; i < bufferSize; i++) {
            const t = i / bufferSize;
            // Fast attack, medium decay
            let envelope;
            if (t < 0.05) {
                envelope = t / 0.05; // Quick attack
            } else if (t < 0.15) {
                envelope = 1.0; // Sustain briefly
            } else {
                envelope = Math.pow(1 - ((t - 0.15) / 0.85), 2); // Smooth decay
            }
            data[i] *= envelope;
        }

        // Noise source
        const noiseSource = ctx.createBufferSource();
        noiseSource.buffer = noiseBuffer;

        // Bandpass filter to shape the paper sound
        const bandpass = ctx.createBiquadFilter();
        bandpass.type = 'bandpass';
        bandpass.frequency.setValueAtTime(2000, now);
        bandpass.frequency.exponentialRampToValueAtTime(800, now + duration * 0.3);
        bandpass.Q.value = 0.7;

        // Highpass to remove rumble
        const highpass = ctx.createBiquadFilter();
        highpass.type = 'highpass';
        highpass.frequency.value = 200;

        // Gain control
        const gainNode = ctx.createGain();
        gainNode.gain.setValueAtTime(this.volume, now);
        gainNode.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Add a subtle "snap" at the start using an oscillator
        const snapOsc = ctx.createOscillator();
        snapOsc.type = 'sine';
        snapOsc.frequency.setValueAtTime(400, now);
        snapOsc.frequency.exponentialRampToValueAtTime(100, now + 0.05);

        const snapGain = ctx.createGain();
        snapGain.gain.setValueAtTime(this.volume * 0.15, now);
        snapGain.gain.exponentialRampToValueAtTime(0.001, now + 0.05);

        // Connect noise path
        noiseSource.connect(bandpass);
        bandpass.connect(highpass);
        highpass.connect(gainNode);
        gainNode.connect(ctx.destination);

        // Connect snap path
        snapOsc.connect(snapGain);
        snapGain.connect(ctx.destination);

        // Play
        noiseSource.start(now);
        noiseSource.stop(now + duration);
        snapOsc.start(now);
        snapOsc.stop(now + 0.06);
    }

    toggle() {
        this.enabled = !this.enabled;
        return this.enabled;
    }

    setVolume(vol) {
        this.volume = Math.max(0, Math.min(1, vol));
    }
}

// Export as global
window.FlipSoundGenerator = FlipSoundGenerator;
