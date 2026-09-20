document.querySelectorAll('[data-emoji-picker]').forEach(function (picker) {
    const trigger = picker.querySelector('[data-emoji-trigger]');
    const popup = picker.querySelector('[data-emoji-popup]');
    const preview = picker.querySelector('[data-emoji-preview]');
    const hiddenInput = picker.querySelector('input[type="hidden"]');
    let variantFlyout = null;

    function closeVariantFlyout() {
        if (variantFlyout) {
            variantFlyout.remove();
            variantFlyout = null;
        }
    }

    function closePopup() {
        popup.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        closeVariantFlyout();
    }

    function selectEmoji(button, emoji) {
        hiddenInput.value = emoji;
        preview.textContent = emoji;
        picker.querySelectorAll('.emoji-option.selected').forEach(function (option) {
            option.classList.remove('selected');
        });
        const canonical = picker.querySelector('.emoji-option[data-emoji="' + CSS.escape(emoji) + '"]');
        (canonical || button).classList.add('selected');
    }

    function openVariantFlyout(button) {
        closeVariantFlyout();
        const variants = JSON.parse(button.dataset.variants);
        const flyout = document.createElement('div');
        flyout.className = 'emoji-variant-flyout';
        variants.forEach(function (variant) {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.className = 'emoji-option';
            swatch.dataset.emoji = variant.emoji;
            swatch.title = variant.name;
            swatch.setAttribute('aria-label', variant.name);
            swatch.textContent = variant.emoji;
            swatch.addEventListener('click', function (event) {
                event.stopPropagation();
                selectEmoji(swatch, variant.emoji);
                closePopup();
            });
            flyout.appendChild(swatch);
        });
        button.insertAdjacentElement('afterend', flyout);
        variantFlyout = flyout;
    }

    trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        const isOpen = !popup.hidden;
        if (isOpen) {
            closePopup();
            return;
        }
        popup.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    });

    popup.querySelectorAll('.emoji-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            popup.querySelectorAll('.emoji-tab.active').forEach(function (active) {
                active.classList.remove('active');
            });
            popup.querySelectorAll('.emoji-tab-panel.active').forEach(function (active) {
                active.classList.remove('active');
            });
            tab.classList.add('active');
            popup.querySelector('[data-emoji-panel="' + CSS.escape(tab.dataset.emojiTab) + '"]').classList.add('active');
            closeVariantFlyout();
        });
    });

    popup.querySelectorAll('.emoji-option').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (button.dataset.variants) {
                if (variantFlyout && variantFlyout.previousElementSibling === button) {
                    closeVariantFlyout();
                    return;
                }
                openVariantFlyout(button);
                return;
            }
            selectEmoji(button, button.dataset.emoji);
            closePopup();
        });
    });

    popup.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    document.addEventListener('click', function () {
        closePopup();
    });
});
