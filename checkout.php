<?php
/**
 * Checkout now happens inside the cart drawer on the menu page.
 *
 * This file stays only as a redirect for old links and bookmarks: the visitor
 * lands back on the menu with the drawer already open.
 */
header('Location: index.php?cart=open');
exit;
