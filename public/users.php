<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/EmojiCatalog.php';

use Glider\Storage;
use Glider\EmojiCatalog;

Storage::ensure();
$users = Storage::readUsers();
$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
foreach ($users as $user) {
    if ((int) ($user['id'] ?? 0) === $editId) {
        $editUser = $user;
        break;
    }
}
$message = '';
$emojiGroups = EmojiCatalog::grouped();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $id = $id ?: ((count($users) > 0 ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $users)) : 0) + 1);
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Bitte Vorname, Nachname und eine gültige E-Mail-Adresse eingeben.';
    } else {
        $selectedEmoji = trim((string) ($_POST['emoji'] ?? ''));
        $item = ['id' => $id, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'emoji' => EmojiCatalog::contains($selectedEmoji) ? $selectedEmoji : '😀'];
        $updated = false;
        foreach ($users as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $users[$index] = $item;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $users[] = $item;
        }
        Storage::saveUsers($users);
        header('Location: /users.php?saved=1');
        exit;
    }
}

pageHeader('Benutzer');
if (isset($_GET['saved'])): ?><div class="alert">Benutzer wurde gespeichert.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <h2><?= $editUser ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen'; ?></h2>
    <form method="post" class="stacked-form">
        <input type="hidden" name="id" value="<?= e($editUser['id'] ?? ''); ?>" />
        <div class="row two-col"><label>Vorname<input type="text" name="first_name" value="<?= e($editUser['first_name'] ?? ''); ?>" required /></label><label>Nachname<input type="text" name="last_name" value="<?= e($editUser['last_name'] ?? ''); ?>" required /></label></div>
        <label>E-Mail-Adresse<input type="email" name="email" value="<?= e($editUser['email'] ?? ''); ?>" required /></label>
        <fieldset class="emoji-picker"><legend>Emoticon auswählen</legend><input type="hidden" name="emoji" id="selected-emoji" value="<?= e($editUser['emoji'] ?? ''); ?>" required />
            <?php foreach ($emojiGroups as $groupName => $subgroups): ?><div class="emoji-group"><h3><?= e($groupName); ?></h3><?php foreach ($subgroups as $subgroupName => $items): ?><div class="emoji-subgroup"><h4><?= e($subgroupName); ?></h4><div class="emoji-grid"><?php foreach ($items as $item): ?><button type="button" class="emoji-option<?= (($editUser['emoji'] ?? '') === $item['emoji']) ? ' selected' : ''; ?>" data-emoji="<?= e($item['emoji']); ?>" title="<?= e($item['name']); ?>" aria-label="<?= e($item['name']); ?>"><?= e($item['emoji']); ?></button><?php endforeach; ?></div></div><?php endforeach; ?></div><?php endforeach; ?>
        </fieldset>
        <button type="submit">Benutzer speichern</button>
    </form>
</section>
<section class="card"><h2>Benutzerliste</h2><div class="table-wrap"><table><thead><tr><th>Emoticon</th><th>Vorname</th><th>Nachname</th><th>E-Mail-Adresse</th><th>Aktion</th></tr></thead><tbody>
<?php if (!$users): ?><tr><td colspan="5">Noch keine Benutzer erfasst.</td></tr><?php endif; ?>
<?php foreach ($users as $user): ?><tr><td class="emoji-cell"><?= e($user['emoji'] ?? ''); ?></td><td><?= e($user['first_name'] ?? ''); ?></td><td><?= e($user['last_name'] ?? ''); ?></td><td><?= e($user['email'] ?? ''); ?></td><td><a class="button-link" href="/users.php?edit=<?= (int) ($user['id'] ?? 0); ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<script>
document.querySelectorAll('.emoji-option').forEach(function (button) {
    button.addEventListener('click', function () {
        document.querySelectorAll('.emoji-option.selected').forEach(function (selected) {
            selected.classList.remove('selected');
        });
        button.classList.add('selected');
        document.getElementById('selected-emoji').value = button.dataset.emoji;
    });
});
</script>
<?php pageFooter();
