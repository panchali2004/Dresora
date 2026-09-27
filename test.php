<?php

$hash = '$2y$10$rFInBb.dL6mg9HJLl/sq5OPf5Qyz8BbwZZg8GxPPQztG5KcVt9Y/G';

if (password_verify('DresoraAdmin@2026', $hash)) {
    echo "PASSWORD MATCH";
} else {
    echo "PASSWORD DOES NOT MATCH";
}
?>