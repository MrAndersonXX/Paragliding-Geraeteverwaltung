<?php

require_once __DIR__ . '/../src/PasswordPolicy.php';

use Glider\PasswordPolicy;

function passwordRequirements(string $passwordField = 'password', string $confirmationField = 'password_confirm', bool $optional = false): void
{
    $requirements = [
        'minimum_length' => __('password.requirement.minimum_length', ['length' => PasswordPolicy::MIN_LENGTH]),
        'uppercase' => __('password.requirement.uppercase'),
        'lowercase' => __('password.requirement.lowercase'),
        'number' => __('password.requirement.number'),
        'special_character' => __('password.requirement.special_character'),
        'confirmation' => __('password.requirement.confirmation'),
    ];
    ?>
    <section class="password-requirements" data-password-requirements data-password-field="<?= htmlspecialchars($passwordField, ENT_QUOTES, 'UTF-8'); ?>" data-confirmation-field="<?= htmlspecialchars($confirmationField, ENT_QUOTES, 'UTF-8'); ?>" data-fulfilled-label="<?= htmlspecialchars(__('password.status.fulfilled'), ENT_QUOTES, 'UTF-8'); ?>" data-unfulfilled-label="<?= htmlspecialchars(__('password.status.unfulfilled'), ENT_QUOTES, 'UTF-8'); ?>" data-idle-label="<?= htmlspecialchars(__('password.status.idle'), ENT_QUOTES, 'UTF-8'); ?>" data-optional-label="<?= htmlspecialchars(__('password.status.optional'), ENT_QUOTES, 'UTF-8'); ?>" data-all-fulfilled-label="<?= htmlspecialchars(__('password.status.all_fulfilled'), ENT_QUOTES, 'UTF-8'); ?>" data-remaining-label="<?= htmlspecialchars(__('password.status.remaining', ['count' => ':count']), ENT_QUOTES, 'UTF-8'); ?>"<?= $optional ? ' data-optional="true"' : ''; ?> aria-labelledby="password-requirements-title">
        <h4 id="password-requirements-title"><?= htmlspecialchars(__('password.requirements_title'), ENT_QUOTES, 'UTF-8'); ?></h4>
        <ul>
            <?php foreach ($requirements as $requirement => $label): ?>
                <li data-password-requirement="<?= htmlspecialchars($requirement, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="password-requirement-icon" aria-hidden="true"></span>
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="password-requirement-status" data-status aria-live="polite"></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="password-requirements-summary" data-summary aria-live="polite"></p>
    </section>
    <?php
}