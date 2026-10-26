<?php
/**
 * RETIRED. Deleting through a GET link (delete.php?id=...) could be triggered
 * by any page or image tag the user happened to open (CSRF). Removing a movie
 * is now a POST with a CSRF token handled by actions.php.
 */
header('Location: index.php');
exit;
