<?php
// Fallback when mod_rewrite is unavailable: send visitors to the public folder.
header('Location: public/');
exit;
