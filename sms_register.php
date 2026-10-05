<?php
declare(strict_types=1);
// The SMS-only registration page was replaced by the Resident Portal sign-up, which also registers the verified number
// for announcement texts.
header('Location: register.php', true, 301);
exit;
