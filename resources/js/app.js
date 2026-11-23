document.addEventListener('DOMContentLoaded', () => {
    const errorSummary = document.getElementById('error-summary');
    if (errorSummary) errorSummary.focus();

    document.querySelectorAll('[data-copy-target], [data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = button.dataset.copyTarget ? document.getElementById(button.dataset.copyTarget) : null;
            const value = target ? target.value : button.dataset.copyValue;
            const feedback = document.getElementById(button.dataset.copyFeedback);
            try {
                await navigator.clipboard.writeText(value);
                if (feedback) feedback.textContent = button.dataset.copyTarget ? 'Código Pix copiado.' : 'Link copiado.';
            } catch {
                if (target) {
                    target.focus();
                    target.select();
                }
                if (feedback) feedback.textContent = 'Não foi possível copiar. Selecione o texto e copie manualmente.';
            }
        });
    });

    // Regras de negócio também na tela: o servidor confere de novo ao salvar.
    document.querySelectorAll('[data-max-from]').forEach((input) => {
        const source = document.getElementById(input.dataset.maxFrom);
        if (!source) return;
        const sync = () => {
            input.max = source.value || '';
            const tooLate = source.value && input.value && input.value > source.value;
            input.setCustomValidity(tooLate ? input.dataset.maxMessage : '');
        };
        source.addEventListener('input', sync);
        input.addEventListener('input', sync);
        sync();
    });

    document.querySelectorAll('[data-min-message]').forEach((input) => {
        const check = () => {
            input.setCustomValidity('');
            if (input.validity.rangeUnderflow) input.setCustomValidity(input.dataset.minMessage);
        };
        input.addEventListener('input', check);
        check();
    });

    document.querySelectorAll('[data-progress-form]').forEach((form) => {
        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.dataset.originalLabel = submit.textContent;
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();

                return;
            }

            if (submit && form.checkValidity()) {
                submit.disabled = true;
                submit.textContent = submit.dataset.submitLabel || 'Aguarde…';
            }
        });
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-progress-form] [type="submit"]').forEach((submit) => {
            submit.disabled = false;
            if (submit.dataset.originalLabel) submit.textContent = submit.dataset.originalLabel;
        });
    });

    const paymentPoll = document.querySelector('[data-payment-poll]');
    if (paymentPoll) {
        const statusText = paymentPoll.querySelector('[data-payment-poll-status]');
        const startedAt = Date.now();
        const maxDurationMs = 30 * 60 * 1000;
        let delayMs = 5000;
        let timer = null;
        let checking = false;

        const schedule = () => {
            clearTimeout(timer);
            if (Date.now() - startedAt > maxDurationMs) {
                if (statusText) statusText.textContent = 'Se você já pagou, toque em "Já paguei · verificar pagamento".';

                return;
            }
            timer = setTimeout(check, delayMs);
        };

        const check = async () => {
            if (checking || document.visibilityState === 'hidden') return;
            checking = true;
            try {
                const response = await fetch(paymentPoll.dataset.paymentPoll, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!response.ok) throw new Error(String(response.status));
                const result = await response.json();
                if (result.status !== 'awaiting_payment' || result.payment !== paymentPoll.dataset.paymentState) {
                    if (statusText) statusText.textContent = 'Pagamento atualizado. Abrindo a confirmação…';
                    window.location.reload();

                    return;
                }
                delayMs = 5000;
            } catch {
                delayMs = Math.min(delayMs * 2, 30000);
            } finally {
                checking = false;
            }
            schedule();
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') check();
        });
        schedule();
    }

    document.querySelectorAll('input[data-mask]').forEach((input) => {
        input.addEventListener('blur', () => {
            let digits = input.value.replace(/\D/g, '');
            if (input.dataset.mask === 'phone') {
                if (digits.length === 13 && digits.startsWith('55')) digits = digits.slice(2);
                if (digits.length === 11) input.value = `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
            }
            if (input.dataset.mask === 'cpf' && digits.length === 11) {
                input.value = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
            }
        });
    });

    const bannerInput = document.querySelector('[data-banner-input]');
    if (bannerInput) {
        const newPreview = document.querySelector('[data-new-banner-preview]');
        const newPreviewImage = document.querySelector('[data-new-banner-image]');
        const currentPreview = document.querySelector('[data-current-banner]');
        const removeBanner = document.querySelector('[data-remove-banner]');
        let previewUrl;

        bannerInput.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            const file = bannerInput.files[0];

            if (file?.type.startsWith('image/')) {
                previewUrl = URL.createObjectURL(file);
                newPreviewImage.src = previewUrl;
                newPreview.hidden = false;
                if (currentPreview) currentPreview.hidden = true;
                if (removeBanner) removeBanner.checked = false;
            } else {
                newPreview.hidden = true;
                if (currentPreview) currentPreview.hidden = !!removeBanner?.checked;
            }
        });

        removeBanner?.addEventListener('change', () => {
            if (removeBanner.checked) {
                bannerInput.value = '';
                newPreview.hidden = true;
            }
            if (currentPreview) currentPreview.hidden = removeBanner.checked;
        });
    }
});
