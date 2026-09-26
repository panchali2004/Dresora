<?php

session_start();

session_unset();
session_destroy();

echo '<script>
    localStorage.removeItem("dresoraLoggedIn");
    localStorage.removeItem("dresoraRole");
    localStorage.removeItem("dresoraDashboard");

    window.location.href = "../account.php";
</script>';

exit;

?>