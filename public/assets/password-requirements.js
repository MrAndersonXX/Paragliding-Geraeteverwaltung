(function () {

    function evaluate(password) {
        return {
            minimum_length: Array.from(password).length >= 12,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special_character: /[^\p{L}\p{N}]/u.test(password)
        };
    }

    document.querySelectorAll('[data-password-requirements]').forEach(function (requirements) {
        const form = requirements.closest('form');
        if (!form) {
            return;
        }
        const password = form.querySelector('[name="' + requirements.dataset.passwordField + '"]');
        const confirmation = form.querySelector('[name="' + requirements.dataset.confirmationField + '"]');
        if (!password || !confirmation) {
            return;
        }

        function update() {
            const statusLabels = {
                fulfilled: requirements.dataset.fulfilledLabel,
                unfulfilled: requirements.dataset.unfulfilledLabel,
                idle: requirements.dataset.idleLabel,
                optional: requirements.dataset.optionalLabel,
                allFulfilled: requirements.dataset.allFulfilledLabel,
                remaining: requirements.dataset.remainingLabel
            };
            const isOptionalAndEmpty = requirements.dataset.optional === 'true' && password.value === '' && confirmation.value === '';
            const results = evaluate(password.value);
            results.confirmation = password.value !== '' && password.value === confirmation.value;
            let fulfilled = 0;

            Object.keys(results).forEach(function (name) {
                const item = requirements.querySelector('[data-password-requirement="' + name + '"]');
                const status = item.querySelector('[data-status]');
                const met = results[name];
                item.classList.toggle('is-fulfilled', !isOptionalAndEmpty && met);
                item.classList.toggle('is-unfulfilled', !isOptionalAndEmpty && !met);
                item.classList.toggle('is-inactive', isOptionalAndEmpty);
                status.textContent = isOptionalAndEmpty ? statusLabels.idle : (met ? statusLabels.fulfilled : statusLabels.unfulfilled);
                if (met) {
                    fulfilled += 1;
                }
            });

            requirements.querySelector('[data-summary]').textContent = isOptionalAndEmpty
                ? statusLabels.optional
                : (fulfilled === Object.keys(results).length ? statusLabels.allFulfilled : statusLabels.remaining.replace(':count', String(Object.keys(results).length - fulfilled)));
        }

        password.addEventListener('input', update);
        confirmation.addEventListener('input', update);
        update();
    });
}());